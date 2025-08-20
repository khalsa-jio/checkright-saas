<?php

namespace App\Services;

use App\Events\InvitationAccepted;
use App\Events\InvitationSent;
use App\Events\TenantCreated;
use App\Events\TenantCreationFailed;
use App\Exceptions\DomainException;
use App\Exceptions\InvitationException;
use App\Exceptions\TenantCreationException;
use App\Exceptions\TenantValidationException;
use App\Jobs\SendInvitationEmailJob;
use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Caching\TenantCacheService;
use App\Services\ErrorHandling\TenantCleanupService;
use Artisan;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Models\Domain;

class TenantCreationService
{
    protected TenantCleanupService $cleanupService;

    protected TenantCacheService $cacheService;

    public function __construct(
        TenantCleanupService $cleanupService,
        TenantCacheService $cacheService
    ) {
        $this->cleanupService = $cleanupService;
        $this->cacheService = $cacheService;
    }

    /**
     * Create a new tenant company with the first admin user.
     *
     * @throws TenantCreationException
     */
    public function createTenantWithAdmin(array $companyData, array $adminData): array
    {
        $company = null;
        $invitation = null;

        try {
            return DB::transaction(function () use ($companyData, $adminData, &$company, &$invitation) {
                // Create the tenant (company)
                $company = $this->createTenant($companyData);

                // Create invitation for the first admin
                $invitation = $this->createAdminInvitation($company, $adminData);

                // Send invitation email
                $this->sendInvitationEmail($invitation);

                // Warm up cache for the new tenant (if caching is enabled)
                if ($this->cacheService->isCachingEnabled()) {
                    $this->cacheService->warmupCompanyCache($company);
                }

                // Fire tenant created event
                TenantCreated::dispatch($company->id, $invitation->id, ['operation' => 'create_tenant_with_admin']);

                return [
                    'company' => $company,
                    'invitation' => $invitation,
                ];
            });
        } catch (TenantValidationException|InvitationException|DomainException $e) {
            // Fire failure event for domain-specific exceptions
            TenantCreationFailed::dispatch($companyData, $adminData, $e, ['operation' => 'create_tenant_with_admin']);
            throw $e;
        } catch (Exception $e) {
            // Perform cleanup on failure
            if ($company || $invitation) {
                try {
                    $this->cleanupService->cleanupFailedTenantCreation(
                        $company,
                        $invitation,
                        null,
                        ['operation' => 'create_tenant_with_admin', 'error' => $e->getMessage()]
                    );
                } catch (Exception $cleanupException) {
                    // Log cleanup failure but don't override original exception
                    activity('cleanup_failed')
                        ->withProperties([
                            'original_error' => $e->getMessage(),
                            'cleanup_error' => $cleanupException->getMessage(),
                        ])
                        ->log('Failed to cleanup after tenant creation failure');
                }
            }

            $tenantException = new TenantCreationException(
                'Failed to create tenant: ' . $e->getMessage(),
                $companyData,
                $adminData,
                0,
                $e
            );

            // Fire failure event
            TenantCreationFailed::dispatch($companyData, $adminData, $tenantException, ['operation' => 'create_tenant_with_admin']);

            throw $tenantException;
        }
    }

    /**
     * Create a new tenant company.
     *
     * @throws TenantValidationException
     * @throws DomainException
     */
    protected function createTenant(array $data): Company
    {
        // Validate input data
        $validator = validator($data, [
            'name' => 'required|string|max:255|min:2',
            'domain' => [
                'required',
                'string',
                'alpha_dash',
                'max:50',
                'min:3',
                'unique:tenants,domain',
                'regex:/^[a-z0-9-]+$/', // Only lowercase letters, numbers, and hyphens
            ],
        ], [
            'name.required' => 'Company name is required.',
            'name.min' => 'Company name must be at least 2 characters.',
            'domain.required' => 'Domain is required.',
            'domain.alpha_dash' => 'Domain may only contain letters, numbers, and hyphens.',
            'domain.unique' => 'This domain is already taken.',
            'domain.regex' => 'Domain must be lowercase and contain only letters, numbers, and hyphens.',
            'domain.min' => 'Domain must be at least 3 characters.',
        ]);

        if ($validator->fails()) {
            throw new TenantValidationException(
                'Tenant validation failed',
                $validator->errors(),
                $data
            );
        }

        // Create the company/tenant
        $company = Company::create([
            'name' => $data['name'],
            'domain' => $data['domain'],
        ]);

        // Create domain record for tenancy
        try {
            $company->domains()->create([
                'domain' => $data['domain'] . config('tenant.domain.suffix'),
            ]);
        } catch (Exception $e) {
            // Clean up the company if domain creation fails
            try {
                $this->cleanupService->cleanupFailedTenantCreation(
                    $company,
                    null,
                    null,
                    ['operation' => 'domain_creation', 'domain' => $data['domain']]
                );
            } catch (Exception $cleanupException) {
                // Log cleanup failure but don't override original exception
                activity('domain_cleanup_failed')
                    ->performedOn($company)
                    ->withProperties([
                        'domain_error' => $e->getMessage(),
                        'cleanup_error' => $cleanupException->getMessage(),
                    ])
                    ->log('Failed to cleanup after domain creation failure');
            }

            throw new DomainException(
                'Failed to create domain record: ' . $e->getMessage(),
                $data['domain'],
                'create',
                0,
                $e
            );
        }

        // Initialize tenant database
        $company->run(function () {
            // Run tenant-specific migrations
            $this->runTenantMigrations();
        });

        activity('tenant_creation')
            ->performedOn($company)
            ->withProperties([
                'company_name' => $company->name,
                'domain' => $company->domain,
            ])
            ->log('New tenant company created');

        return $company;
    }

