<?php

namespace App\Services\Eis;

use App\Models\Invoice;
use App\Models\TransmissionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class EisResponseParser
{
    public function parse(array $body, int $statusCode): array
    {
        $reference = $body['reference_no']
            ?? $body['eis_reference_no']
            ?? $body['data']['reference_no']
            ?? $body['data']['eis_reference_no']
            ?? null;

        $status = $body['status']
            ?? $body['eis_status']
            ?? $body['data']['status']
            ?? null;

        if ($statusCode >= 200 && $statusCode < 300) {
            $normalizedStatus = $this->normalizeStatus($status ?? 'acknowledged');

            return [
                'success' => true,
                'eis_status' => $normalizedStatus,
                'eis_reference_no' => $reference,
                'raw' => $body,
            ];
        }

        return [
            'success' => false,
            'eis_status' => $this->normalizeStatus($status ?? 'rejected'),
            'eis_reference_no' => $reference,
            'error' => $body['error'] ?? $body['message'] ?? 'EIS transmission failed.',
            'raw' => $body,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim($status));
    }
}
