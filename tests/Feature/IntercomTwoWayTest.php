<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IntercomTwoWayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_two_way_intercom_lifecycle(): void
    {
        // 1. Buat User & Device Outdoor
        $user = User::create([
            'name' => 'Operator Test',
            'email' => 'operator-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);
        $device = Device::create([
            'user_id' => $user->id,
            'device_name' => 'IOTR-OUTDOOR-' . strtoupper(uniqid()),
            'device_password' => 'TESTPASS123',
            'device_type' => 'outdoor',
            'nama_ruangan' => 'Gerbang Pengujian',
            'api_token' => 'test-outdoor-token-' . uniqid(),
            'pending_action' => null,
        ]);

        // 2. User klik tombol "Sedang Diluar Rumah" (POST /device/{id}/set-action)
        $responseAction = $this->actingAs($user)
            ->postJson("/device/{$device->id}/set-action", [
                'action' => 'diluar',
            ]);

        $responseAction->assertStatus(200);
        $responseAction->assertJson([
            'status' => 'success',
            'pending_action' => 'diluar',
        ]);

        $this->assertEquals('diluar', $device->fresh()->pending_action);

        // 3. ESP32 Outdoor mengirim HTTP POST /api/telemetry
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, '[Telegram Notification]')
                    && str_contains($message, 'Tamu terdeteksi');
            });

        $telemetryPayload = [
            'status_bel' => 1,
            'sudut_solar' => 95,
            'cahaya_timur' => 2000,
            'cahaya_barat' => 1800,
        ];

        $responseTelemetry = $this->withHeaders([
            'Authorization' => "Bearer {$device->api_token}",
        ])->postJson('/api/telemetry', $telemetryPayload);

        // Harus mengembalikan 200 dengan reply_action == 'diluar'
        $responseTelemetry->assertStatus(200);
        $responseTelemetry->assertJson([
            'reply_action' => 'diluar',
        ]);

        // pending_action di database harus sudah direset menjadi null
        $this->assertNull($device->fresh()->pending_action);

        // 4. Request berikutnya dari ESP32 (tidak ada pending action lagi)
        $responseTelemetryNext = $this->withHeaders([
            'Authorization' => "Bearer {$device->api_token}",
        ])->postJson('/api/telemetry', [
            'status_bel' => 0,
            'sudut_solar' => 90,
            'cahaya_timur' => 2000,
            'cahaya_barat' => 2000,
        ]);

        $responseTelemetryNext->assertStatus(200);
        $responseTelemetryNext->assertJson([
            'reply_action' => null,
            'message' => 'Data saved',
        ]);
    }

    public function test_dashboard_renders_with_security_logs(): void
    {
        $user = User::create([
            'name' => 'Operator Test',
            'email' => 'operator-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        $device = Device::create([
            'user_id' => $user->id,
            'device_name' => 'IOTR-OUTDOOR-' . strtoupper(uniqid()),
            'device_password' => 'TESTPASS123',
            'device_type' => 'outdoor',
            'nama_ruangan' => 'Gerbang Utama',
            'api_token' => 'test-token-' . uniqid(),
            'pending_action' => null,
        ]);

        \App\Models\Telemetry::create([
            'device_id' => $device->id,
            'status_bel' => 1,
            'sudut_solar' => 90,
            'cahaya_timur' => 2100,
            'cahaya_barat' => 1900,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Recent Security Logs');
        $response->assertSee('Set Status: Sedang Diluar Rumah');
        $response->assertSee('TAMU DATANG (1x KLIK)');
    }
}
