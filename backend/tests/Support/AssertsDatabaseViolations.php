<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Asserts that PostgreSQL itself rejects a statement (not just application validation).
 * The action runs in a savepoint, so the surrounding test transaction stays usable.
 */
trait AssertsDatabaseViolations
{
    public const NOT_NULL = '23502';

    public const RESTRICT = '23001';

    public const FOREIGN_KEY = '23503';

    public const UNIQUE = '23505';

    public const CHECK = '23514';

    /**
     * @param  Closure(): mixed  $action
     */
    protected function assertViolation(string $sqlState, Closure $action): void
    {
        try {
            DB::transaction($action);
        } catch (QueryException $e) {
            $this->assertSame($sqlState, (string) $e->getCode(), 'Unexpected SQLSTATE: '.$e->getMessage());

            return;
        }

        $this->fail("Expected database violation {$sqlState}, but the statement succeeded.");
    }
}