    /**
     * Create an admin invitation for the tenant.
     *
     * @throws InvitationException
     */
    protected function createAdminInvitation(Company $company, array $adminData): Invitation
    {
        // Validate admin email
        $validator = validator($adminData, [
            'email' => 'required|email:rfc,dns|max:255',
        ], [
            'email.required' => 'Admin email is required.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        if ($validator->fails()) {
            throw new InvitationException(
                'Invalid admin email provided',
                null,
                ['validation_errors' => $validator->errors()->toArray()],
                422
            );
        }

        // Check if email is already invited for this tenant
        $existingInvitation = Invitation::where('tenant_id', $company->id)
            ->where('email', $adminData['email'])
            ->where('accepted_at', null)
            ->first();

        if ($existingInvitation) {
            throw new InvitationException(
                'An invitation has already been sent to this email address for this company.',
                $existingInvitation,
                ['admin_email' => $adminData['email'], 'company_id' => $company->id]
            );
        }

        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => $adminData['email'],
            'role' => 'admin',
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays(7),
            'invited_by' => null, // System-generated invitation
        ]);

        activity('admin_invitation')
            ->performedOn($invitation)
            ->withProperties([
                'company_name' => $company->name,
                'admin_email' => $adminData['email'],
            ])
            ->log('Admin invitation created for new tenant');

        return $invitation;
    }

    /**
     * Send invitation email.
     */
    protected function sendInvitationEmail(Invitation $invitation): void
    {
        $acceptUrl = $this->buildInvitationUrl($invitation);

        // Check if email sending should be queued
        if (config('tenant.performance.queue_email_sending', true)) {
            SendInvitationEmailJob::dispatch($invitation, $acceptUrl);

            activity('invitation_queued')
                ->performedOn($invitation)
                ->withProperties([
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'company' => $invitation->company->name,
                ])
                ->log('Invitation email queued for sending');

            // Fire invitation sent event (for queued emails)
            InvitationSent::dispatch($invitation, $acceptUrl, ['delivery_method' => 'queued']);
        } else {
            // Send immediately
            try {
                Mail::to($invitation->email)->send(new InvitationMail($invitation, $acceptUrl));

                activity('invitation_sent')
                    ->performedOn($invitation)
                    ->withProperties([
                        'email' => $invitation->email,
                        'role' => $invitation->role,
                        'company' => $invitation->company->name,
                    ])
                    ->log('Invitation email sent successfully');

                // Fire invitation sent event (for immediate emails)
                InvitationSent::dispatch($invitation, $acceptUrl, ['delivery_method' => 'immediate']);
            } catch (Exception $e) {
                activity('invitation_failed')
                    ->performedOn($invitation)
                    ->withProperties([
                        'email' => $invitation->email,
                        'error' => $e->getMessage(),
                    ])
                    ->log('Failed to send invitation email');

                throw $e;
            }
        }
    }

    /**
     * Run tenant-specific migrations.
     */
    protected function runTenantMigrations(): void
    {
        // This method will run migrations specific to tenant databases
        // For now, we'll use the default tenant migrations
        Artisan::call('tenants:migrate');
    }

    /**
     * Accept an invitation and create the user account.
     *
     * @throws InvitationException
     */
    public function acceptInvitation(string $token, array $userData): User
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            throw new InvitationException(
                'Invalid or expired invitation token',
                $invitation,
                ['token' => $token]
            );
        }

        $user = null;

        try {
            return DB::transaction(function () use ($invitation, $userData, &$user) {
                // Create the user
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($userData['password']),
                    'tenant_id' => $invitation->tenant_id,
                    'role' => $invitation->role,
                    'must_change_password' => false,
                ]);

                // Mark invitation as accepted
                $invitation->markAsAccepted();

                activity('user_registration')
                    ->performedOn($user)
                    ->withProperties([
                        'invitation_id' => $invitation->id,
                        'role' => $user->role,
                    ])
                    ->log('User registered via invitation');

                // Fire invitation accepted event
                InvitationAccepted::dispatch($invitation, $user, ['operation' => 'accept_invitation']);

                return $user;
            });
        } catch (Exception $e) {
            // Perform cleanup on failure
            if ($user) {
                try {
                    $this->cleanupService->cleanupFailedTenantCreation(
                        null,
                        null,
                        $user,
                        ['operation' => 'accept_invitation', 'token' => $token]
                    );
                } catch (Exception $cleanupException) {
                    // Log cleanup failure but don't override original exception
                    activity('user_cleanup_failed')
                        ->performedOn($user)
                        ->withProperties([
                            'original_error' => $e->getMessage(),
                            'cleanup_error' => $cleanupException->getMessage(),
                        ])
                        ->log('Failed to cleanup after user creation failure');
                }
            }

            throw new InvitationException(
                'Failed to accept invitation: ' . $e->getMessage(),
                $invitation,
                ['token' => $token, 'user_data' => Arr::except($userData, ['password'])],
                0,
                $e
            );
        }
    }

    /**
     * Build invitation URL using central domain (consistent with Invitation model).
     */
    protected function buildInvitationUrl(Invitation $invitation): string
    {
        // Use the Invitation model's getAcceptanceUrl() method to ensure consistency
        return $invitation->getAcceptanceUrl();
    }
}
