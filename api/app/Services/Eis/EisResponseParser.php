<?php

namespace App\Services\Eis;

class EisResponseParser
{
    /** @var list<string> */
    private const REJECTED_STATUSES = [
        'rejected',
        'invalid',
        'denied',
        'validation_failed',
        'validation_error',
    ];

    /** @var list<string> */
    private const SUCCESS_STATUSES = [
        'acknowledged',
        'accepted',
        'pending',
        'success',
    ];

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

        $normalizedStatus = $this->normalizeStatus(
            $status ?? ($statusCode >= 200 && $statusCode < 300 ? 'acknowledged' : 'failed')
        );

        if ($statusCode >= 200 && $statusCode < 300) {
            if ($this->isRejectedStatus($normalizedStatus)) {
                return [
                    'success' => false,
                    'eis_status' => 'rejected',
                    'eis_reference_no' => $reference,
                    'error' => $body['error'] ?? $body['message'] ?? 'EIS rejected the invoice.',
                    'raw' => $body,
                ];
            }

            return [
                'success' => true,
                'eis_status' => $this->isSuccessStatus($normalizedStatus) ? $normalizedStatus : 'acknowledged',
                'eis_reference_no' => $reference,
                'raw' => $body,
            ];
        }

        return [
            'success' => false,
            'eis_status' => $this->isRejectedStatus($normalizedStatus) ? 'rejected' : 'failed',
            'eis_reference_no' => $reference,
            'error' => $body['error'] ?? $body['message'] ?? 'EIS transmission failed.',
            'raw' => $body,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim(str_replace(' ', '_', $status)));
    }

    private function isRejectedStatus(string $status): bool
    {
        return in_array($status, self::REJECTED_STATUSES, true);
    }

    private function isSuccessStatus(string $status): bool
    {
        return in_array($status, self::SUCCESS_STATUSES, true);
    }
}
