<?php

namespace App\Services\Alerts;

use App\Events\AlertCreated;
use App\Models\Alert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;

class AlertEmitter
{
    public const DEDUPE_HOURS = 1;

    public static function processingFailure(
        Invoice $invoice,
        string $title,
        array $details = [],
        string $severity = Alert::SEVERITY_WARNING,
    ): ?Alert {
        [$merchantId, $vendorId] = self::resolveInvoiceContext($invoice);

        return self::emit(
            category: Alert::CATEGORY_PROCESSING,
            subType: 'processing_failure',
            severity: $severity,
            title: $title,
            message: $details['message'] ?? $title,
            details: $details,
            merchantId: $merchantId,
            invoiceId: $invoice->id,
            vendorId: $vendorId,
            entityType: Invoice::class,
            entityId: $invoice->id,
        );
    }

    public static function eisRejection(
        Invoice $invoice,
        string $eisStatus,
        array $details = [],
        string $severity = Alert::SEVERITY_WARNING,
    ): ?Alert {
        [$merchantId, $vendorId] = self::resolveInvoiceContext($invoice);

        $title = sprintf(
            'EIS rejected invoice %s',
            $invoice->bridge_transaction_id ?? $invoice->id
        );

        return self::emit(
            category: Alert::CATEGORY_EIS,
            subType: 'eis_rejection',
            severity: $severity,
            title: $title,
            message: $details['message'] ?? $title,
            details: array_merge(['eis_status' => $eisStatus], $details),
            merchantId: $merchantId,
            invoiceId: $invoice->id,
            vendorId: $vendorId,
            entityType: Invoice::class,
            entityId: $invoice->id,
        );
    }

    public static function certificateExpiry(
        MerchantCertificate $certificate,
        string $level,
        array $details = [],
    ): ?Alert {
        $merchant = $certificate->merchant;
        $severity = in_array($level, ['expired', 'expiring_7'], true)
            ? Alert::SEVERITY_CRITICAL
            : Alert::SEVERITY_WARNING;

        $title = sprintf(
            'Certificate %s for %s',
            str_replace('_', ' ', $level),
            $merchant?->name ?? 'merchant '.$certificate->merchant_id
        );

        return self::emit(
            category: Alert::CATEGORY_CERTIFICATE,
            subType: 'certificate_'.$level,
            severity: $severity,
            title: $title,
            message: $title,
            details: array_merge(['level' => $level], $details),
            merchantId: $certificate->merchant_id,
            certificateId: $certificate->id,
            vendorId: $merchant?->vendor_id,
            entityType: MerchantCertificate::class,
            entityId: $certificate->id,
            dedupeKey: "certificate:{$certificate->id}:{$level}",
        );
    }

    public static function webhookFailure(
        Vendor $vendor,
        ?Invoice $invoice,
        int $statusCode,
        string $event,
        array $details = [],
    ): ?Alert {
        [$merchantId, $invoiceId] = $invoice
            ? [self::resolveInvoiceContext($invoice)[0], $invoice->id]
            : [null, null];

        $title = sprintf('Webhook failed for vendor %s', $vendor->name);

        return self::emit(
            category: Alert::CATEGORY_WEBHOOK,
            subType: 'webhook_failure',
            severity: $statusCode >= 500 ? Alert::SEVERITY_CRITICAL : Alert::SEVERITY_WARNING,
            title: $title,
            message: $title,
            details: array_merge([
                'status_code' => $statusCode,
                'event' => $event,
            ], $details),
            merchantId: $merchantId,
            invoiceId: $invoiceId,
            vendorId: $vendor->id,
            entityType: Vendor::class,
            entityId: $vendor->id,
            dedupeKey: "webhook:{$vendor->id}:{$event}:{$invoiceId}",
        );
    }

    public static function systemIssue(
        string $subType,
        string $title,
        string $message,
        array $details = [],
        string $severity = Alert::SEVERITY_WARNING,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $dedupeKey = null,
    ): ?Alert {
        return self::emit(
            category: Alert::CATEGORY_SYSTEM,
            subType: $subType,
            severity: $severity,
            title: $title,
            message: $message,
            details: $details,
            entityType: $entityType,
            entityId: $entityId,
            dedupeKey: $dedupeKey,
        );
    }

    public static function emit(
        string $category,
        string $subType,
        string $severity,
        string $title,
        string $message,
        array $details = [],
        ?int $merchantId = null,
        ?int $invoiceId = null,
        ?int $certificateId = null,
        ?int $vendorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $dedupeKey = null,
    ): ?Alert {
        if (self::isDuplicate($category, $invoiceId, $certificateId, $vendorId, $dedupeKey, $subType, $entityType, $entityId)) {
            return null;
        }

        if ($dedupeKey !== null) {
            $details['dedupe_key'] = $dedupeKey;
        }

        $alert = Alert::create([
            'category' => $category,
            'type' => $subType,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'details' => $details,
            'metadata' => $details,
            'merchant_id' => $merchantId,
            'invoice_id' => $invoiceId,
            'certificate_id' => $certificateId,
            'vendor_id' => $vendorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        event(new AlertCreated($alert));

        return $alert;
    }

    private static function isDuplicate(
        string $category,
        ?int $invoiceId,
        ?int $certificateId,
        ?int $vendorId,
        ?string $dedupeKey,
        string $subType,
        ?string $entityType,
        ?int $entityId,
    ): bool {
        $since = now()->subHours((int) config('alerts.dedupe_hours', self::DEDUPE_HOURS));

        $query = Alert::query()
            ->where('category', $category)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', $since);

        if ($invoiceId !== null) {
            return (clone $query)->where('invoice_id', $invoiceId)->exists();
        }

        if ($certificateId !== null) {
            return (clone $query)->where('certificate_id', $certificateId)->where('type', $subType)->exists();
        }

        if ($dedupeKey !== null) {
            return (clone $query)->where('details->dedupe_key', $dedupeKey)->exists();
        }

        if ($entityType !== null && $entityId !== null) {
            return (clone $query)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('type', $subType)
                ->exists();
        }

        if ($vendorId !== null && $category === Alert::CATEGORY_WEBHOOK) {
            return (clone $query)->where('vendor_id', $vendorId)->where('type', $subType)->exists();
        }

        return false;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private static function resolveInvoiceContext(Invoice $invoice): array
    {
        $merchant = Merchant::query()
            ->where('merchant_code', $invoice->merchant_code)
            ->first();

        return [$merchant?->id, $merchant?->vendor_id];
    }
}
