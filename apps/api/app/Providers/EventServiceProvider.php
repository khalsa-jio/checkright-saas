<?php

namespace App\Providers;

use App\Events\InvitationAccepted;
use App\Events\InvitationSent;
use App\Events\TenantCreated;
use App\Events\TenantCreationFailed;
use App\Listeners\SetupNewTenant;
use App\Listeners\TrackTenantCreationFailure;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Observers\CompanyObserver;
use App\Observers\InvitationObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Laravel default events
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Tenant-related events
        TenantCreated::class => [
            SetupNewTenant::class,
        ],

        TenantCreationFailed::class => [
            TrackTenantCreationFailure::class,
        ],

        InvitationSent::class => [
            'App\Listeners\TrackInvitationActivity@handleInvitationSent',
        ],

        InvitationAccepted::class => [
            'App\Listeners\TrackInvitationActivity@handleInvitationAccepted',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register model observers
        Company::observe(CompanyObserver::class);
        User::observe(UserObserver::class);
        Invitation::observe(InvitationObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
