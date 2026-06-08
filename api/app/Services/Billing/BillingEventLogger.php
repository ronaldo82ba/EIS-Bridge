<?php

namespace App\Services\Billing;

use App\Models\BillingEvent;
use App\Models\LicensePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BillingEventLogger
{
    public static function log(
        string $event,
        Model $subject,
        ?LicensePlan $plan = null,
        ?User $performer = null,
        array $metadata = [],
    ): BillingEvent {
        return BillingEvent::create([
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'license_plan_id' => $plan?->id,
            'performed_by' => $performer?->id,
            'metadata' => $metadata ?: null,
        ]);
    }
}
