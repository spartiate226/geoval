<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WeatherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WeatherController extends Controller
{
    /**
     * Get all weather records.
     */
    public function index()
    {
        $weather = WeatherData::orderBy('date', 'desc')->get();
        return response()->json($weather);
    }

    /**
     * Store new daily weather observation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|unique:weather_data,date',
            'temperature' => 'required|numeric',
            'humidity' => 'required|numeric|between:0,100',
            'precipitation' => 'required|numeric',
            'wind_speed' => 'required|numeric',
            'solar_radiation' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $weather = WeatherData::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Données météorologiques enregistrées',
            'weather' => $weather
        ], 201);
    }
}
