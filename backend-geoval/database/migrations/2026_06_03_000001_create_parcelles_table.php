<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parcelles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner')->nullable();
            $table->string('crop_type');
            $table->date('planting_date')->nullable();
            $table->date('harvest_date')->nullable();
            $table->decimal('yield', 10, 2)->nullable(); // Yield in kg/ha or tons
            $table->timestamps();
        });

        // Check if PostGIS extension is active
        $hasPostgis = false;
        try {
            $result = DB::select("SELECT extname FROM pg_extension WHERE extname = 'postgis'");
            $hasPostgis = count($result) > 0;
        } catch (\Exception $e) {
            // ignore
        }

        if ($hasPostgis) {
            // Add geometry column (Polygon, 4326 SRID)
            DB::statement('ALTER TABLE parcelles ADD COLUMN geom geometry(Polygon, 4326)');
            // Add spatial index
            DB::statement('CREATE INDEX parcelles_geom_idx ON parcelles USING GIST (geom)');
        } else {
            // Fallback column to store GeoJSON geometry string
            Schema::table('parcelles', function (Blueprint $table) {
                $table->text('geom')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcelles');
    }
};
