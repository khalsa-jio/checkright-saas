<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'name',
        'email',
        'avatar',
        'token',
        'refresh_token',
        'expires_in',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_in' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        if (! $this->expires_in) {
            return false;
        }

        return now()->timestamp > ($this->updated_at->timestamp + $this->expires_in);
    }

    public function getProviderDisplayNameAttribute(): string
    {
        return match ($this->provider) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            default => ucfirst($this->provider),
        };
    }
}
