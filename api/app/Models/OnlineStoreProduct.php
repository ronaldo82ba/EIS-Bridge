<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineStoreProduct extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'sku',
        'category',
        'brand',
        'price',
        'in_stock',
        'sort_order',
    ];

    protected $casts = [
        'in_stock' => 'boolean',
        'price' => 'integer',
        'sort_order' => 'integer',
    ];
}
