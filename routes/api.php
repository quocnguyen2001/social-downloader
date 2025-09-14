<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoDownloadController;
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
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
        Route::post('/new-password', [AuthController::class, 'newPassword'])->name('api.auth.new-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
    });

    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('api.auth.change-password');
        Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [UserController::class, 'me'])->name('api.user.me');
        Route::post('/me', [UserController::class, 'updateProfile'])->name('api.user.update-profile');
    });

    Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->name('api.orders.store');
        Route::get('/', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('/{id}', [OrderController::class, 'show'])->name('api.orders.show');
    });

    Route::middleware('auth:sanctum')->get('/customer/orders', [OrderController::class, 'index'])
        ->name('api.customer.orders');

    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('api.settings.index');

    Route::get('/membership-plans', [MembershipPlanController::class, 'index'])
        ->name('api.membership-plans.index');
    Route::get('/membership-plans/{id}', [MembershipPlanController::class, 'show'])
        ->name('api.membership-plans.show');

    Route::prefix('extract')->group(function () {
        Route::get('/platforms', [VideoExtractionController::class, 'platforms'])
            ->name('api.extract.platforms');
    });

    Route::get('/download-media/{download_option_id}', [VideoDownloadController::class, 'downloadFile'])
        ->name('api.download.file');

    Route::middleware('api.auth')->group(function () {
        Route::middleware('guest.rate.limit')->prefix('guest')->group(function () {
            Route::post('/extract-video', [VideoExtractionController::class, 'extractGuest'])
                ->name('api.guest.extract-video');
        });

        Route::middleware(['auth:sanctum', 'auth.rate.limit'])->prefix('auth')->group(function () {
            Route::post('/extract-video', [VideoExtractionController::class, 'extractAuthenticated'])
                ->name('api.auth.extract-video');
        });

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{id}', [OrderController::class, 'show']);
        });


        Route::get('extract/status/{sessionId}', [VideoExtractionController::class, 'status'])
            ->name('api.extract.status');

        Route::prefix('download')->group(function () {
            Route::post('/trigger', [VideoDownloadController::class, 'triggerDownload'])
                ->name('api.download.trigger');
            Route::get('/status', [VideoDownloadController::class, 'checkStatus'])
                ->name('api.download.status');
        });

        Route::get('payment-methods', [PublicController::class, 'paymentMethods']);

        Route::get('payment-status/{chargeId}', [PublicController::class, 'checkBankTransferStatus']);
    });
});
