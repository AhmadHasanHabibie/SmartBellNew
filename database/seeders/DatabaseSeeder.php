<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // =========================================================================
        // 1. REGISTRASI 2 PERANGKAT FISIK KELUARAN PABRIK (PRE-MANUFACTURED)
        //    Status awal: user_id = null (Siap diklaim / dipairing oleh pengguna)
        // =========================================================================

        $outdoorDevice = Device::updateOrCreate(
            ['device_name' => 'IOTR-OUTDOOR-01'],
            [
                'user_id' => null,
                'device_password' => 'OUTDOOR123',
                'device_type' => 'outdoor',
                'nama_ruangan' => null,
                'api_token' => 'IOTR_SECRET_TOKEN_OUTDOOR_ESP32_998877',
                'last_seen_at' => $now,
            ]
        );

        $indoorDevice = Device::updateOrCreate(
            ['device_name' => 'IOTR-INDOOR-01'],
            [
                'user_id' => null,
                'device_password' => 'INDOOR123',
                'device_type' => 'indoor',
                'nama_ruangan' => null,
                'api_token' => 'IOTR_SECRET_TOKEN_INDOOR_ESP32_112233',
                'last_seen_at' => $now,
            ]
        );

        // Bersihkan telemetri lama jika seeder dijalankan ulang
        Telemetry::whereIn('device_id', [$outdoorDevice->id, $indoorDevice->id])->delete();

        // =========================================================================
        // 2. GENERATE 15 BARIS TELEMETRY AWAL UNTUK UNIT OUTDOOR
        //    Simulasi pergerakan matahari (LDR Timur vs Barat), Servo Solar Tracker,
        //    dan aktivitas Smart Bell (0 = Standby, 1 = Tamu, 2 = Kurir Paket)
        // =========================================================================

        $outdoorSamples = [
            ['timur' => 3250, 'barat' => 1420, 'solar' => 45,  'bel' => 0],
            ['timur' => 3180, 'barat' => 1550, 'solar' => 50,  'bel' => 0],
            ['timur' => 3100, 'barat' => 1690, 'solar' => 55,  'bel' => 0],
            ['timur' => 2980, 'barat' => 1820, 'solar' => 62,  'bel' => 1], // Tamu datang (1x Klik)
            ['timur' => 2890, 'barat' => 1950, 'solar' => 68,  'bel' => 0],
            ['timur' => 2760, 'barat' => 2100, 'solar' => 75,  'bel' => 0],
            ['timur' => 2650, 'barat' => 2280, 'solar' => 82,  'bel' => 0],
            ['timur' => 2540, 'barat' => 2490, 'solar' => 88,  'bel' => 0],
            ['timur' => 2480, 'barat' => 2520, 'solar' => 90,  'bel' => 2], // Kurir paket (3x Klik)
            ['timur' => 2350, 'barat' => 2680, 'solar' => 98,  'bel' => 0],
            ['timur' => 2210, 'barat' => 2810, 'solar' => 105, 'bel' => 0],
            ['timur' => 2090, 'barat' => 2940, 'solar' => 112, 'bel' => 0],
            ['timur' => 1980, 'barat' => 3080, 'solar' => 120, 'bel' => 1], // Tamu datang (1x Klik)
            ['timur' => 1850, 'barat' => 3190, 'solar' => 128, 'bel' => 0],
            ['timur' => 1760, 'barat' => 3310, 'solar' => 135, 'bel' => 0], // Data terbaru (Standby)
        ];

        foreach ($outdoorSamples as $index => $sample) {
            $timestamp = $now->copy()->subMinutes(14 - $index);

            Telemetry::create([
                'device_id' => $outdoorDevice->id,
                'suhu' => null,
                'kelembapan' => null,
                'kadar_gas' => null,
                'sudut_servo_gas' => null,
                'status_bel' => $sample['bel'],
                'sudut_solar' => $sample['solar'],
                'cahaya_timur' => $sample['timur'],
                'cahaya_barat' => $sample['barat'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // =========================================================================
        // 3. GENERATE 15 BARIS TELEMETRY AWAL UNTUK UNIT INDOOR
        //    Simulasi sensor DHT22 (Suhu & Kelembapan), MQ-2 (Kadar Gas ADC),
        //    Servo Indikator Gas (20 = Aman, 90 = Waspada, 160 = Bahaya)
        // =========================================================================

        $indoorSamples = [
            ['suhu' => 27.4, 'lembap' => 64.5, 'gas' => 620,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 27.6, 'lembap' => 64.2, 'gas' => 645,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 27.8, 'lembap' => 63.8, 'gas' => 690,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.1, 'lembap' => 63.1, 'gas' => 740,  'servo_gas' => 20, 'bel' => 1],
            ['suhu' => 28.3, 'lembap' => 62.7, 'gas' => 810,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.5, 'lembap' => 62.0, 'gas' => 950,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.9, 'lembap' => 61.4, 'gas' => 1280, 'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 29.2, 'lembap' => 60.8, 'gas' => 1650, 'servo_gas' => 90, 'bel' => 0], // Sempat Waspada (Aktivitas Memasak)
            ['suhu' => 29.4, 'lembap' => 60.5, 'gas' => 1780, 'servo_gas' => 90, 'bel' => 2],
            ['suhu' => 29.1, 'lembap' => 61.0, 'gas' => 1420, 'servo_gas' => 20, 'bel' => 0], // Kembali Aman
            ['suhu' => 28.8, 'lembap' => 61.6, 'gas' => 1150, 'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.5, 'lembap' => 62.2, 'gas' => 920,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.3, 'lembap' => 62.8, 'gas' => 830,  'servo_gas' => 20, 'bel' => 1],
            ['suhu' => 28.1, 'lembap' => 63.2, 'gas' => 760,  'servo_gas' => 20, 'bel' => 0],
            ['suhu' => 28.0, 'lembap' => 63.5, 'gas' => 715,  'servo_gas' => 20, 'bel' => 0], // Data terbaru (Aman & Stabil)
        ];

        foreach ($indoorSamples as $index => $sample) {
            $timestamp = $now->copy()->subMinutes(14 - $index);

            Telemetry::create([
                'device_id' => $indoorDevice->id,
                'suhu' => $sample['suhu'],
                'kelembapan' => $sample['lembap'],
                'kadar_gas' => $sample['gas'],
                'sudut_servo_gas' => $sample['servo_gas'],
                'status_bel' => $sample['bel'],
                'sudut_solar' => null,
                'cahaya_timur' => null,
                'cahaya_barat' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }
}
