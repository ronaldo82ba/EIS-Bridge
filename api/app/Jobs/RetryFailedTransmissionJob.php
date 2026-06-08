<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Services\Activity\MerchantActivityService;
use App\Services\Alerts\AlertEmitter;
use App\Support\InvoiceBroadcaster;
use App\Services\Eis\EisClient;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedTransmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
        public int $attempt = 1,
    ) {
        $this->onQueue('retry');
    }

    public function handle(EisClient $client): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || empty($invoice->signed_json)) {
            return;
        }

        $maxAttempts = $this->resolveMaxAttempts($invoice);

        try {
            $result = $client->send($invoice->signed_json, $invoice);

            if ($result['success']) {
                $invoice->update([
                    'processing_status' => 'sent',
                    'eis_status' => $result['eis_status'],
                    'eis_reference_no' => $result['eis_reference_no'],
                ]);
                InvoiceBroadcaster::statusUpdated($invoice);

                MerchantActivityService::broadcastForInvoice($invoice, 'eis_acknowledged', [
                    'source_event' => 'sent_to_eis',
                    'metadata' => [
                        'eis_reference_no' => $result['eis_reference_no'],
                        'eis_status' => $result['eis_status'],
                    ],
                ]);

                WebhookDispatcher::dispatchEvent($invoice->fresh(), 'transaction.eis_acknowledged');

                return;
            }

            $this->handleFailure($invoice, $maxAttempts, $result['eis_status'] ?? 'failed', $result['error'] ?? 'EIS rejected transmission.');
        } catch (\Throwable $e) {
            TransmissionLog::create([
                'invoice_id' => $invoice->id,
                'event' => 'retry_transmission_failed',
                'timestamp' => now(),
                'metadata' => [
                    'attempt' => $this->attempt,
                    'message' => $e->getMessage(),
                ],
            ]);

            MerchantActivityService::broadcastForInvoice($invoice, 'retry_scheduled', [
                'source_event' => 'retry_transmission_failed',
                'metadata' => [
                    'attempt' => $this->attempt,
                    'message' => $e->getMessage(),
                ],
            ]);

            Log::error('EIS retry transmission failed', [
                'invoice_id' => $invoice->id,
                'attempt' => $this->attempt,
                'message' => $e->getMessage(),
            ]);

            $this->handleFailure($invoice, $maxAttempts, 'failed', $e->getMessage());
        }
    }

    public function calculateBackoff(int $attempt): int
    {
        $backoff = config('eis.retry_backoff', [60, 300, 900, 3600, 7200]);
        $index = max(0, $attempt - 1);

        return $backoff[min($index, count($backoff) - 1)] ?? 60;
    }

    private function handleFailure(Invoice $invoice, int $maxAttempts, string $eisStatus, string $message): void
    {
        if ($this->attempt >= $maxAttempts) {
            $invoice->update([
                'processing_status' => 'retry_failed',
                'eis_status' => $eisStatus === 'failed' ? 'failed' : $eisStatus,
            ]);
            InvoiceBroadcaster::statusUpdated($invoice);

            if ($eisStatus === 'rejected') {
                MerchantActivityService::broadcastForInvoice($invoice, 'eis_rejected', [
                    'source_event' => 'eis_rejected',
                    'metadata' => ['message' => $message, 'eis_status' => $eisStatus],
                ]);
                AlertEmitter::eisRejection($invoice, $eisStatus, [
                    'message' => $message,
                    'attempts' => $this->attempt,
                ]);
            } else {
                AlertEmitter::processingFailure(
                    $invoice,
                    sprintf(
                        'Transmission retries exhausted for invoice %s',
                        $invoice->bridge_transaction_id ?? $invoice->id
                    ),
                    [
                        'message' => $message,
                        'attempts' => $this->attempt,
                        'max_attempts' => $maxAttempts,
                    ],
                    \App\Models\Alert::SEVERITY_CRITICAL
                );
            }

            Log::error('EIS transmission retries exhausted', [
                'invoice_id' => $invoice->id,
                'attempt' => $this->attempt,
                'max_attempts' => $maxAttempts,
                'message' => $message,
            ]);

            return;
        }

        MerchantActivityService::broadcastForInvoice($invoice, 'retry_scheduled', [
            'source_event' => 'retry_transmission_failed',
            'metadata' => [
                'attempt' => $this->attempt,
                'message' => $message,
                'next_attempt' => $this->attempt + 1,
            ],
        ]);

        self::dispatch($invoice->id, $this->attempt + 1)
            ->delay(now()->addSeconds($this->calculateBackoff($this->attempt)))
            ->onQueue('retry');
    }

    private function resolveMaxAttempts(Invoice $invoice): int
    {
        $merchant = Merchant::where('merchant_code', $invoice->merchant_code)->first();
        $vendorMax = $merchant?->vendor?->eis_retry_max_attempts;

        if ($vendorMax !== null) {
            return (int) $vendorMax;
        }

        return (int) config('eis.retry_max_attempts', 5);
    }
}
