<?php

namespace App\Exceptions;

use Exception;

class InvalidDeviceException extends Exception
{
    public function __construct(string $message = 'Invalid or untrusted device', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
