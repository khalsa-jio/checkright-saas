<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getView(): string
    {
        return 'filament.pages.login';
    }

    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            // Handle validation errors gracefully
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError("data.{$field}", $message);
                }
            }

            // Clear password field for security
            $this->form->fill([
                'email' => $this->form->getState()['email'] ?? '',
                'password' => '',
                'remember' => $this->form->getState()['remember'] ?? false,
            ]);

            return null;
        } catch (\Exception $exception) {
            // Handle other errors like session timeout (419)
            if ($exception->getCode() === 419) {
                Notification::make()
                    ->title('Session Expired')
                    ->body('Please refresh the page and try again.')
                    ->warning()
                    ->send();

                // Just add error and return null instead of redirect
                $this->addError('data.email', 'Session expired. Please try again.');

                return null;
            }

            // Generic error handling
            $this->addError('data.email', 'An error occurred. Please try again.');

            return null;
        }
    }

    public function getSocialLoginButtons(): array
    {
        // Check if social login routes exist (try both central and tenant route names)
        $routeName = null;
        $currentHost = request()->getHost();
        $centralDomains = config('tenancy.central_domains', ['checkright.test']);

        // For tenant domains, use tenant routes
        if (! in_array($currentHost, $centralDomains)) {
            if (app('router')->has('tenant.auth.redirect')) {
                $routeName = 'tenant.auth.redirect';
            }
        } else {
            // For central domain, try central routes
            if (app('router')->has('social.redirect')) {
                $routeName = 'social.redirect';
            } elseif (app('router')->has('tenant.auth.redirect')) {
                // Fallback to tenant route for central domain
                $routeName = 'tenant.auth.redirect';
            }
        }

        // If no routes found, don't show social buttons
        if (! $routeName) {
            return [];
        }

        return [
            [
                'provider' => 'google',
                'label' => 'Continue with Google',
                'color' => '#DB4437',
                'url' => route($routeName, ['provider' => 'google']),
            ],
            [
                'provider' => 'facebook',
                'label' => 'Continue with Facebook',
                'color' => '#4267B2',
                'url' => route($routeName, ['provider' => 'facebook']),
            ],
            [
                'provider' => 'instagram',
                'label' => 'Continue with Instagram',
                'color' => '#E4405F',
                'url' => route($routeName, ['provider' => 'instagram']),
            ],
        ];
    }
}
