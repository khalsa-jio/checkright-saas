<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Log;
use Stancl\Tenancy\Database\Models\Domain;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Create User';
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Get current tenant context
        $currentTenant = tenant();
        $currentDomain = request()->getHost();

        // Handle central domain vs tenant domain for direct creation
        if (! $currentTenant) {
            // Try to manually resolve tenant if middleware failed
            try {
                $domain = Domain::where('domain', $currentDomain)->first();
                if ($domain && $domain->tenant) {
                    tenancy()->initialize($domain->tenant);
                    $currentTenant = tenant();
                }
            } catch (Exception $e) {
                Log::error('Manual tenant initialization failed for direct creation', [
                    'domain' => $currentDomain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final check
        if (! $currentTenant) {
            Notification::make()
                ->title('User creation must be done from a tenant domain')
                ->body("Domain: {$currentDomain}. Unable to identify tenant. Please access this form from your organization's subdomain.")
                ->danger()
                ->send();

            $this->halt();
        }

        // Check for duplicate user email
        $existingUser = User::where('email', $data['email'])
            ->where('tenant_id', $currentTenant->id)
            ->first();

        if ($existingUser) {
            Notification::make()
                ->title('User Already Exists')
                ->body('A user with this email already exists.')
                ->danger()
                ->send();

            $this->halt();
        }

        // Set tenant_id and ensure email_verified_at is set for direct creation
        $data['tenant_id'] = $currentTenant->id;
        $data['email_verified_at'] = now();

        // Create user directly
        return User::create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
