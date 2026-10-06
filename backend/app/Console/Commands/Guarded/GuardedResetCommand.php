<?php

namespace App\Console\Commands\Guarded;

use App\Support\Database\GuardsDestructiveTarget;
use Illuminate\Database\Console\Migrations\ResetCommand as LaravelResetCommand;

/**
 * Laravel's Reset command, refusing to run against a database that is neither a *_test database nor explicitly allowed.
 * Replaces the stock command in the container (AppServiceProvider); see docs/data-model/database-safety.md.
 */
class GuardedResetCommand extends LaravelResetCommand
{
    use GuardsDestructiveTarget;

    public function handle()
    {
        $this->assertSafeDatabaseTarget();

        return parent::handle();
    }
}
