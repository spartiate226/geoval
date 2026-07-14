<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatelliteImage extends Model
{
    protected $fillable = [
        'filename',
        'file_path',
        'acquisition_date',
        'cloud_cover',
        'bands_meta'
    ];

    protected $casts = [
        'acquisition_date' => 'datetime',
        'cloud_cover' => 'decimal:2',
        'bands_meta' => 'array',
    ];

    public function analyses()
    {
        return $this->hasMany(Analysis::class);
    }
}
