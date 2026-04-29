<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FuelRecordController;

// Публичные маршруты
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Защищенные маршруты (требуют токена Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Здесь позже будут роуты для FuelRecord

    // Маршруты для работы с заправками
    Route::get('/records', [FuelRecordController::class, 'index']);
    Route::post('/records', [FuelRecordController::class, 'store']);
    Route::put('/records/{fuelRecord}', [FuelRecordController::class, 'update']);
    Route::delete('/records/{fuelRecord}', [FuelRecordController::class, 'destroy']);

});
