<?php

namespace App\Jobs\Bulk;

use App\Jobs\SignInvoiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetrySigningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {
        $this->onQueue('signing');
    }

    public function handle(): void
    {
        SignInvoiceJob::dispatch($this->invoiceId);
    }
}
