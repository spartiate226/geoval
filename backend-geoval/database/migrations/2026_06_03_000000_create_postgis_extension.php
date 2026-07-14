<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (\Exception $e) {
            Log::warning('PostGIS extension is not installed or available on this PostgreSQL server. Falling back to JSON/TEXT geometry mapping. Error: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP EXTENSION IF EXISTS postgis');
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
