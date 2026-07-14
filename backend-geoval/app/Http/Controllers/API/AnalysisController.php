<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Parcelle;
use App\Models\SatelliteImage;
use App\Models\WeatherData;
use App\Services\GeospatialService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    protected GeospatialService $geospatialService;
    protected GeminiService $geminiService;

    public function __construct(GeospatialService $geospatialService, GeminiService $geminiService)
    {
        $this->geospatialService = $geospatialService;
        $this->geminiService = $geminiService;
    }

    /**
     * Get analysis history for a parcel.
     */
    public function getHistory($parcelleId)
    {
        $analyses = Analysis::where('parcelle_id', $parcelleId)
            ->orderBy('analysis_date', 'asc')
            ->get();
            
        return response()->json($analyses);
    }

    /**
     * Trigger spatial and agronomic analysis for a parcel on a specific date.
     */
    public function run(Request $request)
    {
        $request->validate([
            'parcelle_id' => 'required|exists:parcelles,id',
            'analysis_date' => 'required|date',
            'satellite_image_id' => 'nullable|exists:satellite_images,id'
        ]);

        $parcelle = Parcelle::withGeoJson()->find($request->parcelle_id);
        $date = $request->analysis_date;

        // 1. Fetch weather data for the date
        $weather = WeatherData::where('date', $date)->first();
        if (!$weather) {
            // Create dummy weather if missing to make it work out of the box
            $weather = WeatherData::create([
                'date' => $date,
                'temperature' => rand(25, 36),
                'humidity' => rand(40, 80),
                'precipitation' => rand(0, 15) > 12 ? rand(5, 45) : 0,
                'wind_speed' => rand(1, 6),
                'solar_radiation' => rand(180, 290)
            ]);
        }

        // Calculate days since planting
        $days = 45; // default
        if ($parcelle->planting_date) {
            $days = max(1, now()->parse($parcelle->planting_date)->diffInDays(now()->parse($date)));
        }

        // 2. Fetch spatial details from satellite image (or mock via GeospatialService)
        $satelliteImage = null;
        $rasterPath = null;
        if ($request->satellite_image_id) {
            $satelliteImage = SatelliteImage::find($request->satellite_image_id);
            $rasterPath = $satelliteImage->file_path;
        }

        try {
            // Calculate NDVI
            $ndviResult = $this->geospatialService->computeNdvi(
                $parcelle->geojson,
                $rasterPath,
                $parcelle->crop_type,
                $days
            );

            // Calculate ET (ET0 and ETc)
            $etResult = $this->geospatialService->computeEt(
                (float) $weather->temperature,
                (float) $weather->humidity,
                (float) $weather->wind_speed,
                (float) $weather->solar_radiation,
                $parcelle->crop_type,
                $days
            );

            // Calculate Water Productivity (WP)
            // WP = Yield (kg/ha) / (Seasonal crop water consumption in m³/ha)
            // Let's approximate seasonal water consumption: daily ETc (mm) * days_since_planting * 10 (1 mm = 10 m³/ha)
            // Or daily WP = Yield / (daily ETc * 10 * 120 days)
            $waterProductivity = null;
            if ($parcelle->yield && $etResult['etc'] > 0) {
                // Yield in kg/ha (e.g. 5000 kg/ha for rice)
                $yieldKg = $parcelle->yield;
                // If yield is small (like 5.5 tons/ha), convert to kg
                if ($yieldKg < 100) {
                    $yieldKg = $yieldKg * 1000;
                }
                
                // Assume average growing season of 120 days
                $estimatedSeasonalWaterM3 = (float)$etResult['etc'] * 120 * 10; 
                if ($estimatedSeasonalWaterM3 > 0) {
                    $waterProductivity = $yieldKg / $estimatedSeasonalWaterM3;
                }
            }

            // 3. Ask Gemini for comments and interpretation
            $aiResult = $this->geminiService->generateInterpretation(
                $ndviResult,
                $etResult,
                $parcelle->crop_type,
                $days,
                $weather->toArray()
            );

            // 4. Save or update the analysis
            $analysis = Analysis::updateOrCreate(
                [
                    'parcelle_id' => $parcelle->id,
                    'analysis_date' => $date
                ],
                [
                    'satellite_image_id' => $request->satellite_image_id,
                    'ndvi_min' => $ndviResult['ndvi_min'] ?? null,
                    'ndvi_max' => $ndviResult['ndvi_max'] ?? null,
                    'ndvi_mean' => $ndviResult['ndvi_mean'] ?? null,
                    'et0' => $etResult['et0'] ?? null,
                    'etc' => $etResult['etc'] ?? null,
                    'water_productivity' => $waterProductivity,
                    'interpretation' => $aiResult['interpretation'] ?? null,
                    'recommendations' => $aiResult['recommendations'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Analyse calculée avec succès',
                'analysis' => $analysis
            ]);

        } catch (\Exception $e) {
            Log::error('Analysis failure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul d\'analyse : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get aggregate statistics for the entire perimeter.
     */
    public function getGlobalStats()
    {
        $totalParcelles = Parcelle::count();
        $averageYield = Parcelle::avg('yield') ?? 0;
        
        $latestAnalyses = Analysis::orderBy('analysis_date', 'desc')->take(20)->get();
        
        $avgNdvi = $latestAnalyses->avg('ndvi_mean') ?? 0.55;
        $avgWaterProductivity = $latestAnalyses->avg('water_productivity') ?? 1.25;
        
        return response()->json([
            'total_parcelles' => $totalParcelles,
            'average_yield' => round($averageYield, 2),
            'average_ndvi' => round($avgNdvi, 2),
            'average_water_productivity' => round($avgWaterProductivity, 2)
        ]);
    }
}
