<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'last_login_at',
        'must_change_password',
        'avatar_url',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get the company that owns the user.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Get the device registrations for this user.
     */
    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }

    /**
     * Get the security events for this user.
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    /**
     * Get the social accounts for this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Check if user has linked a specific social provider.
     */
    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    /**
     * Get social account for a specific provider.
     */
    public function getSocialAccount(string $provider): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', $provider)->first();
    }

    /**
     * Get user's avatar URL from their social accounts.
     * Returns the most recent avatar or null if none found.
     */
    public function getAvatarUrl(): ?string
    {
        $socialAccount = $this->socialAccounts()
            ->whereNotNull('avatar')
            ->latest('updated_at')
            ->first();

        return $socialAccount?->avatar;
    }

    /**
     * Get avatar URL from a specific social provider.
     */
    public function getAvatarUrlFromProvider(string $provider): ?string
    {
        $socialAccount = $this->getSocialAccount($provider);

        return $socialAccount?->avatar;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is a manager.
     */
    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    /**
     * Check if user is an operator.
     */
    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }

    /**
     * Check if user is a super-admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Get the token abilities for this user based on their role.
     */
    public function getTokenAbilities(): array
    {
        $baseAbilities = ['read'];

        return match ($this->role) {
            'admin' => array_merge($baseAbilities, [
                'create', 'update', 'delete',
                'manage-users', 'manage-roles', 'view-analytics',
            ]),
            'manager' => array_merge($baseAbilities, [
                'create', 'update',
                'manage-users', 'view-analytics',
            ]),
            'operator' => array_merge($baseAbilities, [
                'create', 'update',
            ]),
            default => $baseAbilities
        };
    }

    /**
     * Check if user has specific token ability.
     */
    public function hasTokenAbility(string $ability): bool
    {
        return in_array($ability, $this->getTokenAbilities());
    }

    /**
     * Ensure the user belongs to the current tenant context.
     */
    public function belongsToCurrentTenant(): bool
    {
        if (! app()->bound('tenant')) {
            return true; // Allow access if no tenant context
        }

        $currentTenant = tenant();

        return $currentTenant && $this->tenant_id === $currentTenant->id;
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'last_login_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow access for admin, manager, and super-admin roles
        return $this->isAdmin() || $this->isManager() || $this->isSuperAdmin();
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
