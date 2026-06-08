<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('mapping');
    }

    public function handle(): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        MapInvoiceJob::dispatch($invoice->id);
    }
}
