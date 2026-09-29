<?php

namespace App\Jobs;

use App\Models\Invoice;
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

class TransmitInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $invoiceId,
        public int $attempt = 1,
    ) {
        $this->onQueue('transmission');
    }

    public function handle(EisClient $client): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || empty($invoice->signed_json)) {
            return;
        }

        if (in_array($invoice->processing_status, ['sent', 'retry_failed'], true)) {
            return;
        }

        $invoice->update(['processing_status' => 'transmitting']);
        InvoiceBroadcaster::statusUpdated($invoice);

        MerchantActivityService::broadcastForInvoice($invoice, 'transmission_attempt', [
            'source_event' => 'transmitting',
        ]);

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

            if (($result['eis_status'] ?? '') === 'rejected') {
                $this->markRejected($invoice, $result['eis_status']);

                return;
            }

            $this->markTransmissionFailed($invoice, $result['eis_status'] ?? 'failed');
        } catch (\Throwable $e) {
            TransmissionLog::create([
                'invoice_id' => $invoice->id,
                'event' => 'transmission_failed',
                'timestamp' => now(),
                'metadata' => [
                    'attempt' => $this->attempt,
                    'message' => $e->getMessage(),
                ],
            ]);

            Log::error('EIS transmission failed', [
                'invoice_id' => $invoice->id,
                'attempt' => $this->attempt,
                'message' => $e->getMessage(),
            ]);

            $this->markTransmissionFailed($invoice, 'failed');
        }
    }

    private function markRejected(Invoice $invoice, string $eisStatus): void
    {
        $invoice->update([
            'processing_status' => 'transmission_failed',
            'eis_status' => $eisStatus,
        ]);
        InvoiceBroadcaster::statusUpdated($invoice);

        MerchantActivityService::broadcastForInvoice($invoice, 'eis_rejected', [
            'source_event' => 'eis_rejected',
            'metadata' => ['eis_status' => $eisStatus],
        ]);

        AlertEmitter::eisRejection($invoice, $eisStatus, [
            'processing_status' => 'transmission_failed',
        ]);

        WebhookDispatcher::dispatchEvent($invoice->fresh(), 'transaction.eis_rejected');
    }

    private function markTransmissionFailed(Invoice $invoice, string $eisStatus): void
    {
        if ($invoice->processing_status === 'retry_failed') {
            return;
        }

        $invoice->update([
            'processing_status' => 'transmission_failed',
            'eis_status' => $eisStatus === 'failed' ? 'failed' : $eisStatus,
        ]);
        InvoiceBroadcaster::statusUpdated($invoice);

        AlertEmitter::processingFailure(
            $invoice,
            sprintf(
                'Transmission failed for invoice %s',
                $invoice->bridge_transaction_id ?? $invoice->id
            ),
            ['eis_status' => $eisStatus]
        );

        WebhookDispatcher::dispatchEvent($invoice->fresh(), 'transaction.eis_failed');

        RetryFailedTransmissionJob::dispatch($invoice->id, 1)->onQueue('retry');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TransmitInvoiceJob exhausted retries.', [
            'invoice_id' => $this->invoiceId,
            'attempt' => $this->attempt,
            'message' => $exception->getMessage(),
        ]);
    }
}
