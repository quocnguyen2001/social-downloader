<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoDownloadController;
use App\Http\Controllers\Api\VideoExtractionController;
use App\Http\Controllers\Api\OrderController;
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

// Video extraction API (existing functionality)
Route::prefix('v1')->group(function () {
    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
        Route::post('/new-password', [AuthController::class, 'newPassword'])->name('api.auth.new-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
    });

    // Protected authentication routes (require Sanctum authentication)
    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');
    });

    // User profile routes (require Sanctum authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [UserController::class, 'me'])->name('api.user.me');
        Route::post('/me', [UserController::class, 'updateProfile'])->name('api.user.update-profile');
    });

    // Order management routes (require Sanctum authentication)
    Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->name('api.orders.store');
        Route::get('/', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('/{id}', [OrderController::class, 'show'])->name('api.orders.show');
    });

    // Customer orders route (alternative endpoint)
    Route::middleware('auth:sanctum')->get('/customer/orders', [OrderController::class, 'index'])
        ->name('api.customer.orders');

    // Public endpoints (no authentication required)
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('api.settings.index');

    Route::get('/membership-plans', [MembershipPlanController::class, 'index'])
        ->name('api.membership-plans.index');

    Route::prefix('extract')->group(function () {
        Route::get('/platforms', [VideoExtractionController::class, 'platforms'])
            ->name('api.extract.platforms');
    });

    // Public file download endpoint
    Route::get('/download-media/{download_option_id}', [VideoDownloadController::class, 'downloadFile'])
        ->name('api.download.file');

    // Protected endpoints (require API key authentication)
    Route::middleware('api.auth')->group(function () {
        // Guest endpoints (no authentication, with rate limiting)
        Route::middleware('guest.rate.limit')->prefix('guest')->group(function () {
            Route::post('/extract-video', [VideoExtractionController::class, 'extractGuest'])
                ->name('api.guest.extract-video');
        });

        // Authenticated endpoints (Sanctum authentication with rate limiting)
        Route::middleware(['auth:sanctum', 'auth.rate.limit'])->prefix('auth')->group(function () {
            Route::post('/extract-video', [VideoExtractionController::class, 'extractAuthenticated'])
                ->name('api.auth.extract-video');
        });

        Route::get('extract/status/{sessionId}', [VideoExtractionController::class, 'status'])
            ->name('api.extract.status');

        // Video download endpoints
        Route::prefix('download')->group(function () {
            Route::post('/trigger', [VideoDownloadController::class, 'triggerDownload'])
                ->name('api.download.trigger');
            Route::get('/status', [VideoDownloadController::class, 'checkStatus'])
                ->name('api.download.status');
        });
    });
});
