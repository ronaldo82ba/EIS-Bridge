<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantCertificate extends Model
{
    protected $fillable = [
        'merchant_id',
        'filename',
        'file_path',
        'password_encrypted',
        'expires_at',
        'parsed_at',
        'uploaded_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'parsed_at' => 'datetime',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function certificateAlerts(): HasMany
    {
        return $this->hasMany(CertificateAlert::class, 'certificate_id');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->copy()->startOfDay()->lte(now()->startOfDay());
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return false;
        }

        return $this->expires_at->copy()->startOfDay()->lte(now()->startOfDay()->addDays($days));
    }
}
