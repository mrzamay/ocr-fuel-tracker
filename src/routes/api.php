<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FuelRecordController;
use App\Http\Controllers\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

    Route::get('/records', [FuelRecordController::class, 'index']);
    Route::get('/records/meta', [FuelRecordController::class, 'meta']);
    Route::post('/records', [FuelRecordController::class, 'store']);
    Route::put('/records/{fuelRecord}', [FuelRecordController::class, 'update']);
    Route::delete('/records/{fuelRecord}', [FuelRecordController::class, 'destroy']);
});
