<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invitation extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = bin2hex(random_bytes(32)); // Cryptographically secure 64-char hex token
            }

            if (empty($invitation->expires_at)) {
                $invitation->expires_at = Carbon::now()->addDays(7);
            }
        });
    }

    /**
     * Get the company that owns the invitation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if the invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    /**
     * Check if the invitation is still valid.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted() && ! $this->userAlreadyExists();
    }

    /**
     * Check if a user already exists with the invitation email.
     */
    public function userAlreadyExists(): bool
    {
        return User::where('email', $this->email)
            ->where('tenant_id', $this->tenant_id)
            ->exists();
    }

    /**
     * Get the existing user for this invitation email if one exists.
     */
    public function existingUser(): ?User
    {
        return User::where('email', $this->email)
            ->where('tenant_id', $this->tenant_id)
            ->first();
    }

    /**
     * Get the status of the invitation.
     */
    public function getStatus(): string
    {
        if ($this->isAccepted()) {
            return 'accepted';
        }

        if ($this->userAlreadyExists()) {
            return 'user_exists';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }

    /**
     * Mark the invitation as accepted.
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => Carbon::now()]);
    }

    /**
     * Scope for expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', Carbon::now())
            ->whereNull('accepted_at');
    }

    /**
     * Scope for pending invitations (valid and not accepted, user doesn't exist).
     */
    public function scopePending($query)
    {
        return $query->where('expires_at', '>', Carbon::now())
            ->whereNull('accepted_at')
            ->whereNotExists(function ($subquery) {
                $subquery->select('id')
                    ->from('users')
                    ->whereColumn('users.email', 'invitations.email')
                    ->whereColumn('users.tenant_id', 'invitations.tenant_id');
            });
    }

    /**
     * Scope for invitations where user already exists.
     */
    public function scopeUserExists($query)
    {
        return $query->whereExists(function ($subquery) {
            $subquery->select('id')
                ->from('users')
                ->whereColumn('users.email', 'invitations.email')
                ->whereColumn('users.tenant_id', 'invitations.tenant_id');
        })
            ->whereNull('accepted_at');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    /**
     * Get the acceptance URL for this invitation.
     * Returns URL for the appropriate domain based on invitation context.
     */
    public function getAcceptanceUrl(): string
    {
        // Determine if this is a central domain invitation (no tenant_id) or tenant invitation
        if (empty($this->tenant_id)) {
            // Central domain invitation for super admin - use different path to avoid route collision
            $centralDomains = config('tenancy.central_domains', []);
            $domain = $centralDomains[0] ?? 'localhost';
            $protocol = request() && request()->isSecure() ? 'https' : 'http';

            return "{$protocol}://{$domain}/central-invitation/{$this->token}";
        }

        // Tenant domain invitation - construct tenant domain URL
        $company = $this->company;
        if (! $company || ! $company->domain) {
            throw new \Exception('Invalid tenant company or domain for invitation');
        }

        $protocol = request() && request()->isSecure() ? 'https' : 'http';
        $tenantDomain = $company->domain . config('tenant.domain.suffix', '.test');

        return "{$protocol}://{$tenantDomain}/invitation/{$this->token}";
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'role', 'accepted_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
