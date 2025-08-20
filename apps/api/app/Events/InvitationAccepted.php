<?php

namespace App\Events;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationAccepted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invitation $invitation,
        public User $user,
        public array $context = []
    ) {
        $this->context = array_merge([
            'accepted_at' => now(),
            'registration_source' => 'invitation',
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
     * Get acceptance data for analytics.
     */
    public function getAcceptanceData(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'role' => $this->user->role,
            'company_id' => $this->user->tenant_id,
            'company_name' => $this->user->company->name,
            'invitation_sent_at' => $this->invitation->created_at,
            'time_to_accept' => $this->invitation->created_at->diffInHours(now()),
            'context' => $this->context,
        ];
    }
}
