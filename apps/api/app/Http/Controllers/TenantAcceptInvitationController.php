<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class TenantAcceptInvitationController extends Controller
{
    public function show(string $token)
    {
        // Ensure we're in a tenant context
        if (! tenancy()->initialized) {
            abort(404, 'This invitation must be accessed from the tenant domain.');
        }

        $currentTenantId = tenant('id');

        // Find invitation that belongs to this tenant
        $invitation = Invitation::where('token', $token)
            ->where('tenant_id', $currentTenantId)
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return view('invitations.invalid', [
                'message' => 'This invitation is invalid, expired, or has already been accepted.',
            ]);
        }

        return view('invitations.accept', compact('invitation', 'token'));
    }

    public function store(Request $request, string $token)
    {
        // Ensure we're in a tenant context
        if (! tenancy()->initialized) {
            return redirect()->back()->with('error', 'Invalid access context.');
        }

        $currentTenantId = tenant('id');

        // Find invitation that belongs to this tenant
        $invitation = Invitation::where('token', $token)
            ->where('tenant_id', $currentTenantId)
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->back()->with('error', 'Invalid or expired invitation.');
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
            // Create the user account within tenant context
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'role' => $invitation->role,
                'tenant_id' => $currentTenantId, // Ensure user belongs to current tenant
                'email_verified_at' => now(),
            ]);

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // Log the activity
            activity('tenant_invitation_accepted')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'invitation_id' => $invitation->id,
                    'role' => $invitation->role,
                    'tenant_id' => $currentTenantId,
                ])
                ->log('User accepted tenant invitation and created account');

            // Log the user in immediately
            Auth::login($user);

            // Flash success message and redirect to tenant dashboard
            session()->flash('success', 'Account created successfully! Welcome to your workspace.');

            // Redirect to tenant admin panel
            return redirect('/admin');
        } catch (Exception $e) {
            // Log the error
            logger()->error('Tenant invitation acceptance error', [
                'token' => $token,
                'tenant_id' => $currentTenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while creating your account. Please try again.')
                ->withInput();
        }
    }
}
