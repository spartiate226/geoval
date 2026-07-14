<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ParcelleController;
use App\Http\Controllers\API\AnalysisController;
use App\Http\Controllers\API\WeatherController;
use App\Http\Controllers\API\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Parcelles
Route::get('/parcelles', [ParcelleController::class, 'index']);
Route::post('/parcelles', [ParcelleController::class, 'store']);
Route::get('/parcelles/{id}', [ParcelleController::class, 'show']);
Route::put('/parcelles/{id}', [ParcelleController::class, 'update']);
Route::delete('/parcelles/{id}', [ParcelleController::class, 'destroy']);
Route::post('/parcelles/import-geojson', [ParcelleController::class, 'importGeoJson']);

// Weather
Route::get('/weather', [WeatherController::class, 'index']);
Route::post('/weather', [WeatherController::class, 'store']);

// Analyses
Route::post('/analysis/run', [AnalysisController::class, 'run']);
Route::get('/analysis/history/{parcelle_id}', [AnalysisController::class, 'getHistory']);
Route::get('/analysis/global-stats', [AnalysisController::class, 'getGlobalStats']);

// Reports
Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
