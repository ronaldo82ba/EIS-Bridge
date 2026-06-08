<?php

namespace App\Enums;

enum BillingModel: string
{
    case OneTime = 'one_time';
    case PerUnit = 'per_unit';
    case RecurringMonthly = 'recurring_monthly';
}
