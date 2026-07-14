<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Parcelle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ParcelleController extends Controller
{
    /**
     * Check if PostGIS extension is active.
     */
    private function hasPostgis(): bool
    {
        static $hasPostgis = null;
        if ($hasPostgis === null) {
            try {
                $hasPostgis = count(DB::select("SELECT extname FROM pg_extension WHERE extname = 'postgis'")) > 0;
            } catch (\Exception $e) {
                $hasPostgis = false;
            }
        }
        return $hasPostgis;
    }

    /**
     * List all parcelles with their GeoJSON geometries.
     */
    public function index()
    {
        $parcelles = Parcelle::withGeoJson()->get();
        return response()->json($parcelles);
    }

    /**
     * Store a new parcelle.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'owner' => 'nullable|string|max:255',
            'crop_type' => 'required|string|max:255',
            'planting_date' => 'nullable|date',
            'harvest_date' => 'nullable|date',
            'yield' => 'nullable|numeric',
            'geometry' => 'required|array' // GeoJSON geometry object (e.g. type: Polygon, coordinates: ...)
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $geomJson = json_encode($request->geometry);
            
            // Generate SQL insert with PostGIS ST_GeomFromGeoJSON
            $parcelle = DB::transaction(function () use ($request, $geomJson) {
                // PostGIS insert
                $id = DB::table('parcelles')->insertGetId([
                    'name' => $request->name,
                    'owner' => $request->owner,
                    'crop_type' => $request->crop_type,
                    'planting_date' => $request->planting_date,
                    'harvest_date' => $request->harvest_date,
                    'yield' => $request->yield,
                    'created_at' => now(),
                    'updated_at' => now(),
                    // Convert geometry from geojson and force SRID 4326 if PostGIS is available
                    'geom' => $this->hasPostgis() 
                        ? DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{$geomJson}'), 4326)") 
                        : $geomJson
                ]);

                return Parcelle::withGeoJson()->find($id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Parcelle créée avec succès',
                'parcelle' => $parcelle
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating parcelle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création géospatiale : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific parcelle.
     */
    public function show($id)
    {
        $parcelle = Parcelle::withGeoJson()->find($id);
        if (!$parcelle) {
            return response()->json(['message' => 'Parcelle non trouvée'], 404);
        }
        return response()->json($parcelle);
    }

    /**
     * Update a parcelle.
     */
    public function update(Request $request, $id)
    {
        $parcelle = Parcelle::find($id);
        if (!$parcelle) {
            return response()->json(['message' => 'Parcelle non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'owner' => 'nullable|string|max:255',
            'crop_type' => 'sometimes|required|string|max:255',
            'planting_date' => 'nullable|date',
            'harvest_date' => 'nullable|date',
            'yield' => 'nullable|numeric',
            'geometry' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($request, $parcelle, $id) {
                $updateData = $request->only(['name', 'owner', 'crop_type', 'planting_date', 'harvest_date', 'yield']);
                $updateData['updated_at'] = now();

                if ($request->has('geometry')) {
                    $geomJson = json_encode($request->geometry);
                    $updateData['geom'] = $this->hasPostgis()
                        ? DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{$geomJson}'), 4326)")
                        : $geomJson;
                }

                DB::table('parcelles')->where('id', $id)->update($updateData);
            });

            return response()->json([
                'success' => true,
                'message' => 'Parcelle mise à jour avec succès',
                'parcelle' => Parcelle::withGeoJson()->find($id)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating parcelle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour géospatiale'
            ], 500);
        }
    }

    /**
     * Delete a parcelle.
     */
    public function destroy($id)
    {
        $parcelle = Parcelle::find($id);
        if (!$parcelle) {
            return response()->json(['message' => 'Parcelle non trouvée'], 404);
        }

        $parcelle->delete();
        return response()->json([
            'success' => true,
            'message' => 'Parcelle supprimée avec succès'
        ]);
    }

    /**
     * Import GeoJSON file containing features.
     */
    public function importGeoJson(Request $request)
    {
        $request->validate([
            'file' => 'required|file'
        ]);

        try {
            $content = file_get_contents($request->file('file')->getRealPath());
            $geojson = json_decode($content, true);

            if (!$geojson || !isset($geojson['type'])) {
                return response()->json(['message' => 'Format GeoJSON invalide'], 400);
            }

            $features = [];
            if ($geojson['type'] === 'FeatureCollection') {
                $features = $geojson['features'];
            } elseif ($geojson['type'] === 'Feature') {
                $features = [$geojson];
            } else {
                return response()->json(['message' => 'Type GeoJSON non supporté (doit être Feature ou FeatureCollection)'], 400);
            }

            $importedCount = 0;

            DB::transaction(function () use ($features, &$importedCount) {
                foreach ($features as $feature) {
                    $properties = $feature['properties'] ?? [];
                    $geometry = $feature['geometry'] ?? null;

                    if (!$geometry || $geometry['type'] !== 'Polygon') {
                        continue; // Skip non-polygons or empty geometries
                    }

                    $name = $properties['name'] ?? $properties['nom'] ?? ('Parcelle ' . (Parcelle::count() + 1));
                    $owner = $properties['owner'] ?? $properties['proprietaire'] ?? null;
                    $cropType = $properties['crop_type'] ?? $properties['culture'] ?? 'Riz';
                    $plantingDate = $properties['planting_date'] ?? $properties['date_semis'] ?? null;
                    $yield = $properties['yield'] ?? $properties['rendement'] ?? null;

                    $geomJson = json_encode($geometry);

                    DB::table('parcelles')->insert([
                        'name' => $name,
                        'owner' => $owner,
                        'crop_type' => $cropType,
                        'planting_date' => $plantingDate,
                        'yield' => $yield,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'geom' => $this->hasPostgis()
                            ? DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{$geomJson}'), 4326)")
                            : $geomJson
                    ]);

                    $importedCount++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => "{$importedCount} parcelles importées avec succès",
                'parcelles' => Parcelle::withGeoJson()->get()
            ]);

        } catch (\Exception $e) {
            Log::error('Error importing GeoJSON: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'import GeoJSON : ' . $e->getMessage()], 500);
        }
    }
}
