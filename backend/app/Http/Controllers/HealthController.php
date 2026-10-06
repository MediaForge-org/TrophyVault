<?php

namespace App\Http\Controllers;

use App\Health\HealthChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(HealthChecker $checker): JsonResponse
    {
        $results = $checker->check();
        $components = [];
        $healthy = true;

        foreach ($results as $name => $result) {
            $components[$name] = $result->ok ? 'ok' : 'fail';
            if (! $result->ok) {
                $healthy = false;
                Log::error("Health check failed: {$name}", ['error' => $result->error]);
            }
        }

        return response()->json(
            ['status' => $healthy ? 'ok' : 'degraded', 'components' => $components],
            $healthy ? 200 : 503,
        );
    }
}
