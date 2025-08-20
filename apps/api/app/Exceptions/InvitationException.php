<?php

namespace App\Exceptions;

use App\Models\Invitation;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvitationException extends Exception
{
    protected $invitation;

    protected $context;

    public function __construct(string $message, ?Invitation $invitation = null, array $context = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->invitation = $invitation;
        $this->context = $context;
    }

    /**
     * Get the invitation that caused the exception.
     */
    public function getInvitation(): ?Invitation
    {
        return $this->invitation;
    }

    /**
     * Get additional context about the exception.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        $properties = [
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
            'code' => $this->getCode(),
        ];

        if ($this->invitation) {
            $properties['invitation_id'] = $this->invitation->id;
            $properties['invitation_email'] = $this->invitation->email;
            $properties['invitation_role'] = $this->invitation->role;
            $properties['tenant_id'] = $this->invitation->tenant_id;
        }

        activity('invitation_exception')
            ->performedOn($this->invitation)
            ->withProperties($properties)
            ->log('Invitation exception occurred');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Invitation Error',
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ], 422);
        }

        return response()->view('errors.invitation', [
            'message' => $this->getMessage(),
        ], 422);
    }
}
