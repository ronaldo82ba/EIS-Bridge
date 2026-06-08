<?php

namespace App\Services\Mapping;

use RuntimeException;

class PosJsonValidationException extends RuntimeException
{
    public function __construct(
        public readonly array $fields,
        string $message = 'Invalid POS JSON payload.',
    ) {
        parent::__construct($message);
    }
}
