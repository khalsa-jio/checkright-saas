<?php

namespace App\Events;

use Exception;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantCreationFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $exceptionData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public array $companyData,
        public array $adminData,
        Exception $exception,
        public array $context = []
    ) {
        // Extract only serializable data from exception (don't store the object itself)
        $this->exceptionData = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        $this->context = array_merge([
            'failed_at' => now(),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
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
     * Get failure data for analysis.
     */
    public function getFailureData(): array
    {
        return [
            'company_name' => $this->companyData['name'] ?? 'Unknown',
            'domain' => $this->companyData['domain'] ?? 'Unknown',
            'admin_email' => $this->adminData['email'] ?? 'Unknown',
            'error_message' => $this->exceptionData['message'],
            'error_type' => $this->exceptionData['type'],
            'error_file' => $this->exceptionData['file'],
            'error_line' => $this->exceptionData['line'],
            'context' => $this->context,
        ];
    }

    /**
     * Get the original exception data (for compatibility).
     */
    public function getException(): array
    {
        return $this->exceptionData;
    }
}
