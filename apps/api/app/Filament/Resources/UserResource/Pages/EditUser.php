<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->label('Deactivate User')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('User Deactivated')
                        ->body('The user has been deactivated successfully.')
                ),
            RestoreAction::make()
                ->label('Reactivate User')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('User Reactivated')
                        ->body('The user has been reactivated successfully.')
                ),
            ForceDeleteAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('User Updated')
            ->body('The user has been updated successfully.');
    }
}
