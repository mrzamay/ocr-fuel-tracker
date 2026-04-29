<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelRecord extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'volume', 'date', 'receipt_image_path', 'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'volume' => 'decimal:2',
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
