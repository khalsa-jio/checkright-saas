<?php

namespace App\Events;

use App\Models\Invitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationSent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invitation $invitation,
        public string $acceptUrl,
        public array $context = []
    ) {
        $this->context = array_merge([
            'sent_at' => now(),
            'delivery_method' => 'email',
        ], $context);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get invitation data for tracking.
     */
    public function getInvitationData(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'role' => $this->invitation->role,
            'company_id' => $this->invitation->tenant_id,
            'company_name' => $this->invitation->company->name,
            'accept_url' => $this->acceptUrl,
            'expires_at' => $this->invitation->expires_at,
            'context' => $this->context,
        ];
    }
}
