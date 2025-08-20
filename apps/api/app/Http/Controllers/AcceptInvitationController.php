<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationController extends Controller
{
    public function show(string $token)
    {
        // Find invitation (can be either super-admin or tenant invitation)
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return view('invitations.invalid', [
                'message' => 'This invitation is invalid, expired, or has already been accepted.',
            ]);
        }

        // Security check: If no tenant_id, MUST be super-admin role
        if (! $invitation->tenant_id && $invitation->role !== 'super-admin') {
            return view('invitations.invalid', [
                'message' => 'This invitation has an invalid configuration.',
            ]);
        }

        return view('invitations.accept', compact('invitation', 'token'));
    }

    public function store(Request $request, string $token)
    {
        // Find invitation (can be either super-admin or tenant invitation)
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->back()->with('error', 'Invalid or expired invitation.');
        }

        // Security check: If no tenant_id, MUST be super-admin role
        if (! $invitation->tenant_id && $invitation->role !== 'super-admin') {
            return redirect()->back()->with('error', 'This invitation has an invalid configuration.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            if (! $invitation->tenant_id) {
                // Super-admin invitation (no tenant_id)
                return $this->handleSuperAdminInvitation($request, $invitation);
            } else {
                // Tenant invitation (from super-admin)
                return $this->handleTenantInvitation($request, $invitation);
            }
        } catch (Exception $e) {
            // Log the error
            logger()->error('Invitation acceptance error', [
                'token' => $token,
                'tenant_id' => $invitation->tenant_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while creating your account. Please try again.')
                ->withInput();
        }
    }

    private function handleSuperAdminInvitation(Request $request, Invitation $invitation)
    {
        // Create the super admin user account (no tenant_id for central domain)
        $user = User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'role' => $invitation->role,
            'tenant_id' => null, // Central domain users have no tenant_id
            'email_verified_at' => now(),
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        // Log the activity
        activity('central_invitation_accepted')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
                'domain_type' => 'central',
            ])
            ->log('Super admin accepted central domain invitation and created account');

        // Log the user in immediately to central domain
        \Illuminate\Support\Facades\Auth::login($user);

        // Flash success message and redirect to central admin panel
        session()->flash('success', 'Account created successfully! Welcome to the central administration panel.');

        // Generate proper admin URL using current request context to avoid domain constraint issues
        $protocol = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHost();
        $port = $request->getPort();

        // Include port if it's not the default port for the protocol
        $portString = '';
        if (($protocol === 'http' && $port !== 80) || ($protocol === 'https' && $port !== 443)) {
            $portString = ':' . $port;
        }

        $adminUrl = "{$protocol}://{$host}{$portString}/admin";

        return redirect($adminUrl);
    }

    private function handleTenantInvitation(Request $request, Invitation $invitation)
    {
        // Create the tenant user account
        $user = User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'role' => $invitation->role,
            'tenant_id' => $invitation->tenant_id,
            'email_verified_at' => now(),
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        // Log the activity
        activity('tenant_invitation_accepted_from_central')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
                'tenant_id' => $invitation->tenant_id,
                'domain_type' => 'cross_domain',
            ])
            ->log('User accepted tenant invitation from central domain and created account');

        // Generate impersonation token for cross-domain authentication
        $impersonationTokenModel = tenancy()->impersonate($invitation->company, $user->id, '/admin');
        $impersonationToken = $impersonationTokenModel->token;

        // Build redirect URL to tenant domain with impersonation token
        $company = $invitation->company;
        $tenantDomain = $company->domain . config('tenant.domain.suffix', '.checkright.test');
        $protocol = $request->isSecure() ? 'https' : 'http';
        $redirectUrl = "{$protocol}://{$tenantDomain}/impersonate/{$impersonationToken}";

        // Flash success message for tenant domain
        session()->flash('success', 'Account created successfully! Redirecting to your workspace.');

        return redirect($redirectUrl);
    }
}
