<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')
                ->constrained('devices')
                ->cascadeOnDelete();
            $table->float('suhu')->nullable();
            $table->float('kelembapan')->nullable();
            $table->integer('kadar_gas')->nullable();
            $table->integer('sudut_servo_gas')->nullable();
            $table->integer('status_bel')->default(0);
            $table->integer('sudut_solar')->nullable();
            $table->integer('cahaya_timur')->nullable();
            $table->integer('cahaya_barat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetries');
    }
};

