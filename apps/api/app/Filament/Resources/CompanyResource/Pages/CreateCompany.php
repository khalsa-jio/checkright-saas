<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Services\TenantCreationService;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenantService = app(TenantCreationService::class);

        try {
            $result = $tenantService->createTenantWithAdmin(
                [
                    'name' => $data['name'],
                    'domain' => $data['domain'],
                ],
                [
                    'email' => $data['admin_email'],
                ]
            );

            Notification::make()
                ->title('Company created successfully!')
                ->body('An invitation has been sent to ' . $data['admin_email'])
                ->success()
                ->send();

            return $result['company'];
        } catch (Exception $e) {
            Notification::make()
                ->title('Error creating company')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
