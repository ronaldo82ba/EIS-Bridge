<?php

namespace App\Jobs\Bulk;

use App\Jobs\MapInvoiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('mapping');
    }

    public function handle(): void
    {
        MapInvoiceJob::dispatch($this->invoiceId);
    }
}
