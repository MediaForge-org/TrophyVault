<?php

namespace App\Console\Commands\Guarded;

use App\Support\Database\GuardsDestructiveTarget;
use Illuminate\Database\Console\WipeCommand as LaravelWipeCommand;

/**
 * Laravel's Wipe command, refusing to run against a database that is neither a *_test database nor explicitly allowed.
 * Replaces the stock command in the container (AppServiceProvider); see docs/data-model/database-safety.md.
 */
class GuardedWipeCommand extends LaravelWipeCommand
{
    use GuardsDestructiveTarget;

    public function handle()
    {
        $this->assertSafeDatabaseTarget();

        return parent::handle();
    }
}
