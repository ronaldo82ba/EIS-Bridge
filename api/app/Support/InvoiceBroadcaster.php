<?php

namespace App\Support;

use App\Events\AnalyticsUpdated;
use App\Events\InvoiceStatusUpdated;
use App\Models\Invoice;

class InvoiceBroadcaster
{
    public static function statusUpdated(Invoice $invoice): void
    {
        $fresh = $invoice->fresh();

        event(new InvoiceStatusUpdated($fresh));
        event(new AnalyticsUpdated($fresh, 'status_change'));
    }

    public static function created(Invoice $invoice): void
    {
        event(new AnalyticsUpdated($invoice->fresh(), 'new_invoice'));
    }
}
