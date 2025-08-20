<?php

namespace App\Exceptions;

use Exception;

class InvalidApiKeyException extends Exception
{
    public function __construct(string $message = 'Invalid API key', int $code = 401, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
