<?php

namespace App\Jobs;

use App\Exceptions\TenantCreationException;
use App\Services\TenantCreationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $timeout = 300; // 5 minutes

    public $backoff = [60, 120, 300]; // Exponential backoff: 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $companyData,
        public array $adminData
    ) {
        $this->onQueue('tenant-creation');
    }

    /**
     * Execute the job.
     */
    public function handle(TenantCreationService $tenantService): array
    {
        try {
            Log::info('Starting tenant creation job', [
                'company_data' => $this->companyData,
                'admin_email' => $this->adminData['email'] ?? 'unknown',
            ]);

            $result = $tenantService->createTenantWithAdmin($this->companyData, $this->adminData);

            Log::info('Tenant creation job completed successfully', [
                'company_id' => $result['company']->id,
                'invitation_id' => $result['invitation']->id,
            ]);

            return $result;
        } catch (TenantCreationException $e) {
            Log::error('Tenant creation job failed with domain-specific error', [
                'error' => $e->getMessage(),
                'company_data' => $e->getCompanyData(),
                'admin_data' => $e->getAdminData(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        } catch (Exception $e) {
            Log::error('Tenant creation job failed with generic error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_data' => $this->companyData,
                'admin_email' => $this->adminData['email'] ?? 'unknown',
            ]);

            // Wrap generic exceptions in domain-specific exception
            throw new TenantCreationException(
                'Tenant creation job failed: ' . $e->getMessage(),
                $this->companyData,
                $this->adminData,
                0,
                $e
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Tenant creation job failed permanently', [
            'error' => $exception->getMessage(),
            'company_data' => $this->companyData,
            'admin_email' => $this->adminData['email'] ?? 'unknown',
        ]);

        activity('tenant_creation_failed')
            ->withProperties([
                'company_data' => $this->companyData,
                'admin_email' => $this->adminData['email'] ?? 'unknown',
                'error' => $exception->getMessage(),
            ])
            ->log('Tenant creation job failed permanently after all retries');
    }
}
