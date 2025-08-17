<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\TokenController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin routes for token management
Route::prefix('admin')->middleware(['web'])->group(function () {
    Route::delete('users/tokens/{token}/revoke', [TokenController::class, 'revoke'])
        ->name('admin.tokens.revoke');
});
