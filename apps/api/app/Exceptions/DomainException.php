<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DomainException extends Exception
{
    protected $domain;

    protected $operation;

    public function __construct(string $message, string $domain = '', string $operation = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->domain = $domain;
        $this->operation = $operation;
    }

    /**
     * Get the domain that caused the exception.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get the operation that was being performed.
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        activity('domain_exception')
            ->withProperties([
                'message' => $this->getMessage(),
                'domain' => $this->getDomain(),
                'operation' => $this->getOperation(),
                'code' => $this->getCode(),
            ])
            ->log('Domain exception occurred');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Domain Error',
                'message' => $this->getMessage(),
                'domain' => $this->getDomain(),
                'operation' => $this->getOperation(),
                'code' => $this->getCode(),
            ], 422);
        }

        return response()->view('errors.domain', [
            'message' => $this->getMessage(),
            'domain' => $this->getDomain(),
        ], 422);
    }
}
