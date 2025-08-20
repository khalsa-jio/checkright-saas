<?php

namespace App\Listeners;

use App\Events\TenantCreationFailed;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackTenantCreationFailure implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'analytics';

    public $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(TenantCreationFailed $event): void
    {
        $failureData = $event->getFailureData();

        Log::warning('Tenant creation failed - tracking for analysis', $failureData);

        try {
            // Track failure metrics
            $this->trackFailureMetrics($event);

            // Send alert to administrators
            $this->sendFailureAlert($event);

            // Store failure data for analysis
            $this->storeFailureAnalytics($event);
        } catch (Exception $e) {
            Log::error('Failed to track tenant creation failure', [
                'original_failure' => $failureData,
                'tracking_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track failure metrics for monitoring.
     */
    protected function trackFailureMetrics(TenantCreationFailed $event): void
    {
        $failureData = $event->getFailureData();

        // Log metrics that could be picked up by monitoring systems
        Log::channel('metrics')->info('tenant_creation_failure', [
            'metric' => 'tenant_creation_failure',
            'error_type' => $failureData['error_type'],
            'domain' => $failureData['domain'],
            'timestamp' => now(),
        ]);

        activity('tenant_creation_failure_tracked')
            ->withProperties($failureData)
            ->log('Tenant creation failure tracked for analysis');
    }

    /**
     * Send failure alert to system administrators.
     */
    protected function sendFailureAlert(TenantCreationFailed $event): void
    {
        $failureData = $event->getFailureData();

        // TODO 3 - In a real implementation, you might:
        // - Send email alerts to administrators
        // - Post to Slack
        // - Create support tickets
        // - Update monitoring dashboards

        Log::alert('TENANT CREATION FAILURE ALERT', [
            'company_name' => $failureData['company_name'],
            'domain' => $failureData['domain'],
            'admin_email' => $failureData['admin_email'],
            'error' => $failureData['error_message'],
            'timestamp' => now(),
        ]);
    }

    /**
     * Store failure data for analysis.
     */
    protected function storeFailureAnalytics(TenantCreationFailed $event): void
    {
        $failureData = $event->getFailureData();

        // Store in analytics database/service for trend analysis
        // This could help identify:
        // - Common failure patterns
        // - Peak failure times
        // - Domain/email patterns in failures
        // - Error type distributions

        Log::info('Failure analytics data stored', [
            'failure_id' => uniqid('failure_', true),
            'data' => $failureData,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(TenantCreationFailed $event, Throwable $exception): void
    {
        Log::error('TrackTenantCreationFailure listener failed', [
            'original_failure' => $event->getFailureData(),
            'listener_error' => $exception->getMessage(),
        ]);
    }
}
