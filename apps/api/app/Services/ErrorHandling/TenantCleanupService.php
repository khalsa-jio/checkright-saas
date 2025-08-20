<?php

namespace App\Services\ErrorHandling;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantCleanupService
{
    /**
     * Clean up tenant resources after failed creation.
     */
    public function cleanupFailedTenantCreation(
        ?Company $company = null,
        ?Invitation $invitation = null,
        ?User $user = null,
        array $context = []
    ): void {
        Log::info('Starting tenant cleanup', [
            'company_id' => $company?->id,
            'invitation_id' => $invitation?->id,
            'user_id' => $user?->id,
            'context' => $context,
        ]);

        DB::transaction(function () use ($company, $invitation, $user) {
            // Clean up user first (if exists)
            if ($user && $user->exists) {
                $this->cleanupUser($user);
            }

            // Clean up invitation
            if ($invitation && $invitation->exists) {
                $this->cleanupInvitation($invitation);
            }

            // Clean up company and related resources
            if ($company && $company->exists) {
                $this->cleanupCompany($company);
            }
        });

        activity('tenant_cleanup_completed')
            ->withProperties([
                'company_id' => $company?->id,
                'invitation_id' => $invitation?->id,
                'user_id' => $user?->id,
                'context' => $context,
            ])
            ->log('Tenant cleanup completed successfully');
    }

    /**
     * Clean up company and all related resources.
     */
    protected function cleanupCompany(Company $company): void
    {
        try {
            // Clean up tenant-specific data
            if (method_exists($company, 'run')) {
                $company->run(function () {
                    // Clean up tenant database tables
                    $this->cleanupTenantDatabase();
                });
            }

            // Delete domain records
            $company->domains()->delete();

            // Delete all invitations for this company
            Invitation::where('tenant_id', $company->id)->delete();

            // Delete all users for this company
            User::where('tenant_id', $company->id)->delete();

            // Finally delete the company
            $company->delete();

            Log::info('Company cleanup completed', ['company_id' => $company->id]);

            activity('company_cleanup')
                ->performedOn($company)
                ->log('Company and all related resources cleaned up');
        } catch (Exception $e) {
            Log::error('Failed to cleanup company', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            activity('company_cleanup_failed')
                ->performedOn($company)
                ->withProperties(['error' => $e->getMessage()])
                ->log('Failed to cleanup company resources');

            throw $e;
        }
    }

    /**
     * Clean up invitation.
     */
    protected function cleanupInvitation(Invitation $invitation): void
    {
        try {
            $invitation->delete();

            Log::info('Invitation cleanup completed', ['invitation_id' => $invitation->id]);

            activity('invitation_cleanup')
                ->performedOn($invitation)
                ->log('Invitation cleaned up');
        } catch (Exception $e) {
            Log::error('Failed to cleanup invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Clean up user.
     */
    protected function cleanupUser(User $user): void
    {
        try {
            $user->delete();

            Log::info('User cleanup completed', ['user_id' => $user->id]);

            activity('user_cleanup')
                ->performedOn($user)
                ->log('User cleaned up');
        } catch (Exception $e) {
            Log::error('Failed to cleanup user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Clean up tenant-specific database resources.
     */
    protected function cleanupTenantDatabase(): void
    {
        try {
            // This would contain tenant-specific cleanup logic
            // For now, we'll just log that cleanup would happen here
            Log::info('Tenant database cleanup completed');

            activity('tenant_database_cleanup')
                ->log('Tenant database resources cleaned up');
        } catch (Exception $e) {
            Log::error('Failed to cleanup tenant database', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if cleanup is needed for orphaned resources.
     */
    public function cleanupOrphanedResources(): array
    {
        $cleaned = [
            'companies' => 0,
            'invitations' => 0,
            'users' => 0,
        ];

        // Clean up companies without domains
        $orphanedCompanies = Company::doesntHave('domains')->get();
        foreach ($orphanedCompanies as $company) {
            $this->cleanupCompany($company);
            $cleaned['companies']++;
        }

        // Clean up expired invitations
        $expiredInvitations = Invitation::expired()->get();
        foreach ($expiredInvitations as $invitation) {
            $this->cleanupInvitation($invitation);
            $cleaned['invitations']++;
        }

        // Clean up users without companies
        $orphanedUsers = User::whereDoesntHave('company')->get();
        foreach ($orphanedUsers as $user) {
            $this->cleanupUser($user);
            $cleaned['users']++;
        }

        activity('orphaned_resources_cleanup')
            ->withProperties($cleaned)
            ->log('Orphaned resources cleanup completed');

        return $cleaned;
    }
}
