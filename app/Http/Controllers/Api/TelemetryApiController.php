<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelemetryApiController extends Controller
{
    /**
     * Menerima HTTP POST JSON dari mikrokontroler ESP32 (Unit Outdoor maupun Indoor).
     * Endpoint: POST /api/telemetry
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Ekstraksi Token dari Header Authorization Bearer ATAU X-API-TOKEN (atau fallback body)
        $apiToken = $request->bearerToken()
            ?? $request->header('X-API-TOKEN')
            ?? $request->input('api_token');

        if (!$apiToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: API Token tidak ditemukan pada Header (Bearer / X-API-TOKEN).',
            ], 401);
        }

        // 2. Cari perangkat fisik ESP32 berdasarkan api_token pabrikan
        $device = Device::where('api_token', $apiToken)->first();

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: API Token tidak dikenali oleh server IoTR.',
            ], 401);
        }

        // 3. Validasi struktur payload sensor yang dikirim ESP32
        $validator = Validator::make($request->all(), [
            'suhu' => ['nullable', 'numeric', 'between:-40,125'],
            'kelembapan' => ['nullable', 'numeric', 'between:0,100'],
            'kadar_gas' => ['nullable', 'integer', 'min:0', 'max:4095'],
            'sudut_servo_gas' => ['nullable', 'integer', 'min:0', 'max:180'],
            'status_bel' => ['nullable', 'integer', 'in:0,1,2'],
            'sudut_solar' => ['nullable', 'integer', 'min:0', 'max:180'],
            'cahaya_timur' => ['nullable', 'integer', 'min:0', 'max:4095'],
            'cahaya_barat' => ['nullable', 'integer', 'min:0', 'max:4095'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload telemetri tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 4. Update detak jantung (Heartbeat) perangkat menjadi Online (now)
        $device->update([
            'last_seen_at' => now(),
        ]);

        // 5. Kalkulasi otomatis sudut_servo_gas jika ESP32 Indoor mengirim kadar_gas tanpa sudut_servo_gas
        $kadarGas = $request->input('kadar_gas');
        $sudutServoGas = $request->input('sudut_servo_gas');

        if ($device->device_type === 'indoor' && $kadarGas !== null && $sudutServoGas === null) {
            if ($kadarGas >= 3000) {
                $sudutServoGas = 160; // Bahaya
            } elseif ($kadarGas >= 1500) {
                $sudutServoGas = 90;  // Waspada
            } else {
                $sudutServoGas = 20;  // Aman
            }
        }

        // 6. Simpan log telemetri sesuai tipe unit (Indoor / Outdoor)
        $telemetry = Telemetry::create([
            'device_id' => $device->id,
            'suhu' => $device->device_type === 'indoor' ? $request->input('suhu') : null,
            'kelembapan' => $device->device_type === 'indoor' ? $request->input('kelembapan') : null,
            'kadar_gas' => $device->device_type === 'indoor' ? $kadarGas : null,
            'sudut_servo_gas' => $device->device_type === 'indoor' ? $sudutServoGas : null,
            'status_bel' => $request->input('status_bel', 0),
            'sudut_solar' => $device->device_type === 'outdoor' ? $request->input('sudut_solar') : null,
            'cahaya_timur' => $device->device_type === 'outdoor' ? $request->input('cahaya_timur') : null,
            'cahaya_barat' => $device->device_type === 'outdoor' ? $request->input('cahaya_barat') : null,
        ]);

        // 7. Kembalikan Response JSON 201 Created ke ESP32
        return response()->json([
            'status' => 'success',
            'message' => 'Data telemetri ESP32 berhasil direkam.',
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'is_paired' => $device->user_id !== null,
                'last_seen_at' => $device->last_seen_at->toIso8601String(),
            ],
            'data' => $telemetry,
        ], 201);
    }
}

