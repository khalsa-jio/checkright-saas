<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;

class TenantValidationException extends Exception
{
    protected $validationErrors;

    protected $inputData;

    public function __construct(string $message, MessageBag $validationErrors, array $inputData = [], int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
        $this->inputData = $inputData;
    }

    /**
     * Get the validation errors.
     */
    public function getValidationErrors(): MessageBag
    {
        return $this->validationErrors;
    }

    /**
     * Get the input data that failed validation.
     */
    public function getInputData(): array
    {
        // Remove sensitive data
        $safeData = $this->inputData;
        unset($safeData['password'], $safeData['password_confirmation']);

        return $safeData;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        activity('tenant_validation_exception')
            ->withProperties([
                'message' => $this->getMessage(),
                'validation_errors' => $this->validationErrors->toArray(),
                'input_data' => $this->getInputData(),
                'code' => $this->getCode(),
            ])
            ->log('Tenant validation exception occurred');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Validation Failed',
                'message' => $this->getMessage(),
                'errors' => $this->validationErrors->toArray(),
                'code' => $this->getCode(),
            ], $this->getCode());
        }

        return back()
            ->withInput($this->getInputData())
            ->withErrors($this->validationErrors)
            ->with('error', $this->getMessage());
    }
}
