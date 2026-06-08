<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public const CATEGORY_PROCESSING = 'processing';

    public const CATEGORY_EIS = 'eis';

    public const CATEGORY_CERTIFICATE = 'certificate';

    public const CATEGORY_WEBHOOK = 'webhook';

    public const CATEGORY_SYSTEM = 'system';

    public const TYPE_CERTIFICATE_EXPIRING = 'certificate_expiring';

    public const TYPE_PTT_EXPIRING = 'ptt_expiring';

    public const TYPE_HIGH_ERROR_RATE = 'high_error_rate';

    public const TYPE_QUEUE_BACKLOG = 'queue_backlog';

    public const TYPE_LICENSE_EXPIRING = 'license_expiring';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'category',
        'type',
        'severity',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'metadata',
        'details',
        'merchant_id',
        'invoice_id',
        'certificate_id',
        'vendor_id',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'details' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected $appends = [
        'status',
    ];

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(MerchantCertificate::class, 'certificate_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getStatusAttribute(): string
    {
        return $this->resolved_at ? 'resolved' : 'open';
    }

    public function displayCategory(): string
    {
        if ($this->category) {
            return $this->category;
        }

        return match ($this->type) {
            self::TYPE_CERTIFICATE_EXPIRING, self::TYPE_PTT_EXPIRING => self::CATEGORY_CERTIFICATE,
            self::TYPE_HIGH_ERROR_RATE, self::TYPE_QUEUE_BACKLOG, self::TYPE_LICENSE_EXPIRING => self::CATEGORY_SYSTEM,
            default => self::CATEGORY_SYSTEM,
        };
    }

    public function displayDetails(): array
    {
        return $this->details ?? $this->metadata ?? [];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }
}
