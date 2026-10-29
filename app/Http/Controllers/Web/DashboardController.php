<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Menampilkan halaman Dashboard Utama IoTR beserta perangkat milik User yang sedang login.
     */
    public function index(): View
    {
        $user = Auth::user();

        // Ambil seluruh perangkat milik user dengan eager loading data telemetri terbaru
        $devices = $user->devices()
            ->with('latestTelemetry')
            ->orderByRaw("FIELD(device_type, 'outdoor', 'indoor')")
            ->get();

        // Statistik ringkas untuk Header Pusat Kendali
        $totalDevices = $devices->count();
        $outdoorCount = $devices->where('device_type', 'outdoor')->count();
        $indoorCount = $devices->where('device_type', 'indoor')->count();
        $onlineCount = $devices->filter(fn (Device $device) => $device->isOnline())->count();

        // Deteksi status keamanan global dari seluruh perangkat yang terhubung
        $hasGasDanger = $devices->contains(function (Device $device) {
            return $device->device_type === 'indoor'
                && $device->latestTelemetry
                && $device->latestTelemetry->kadar_gas >= 3000;
        });

        $hasGasWarning = !$hasGasDanger && $devices->contains(function (Device $device) {
            return $device->device_type === 'indoor'
                && $device->latestTelemetry
                && $device->latestTelemetry->kadar_gas >= 1500
                && $device->latestTelemetry->kadar_gas < 3000;
        });

        $activeBellStatus = $devices->max(function (Device $device) {
            return $device->latestTelemetry ? (int) $device->latestTelemetry->status_bel : 0;
        }) ?? 0;

        return view('dashboard.index', compact(
            'devices',
            'totalDevices',
            'outdoorCount',
            'indoorCount',
            'onlineCount',
            'hasGasDanger',
            'hasGasWarning',
            'activeBellStatus'
        ));
    }

    /**
     * Memproses klaim / pairing perangkat ESP32 keluaran pabrik ke akun pengguna.
     */
    public function pairDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
            'device_password' => ['required', 'string', 'max:100'],
            'nama_ruangan' => ['required', 'string', 'max:50'],
        ], [
            'device_name.required' => 'Nama / S/N Perangkat wajib diisi.',
            'device_password.required' => 'Password Perangkat di bodi belakang alat wajib diisi.',
            'nama_ruangan.required' => 'Lokasi pemasangan ruangan wajib diisi.',
            'nama_ruangan.max' => 'Nama lokasi pemasangan maksimal 50 karakter.',
        ]);

        $inputDeviceName = strtoupper(trim($validated['device_name']));
        $inputPassword = trim($validated['device_password']);
        $inputRuangan = trim($validated['nama_ruangan']);

        // Cari perangkat berdasarkan device_name (case-insensitive)
        $device = Device::whereRaw('UPPER(device_name) = ?', [$inputDeviceName])->first();

        // Validasi keberadaan perangkat & kecocokan password pabrikan
        if (!$device || $device->device_password !== $inputPassword) {
            return back()
                ->withInput($request->only('device_name', 'nama_ruangan'))
                ->with('error', 'Gagal Pairing: Nama Perangkat atau Password di belakang alat tidak sesuai!');
        }

        // Cek apakah perangkat sudah diklaim oleh akun ini atau akun pengguna lain
        if ($device->user_id !== null) {
            if ((int) $device->user_id === (int) Auth::id()) {
                return back()->with('info', "Informasi: Perangkat {$device->device_name} sudah terhubung di akun Anda.");
            }

            return back()
                ->withInput($request->only('device_name', 'nama_ruangan'))
                ->with('error', 'Perangkat ini sudah terikat pada akun pengguna lain!');
        }

        // Klaim perangkat ke akun pengguna yang sedang login
        $device->update([
            'user_id' => Auth::id(),
            'nama_ruangan' => $inputRuangan,
        ]);

        return redirect('/dashboard')
            ->with('success', "Berhasil! Perangkat {$device->device_name} resmi terhubung ke akun Anda.");
    }

    /**
     * Melepas klaim perangkat (Unpair) dari akun pengguna dan mengembalikannya ke status pabrikan.
     */
    public function unpairDevice(Device $device): RedirectResponse
    {
        if ((int) $device->user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak: Anda bukan pemilik sah perangkat ini.');
        }

        $deviceName = $device->device_name;

        $device->update([
            'user_id' => null,
            'nama_ruangan' => null,
        ]);

        return redirect('/dashboard')
            ->with('success', "Perangkat {$deviceName} berhasil dilepas dari akun Anda dan siap diklaim ulang.");
    }

    /**
     * Endpoint JSON Real-Time untuk AJAX Polling & Riwayat Grafik Chart.js (20 Data Terakhir).
     * Endpoint: GET /dashboard/device/{device}/json
     */
    public function getDeviceTelemetry(Device $device): JsonResponse
    {
        if ((int) $device->user_id !== (int) Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Anda tidak memiliki otorisasi atas perangkat ini.',
            ], 403);
        }

        $latest = $device->latestTelemetry;

        $history = $device->telemetries()
            ->latest()
            ->take(20)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Telemetry $item) {
                return [
                    'id' => $item->id,
                    'suhu' => $item->suhu,
                    'kelembapan' => $item->kelembapan,
                    'kadar_gas' => $item->kadar_gas,
                    'sudut_servo_gas' => $item->sudut_servo_gas,
                    'status_bel' => $item->status_bel,
                    'sudut_solar' => $item->sudut_solar,
                    'cahaya_timur' => $item->cahaya_timur,
                    'cahaya_barat' => $item->cahaya_barat,
                    'time_label' => $item->created_at ? $item->created_at->format('H:i:s') : '--:--:--',
                ];
            });

        return response()->json([
            'status' => 'success',
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'nama_ruangan' => $device->nama_ruangan,
            ],
            'is_online' => $device->isOnline(),
            'last_seen_human' => $device->last_seen_at
                ? $device->last_seen_at->diffForHumans()
                : 'Belum pernah sinkron',
            'latest' => $latest,
            'history' => $history,
        ]);
    }
}
