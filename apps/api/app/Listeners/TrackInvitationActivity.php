<?php

namespace App\Listeners;

use App\Events\InvitationAccepted;
use App\Events\InvitationSent;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackInvitationActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'analytics';

    public $tries = 2;

    /**
     * Handle invitation sent event.
     */
    public function handleInvitationSent(InvitationSent $event): void
    {
        $invitationData = $event->getInvitationData();

        Log::info('Invitation sent - tracking activity', [
            'invitation_id' => $invitationData['invitation_id'],
            'email' => $invitationData['email'],
            'company_id' => $invitationData['company_id'],
        ]);

        try {
            // Track invitation metrics
            $this->trackInvitationMetrics('sent', $invitationData);

            // Update company invitation statistics
            $this->updateCompanyStats($invitationData['company_id'], 'invitation_sent');
        } catch (Exception $e) {
            Log::error('Failed to track invitation sent activity', [
                'invitation_data' => $invitationData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle invitation accepted event.
     */
    public function handleInvitationAccepted(InvitationAccepted $event): void
    {
        $acceptanceData = $event->getAcceptanceData();

        Log::info('Invitation accepted - tracking conversion', [
            'invitation_id' => $acceptanceData['invitation_id'],
            'user_id' => $acceptanceData['user_id'],
            'time_to_accept' => $acceptanceData['time_to_accept'] . ' hours',
        ]);

        try {
            // Track conversion metrics
            $this->trackInvitationMetrics('accepted', $acceptanceData);

            // Update company conversion statistics
            $this->updateCompanyStats($acceptanceData['company_id'], 'invitation_accepted');

            // Track time-to-acceptance for optimization
            $this->trackTimeToAcceptance($acceptanceData);
        } catch (Exception $e) {
            Log::error('Failed to track invitation acceptance activity', [
                'acceptance_data' => $acceptanceData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track invitation-related metrics.
     */
    protected function trackInvitationMetrics(string $action, array $data): void
    {
        // Log metrics for monitoring systems
        Log::channel('metrics')->info("invitation_{$action}", [
            'metric' => "invitation_{$action}",
            'company_id' => $data['company_id'],
            'role' => $data['role'],
            'timestamp' => now(),
        ]);

        activity("invitation_{$action}_tracked")
            ->withProperties($data)
            ->log("Invitation {$action} activity tracked");
    }

    /**
     * Update company-specific invitation statistics.
     */
    protected function updateCompanyStats(string $companyId, string $eventType): void
    {
        // This could update cached statistics or trigger
        // statistics recalculation for the company

        Log::info('Company invitation stats updated', [
            'company_id' => $companyId,
            'event_type' => $eventType,
        ]);

        // TODO 4 -In a real implementation, you might:
        // - Update cached statistics
        // - Trigger analytics calculation jobs
        // - Update dashboards
        // - Send notifications on milestones
    }

    /**
     * Track time-to-acceptance analytics.
     */
    protected function trackTimeToAcceptance(array $acceptanceData): void
    {
        $timeToAccept = $acceptanceData['time_to_accept'];

        Log::channel('metrics')->info('invitation_acceptance_time', [
            'metric' => 'invitation_acceptance_time',
            'hours' => $timeToAccept,
            'company_id' => $acceptanceData['company_id'],
            'role' => $acceptanceData['role'],
            'timestamp' => now(),
        ]);

        // TODO - This data could be used to:
        // - Optimize invitation expiry times
        // - Identify patterns in user behavior
        // - A/B test invitation content/timing
        // - Improve onboarding flows
    }

    /**
     * Handle a job failure.
     */
    public function failed($event, Throwable $exception): void
    {
        $eventType = get_class($event);

        Log::error("TrackInvitationActivity listener failed for {$eventType}", [
            'event_class' => $eventType,
            'error' => $exception->getMessage(),
        ]);
    }
}
