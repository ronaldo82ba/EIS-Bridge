<?php

namespace App\Jobs\Bulk;

use App\Jobs\SignInvoiceJob;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ForceResignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('signing');
    }

    public function handle(): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'signed_json' => null,
            'processing_status' => 'mapped',
        ]);

        SignInvoiceJob::dispatch($invoice->id);
    }
}
