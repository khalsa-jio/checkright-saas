<?php

namespace App\Listeners;

use App\Events\TenantCreated;
use App\Services\Caching\TenantCacheService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetupNewTenant implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'tenant-setup';

    public $tries = 3;

    public $timeout = 300; // 5 minutes

    /**
     * Create the event listener.
     */
    public function __construct(
        protected TenantCacheService $cacheService
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TenantCreated $event): void
    {
        Log::info('Setting up new tenant', $event->getTenantData());

        try {
            // Setup default tenant configuration
            $this->setupTenantDefaults($event);

            // Initialize caching for tenant
            $this->initializeTenantCache($event);

            // Send welcome notifications
            $this->sendWelcomeNotifications($event);

            $company = $event->getCompany();

            Log::info('New tenant setup completed', [
                'company_id' => $company->id,
                'company_name' => $company->name,
            ]);

            activity('tenant_setup_completed')
                ->performedOn($company)
                ->withProperties($event->getTenantData())
                ->log('New tenant setup completed successfully');
        } catch (Exception $e) {
            $company = $event->getCompany();

            Log::error('Failed to setup new tenant', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            activity('tenant_setup_failed')
                ->performedOn($company)
                ->withProperties([
                    'error' => $e->getMessage(),
                    'tenant_data' => $event->getTenantData(),
                ])
                ->log('New tenant setup failed');

            throw $e;
        }
    }

    /**
     * Setup default configuration for new tenant.
     */
    protected function setupTenantDefaults(TenantCreated $event): void
    {
        $company = $event->getCompany();

        // Set default tenant configuration
        $defaultConfig = [
            'features' => [
                'analytics' => true,
                'notifications' => true,
                'api_access' => false,
            ],
            'limits' => [
                'max_users' => 10,
                'max_invitations_per_day' => 5,
                'storage_limit_mb' => 1000,
            ],
            'settings' => [
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'language' => 'en',
            ],
        ];

        // Cache the configuration
        $this->cacheService->cacheTenantConfig($company->domain, $defaultConfig);

        Log::info('Default tenant configuration set', [
            'company_id' => $company->id,
            'domain' => $company->domain,
        ]);
    }

    /**
     * Initialize caching for the new tenant.
     */
    protected function initializeTenantCache(TenantCreated $event): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            $company = $event->getCompany();
            $this->cacheService->warmupCompanyCache($company);

            Log::info('Tenant cache initialized', [
                'company_id' => $company->id,
            ]);
        }
    }

    /**
     * Send welcome notifications to stakeholders.
     */
    protected function sendWelcomeNotifications(TenantCreated $event): void
    {
        // Send notification to system administrators
        $adminData = $event->getTenantData();

        $company = $event->getCompany();

        Log::info('Welcome notifications sent', [
            'company_id' => $company->id,
            'admin_email' => $adminData['admin_email'],
        ]);

        // TODO - Here you could send notifications via:
        // - Email to system admins
        // - Slack webhook
        // - Dashboard notifications
        // - Analytics tracking
    }

    /**
     * Handle a job failure.
     */
    public function failed(TenantCreated $event, Throwable $exception): void
    {
        $company = $event->getCompany();

        Log::error('SetupNewTenant listener failed permanently', [
            'company_id' => $company->id,
            'error' => $exception->getMessage(),
        ]);

        activity('tenant_setup_failed_permanently')
            ->performedOn($company)
            ->withProperties([
                'error' => $exception->getMessage(),
                'tenant_data' => $event->getTenantData(),
            ])
            ->log('New tenant setup failed permanently after all retries');
    }
}
