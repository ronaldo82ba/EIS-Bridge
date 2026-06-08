<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventoryItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'external_id',
        'name',
        'sku',
        'category',
        'brand',
        'price',
        'in_stock',
    ];

    protected $casts = [
        'in_stock' => 'boolean',
        'price' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
