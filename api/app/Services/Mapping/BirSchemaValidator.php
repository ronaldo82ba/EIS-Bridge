<?php

namespace App\Services\Mapping;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BirSchemaValidator
{
    private ?string $schemaPath = null;

    public function validate(array $birJson): void
    {
        if (class_exists(\Opis\JsonSchema\Validator::class)) {
            $this->validateWithOpis($birJson);

            return;
        }

        $this->validateWithLaravel($birJson);
    }

    public function schemaPath(): string
    {
        if ($this->schemaPath !== null) {
            return $this->schemaPath;
        }

        $candidates = [
            base_path('../docs/schemas/bir-eis-invoice.schema.json'),
            base_path('docs/schemas/bir-eis-invoice.schema.json'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $this->schemaPath = $path;
            }
        }

        throw new BirSchemaValidationException(
            ['schema'],
            'BIR EIS invoice schema file not found.'
        );
    }

    private function validateWithOpis(array $birJson): void
    {
        $schemaJson = file_get_contents($this->schemaPath());
        $schema = json_decode($schemaJson);

        if ($schema === null) {
            throw new BirSchemaValidationException(['schema'], 'BIR EIS invoice schema is invalid JSON.');
        }

        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->validate(
            json_decode(json_encode($birJson)),
            $schema
        );

        if ($result->isValid()) {
            return;
        }

        $errors = $this->collectOpisErrors($result->error());

        throw new BirSchemaValidationException(
            $errors,
            'BIR EIS invoice payload failed schema validation: '.$this->formatErrors($errors)
        );
    }

    private function collectOpisErrors(?\Opis\JsonSchema\Errors\ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }

        $subErrors = $error->subErrors();

        if (empty($subErrors)) {
            return [[
                'path' => implode('.', $error->data()->path()) ?: 'root',
                'message' => $error->message(),
            ]];
        }

        return collect($subErrors)
            ->flatMap(fn ($sub) => $this->collectOpisErrors($sub))
            ->values()
            ->all();
    }

    private function validateWithLaravel(array $birJson): void
    {
        try {
            Validator::make($birJson, $this->laravelRules())->validate();
        } catch (ValidationException $e) {
            $errors = collect($e->errors())
                ->flatMap(fn (array $messages, string $field) => array_map(
                    fn (string $message) => ['path' => $field, 'message' => $message],
                    $messages
                ))
                ->values()
                ->all();

            throw new BirSchemaValidationException(
                $errors,
                'BIR EIS invoice payload failed schema validation: '.$this->formatErrors($errors)
            );
        }
    }

    private function formatErrors(array $errors): string
    {
        return collect($errors)
            ->map(fn (array $error) => ($error['path'] ?? 'root').': '.($error['message'] ?? 'invalid'))
            ->implode('; ');
    }

    private function laravelRules(): array
    {
        return [
            'document_type' => ['required', 'string'],
            'transaction_id' => ['required', 'string'],
            'transaction_datetime' => ['required', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'merchant' => ['required', 'array'],
            'merchant.code' => ['required', 'string'],
            'merchant.name' => ['nullable', 'string'],
            'merchant.tin' => ['nullable', 'string'],
            'merchant.address' => ['nullable', 'string'],
            'branch' => ['required', 'array'],
            'branch.code' => ['required', 'string'],
            'branch.name' => ['nullable', 'string'],
            'branch.address' => ['nullable', 'string'],
            'device' => ['required', 'array'],
            'device.pos_device_id' => ['required', 'string'],
            'device.name' => ['nullable', 'string'],
            'ptt' => ['nullable', 'array'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.line_no' => ['required', 'integer', 'min:1'],
            'line_items.*.sku' => ['required', 'string'],
            'line_items.*.description' => ['required', 'string'],
            'line_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.gross_amount' => ['required', 'numeric', 'min:0'],
            'totals' => ['required', 'array'],
            'totals.gross_amount' => ['required', 'numeric', 'min:0'],
            'totals.discount_amount' => ['required', 'numeric', 'min:0'],
            'totals.vatable_sales' => ['required', 'numeric', 'min:0'],
            'totals.vat_amount' => ['required', 'numeric', 'min:0'],
            'totals.vat_exempt_sales' => ['required', 'numeric', 'min:0'],
            'totals.zero_rated_sales' => ['required', 'numeric', 'min:0'],
            'totals.service_charge' => ['required', 'numeric', 'min:0'],
            'totals.net_amount' => ['required', 'numeric', 'min:0'],
            'payment' => ['required', 'array'],
            'payment.method' => ['required', 'string'],
            'payment.amount' => ['required', 'numeric', 'min:0'],
            'eis_fields' => ['required', 'array'],
            'eis_fields.submission_version' => ['required', 'string'],
            'eis_fields.source' => ['required', 'string', 'in:EIS_BRIDGE'],
            'customer' => ['nullable', 'array'],
            'references' => ['nullable', 'array'],
            'metadata' => ['nullable'],
        ];
    }
}
