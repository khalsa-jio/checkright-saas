<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Invitation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvitation extends CreateRecord
{
    protected static string $resource = InvitationResource::class;

    public function getTitle(): string
    {
        return 'Send Invitation';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        // For super admin, allow invitations to any company
        if ($user?->isSuperAdmin()) {
            // tenant_id should come from the form data for super admin
            if (! isset($data['tenant_id'])) {
                Notification::make()
                    ->title('Company selection required')
                    ->danger()
                    ->send();
                $this->halt();
            }
        } else {
            // For regular users, use their tenant_id
            if (! $user || ! $user->tenant_id) {
                Notification::make()
                    ->title('User must be associated with a company')
                    ->danger()
                    ->send();
                $this->halt();
            }
            $data['tenant_id'] = $user->tenant_id;
        }

        // Check for duplicate invitation
        $existingInvitation = Invitation::where('email', $data['email'])
            ->where('tenant_id', $data['tenant_id'])
            ->first();

        if ($existingInvitation) {
            Notification::make()
                ->title('Duplicate Invitation')
                ->body('An invitation for this email already exists in the selected organization.')
                ->danger()
                ->send();
            $this->halt();
        }

        $data['invited_by'] = auth()->id();

        try {
            $invitation = Invitation::create($data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handle database-level unique constraint violation as backup
            Notification::make()
                ->title('Duplicate Invitation')
                ->body('An invitation for this email already exists in the selected organization. Please try again with a different email.')
                ->danger()
                ->send();
            $this->halt();
        }

        // Use central domain for invitation URLs (not tenant domain)
        $centralDomain = config('tenancy.central_domains')[0] ?? request()->getHost();
        $protocol = request()->isSecure() ? 'https' : 'http';
        $acceptUrl = "{$protocol}://{$centralDomain}/accept-invitation/{$invitation->token}";
        SendInvitationEmailJob::dispatch($invitation, $acceptUrl);

        Notification::make()
            ->title('Invitation Sent')
            ->body("Invitation sent to {$invitation->email}")
            ->success()
            ->send();

        return $invitation;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // handled manually
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
