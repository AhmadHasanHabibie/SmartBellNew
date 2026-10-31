<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IoTR Enterprise Web Routes
|--------------------------------------------------------------------------
|
| Seluruh rute antarmuka web untuk autentikasi mandiri, manajemen pairing
| perangkat komersial ESP32, dan dashboard monitoring real-time.
|
*/

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Grup Rute Tamu (Belum Login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// Grup Rute Terproteksi (Sudah Login)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/pair', [DashboardController::class, 'pairDevice'])->name('device.pair');
    Route::delete('/dashboard/device/{device}/unpair', [DashboardController::class, 'unpairDevice'])->name('device.unpair');

    // Endpoint Interkom 2 Arah: Mengatur pending action (misal: 'diluar')
    Route::post('/device/{device}/set-action', [DashboardController::class, 'setPendingAction'])->name('device.set-action');

    // Endpoint AJAX Polling Real-Time & Riwayat Chart.js
    Route::get('/dashboard/device/{device}/json', [DashboardController::class, 'getDeviceTelemetry'])->name('device.json');
});
