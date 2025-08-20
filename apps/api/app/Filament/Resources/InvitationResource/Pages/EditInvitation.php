<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvitation extends EditRecord
{
    protected static string $resource = InvitationResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        if ($record->isAccepted()) {
            return 'View Invitation (Read Only)';
        }

        return 'Edit Invitation';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $invitation = $this->getRecord();

        // Redirect to view if invitation is accepted
        if ($invitation->isAccepted()) {
            Notification::make()
                ->title('Cannot Edit Accepted Invitation')
                ->body('This invitation has been accepted and cannot be modified.')
                ->warning()
                ->send();

            $this->redirectRoute('filament.admin.resources.invitations.index');
        }
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();
        $invitation = $this->getRecord();

        // Check for duplicate invitation (if changing email or tenant_id)
        if (isset($data['email']) && isset($data['tenant_id'])) {
            $existingInvitation = \App\Models\Invitation::where('email', $data['email'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('id', '!=', $invitation->id)
                ->first();

            if ($existingInvitation) {
                Notification::make()
                    ->title('Duplicate Invitation')
                    ->body('An invitation for this email already exists in the selected organization.')
                    ->danger()
                    ->send();
                $this->halt();
            }
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Invitation Updated')
            ->success();
    }
}
