<?php

namespace App\Services\CodeBooks;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Mapping\PosJsonValidator;
use App\Services\Mapping\PosToBirMapper;
use App\Services\TransactionProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adapts CodeBooks BridgePayloadService JSON into Vendor Standard Sale Object(s).
 *
 * Print/PDF RR 7-2024 face stays in CodeBooks. This path is connector/export only.
 *
 * Books-origin payment policy: method OTHER, amount = document gross (or net fallback).
 */
class CodeBooksIngestAdapter
{
    public const VIRTUAL_POS_DEVICE_ID = 'CODEBOOKS-VIRTUAL';

    public function __construct(
        private readonly PosJsonValidator $posJsonValidator,
        private readonly PosToBirMapper $posToBirMapper,
        private readonly TransactionProcessor $transactionProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  CodeBooks export: meta + company + documents[]
     * @return array<string, mixed>
     */
    public function ingest(array $payload, Vendor $vendor, bool $dryRun = true): array
    {
        $company = $payload['company'] ?? null;
        $documents = $payload['documents'] ?? null;

        if (! is_array($company) || ! is_array($documents)) {
            throw ValidationException::withMessages([
                'payload' => ['CodeBooks export requires company object and documents array.'],
            ]);
        }

        if ($documents === []) {
            throw ValidationException::withMessages([
                'documents' => ['At least one document is required.'],
            ]);
        }

        return DB::transaction(function () use ($payload, $company, $documents, $vendor, $dryRun) {
            $merchant = $this->upsertMerchant($vendor, $company);
            $branch = $this->upsertBranch($merchant, $company);
            $device = $this->ensureVirtualDevice($branch);

            $saleObjects = [];
            $results = [];

            foreach ($documents as $index => $document) {
                if (! is_array($document)) {
                    throw ValidationException::withMessages([
                        "documents.{$index}" => ['Each document must be an object.'],
                    ]);
                }

                $sale = $this->mapDocumentToSaleObject($document, $merchant, $branch, $device);
                $this->posJsonValidator->validate($sale);
                $saleObjects[] = $sale;

                if ($dryRun) {
                    $bir = $this->posToBirMapper->map($sale);
                    $results[] = [
                        'index' => $index,
                        'transaction_id' => $sale['transaction_id'],
                        'status' => 'dry_run',
                        'sale_object' => $sale,
                        'bir_json' => $bir,
                    ];

                    continue;
                }

                $processed = $this->transactionProcessor->processSingle($sale, $vendor);
                $results[] = [
                    'index' => $index,
                    'transaction_id' => $sale['transaction_id'],
                    'status' => $processed['status'] ?? 'unknown',
                    'result' => $processed,
                ];
            }

            return [
                'dry_run' => $dryRun,
                'vendor_id' => $vendor->id,
                'merchant' => [
                    'id' => $merchant->id,
                    'merchant_code' => $merchant->merchant_code,
                    'bir_ack_number' => $merchant->bir_ack_number,
                    'bir_ack_date' => $merchant->bir_ack_date?->toDateString(),
                ],
                'branch' => [
                    'id' => $branch->id,
                    'branch_code' => $branch->branch_code,
                ],
                'device' => [
                    'id' => $device->id,
                    'pos_device_id' => $device->pos_device_id,
                ],
                'document_count' => count($documents),
                'sale_objects' => $saleObjects,
                'results' => $results,
                'meta' => $payload['meta'] ?? null,
                'disclaimer' => 'BIR Ready payload path only. Not BIR Accredited. Print face remains CodeBooks.',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function upsertMerchant(Vendor $vendor, array $company): Merchant
    {
        $tin = trim((string) ($company['tin'] ?? ''));
        $name = trim((string) ($company['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'company.name' => ['Company name is required.'],
            ]);
        }

        $merchantCode = $this->resolveMerchantCode($vendor, $company, $tin, $name);

        $attributes = [
            'name' => $name,
            'trade_name' => $this->nullableString($company['trade_name'] ?? null),
            'tin' => $tin !== '' ? $tin : null,
            'address' => $this->nullableString($company['address'] ?? null),
            'vat_registered' => array_key_exists('vat_registered', $company)
                ? (is_null($company['vat_registered']) ? null : (bool) $company['vat_registered'])
                : null,
            'rdo_code' => $this->nullableString($company['rdo_code'] ?? null),
            'bir_ack_number' => $this->nullableString($company['bir_ack_number'] ?? null),
            'bir_ack_date' => $this->nullableDate($company['bir_ack_date'] ?? null),
            'status' => 'active',
        ];

        $merchant = Merchant::query()
            ->where('vendor_id', $vendor->id)
            ->where(function ($q) use ($merchantCode, $tin) {
                $q->where('merchant_code', $merchantCode);
                if ($tin !== '') {
                    $q->orWhere('tin', $tin);
                }
            })
            ->first();

        if ($merchant) {
            $merchant->fill($attributes);
            if ($merchant->merchant_code === '' || $merchant->merchant_code === null) {
                $merchant->merchant_code = $merchantCode;
            }
            $merchant->save();

            return $merchant->fresh();
        }

        return Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $merchantCode,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function upsertBranch(Merchant $merchant, array $company): Branch
    {
        $branchCode = $this->normalizeBranchCode($company['branch_code'] ?? null);

        $branch = Branch::query()
            ->where('merchant_id', $merchant->id)
            ->where('branch_code', $branchCode)
            ->first();

        $attributes = [
            'name' => $branch?->name ?: ('Branch '.$branchCode),
            'address' => $this->nullableString($company['address'] ?? null) ?? $branch?->address,
            'status' => 'active',
        ];

        if ($branch) {
            $branch->update($attributes);

            return $branch->fresh();
        }

        return Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => $branchCode,
            ...$attributes,
        ]);
    }

    private function ensureVirtualDevice(Branch $branch): Device
    {
        $device = Device::query()
            ->where('branch_id', $branch->id)
            ->where('pos_device_id', self::VIRTUAL_POS_DEVICE_ID)
            ->first();

        if ($device) {
            return $device;
        }

        return Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => self::VIRTUAL_POS_DEVICE_ID,
            'name' => 'CodeBooks Virtual Device',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function mapDocumentToSaleObject(
        array $document,
        Merchant $merchant,
        Branch $branch,
        Device $device,
    ): array {
        $number = trim((string) ($document['number'] ?? ''));
        if ($number === '') {
            throw ValidationException::withMessages([
                'documents' => ['Each document requires a number (SI serial).'],
            ]);
        }

        $issueDate = $document['date'] ?? null;
        if (! is_string($issueDate) || trim($issueDate) === '') {
            throw ValidationException::withMessages([
                'documents' => ['Each document requires an issue date.'],
            ]);
        }

        // Asia/Manila start-of-day for books-origin SI dates (date-only from CodeBooks).
        $datetime = Carbon::parse($issueDate, config('app.timezone', 'Asia/Manila'))
            ->startOfDay()
            ->toIso8601String();

        $customerIn = is_array($document['customer'] ?? null) ? $document['customer'] : [];
        $totalsIn = is_array($document['totals'] ?? null) ? $document['totals'] : [];
        $lines = is_array($document['lines'] ?? null) ? $document['lines'] : [];

        if ($lines === []) {
            throw ValidationException::withMessages([
                'documents' => ['Each document requires at least one line.'],
            ]);
        }

        $items = [];
        foreach ($lines as $i => $line) {
            if (! is_array($line)) {
                continue;
            }
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $items[] = [
                'line_no' => (int) ($line['line_no'] ?? ($i + 1)),
                'sku' => (string) ($line['sku'] ?? ('LINE-'.($i + 1))),
                'description' => (string) ($line['description'] ?? 'Item'),
                'qty' => $qty > 0 ? $qty : 1,
                'unit' => (string) ($line['unit'] ?? 'PCS'),
                'unit_price' => max(0, $unitPrice),
                'discount' => (float) ($line['discount'] ?? 0),
                'vat_rate' => (float) ($line['vat_rate'] ?? 12),
                'vat_exempt' => (bool) ($line['vat_exempt'] ?? false),
                'zero_rated' => (bool) ($line['zero_rated'] ?? false),
            ];
        }

        $gross = (float) ($totalsIn['gross_amount'] ?? 0);
        $net = (float) ($totalsIn['net_amount'] ?? 0);
        $vat = (float) ($totalsIn['vat_amount'] ?? 0);

        if ($gross <= 0 && $net > 0) {
            $gross = $net + $vat;
        }
        if ($net <= 0 && $gross > 0) {
            $net = $gross;
        }
        if ($gross <= 0) {
            $gross = collect($items)->sum(fn ($item) => $item['qty'] * $item['unit_price']);
            $net = $gross;
        }

        $vatable = (float) ($totalsIn['vatable_sales'] ?? max(0, $net - $vat));
        if ($vat <= 0 && $vatable > 0) {
            $vat = round($vatable * 0.12, 2);
        }

        $documentType = strtoupper((string) ($document['document_type'] ?? 'SI'));

        return [
            'transaction_id' => $number,
            'transaction_datetime' => $datetime,
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => $branch->branch_code,
            'pos_device_id' => $device->pos_device_id,
            'invoice_type' => $documentType,
            'currency' => 'PHP',
            // Always emit buyer keys (empty string when null) for books-origin payloads.
            'customer' => [
                'name' => (string) ($customerIn['name'] ?? ''),
                'tin' => (string) ($customerIn['tin'] ?? ''),
                'address' => (string) ($customerIn['address'] ?? ''),
            ],
            'items' => $items,
            'totals' => [
                'gross' => round($gross, 2),
                'discount' => (float) ($totalsIn['discount_amount'] ?? 0),
                'vatable_sales' => round($vatable, 2),
                'vat_amount' => round($vat, 2),
                'vat_exempt_sales' => (float) ($totalsIn['vat_exempt_sales'] ?? 0),
                'zero_rated_sales' => (float) ($totalsIn['zero_rated_sales'] ?? 0),
                'service_charge' => (float) ($totalsIn['service_charge'] ?? 0),
                'net' => round($net, 2),
            ],
            // Books-origin policy: not a POS tender — use OTHER with amount = gross.
            'payment' => [
                'method' => 'OTHER',
                'amount' => round($gross, 2),
                'details' => [
                    'source' => 'codebooks',
                    'note' => 'Books-origin SI; not a POS cash tender.',
                ],
            ],
            'metadata' => [
                'source' => 'codebooks',
                'document_type' => $documentType,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function resolveMerchantCode(Vendor $vendor, array $company, string $tin, string $name): string
    {
        if (! empty($company['merchant_code'])) {
            return Str::upper(Str::limit((string) $company['merchant_code'], 32, ''));
        }

        $digits = preg_replace('/\D+/', '', $tin) ?: '';
        if ($digits !== '') {
            return 'CB'.Str::limit($digits, 14, '');
        }

        $base = 'CB'.Str::upper(Str::limit(Str::slug($name, ''), 12, ''));
        $code = $base !== 'CB' ? $base : 'CBMERCHANT';
        $suffix = 1;
        while (Merchant::query()->where('vendor_id', $vendor->id)->where('merchant_code', $code)->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function normalizeBranchCode(mixed $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($raw ?? '')) ?: '00000';

        return str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
