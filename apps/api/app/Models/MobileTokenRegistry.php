<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class MobileTokenRegistry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'access_token_id',
        'refresh_token_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this token registry entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the access token.
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'access_token_id');
    }

    /**
     * Get the refresh token.
     */
    public function refreshToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'refresh_token_id');
    }

    /**
     * Check if the token registry entry is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if both tokens are valid.
     */
    public function areTokensValid(): bool
    {
        $accessValid = $this->accessToken &&
                      (! $this->accessToken->expires_at || $this->accessToken->expires_at->isFuture());

        $refreshValid = $this->refreshToken &&
                       (! $this->refreshToken->expires_at || $this->refreshToken->expires_at->isFuture());

        return $accessValid && $refreshValid;
    }

    /**
     * Get token pair status.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->accessToken || ! $this->refreshToken) {
            return 'invalid';
        }

        $accessExpired = $this->accessToken->expires_at?->isPast() ?? false;
        $refreshExpired = $this->refreshToken->expires_at?->isPast() ?? false;

        if ($accessExpired && $refreshExpired) {
            return 'expired';
        }

        if ($accessExpired && ! $refreshExpired) {
            return 'refresh_only';
        }

        if (! $accessExpired && ! $refreshExpired) {
            return 'active';
        }

        return 'unknown';
    }

    /**
     * Scope to get active token registries.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereHas('accessToken', function ($q) {
                $q->where('expires_at', '>', now());
            })
            ->whereHas('refreshToken', function ($q) {
                $q->where('expires_at', '>', now());
            });
    }

    /**
     * Scope to get expired token registries.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->orWhereHas('accessToken', function ($q) {
                $q->where('expires_at', '<=', now());
            })
            ->orWhereHas('refreshToken', function ($q) {
                $q->where('expires_at', '<=', now());
            });
    }

    /**
     * Scope to get registries for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get registries for a specific device.
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope to get registries for a specific user and device.
     */
    public function scopeForUserDevice($query, int $userId, string $deviceId)
    {
        return $query->where('user_id', $userId)->where('device_id', $deviceId);
    }
}
