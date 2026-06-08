<?php

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
