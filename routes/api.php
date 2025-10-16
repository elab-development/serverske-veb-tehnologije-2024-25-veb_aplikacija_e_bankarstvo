<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ExchangeRateController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('accounts', AccountController::class);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transfers',   [TransferController::class, 'store']);
});

// ZA ADMINA
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/exchange-rates/sync', [ExchangeRateController::class, 'sync']);
});
