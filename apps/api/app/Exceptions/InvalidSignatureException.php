<?php

namespace App\Exceptions;

use Exception;

class InvalidSignatureException extends Exception
{
    public function __construct(string $message = 'Request signature validation failed', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
