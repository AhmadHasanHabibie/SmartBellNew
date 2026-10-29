<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telemetry extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'suhu',
        'kelembapan',
        'kadar_gas',
        'sudut_servo_gas',
        'status_bel',
        'sudut_solar',
        'cahaya_timur',
        'cahaya_barat',
    ];

    /**
     * Casting tipe data atribut agar presisi saat diolah menjadi JSON / Grafik.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'suhu' => 'float',
        'kelembapan' => 'float',
        'kadar_gas' => 'integer',
        'sudut_servo_gas' => 'integer',
        'status_bel' => 'integer',
        'sudut_solar' => 'integer',
        'cahaya_timur' => 'integer',
        'cahaya_barat' => 'integer',
    ];

    /**
     * Relasi: Setiap log Telemetry milik satu Device (ESP32).
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}

