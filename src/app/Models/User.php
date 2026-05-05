<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // <-- Вот правильный импорт
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    // <-- Обратите внимание, здесь мы используем импортированный трейт
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function fuelRecords(): HasMany
    {
        return $this->hasMany(FuelRecord::class);
    }
}
