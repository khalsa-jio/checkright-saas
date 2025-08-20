<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Invitation;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get all users in the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        // Super admin can see all users, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $query = User::withTrashed();
        } else {
            // For non-super admin users, scope to their tenant
            $tenantId = auth()->user()->tenant_id;
            $query = User::where('tenant_id', $tenantId)
                ->withTrashed();
        }

        // Apply role filter if specified
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Apply search if specified
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($request->status === 'deactivated') {
                $query->whereNotNull('deleted_at');
            }
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    /**
     * Get a specific user.
     */
    public function show($userId): JsonResponse
    {
        // Super admin can access any user, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $user = User::withTrashed()->findOrFail($userId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $user = User::withTrashed()->where('tenant_id', $tenantId)->findOrFail($userId);
        }

        $this->authorize('view', $user);

        $user->load('company');

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * Invite a new user.
     */
    public function invite(InviteUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Determine tenant for invitation
        if (auth()->user()->isSuperAdmin() && $validated['role'] === 'super-admin') {
            $tenantId = null; // Super admin invitations are tenant-agnostic
        } elseif (auth()->user()->isSuperAdmin() && isset($validated['tenant_id'])) {
            // Super admin can specify which company to invite to
            $tenantId = $validated['tenant_id'];
        } else {
            $tenantId = auth()->user()->tenant_id;
        }

        // Check for existing pending invitation
        $existingInvitation = Invitation::where('email', $validated['email'])
            ->where('tenant_id', $tenantId)
            ->pending()
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'message' => 'A pending invitation already exists for this email address.',
                'error' => 'duplicate_invitation',
            ], 409);
        }

        // Create the invitation
        $invitation = Invitation::create([
            'tenant_id' => $tenantId,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'invited_by' => auth()->id(),
        ]);

        // Send invitation email
        $acceptUrl = config('app.url') . '/accept-invitation/' . $invitation->token;
        SendInvitationEmailJob::dispatch($invitation, $acceptUrl);

        activity()
            ->performedOn($invitation)
            ->causedBy(auth()->user())
            ->log("User invitation sent to {$invitation->email} for {$invitation->role} role");

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'data' => $invitation,
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, $userId): JsonResponse
    {
        // Super admin can access any user, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $user = User::withTrashed()->findOrFail($userId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $user = User::withTrashed()->where('tenant_id', $tenantId)->findOrFail($userId);
        }

        $this->authorize('update', $user);

        $validated = $request->validated();

        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $oldData = $user->only(['name', 'email', 'role', 'must_change_password']);
        $user->update($validated);

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => $oldData,
                'attributes' => $user->only(['name', 'email', 'role', 'must_change_password']),
            ])
            ->log('User updated');

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Deactivate a user (soft delete).
     */
    public function destroy($userId): JsonResponse
    {
        // Super admin can access any user, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $user = User::withTrashed()->findOrFail($userId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $user = User::withTrashed()->where('tenant_id', $tenantId)->findOrFail($userId);
        }

        $this->authorize('delete', $user);

        // Prevent users from deactivating themselves
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
                'error' => 'self_deactivation_forbidden',
            ], 403);
        }

        $user->delete();

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User deactivated');

        return response()->json([
            'message' => 'User deactivated successfully.',
        ]);
    }

    /**
     * Reactivate a user (restore from soft delete).
     */
    public function restore($userId): JsonResponse
    {
        // Super admin can access any user, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $user = User::withTrashed()->findOrFail($userId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $user = User::withTrashed()->where('tenant_id', $tenantId)->findOrFail($userId);
        }

        $this->authorize('restore', $user);

        $user->restore();

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User reactivated');

        return response()->json([
            'message' => 'User reactivated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Force a user to reset their password.
     */
    public function forcePasswordReset($userId): JsonResponse
    {
        // Super admin can access any user, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $user = User::withTrashed()->findOrFail($userId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $user = User::withTrashed()->where('tenant_id', $tenantId)->findOrFail($userId);
        }

        $this->authorize('forcePasswordReset', $user);

        $user->update(['must_change_password' => true]);

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('Password reset forced');

        return response()->json([
            'message' => 'User will be required to reset their password on next login.',
        ]);
    }

    /**
     * Get pending invitations.
     */
    public function pendingInvitations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        // Super admin can see all invitations, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $query = Invitation::with('inviter:id,name,email')->pending();
        } else {
            $tenantId = auth()->user()->tenant_id;
            $query = Invitation::where('tenant_id', $tenantId)
                ->with('inviter:id,name,email')
                ->pending();
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $invitations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($invitations);
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation($invitationId): JsonResponse
    {
        // Super admin can access any invitation, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $invitation = Invitation::findOrFail($invitationId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $invitation = Invitation::where('tenant_id', $tenantId)->findOrFail($invitationId);
        }
        // Check if user can invite with this role
        if (! Gate::allows('invite', [User::class, $invitation->role])) {
            return response()->json([
                'message' => 'You are not authorized to resend this invitation.',
                'error' => 'unauthorized_role',
            ], 403);
        }

        if (! $invitation->isValid()) {
            return response()->json([
                'message' => 'This invitation is no longer valid.',
                'error' => 'invalid_invitation',
            ], 410);
        }

        // Update the invitation with new expiry
        $invitation->update([
            'expires_at' => now()->addDays(7),
            'invited_by' => auth()->id(),
        ]);

        // Resend invitation email
        $acceptUrl = config('app.url') . '/accept-invitation/' . $invitation->token;
        SendInvitationEmailJob::dispatch($invitation, $acceptUrl);

        activity()
            ->performedOn($invitation)
            ->causedBy(auth()->user())
            ->log("Invitation resent to {$invitation->email}");

        return response()->json([
            'message' => 'Invitation resent successfully.',
            'data' => $invitation->fresh(),
        ]);
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation($invitationId): JsonResponse
    {
        // Super admin can access any invitation, others are tenant-scoped
        if (auth()->user()->isSuperAdmin()) {
            $invitation = Invitation::findOrFail($invitationId);
        } else {
            $tenantId = auth()->user()->tenant_id;
            $invitation = Invitation::where('tenant_id', $tenantId)->findOrFail($invitationId);
        }
        // Check if user can manage this invitation
        if (! Gate::allows('invite', [User::class, $invitation->role])) {
            return response()->json([
                'message' => 'You are not authorized to cancel this invitation.',
                'error' => 'unauthorized_role',
            ], 403);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'This invitation has already been accepted.',
                'error' => 'invitation_accepted',
            ], 410);
        }

        $invitation->delete();

        activity()
            ->performedOn($invitation)
            ->causedBy(auth()->user())
            ->log("Invitation cancelled for {$invitation->email}");

        return response()->json([
            'message' => 'Invitation cancelled successfully.',
        ]);
    }

    /**
     * Bulk deactivate users.
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = $request->input('user_ids');
        $currentUserId = auth()->id();

        // Remove current user from the list to prevent self-deactivation
        $userIds = array_filter($userIds, fn ($id) => $id != $currentUserId);

        if (empty($userIds)) {
            return response()->json([
                'message' => 'No valid users to deactivate.',
                'error' => 'empty_user_list',
            ], 400);
        }

        // Get users based on permissions
        if (auth()->user()->isSuperAdmin()) {
            $users = User::whereIn('id', $userIds)->get();
        } else {
            $tenantId = auth()->user()->tenant_id;
            $users = User::where('tenant_id', $tenantId)
                ->whereIn('id', $userIds)
                ->get();
        }

        $deactivatedCount = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $this->authorize('delete', $user);
                $user->delete();

                activity()
                    ->performedOn($user)
                    ->causedBy(auth()->user())
                    ->log('User bulk deactivated');

                $deactivatedCount++;
            } catch (Exception $e) {
                $errors[] = "Failed to deactivate user {$user->name} ({$user->email}): " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Successfully deactivated {$deactivatedCount} users.",
            'deactivated_count' => $deactivatedCount,
            'errors' => $errors,
        ]);
    }
}
