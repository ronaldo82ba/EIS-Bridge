<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction' => ['required', 'array'],
            'transaction.transaction_id' => ['required', 'string', 'max:128'],
            'transaction.transaction_datetime' => [
                'required',
                'string',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! StoreTransactionRequest::isStrictIso8601((string) $value)) {
                        $fail('The '.$attribute.' must be a valid ISO 8601 datetime.');
                    }
                },
            ],
            'transaction.merchant_code' => ['required', 'string', 'max:64'],
            'transaction.branch_code' => ['required', 'string', 'max:64'],
            'transaction.pos_device_id' => ['required', 'string', 'max:64'],
            'transaction.invoice_type' => ['required', 'string', 'max:32'],
            'transaction.items' => ['required', 'array', 'min:1'],
            'transaction.items.*.sku' => ['required', 'string', 'max:128'],
            'transaction.items.*.description' => ['required', 'string', 'max:1024'],
            'transaction.items.*.qty' => [
                'required',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invoiceType = strtolower((string) data_get($this->input('transaction', []), 'invoice_type', ''));
                    if ($invoiceType !== 'refund' && (float) $value <= 0) {
                        $fail('The '.$attribute.' must be greater than 0 unless invoice_type is REFUND.');
                    }
                },
            ],
            'transaction.items.*.unit_price' => [
                'required',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((float) $value <= 0) {
                        $fail('The '.$attribute.' must be greater than 0.');
                    }
                },
            ],
            'transaction.totals' => ['required', 'array'],
            'transaction.totals.net' => ['required', 'numeric'],
            'transaction.payment' => ['required', 'array'],
            'transaction.payment.method' => ['required', 'string', 'max:64'],
            'transaction.payment.amount' => ['required', 'numeric'],
        ];
    }

    protected function passedValidation(): void
    {
        $violations = self::collectSanitizationViolations($this->validated('transaction', []), 'transaction');

        if ($violations !== []) {
            throw new HttpResponseException(response()->json([
                'error' => 'validation_error',
                'message' => 'The transaction payload is invalid.',
                'details' => $violations,
            ], 422));
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => 'validation_error',
            'message' => 'The transaction payload is invalid.',
            'details' => $validator->errors()->toArray(),
        ], 422));
    }

    public static function isStrictIso8601(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\+\d{2}:\d{2}|-\d{2}:\d{2}|Z)$/', $value)) {
            return false;
        }

        try {
            $dt = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return false;
        }

        $normalized = Str::endsWith($value, 'Z')
            ? substr($value, 0, -1).'+00:00'
            : $value;

        return $dt->format('Y-m-d\TH:i:sP') === $normalized;
    }

    public static function collectSanitizationViolations(array $input, string $base = 'transaction'): array
    {
        $violations = [];

        $walker = function (mixed $value, string $path) use (&$violations, &$walker): void {
            if (is_array($value)) {
                foreach ($value as $key => $inner) {
                    $walker($inner, $path.'.'.$key);
                }
                return;
            }

            if (! is_string($value) || $value === '') {
                return;
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                $violations[$path][] = 'Contains control characters.';
            }

            if (preg_match('/<\s*script\b/i', $value)) {
                $violations[$path][] = 'Contains disallowed script content.';
            }

            if (preg_match('/(?:--|\/\*|\*\/|;\s*drop\b|;\s*delete\b|;\s*update\b|;\s*insert\b|\bunion\s+select\b)/i', $value)) {
                $violations[$path][] = 'Contains SQL injection-like content.';
            }
        };

        $walker($input, $base);

        return $violations;
    }
}
