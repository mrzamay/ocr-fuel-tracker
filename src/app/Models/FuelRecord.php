<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelRecord extends Model
{
    protected $fillable = [
        'user_id', 'vehicle_id', 'amount', 'volume', 'unit_price', 'is_full_tank', 'odometer_km',
        'date', 'receipt_image_path', 'status', 'latitude', 'longitude', 'location_accuracy',
        'station_name', 'fuel_type', 'ocr_meta'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'volume' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'is_full_tank' => 'boolean',
        'odometer_km' => 'integer',
        'date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'ocr_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
