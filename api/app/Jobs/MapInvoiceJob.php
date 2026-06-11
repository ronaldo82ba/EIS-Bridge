<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\TransmissionLog;
use App\Services\Activity\MerchantActivityService;
use App\Services\Alerts\AlertEmitter;
use App\Support\InvoiceBroadcaster;
use App\Services\Mapping\BirSchemaValidationException;
use App\Services\Mapping\BirSchemaValidator;
use App\Services\Mapping\PosJsonValidationException;
use App\Services\Mapping\PosToBirMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MapInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('mapping');
    }

    public function handle(PosToBirMapper $mapper, BirSchemaValidator $birValidator): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        if (in_array($invoice->processing_status, ['mapped', 'signed', 'transmitting', 'sent', 'transmission_failed', 'retry_failed', 'failed'], true)) {
            return;
        }

        $invoice->update(['processing_status' => 'mapping']);
        InvoiceBroadcaster::statusUpdated($invoice);

        try {
            $birJson = $mapper->map($invoice);
            $birValidator->validate($birJson);

            $invoice->update([
                'bir_json' => $birJson,
                'processing_status' => 'mapped',
            ]);
            InvoiceBroadcaster::statusUpdated($invoice);

            TransmissionLog::create([
                'invoice_id' => $invoice->id,
                'event' => 'mapped',
                'timestamp' => now(),
                'metadata' => null,
            ]);

            MerchantActivityService::broadcastForInvoice($invoice, 'mapping_completed', [
                'source_event' => 'mapped',
            ]);

            SignInvoiceJob::dispatch($invoice->id);
        } catch (PosJsonValidationException $e) {
            $this->markFailed($invoice, 'mapping_validation_failed', $e->getMessage(), [
                'fields' => $e->fields,
            ]);
        } catch (BirSchemaValidationException $e) {
            $this->markFailed($invoice, 'bir_schema_validation_failed', $e->getMessage(), [
                'errors' => $e->errors,
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($invoice, 'mapping_failed', $e->getMessage());
        }
    }

    private function markFailed(Invoice $invoice, string $event, string $message, ?array $metadata = null): void
    {
        $invoice->update(['processing_status' => 'failed']);
        InvoiceBroadcaster::statusUpdated($invoice);

        $details = array_merge(['message' => $message, 'event' => $event], $metadata ?? []);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => $event,
            'timestamp' => now(),
            'metadata' => $details,
        ]);

        AlertEmitter::processingFailure(
            $invoice,
            sprintf(
                'Processing failed for invoice %s',
                $invoice->bridge_transaction_id ?? $invoice->id
            ),
            $details
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MapInvoiceJob exhausted retries.', [
            'invoice_id' => $this->invoiceId,
            'message' => $exception->getMessage(),
        ]);
    }
}
