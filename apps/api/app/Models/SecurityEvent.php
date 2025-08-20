<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class SecurityEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'event_type',
        'user_id',
        'tenant_id',
        'ip_address',
        'user_agent',
        'device_id',
        'session_id',
        'context',
        'risk_score',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'risk_score' => 'float',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the user associated with this security event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a high-risk event.
     */
    public function isHighRisk(): bool
    {
        return $this->risk_score >= 0.8;
    }

    /**
     * Check if this is a critical event.
     */
    public function isCritical(): bool
    {
        return $this->risk_score >= 0.9;
    }

    /**
     * Get the risk level as a string.
     */
    public function getRiskLevelAttribute(): string
    {
        if ($this->risk_score >= 0.9) {
            return 'critical';
        } elseif ($this->risk_score >= 0.8) {
            return 'high';
        } elseif ($this->risk_score >= 0.6) {
            return 'medium';
        } elseif ($this->risk_score >= 0.3) {
            return 'low';
        } else {
            return 'info';
        }
    }

    /**
     * Get a human-readable event description.
     */
    public function getEventDescriptionAttribute(): string
    {
        $descriptions = [
            'auth_success' => 'Successful authentication',
            'auth_failure' => 'Authentication failed',
            'token_refresh' => 'Token refreshed',
            'permission_denied' => 'Permission denied',
            'suspicious_activity' => 'Suspicious activity detected',
            'rate_limit_exceeded' => 'Rate limit exceeded',
            'device_change' => 'Device changed',
            'geographic_anomaly' => 'Geographic anomaly detected',
            'api_key_validation_failed' => 'API key validation failed',
            'device_validation_failed' => 'Device validation failed',
            'signature_validation_failed' => 'Request signature validation failed',
            'security_validation_success' => 'Security validation successful',
            'untrusted_device_access' => 'Access from untrusted device',
        ];

        return $descriptions[$this->event_type] ?? 'Unknown security event';
    }

    /**
     * Scope to get high-risk events.
     */
    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 0.8);
    }

    /**
     * Scope to get critical events.
     */
    public function scopeCritical($query)
    {
        return $query->where('risk_score', '>=', 0.9);
    }

    /**
     * Scope to get events for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get events by type.
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get recent events.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }
}
