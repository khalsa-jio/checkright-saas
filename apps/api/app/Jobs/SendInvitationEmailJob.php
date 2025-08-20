<?php

namespace App\Jobs;

use App\Exceptions\InvitationException;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvitationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $timeout = 60; // 1 minute

    public $backoff = [30, 60, 120]; // Exponential backoff: 30s, 1min, 2min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invitation $invitation,
        public string $acceptUrl
    ) {
        $this->onQueue('email-sending');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending invitation email', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'company' => $this->invitation->company?->name ?? 'Unknown Company',
            ]);

            Mail::to($this->invitation->email)->send(new InvitationMail($this->invitation, $this->acceptUrl));

            activity('invitation_sent')
                ->performedOn($this->invitation)
                ->withProperties([
                    'email' => $this->invitation->email,
                    'role' => $this->invitation->role,
                    'company' => $this->invitation->company?->name ?? 'Unknown Company',
                ])
                ->log('Invitation email sent successfully via queue');

            Log::info('Invitation email sent successfully', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send invitation email', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'error' => $e->getMessage(),
            ]);

            activity('invitation_failed')
                ->performedOn($this->invitation)
                ->withProperties([
                    'email' => $this->invitation->email,
                    'error' => $e->getMessage(),
                ])
                ->log('Failed to send invitation email via queue');

            // Wrap in domain-specific exception
            throw new InvitationException(
                'Failed to send invitation email: ' . $e->getMessage(),
                $this->invitation,
                ['accept_url' => $this->acceptUrl],
                0,
                $e
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Invitation email job failed permanently', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'error' => $exception->getMessage(),
        ]);

        activity('invitation_failed_permanently')
            ->performedOn($this->invitation)
            ->withProperties([
                'email' => $this->invitation->email,
                'error' => $exception->getMessage(),
            ])
            ->log('Invitation email job failed permanently after all retries');
    }
}
