<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'device_name',
        'device_password',
        'device_type',
        'nama_ruangan',
        'api_token',
        'pending_action',
        'last_seen_at',
    ];

    /**
     * Kolom sensitif yang disembunyikan saat serialisasi JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'device_password',
        'api_token',
    ];

    /**
     * Casting tipe data atribut.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Relasi: Perangkat dimiliki oleh satu User (nullable saat belum diklaim dari pabrik).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Perangkat memiliki banyak log data sensor (Telemetry).
     */
    public function telemetries(): HasMany
    {
        return $this->hasMany(Telemetry::class);
    }

    /**
     * Relasi: Mengambil 1 baris data Telemetry paling mutakhir dari perangkat ini.
     */
    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(Telemetry::class)->latestOfMany();
    }

    /**
     * Helper Method: Mengecek apakah ESP32 sedang Online
     * (aktif mengirim sinyal kurang dari 30 detik yang lalu).
     */
    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInSeconds(now()) < 30;
    }

    /**
     * Accessor: Memungkinkan pemanggilan $device->is_online di Blade / JSON.
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }
}

