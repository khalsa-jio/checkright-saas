<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SecurityLogger
{
    protected array $securityEvents = [
        'auth_success',
        'auth_failure',
        'token_refresh',
        'permission_denied',
        'suspicious_activity',
        'rate_limit_exceeded',
        'device_change',
        'geographic_anomaly',
        'api_key_validation_failed',
        'device_validation_failed',
        'signature_validation_failed',
        'security_validation_success',
        'untrusted_device_access',
    ];

    /**
     * Log a security event with context.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        if (! in_array($event, $this->securityEvents)) {
            Log::warning("Unknown security event type: {$event}");
        }

        $logEntry = [
            'event' => $event,
            'timestamp' => now(),
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'device_id' => request()?->header('X-Device-Id'),
            'session_id' => session()?->getId(),
            'context' => $context,
            'risk_score' => $this->calculateRiskScore($event, $context),
        ];

        // Log to security channel
        Log::channel('security')->info($event, $logEntry);

        // Store in database for high-risk events
        if ($logEntry['risk_score'] > 0.6) {
            $this->storeSecurityEvent($logEntry);
        }

        // Send to SIEM for critical events
        if ($logEntry['risk_score'] > 0.8) {
            $this->sendToSIEM($logEntry);
        }

        // Trigger real-time alerts for critical events
        if ($logEntry['risk_score'] > 0.9) {
            $this->triggerRealTimeAlert($logEntry);
        }
    }

    /**
     * Calculate risk score for a security event.
     */
    protected function calculateRiskScore(string $event, array $context): float
    {
        $baseScores = [
            'auth_failure' => 0.3,
            'permission_denied' => 0.4,
            'suspicious_activity' => 0.8,
            'rate_limit_exceeded' => 0.5,
            'device_change' => 0.6,
            'geographic_anomaly' => 0.7,
            'api_key_validation_failed' => 0.9,
            'device_validation_failed' => 0.8,
            'signature_validation_failed' => 0.9,
            'security_validation_success' => 0.1,
            'untrusted_device_access' => 0.6,
            'auth_success' => 0.1,
            'token_refresh' => 0.1,
        ];

        $baseScore = $baseScores[$event] ?? 0.5;

        // Adjust score based on context
        $modifiers = 0.0;

        // Repeated failures increase risk
        if (isset($context['failure_count']) && $context['failure_count'] > 1) {
            $modifiers += min($context['failure_count'] * 0.1, 0.3);
        }

        // Geographic anomalies increase risk
        if (isset($context['geographic_distance']) && $context['geographic_distance'] > 1000) {
            $modifiers += 0.2;
        }

        // Suspicious user agent patterns
        if (isset($context['suspicious_user_agent']) && $context['suspicious_user_agent']) {
            $modifiers += 0.2;
        }

        // Multiple simultaneous sessions
        if (isset($context['concurrent_sessions']) && $context['concurrent_sessions'] > 3) {
            $modifiers += 0.15;
        }

        return min($baseScore + $modifiers, 1.0);
    }

    /**
     * Store security event in database.
     */
    protected function storeSecurityEvent(array $logEntry): void
    {
        try {
            SecurityEvent::create([
                'event_type' => $logEntry['event'],
                'user_id' => $logEntry['user_id'],
                'tenant_id' => $logEntry['tenant_id'],
                'ip_address' => $logEntry['ip_address'],
                'user_agent' => $logEntry['user_agent'],
                'device_id' => $logEntry['device_id'],
                'session_id' => $logEntry['session_id'],
                'context' => $logEntry['context'],
                'risk_score' => $logEntry['risk_score'],
                'occurred_at' => $logEntry['timestamp'],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to store security event in database', [
                'error' => $e->getMessage(),
                'event' => $logEntry['event'],
            ]);
        }
    }

    /**
     * Send high-risk events to SIEM system.
     */
    protected function sendToSIEM(array $logEntry): void
    {
        // This would integrate with your SIEM system
        // For now, we'll log to a dedicated SIEM channel
        try {
            Log::channel('siem')->critical('High-risk security event', $logEntry);

            // Future: HTTP POST to SIEM webhook
            // Http::post(config('security.siem_webhook_url'), $logEntry);
        } catch (Exception $e) {
            Log::error('Failed to send event to SIEM', [
                'error' => $e->getMessage(),
                'event' => $logEntry['event'],
            ]);
        }
    }

    /**
     * Trigger real-time security alerts.
     */
    protected function triggerRealTimeAlert(array $logEntry): void
    {
        try {
            // Log critical alert
            Log::channel('security')->critical('CRITICAL SECURITY ALERT', $logEntry);

            // Future: Send to alerting system (Slack, PagerDuty, etc.)
            // Notification::route('slack', config('security.alert_webhook'))
            //     ->notify(new SecurityAlert($logEntry));
        } catch (Exception $e) {
            Log::error('Failed to trigger security alert', [
                'error' => $e->getMessage(),
                'event' => $logEntry['event'],
            ]);
        }
    }

    /**
     * Get security events for a user.
     */
    public function getUserSecurityEvents(int $userId, int $limit = 50): array
    {
        return SecurityEvent::where('user_id', $userId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get high-risk security events.
     */
    public function getHighRiskEvents(float $minRiskScore = 0.8, int $limit = 100): array
    {
        return SecurityEvent::where('risk_score', '>=', $minRiskScore)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get security event statistics.
     */
    public function getSecurityStats(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalEvents = SecurityEvent::where('occurred_at', '>=', $startDate)->count();
        $highRiskEvents = SecurityEvent::where('occurred_at', '>=', $startDate)
            ->where('risk_score', '>=', 0.8)
            ->count();
        $uniqueUsers = SecurityEvent::where('occurred_at', '>=', $startDate)
            ->distinct('user_id')
            ->count();

        return [
            'total_events' => $totalEvents,
            'high_risk_events' => $highRiskEvents,
            'unique_users_affected' => $uniqueUsers,
            'risk_ratio' => $totalEvents > 0 ? round(($highRiskEvents / $totalEvents) * 100, 2) : 0,
            'period_days' => $days,
        ];
    }
}
