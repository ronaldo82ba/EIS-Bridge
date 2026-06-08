<?php

namespace App\Services\Signing;

class LoadedCertificate
{
    public function __construct(
        public readonly mixed $privateKey,
        public readonly string $certificate,
        public readonly array $caCertificates = [],
    ) {}
}
