<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Parcelle extends Model
{
    protected $fillable = [
        'name',
        'owner',
        'crop_type',
        'planting_date',
        'harvest_date',
        'yield',
        'geom'
    ];

    protected $casts = [
        'planting_date' => 'date',
        'harvest_date' => 'date',
        'yield' => 'decimal:2',
    ];

    /**
     * Scope to select the geometry as GeoJSON.
     */
    public function scopeWithGeoJson($query)
    {
        static $hasPostgis = null;
        if ($hasPostgis === null) {
            try {
                $hasPostgis = count(DB::select("SELECT extname FROM pg_extension WHERE extname = 'postgis'")) > 0;
            } catch (\Exception $e) {
                $hasPostgis = false;
            }
        }

        if ($hasPostgis) {
            return $query->addSelect([
                '*',
                DB::raw('ST_AsGeoJSON(geom) as geojson')
            ]);
        } else {
            return $query->addSelect([
                '*',
                DB::raw('geom as geojson') // fallback text column
            ]);
        }
    }

    /**
     * Relationship with Analyses.
     */
    public function analyses()
    {
        return $this->hasMany(Analysis::class);
    }
}
