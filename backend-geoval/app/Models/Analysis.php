<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    protected $fillable = [
        'parcelle_id',
        'satellite_image_id',
        'analysis_date',
        'ndvi_min',
        'ndvi_max',
        'ndvi_mean',
        'et0',
        'etc',
        'water_productivity',
        'interpretation',
        'recommendations'
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'ndvi_min' => 'decimal:4',
        'ndvi_max' => 'decimal:4',
        'ndvi_mean' => 'decimal:4',
        'et0' => 'decimal:4',
        'etc' => 'decimal:4',
        'water_productivity' => 'decimal:4',
    ];

    public function parcelle()
    {
        return $this->belongsTo(Parcelle::class);
    }

    public function satelliteImage()
    {
        return $this->belongsTo(SatelliteImage::class);
    }
}
