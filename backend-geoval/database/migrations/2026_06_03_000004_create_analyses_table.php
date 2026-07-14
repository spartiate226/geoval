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
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcelle_id')->constrained('parcelles')->onDelete('cascade');
            $table->foreignId('satellite_image_id')->nullable()->constrained('satellite_images')->onDelete('set null');
            $table->date('analysis_date');
            $table->decimal('ndvi_min', 5, 4)->nullable();
            $table->decimal('ndvi_max', 5, 4)->nullable();
            $table->decimal('ndvi_mean', 5, 4)->nullable();
            $table->decimal('et0', 8, 4)->nullable(); // mm/day
            $table->decimal('etc', 8, 4)->nullable(); // mm/day
            $table->decimal('water_productivity', 10, 4)->nullable(); // kg/m³
            $table->text('interpretation')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();

            // Unique pair of parcelle and analysis date
            $table->unique(['parcelle_id', 'analysis_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
