<?php

namespace App\Services\Mapping;

class PosJsonValidator
{
    private const REQUIRED_ROOT = [
        'transaction_id',
        'transaction_datetime',
        'merchant_code',
        'branch_code',
        'pos_device_id',
        'invoice_type',
        'items',
        'totals',
        'payment',
    ];

    public function validate(array $pos): void
    {
        $missing = [];

        foreach (self::REQUIRED_ROOT as $field) {
            if (! array_key_exists($field, $pos) || $pos[$field] === '' || $pos[$field] === null) {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new PosJsonValidationException(
                $missing,
                'Missing required field: '.implode(', ', $missing)
            );
        }

        if (! is_array($pos['items']) || count($pos['items']) < 1) {
            throw new PosJsonValidationException(['items'], 'At least one line item is required.');
        }

        foreach ($pos['items'] as $index => $item) {
            foreach (['sku', 'description', 'qty', 'unit_price'] as $field) {
                if (! isset($item[$field]) || $item[$field] === '') {
                    $missing[] = "items.{$index}.{$field}";
                }
            }
        }

        foreach (['gross', 'net'] as $field) {
            if (! isset($pos['totals'][$field])) {
                $missing[] = "totals.{$field}";
            }
        }

        foreach (['method', 'amount'] as $field) {
            if (! isset($pos['payment'][$field])) {
                $missing[] = "payment.{$field}";
            }
        }

        if (! empty($missing)) {
            throw new PosJsonValidationException(
                $missing,
                'Missing required field: '.implode(', ', $missing)
            );
        }
    }
}
