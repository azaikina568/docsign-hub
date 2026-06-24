<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('System', 'Состояние сервиса.', weight: 1)]
class HealthController extends Controller
{
    /**
     * Service health check.
     *
     * Лёгкий публичный пинг для мониторинга/проб готовности — без обращения к БД.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'docsign-hub',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
