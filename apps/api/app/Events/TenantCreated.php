<?php

namespace App\Events;

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string|int $companyId,
        public string|int $invitationId,
        public array $context = []
    ) {
        $this->context = array_merge([
            'created_at' => now(),
            'source' => 'system',
        ], $context);
    }

    /**
     * Get the company model (loaded fresh for queue safety).
     */
    public function getCompany(): Company
    {
        return Company::findOrFail($this->companyId);
    }

    /**
     * Get the invitation model (loaded fresh for queue safety).
     */
    public function getInvitation(): Invitation
    {
        return Invitation::findOrFail($this->invitationId);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get additional data about the tenant creation.
     */
    public function getTenantData(): array
    {
        $company = $this->getCompany();
        $invitation = $this->getInvitation();

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'domain' => $company->domain,
            'invitation_id' => $invitation->id,
            'admin_email' => $invitation->email,
            'admin_role' => $invitation->role,
            'context' => $this->context,
        ];
    }
}
