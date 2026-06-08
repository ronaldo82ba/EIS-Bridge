<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Services\Activity\MerchantActivityService;
use App\Services\Alerts\AlertEmitter;
use App\Support\InvoiceBroadcaster;
use App\Services\Signing\CertificateLoader;
use App\Services\Signing\JsonSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SignInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('signing');
    }

    public function handle(CertificateLoader $certificateLoader, JsonSigner $signer): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || empty($invoice->bir_json)) {
            return;
        }

        $invoice->update(['processing_status' => 'signing']);
        InvoiceBroadcaster::statusUpdated($invoice);

        try {
            $merchant = Merchant::where('merchant_code', $invoice->merchant_code)->first();

            if (! $merchant) {
                throw new RuntimeException("Merchant not found for code [{$invoice->merchant_code}].");
            }

            try {
                $cert = $certificateLoader->loadForMerchant($merchant->id);
                $signedJson = $signer->sign($invoice->bir_json, $cert['path'], $cert['password']);
            } catch (RuntimeException $e) {
                if (! config('eis.sandbox_mode')) {
                    throw $e;
                }

                $signedJson = $signer->sandboxSign($invoice->bir_json);
            }

            $invoice->update([
                'signed_json' => $signedJson,
                'processing_status' => 'signed',
            ]);
            InvoiceBroadcaster::statusUpdated($invoice);

            TransmissionLog::create([
                'invoice_id' => $invoice->id,
                'event' => 'signed',
                'timestamp' => now(),
                'metadata' => [
                    'signature_hash' => $signedJson['signature_hash'] ?? null,
                    'algorithm' => $signedJson['algorithm'] ?? null,
                ],
            ]);

            MerchantActivityService::broadcastForInvoice($invoice, 'signing_completed', [
                'source_event' => 'signed',
            ]);

            TransmitInvoiceJob::dispatch($invoice->id);
        } catch (\Throwable $e) {
            $invoice->update(['processing_status' => 'failed']);
            InvoiceBroadcaster::statusUpdated($invoice);

            TransmissionLog::create([
                'invoice_id' => $invoice->id,
                'event' => 'signing_failed',
                'timestamp' => now(),
                'metadata' => ['message' => $e->getMessage()],
            ]);

            AlertEmitter::processingFailure(
                $invoice,
                sprintf(
                    'Signing failed for invoice %s',
                    $invoice->bridge_transaction_id ?? $invoice->id
                ),
                ['message' => $e->getMessage(), 'event' => 'signing_failed']
            );
        }
    }
}
