<?php

use App\Http\Controllers\AuthController;
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

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Company routes
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/lookup/{ticker}', [CompanyController::class, 'lookupByTicker']);
    Route::get('/companies/sec/all', [CompanyController::class, 'getAllCompaniesFromSec']);
    Route::get('/companies/{id}', [CompanyController::class, 'getCompanyDetails']);
    Route::post('/companies/{id}/request-approval', [CompanyController::class, 'requestApproval']);
    Route::post('/companies/{id}/approve', [CompanyController::class, 'approveCompany']);
    Route::post('/companies/{id}/reject', [CompanyController::class, 'rejectCompany']);

    // Metrics routes
    Route::get('/metrics', [MetricsController::class, 'index']);
    Route::get('/summary', [SummaryController::class, 'index']);
    Route::get('/metric/{id}', [MetricDetailController::class, 'show']);
    Route::get('/confidence', [ConfidenceController::class, 'index']);
});
