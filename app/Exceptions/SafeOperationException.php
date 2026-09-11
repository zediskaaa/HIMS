<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class SafeOperationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorId,
        public readonly string $module = 'system',
        public readonly string $operation = 'transaction',
        ?Throwable $previous = null,
        int $code = 500
    ) {
        parent::__construct($message, $code, $previous);
    }
}
