<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TransmissionLog;

class EisClient
{
    public function send(Invoice $invoice): array
    {
        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event'      => 'sent_to_eis',
            'timestamp'  => now(),
            'metadata'   => ['stub' => true],
        ]);

        return [
            'status'           => 'sent',
            'eis_reference_no' => null,
        ];
    }
}
