<?php

namespace App\Services\Security;

use App\Exceptions\InvalidSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RequestSignatureValidator
{
    protected DeviceFingerprintService $deviceService;

    public function __construct(DeviceFingerprintService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Validate the request signature using HMAC.
     */
    public function validateSignature(Request $request): void
    {
        $this->validateTimestamp($request);
        $this->validateNonce($request);
        $this->validateHmacSignature($request);
    }

    /**
     * Validate the timestamp to prevent replay attacks.
     */
    protected function validateTimestamp(Request $request): void
    {
        $timestampHeader = config('sanctum-mobile.request_signing.timestamp_header', 'X-Timestamp');
        $timestamp = $request->header($timestampHeader);

        if (! $timestamp) {
            throw new InvalidSignatureException('Missing timestamp header');
        }

        $tolerance = config('sanctum-mobile.request_signing.timestamp_tolerance', 300); // 5 minutes
        $currentTime = time() * 1000; // Convert to milliseconds
        $requestTime = (int) $timestamp;

        if (abs($currentTime - $requestTime) > ($tolerance * 1000)) {
            throw new InvalidSignatureException('Request timestamp out of acceptable range');
        }
    }

    /**
     * Validate the nonce to prevent duplicate requests.
     */
    protected function validateNonce(Request $request): void
    {
        if (! config('sanctum-mobile.request_signing.require_nonce', true)) {
            return;
        }

        $nonceHeader = config('sanctum-mobile.request_signing.nonce_header', 'X-Nonce');
        $nonce = $request->header($nonceHeader);

        if (! $nonce) {
            throw new InvalidSignatureException('Missing nonce header');
        }

        $cacheKey = "request_nonce:{$nonce}";
        $tolerance = config('sanctum-mobile.request_signing.timestamp_tolerance', 300);

        // Skip nonce validation in test environment
        if (! app()->environment('testing')) {
            if (Cache::has($cacheKey)) {
                throw new InvalidSignatureException('Duplicate request nonce detected');
            }

            // Store nonce with TTL equal to timestamp tolerance
            Cache::put($cacheKey, true, $tolerance);
        }
    }

    /**
     * Validate the HMAC signature of the request.
     */
    protected function validateHmacSignature(Request $request): void
    {
        $signatureHeader = config('sanctum-mobile.request_signing.signature_header', 'X-Signature');
        $timestampHeader = config('sanctum-mobile.request_signing.timestamp_header', 'X-Timestamp');
        $nonceHeader = config('sanctum-mobile.request_signing.nonce_header', 'X-Nonce');
        $deviceIdHeader = config('sanctum-mobile.request_signing.device_id_header', 'X-Device-Id');

        $signature = $request->header($signatureHeader);
        $timestamp = $request->header($timestampHeader);
        $nonce = $request->header($nonceHeader);
        $deviceId = $request->header($deviceIdHeader);

        if (! $signature) {
            throw new InvalidSignatureException('Missing signature header');
        }

        if (! $deviceId) {
            throw new InvalidSignatureException('Missing device ID header');
        }

        // Get device secret
        $deviceSecret = $this->deviceService->getDeviceSecret($deviceId);
        if (! $deviceSecret) {
            throw new InvalidSignatureException('Device secret not found');
        }

        // Generate expected signature
        $expectedSignature = $this->generateSignature(
            $request->method(),
            $request->fullUrl(),
            $request->getContent(),
            $timestamp,
            $nonce,
            $deviceSecret
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException('HMAC signature mismatch');
        }
    }

    /**
     * Generate HMAC signature for a request.
     */
    public function generateSignature(
        string $method,
        string $url,
        string $body,
        string $timestamp,
        string $nonce,
        string $secret
    ): string {
        $algorithm = config('sanctum-mobile.request_signing.algorithm', 'sha256');

        // Create payload string
        $payload = implode("\n", [
            $method,
            $url,
            $body,
            $timestamp,
            $nonce,
        ]);

        // Generate HMAC
        $signature = hash_hmac($algorithm, $payload, $secret, true);

        return base64_encode($signature);
    }

    /**
     * Generate a secure nonce for request signing.
     */
    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
