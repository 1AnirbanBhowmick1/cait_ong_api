<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConfidenceController;
use App\Http\Controllers\MetricDetailController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/lookup/{ticker}', [CompanyController::class, 'lookupByTicker']);
Route::get('/metrics', [MetricsController::class, 'index']);
Route::get('/summary', [SummaryController::class, 'index']);
Route::get('/metric/{id}', [MetricDetailController::class, 'show']);
Route::get('/confidence', [ConfidenceController::class, 'index']);
