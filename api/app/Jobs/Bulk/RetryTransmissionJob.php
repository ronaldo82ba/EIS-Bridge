<?php

namespace App\Jobs\Bulk;

use App\Jobs\TransmitInvoiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryTransmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('transmission');
    }

    public function handle(): void
    {
        TransmitInvoiceJob::dispatch($this->invoiceId);
    }
}
