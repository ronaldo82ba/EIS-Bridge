<?php

namespace App\Models;

use App\Enums\BillingModel;
use App\Enums\BillingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicensePlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'billing_model',
        'unit',
        'amount',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'billing_model' => BillingModel::class,
        'unit' => BillingUnit::class,
    ];

    public function vendorLicenses(): HasMany
    {
        return $this->hasMany(VendorLicense::class);
    }

    public function merchantLicenses(): HasMany
    {
        return $this->hasMany(MerchantLicense::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return match ($category) {
            'vendor' => $query->where('slug', 'like', 'vendor_%'),
            'merchant' => $query->where('slug', 'like', 'merchant_%'),
            'saas' => $query->where('slug', 'like', 'saas_%'),
            default => $query,
        };
    }
}
