<?php

use App\Http\Controllers\Api\TelemetryApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IoTR Enterprise REST API Routes (ESP32 Hardware Ingestion)
|--------------------------------------------------------------------------
|
| Endpoint di dalam grup ini otomatis memiliki prefix "/api" dan bebas dari
| verifikasi CSRF Token web, sehingga siap ditembak langsung oleh ESP32
| menggunakan HTTPClient (POST /api/telemetry).
|
*/

Route::post('/telemetry', [TelemetryApiController::class, 'store'])->name('api.telemetry.store');
