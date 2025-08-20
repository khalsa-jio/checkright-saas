<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => TenantPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register custom gates for user management
        Gate::define('invite', function ($user, $role) {
            // Admin can invite anyone
            if ($user->isAdmin()) {
                return true;
            }

            // Manager can only invite operators
            if ($user->isManager() && $role === 'operator') {
                return true;
            }

            return false;
        });
    }
}
