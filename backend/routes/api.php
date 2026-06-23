<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CancelDocumentController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentPartyController;
use App\Http\Controllers\Api\V1\ExtendDeadlineController;
use App\Http\Controllers\Api\V1\SendDocumentController;
use App\Http\Controllers\Api\V1\Signing\SigningController;
use App\Http\Controllers\Api\V1\Signing\SigningRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'docsign-hub',
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        // Refresh ходит под своим ability: access-токен сюда не подойдёт, refresh — не подойдёт в API.
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['auth:sanctum', 'abilities:issue-access']);
    });

    // Публичное подписание по capability-токену (внешний участник). Свой throttle —
    // по токену и IP, без аутентификации. Для account-bound подпись требует логина.
    Route::prefix('signing')->middleware('throttle:signing')->group(function () {
        Route::get('{token}', [SigningController::class, 'show']);
        Route::post('{token}/sign', [SigningController::class, 'sign']);
    });

    Route::middleware(['auth:sanctum', 'abilities:access-api', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Подписание зарегистрированным участником из дашборда (identity-путь).
        Route::get('signing-requests', [SigningRequestController::class, 'index']);
        Route::post('signing-requests/{party}/sign', [SigningRequestController::class, 'sign']);

        Route::apiResource('documents', DocumentController::class);
        Route::get('documents/{document}/events', [DocumentController::class, 'events']);

        Route::scopeBindings()->group(function () {
            Route::post('documents/{document}/parties', [DocumentPartyController::class, 'store']);
            Route::delete('documents/{document}/parties/{party}', [DocumentPartyController::class, 'destroy']);
        });

        Route::post('documents/{document}/send', SendDocumentController::class);
        Route::post('documents/{document}/cancel', CancelDocumentController::class);
        Route::patch('documents/{document}/deadline', ExtendDeadlineController::class);
    });
});
