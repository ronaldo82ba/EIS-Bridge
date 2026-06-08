<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateAlert extends Model
{
    public const LEVEL_EXPIRED = 'expired';

    public const LEVEL_EXPIRING_7 = 'expiring_7';

    public const LEVEL_EXPIRING_30 = 'expiring_30';

    protected $fillable = [
        'certificate_id',
        'level',
        'notified_admin',
        'notified_vendor',
    ];

    protected $casts = [
        'notified_admin' => 'boolean',
        'notified_vendor' => 'boolean',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(MerchantCertificate::class, 'certificate_id');
    }

    public static function latestLevelFor(int $certificateId): ?string
    {
        return static::query()
            ->where('certificate_id', $certificateId)
            ->orderByRaw("CASE level WHEN 'expired' THEN 1 WHEN 'expiring_7' THEN 2 WHEN 'expiring_30' THEN 3 ELSE 4 END")
            ->value('level');
    }
}
