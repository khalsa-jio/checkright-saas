<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    public function getTitle(): string
    {
        return 'Invitations';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Send Invitation')
                ->icon('heroicon-o-envelope'),
        ];
    }
}
