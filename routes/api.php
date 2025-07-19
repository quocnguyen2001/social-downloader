<?php

use App\Http\Controllers\Api\VideoExtractionController;
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

Route::prefix('v1')->group(function () {
    // Public endpoints (no authentication required)
    Route::prefix('extract')->group(function () {
        Route::get('/platforms', [VideoExtractionController::class, 'platforms'])
            ->name('api.extract.platforms');
    });

    // Protected endpoints (require API key authentication)
    Route::middleware('api.auth')->prefix('extract')->group(function () {
        Route::post('/', [VideoExtractionController::class, 'extract'])
            ->name('api.extract.video');

        Route::get('/status/{sessionId}', [VideoExtractionController::class, 'status'])
            ->name('api.extract.status');
    });
});
