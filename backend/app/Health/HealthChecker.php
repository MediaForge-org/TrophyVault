<?php

namespace App\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Checks the infrastructure the application depends on.
 *
 * Failure details are only logged by the caller, never exposed to HTTP clients.
 */
class HealthChecker
{
    /**
     * @return array{database: CheckResult, redis: CheckResult}
     */
    public function check(): array
    {
        return [
            'database' => $this->attempt(static function (): void {
                DB::select('select 1');
            }),
            'redis' => $this->attempt(static function (): void {
                Redis::connection()->ping();
            }),
        ];
    }

    /**
     * @param  callable(): void  $probe
     */
    private function attempt(callable $probe): CheckResult
    {
        try {
            $probe();

            return new CheckResult(true);
        } catch (Throwable $e) {
            return new CheckResult(false, $e->getMessage());
        }
    }
}
