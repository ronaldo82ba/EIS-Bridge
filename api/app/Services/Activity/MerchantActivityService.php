<?php

namespace App\Services\Activity;

use App\Events\MerchantActivityLogged;
use App\Models\CertificateAlert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class MerchantActivityService
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function broadcast(int $merchantId, string $type, array $details = [], ?int $invoiceId = null): void
    {
        $event = [
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'details' => $details,
        ];

        if ($invoiceId !== null) {
            $event['invoice_id'] = $invoiceId;
        }

        event(new MerchantActivityLogged($merchantId, $event));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function broadcastForInvoice(Invoice $invoice, string $type, array $details = []): void
    {
        $merchant = Merchant::query()
            ->where('merchant_code', $invoice->merchant_code)
            ->first();

        if (! $merchant) {
            return;
        }

        self::broadcast($merchant->id, $type, $details, $invoice->id);
    }

    public const FILTER_GROUPS = [
        'transaction' => ['transaction_received'],
        'mapping' => ['mapping_completed'],
        'signing' => ['signing_completed'],
        'transmission' => ['transmission_attempt', 'eis_acknowledged', 'eis_rejected'],
        'retry' => ['retry_scheduled'],
        'webhook' => ['webhook_delivery'],
        'certificate' => ['certificate_alert'],
    ];

    private const MAPPING_LOG_EVENTS = [
        'mapped',
        'mapping_validation_failed',
        'bir_schema_validation_failed',
        'mapping_failed',
    ];

    private const SIGNING_LOG_EVENTS = [
        'signed',
        'signing_failed',
    ];

    private const TRANSMISSION_LOG_EVENTS = [
        'transmitting',
        'transmission_failed',
    ];

    /**
     * @param  array{type?: string, page?: int, per_page?: int, from?: string, to?: string}  $params
     */
    public function paginate(Merchant $merchant, array $params): LengthAwarePaginator
    {
        $type = $params['type'] ?? 'all';
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 25)));
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : null;
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : null;

        $allowedTypes = $this->resolveAllowedEventTypes($type);
        $total = $this->countEvents($merchant, $allowedTypes, $from, $to);
        $fetchLimit = $page * $perPage;

        $events = collect();

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['transaction'])) {
            $events = $events->merge($this->fetchTransactionEvents($merchant, $from, $to, $fetchLimit));
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['mapping'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                self::MAPPING_LOG_EVENTS,
                'mapping_completed',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['signing'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                self::SIGNING_LOG_EVENTS,
                'signing_completed',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, ['transmission_attempt'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                self::TRANSMISSION_LOG_EVENTS,
                'transmission_attempt',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, ['eis_acknowledged'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                ['sent_to_eis'],
                'eis_acknowledged',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, ['eis_rejected'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                ['eis_rejected'],
                'eis_rejected',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['retry'])) {
            $events = $events->merge($this->fetchTransmissionLogEvents(
                $merchant,
                ['retry_transmission_failed'],
                'retry_scheduled',
                $from,
                $to,
                $fetchLimit,
            ));
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['webhook'])) {
            $events = $events->merge($this->fetchWebhookEvents($merchant, $from, $to, $fetchLimit));
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['certificate'])) {
            $events = $events->merge($this->fetchCertificateAlertEvents($merchant, $from, $to, $fetchLimit));
        }

        $sorted = $events
            ->sortByDesc(fn (array $event) => Carbon::parse($event['created_at'])->timestamp)
            ->values();

        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function resolveAllowedEventTypes(string $type): array
    {
        if ($type === 'all' || $type === '') {
            return array_merge(...array_values(self::FILTER_GROUPS));
        }

        return self::FILTER_GROUPS[$type] ?? [];
    }

    /**
     * @param  list<string>  $allowedTypes
     * @param  list<string>  $groupTypes
     */
    private function includesAny(array $allowedTypes, array $groupTypes): bool
    {
        return count(array_intersect($allowedTypes, $groupTypes)) > 0;
    }

    /**
     * @param  list<string>  $allowedTypes
     */
    private function countEvents(Merchant $merchant, array $allowedTypes, ?Carbon $from, ?Carbon $to): int
    {
        if ($allowedTypes === []) {
            return 0;
        }

        $total = 0;

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['transaction'])) {
            $total += $this->invoiceQuery($merchant, $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['mapping'])) {
            $total += $this->transmissionLogQuery($merchant, self::MAPPING_LOG_EVENTS, $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['signing'])) {
            $total += $this->transmissionLogQuery($merchant, self::SIGNING_LOG_EVENTS, $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, ['transmission_attempt'])) {
            $total += $this->transmissionLogQuery($merchant, self::TRANSMISSION_LOG_EVENTS, $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, ['eis_acknowledged'])) {
            $total += $this->transmissionLogQuery($merchant, ['sent_to_eis'], $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, ['eis_rejected'])) {
            $total += $this->transmissionLogQuery($merchant, ['eis_rejected'], $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['retry'])) {
            $total += $this->transmissionLogQuery($merchant, ['retry_transmission_failed'], $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['webhook'])) {
            $total += $this->webhookQuery($merchant, $from, $to)->count();
        }

        if ($this->includesAny($allowedTypes, self::FILTER_GROUPS['certificate'])) {
            $total += $this->certificateAlertQuery($merchant, $from, $to)->count();
        }

        return $total;
    }

    /**
     * @return Collection<int, array{type: string, created_at: string, details: array<string, mixed>, invoice_id?: int}>
     */
    private function fetchTransactionEvents(Merchant $merchant, ?Carbon $from, ?Carbon $to, int $limit): Collection
    {
        return $this->invoiceQuery($merchant, $from, $to)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'bridge_transaction_id', 'transaction_id', 'processing_status', 'eis_status', 'created_at'])
            ->map(fn (Invoice $invoice) => [
                'type' => 'transaction_received',
                'created_at' => $invoice->created_at->toIso8601String(),
                'invoice_id' => $invoice->id,
                'details' => [
                    'bridge_transaction_id' => $invoice->bridge_transaction_id,
                    'transaction_id' => $invoice->transaction_id,
                    'processing_status' => $invoice->processing_status,
                    'eis_status' => $invoice->eis_status,
                ],
            ]);
    }

    /**
     * @param  list<string>  $logEvents
     * @return Collection<int, array{type: string, created_at: string, details: array<string, mixed>, invoice_id?: int}>
     */
    private function fetchTransmissionLogEvents(
        Merchant $merchant,
        array $logEvents,
        string $activityType,
        ?Carbon $from,
        ?Carbon $to,
        int $limit,
    ): Collection {
        return $this->transmissionLogQuery($merchant, $logEvents, $from, $to)
            ->orderByDesc('transmission_logs.timestamp')
            ->limit($limit)
            ->get(['transmission_logs.invoice_id', 'transmission_logs.event', 'transmission_logs.timestamp', 'transmission_logs.metadata'])
            ->map(fn (TransmissionLog $log) => [
                'type' => $activityType,
                'created_at' => $log->timestamp->toIso8601String(),
                'invoice_id' => $log->invoice_id,
                'details' => [
                    'source_event' => $log->event,
                    'metadata' => $log->metadata,
                ],
            ]);
    }

    /**
     * @return Collection<int, array{type: string, created_at: string, details: array<string, mixed>, invoice_id?: int}>
     */
    private function fetchWebhookEvents(Merchant $merchant, ?Carbon $from, ?Carbon $to, int $limit): Collection
    {
        return $this->webhookQuery($merchant, $from, $to)
            ->orderByDesc('webhook_deliveries.created_at')
            ->limit($limit)
            ->get([
                'webhook_deliveries.id',
                'webhook_deliveries.invoice_id',
                'webhook_deliveries.event',
                'webhook_deliveries.attempt',
                'webhook_deliveries.status_code',
                'webhook_deliveries.success',
                'webhook_deliveries.delivered_at',
                'webhook_deliveries.created_at',
            ])
            ->map(function (WebhookDelivery $delivery) {
                $timestamp = $delivery->delivered_at ?? $delivery->created_at;

                return [
                    'type' => 'webhook_delivery',
                    'created_at' => $timestamp->toIso8601String(),
                    'invoice_id' => $delivery->invoice_id,
                    'details' => [
                        'delivery_id' => $delivery->id,
                        'event' => $delivery->event,
                        'attempt' => $delivery->attempt,
                        'status_code' => $delivery->status_code,
                        'success' => $delivery->success,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array{type: string, created_at: string, details: array<string, mixed>}>
     */
    private function fetchCertificateAlertEvents(Merchant $merchant, ?Carbon $from, ?Carbon $to, int $limit): Collection
    {
        return $this->certificateAlertQuery($merchant, $from, $to)
            ->orderByDesc('certificate_alerts.created_at')
            ->limit($limit)
            ->get(['certificate_alerts.id', 'certificate_alerts.level', 'certificate_alerts.notified_admin', 'certificate_alerts.notified_vendor', 'certificate_alerts.created_at'])
            ->map(fn (CertificateAlert $alert) => [
                'type' => 'certificate_alert',
                'created_at' => $alert->created_at->toIso8601String(),
                'details' => [
                    'alert_id' => $alert->id,
                    'level' => $alert->level,
                    'notified_admin' => $alert->notified_admin,
                    'notified_vendor' => $alert->notified_vendor,
                ],
            ]);
    }

    private function invoiceQuery(Merchant $merchant, ?Carbon $from, ?Carbon $to)
    {
        $query = Invoice::query()->where('merchant_code', $merchant->merchant_code);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  list<string>  $logEvents
     */
    private function transmissionLogQuery(Merchant $merchant, array $logEvents, ?Carbon $from, ?Carbon $to)
    {
        $query = TransmissionLog::query()
            ->select('transmission_logs.*')
            ->join('invoices', 'invoices.id', '=', 'transmission_logs.invoice_id')
            ->where('invoices.merchant_code', $merchant->merchant_code)
            ->whereIn('transmission_logs.event', $logEvents);

        if ($from) {
            $query->where('transmission_logs.timestamp', '>=', $from);
        }

        if ($to) {
            $query->where('transmission_logs.timestamp', '<=', $to);
        }

        return $query;
    }

    private function webhookQuery(Merchant $merchant, ?Carbon $from, ?Carbon $to)
    {
        $invoiceIds = Invoice::query()
            ->where('merchant_code', $merchant->merchant_code)
            ->select('id');

        $query = WebhookDelivery::query()
            ->select('webhook_deliveries.*')
            ->where('webhook_deliveries.vendor_id', $merchant->vendor_id)
            ->where(function ($builder) use ($merchant, $invoiceIds) {
                $builder->whereIn('webhook_deliveries.invoice_id', $invoiceIds)
                    ->orWhere(function ($certificateWebhooks) use ($merchant) {
                        $certificateWebhooks
                            ->whereNull('webhook_deliveries.invoice_id')
                            ->where('webhook_deliveries.event', 'certificate.expiry_alert')
                            ->whereExists(function ($exists) use ($merchant) {
                                $exists->selectRaw('1')
                                    ->from('certificate_alerts')
                                    ->join('merchant_certificates', 'merchant_certificates.id', '=', 'certificate_alerts.certificate_id')
                                    ->where('merchant_certificates.merchant_id', $merchant->id);
                            });
                    });
            });

        if ($from) {
            $query->where('webhook_deliveries.created_at', '>=', $from);
        }

        if ($to) {
            $query->where('webhook_deliveries.created_at', '<=', $to);
        }

        return $query;
    }

    private function certificateAlertQuery(Merchant $merchant, ?Carbon $from, ?Carbon $to)
    {
        $query = CertificateAlert::query()
            ->select('certificate_alerts.*')
            ->join('merchant_certificates', 'merchant_certificates.id', '=', 'certificate_alerts.certificate_id')
            ->where('merchant_certificates.merchant_id', $merchant->id);

        if ($from) {
            $query->where('certificate_alerts.created_at', '>=', $from);
        }

        if ($to) {
            $query->where('certificate_alerts.created_at', '<=', $to);
        }

        return $query;
    }
}
