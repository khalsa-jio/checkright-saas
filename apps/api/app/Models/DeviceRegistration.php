<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class DeviceRegistration extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_info',
        'device_secret',
        'is_trusted',
        'registered_at',
        'trusted_at',
        'trusted_until',
        'last_used_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_trusted' => 'boolean',
        'registered_at' => 'datetime',
        'trusted_at' => 'datetime',
        'trusted_until' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'device_secret',
    ];

    /**
     * Get the user that owns this device registration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the device is currently trusted.
     */
    public function isTrusted(): bool
    {
        return $this->is_trusted &&
               $this->trusted_until &&
               $this->trusted_until->isFuture();
    }

    /**
     * Check if the device trust has expired.
     */
    public function isTrustExpired(): bool
    {
        return $this->is_trusted &&
               $this->trusted_until &&
               $this->trusted_until->isPast();
    }

    /**
     * Get device info as a readable string.
     */
    public function getDeviceInfoStringAttribute(): string
    {
        $info = $this->device_info ?? [];

        $parts = [];
        if (isset($info['platform'])) {
            $parts[] = $info['platform'];
        }
        if (isset($info['model'])) {
            $parts[] = $info['model'];
        }
        if (isset($info['version'])) {
            $parts[] = "v{$info['version']}";
        }

        return implode(' ', $parts) ?: 'Unknown Device';
    }

    /**
     * Scope to get trusted devices.
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true)
            ->where('trusted_until', '>', now());
    }

    /**
     * Scope to get expired trusted devices.
     */
    public function scopeExpiredTrust($query)
    {
        return $query->where('is_trusted', true)
            ->where('trusted_until', '<=', now());
    }

    /**
     * Scope to get devices for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
