<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantCreationException extends Exception
{
    protected $companyData;

    protected $adminData;

    public function __construct(string $message, array $companyData = [], array $adminData = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->companyData = $companyData;
        $this->adminData = $adminData;
    }

    /**
     * Get the company data that caused the exception.
     */
    public function getCompanyData(): array
    {
        return $this->companyData;
    }

    /**
     * Get the admin data that caused the exception.
     */
    public function getAdminData(): array
    {
        // Remove sensitive data like passwords from admin data
        $safeData = $this->adminData;
        unset($safeData['password']);

        return $safeData;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        activity('tenant_creation_exception')
            ->withProperties([
                'message' => $this->getMessage(),
                'company_data' => $this->getCompanyData(),
                'admin_data' => $this->getAdminData(),
                'code' => $this->getCode(),
            ])
            ->log('Tenant creation exception occurred');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant Creation Failed',
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ], 422);
        }

        return response()->view('errors.tenant-creation', [
            'message' => $this->getMessage(),
        ], 422);
    }
}
