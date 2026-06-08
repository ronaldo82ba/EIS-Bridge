<?php

namespace App\Services\Mapping;

use RuntimeException;

class BirSchemaValidationException extends RuntimeException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'BIR EIS invoice payload failed schema validation.',
    ) {
        parent::__construct($message);
    }
}
