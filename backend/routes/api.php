<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'docsign-hub',
            'timestamp' => now()->toISOString(),
        ]);
    });
});
