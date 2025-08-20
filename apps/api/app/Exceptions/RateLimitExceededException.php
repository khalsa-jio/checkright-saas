<?php

namespace App\Exceptions;

use Exception;

class RateLimitExceededException extends Exception
{
    protected string $limitType;

    protected int $retryAfter;

    public function __construct(string $limitType = 'general', int $retryAfter = 3600, string $message = 'Rate limit exceeded', int $code = 429)
    {
        $this->limitType = $limitType;
        $this->retryAfter = $retryAfter;

        parent::__construct($message, $code);
    }

    public function getLimitType(): string
    {
        return $this->limitType;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
