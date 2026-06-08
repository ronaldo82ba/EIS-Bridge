<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillingEvent extends Model
{
    protected $fillable = [
        'event',
        'subject_type',
        'subject_id',
        'license_plan_id',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
