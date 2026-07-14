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
        Schema::create('weather_data', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('temperature', 5, 2); // °C
            $table->decimal('humidity', 5, 2);    // %
            $table->decimal('precipitation', 5, 2); // mm
            $table->decimal('wind_speed', 5, 2);    // m/s
            $table->decimal('solar_radiation', 6, 2); // W/m² or MJ/m²/day
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_data');
    }
};
