<?php

namespace App\Enums;

enum BillingUnit: string
{
    case Merchant = 'merchant';
    case Branch = 'branch';
    case Vendor = 'vendor';
}
