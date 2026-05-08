<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelRecord extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'volume', 'odometer_km', 'date', 'receipt_image_path', 'status',
        'station_name', 'fuel_type'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'volume' => 'decimal:2',
        'odometer_km' => 'integer',
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
