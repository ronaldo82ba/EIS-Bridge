<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'webhook_url',
        'webhook_secret',
    ];

    protected $hidden = [
        'webhook_secret',
    ];

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }
}
