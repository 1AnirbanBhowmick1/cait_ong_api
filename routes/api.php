<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\MetricDetailController;

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
Route::get('/metrics', [MetricsController::class, 'index']);
Route::get('/summary', [SummaryController::class, 'index']);
Route::get('/metric/{id}', [MetricDetailController::class, 'show']);


