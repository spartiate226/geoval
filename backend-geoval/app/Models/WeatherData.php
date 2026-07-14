<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherData extends Model
{
    protected $table = 'weather_data';

    protected $fillable = [
        'date',
        'temperature',
        'humidity',
        'precipitation',
        'wind_speed',
        'solar_radiation'
    ];

    protected $casts = [
        'date' => 'date',
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'precipitation' => 'decimal:2',
        'wind_speed' => 'decimal:2',
        'solar_radiation' => 'decimal:2',
    ];
}
