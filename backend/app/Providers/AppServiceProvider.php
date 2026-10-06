<?php

namespace App\Providers;

use App\Console\Commands\Guarded\GuardedFreshCommand;
use App\Console\Commands\Guarded\GuardedRefreshCommand;
use App\Console\Commands\Guarded\GuardedResetCommand;
use App\Console\Commands\Guarded\GuardedRollbackCommand;
use App\Console\Commands\Guarded\GuardedWipeCommand;
use App\Support\Database\OffsetAwarePostgresGrammar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // All instants are timestamptz: bind them with their UTC offset (see OffsetAwarePostgresGrammar).
        $this->app->afterResolving('db', function (DatabaseManager $db): void {
            $db->extend('pgsql', function (array $config, string $name): Connection {
                $connection = $this->app->make('db.factory')->make($config, $name);
                $connection->setQueryGrammar(new OffsetAwarePostgresGrammar($connection));

                return $connection;
            });
        });

        // Database safety, layer 2 (docs/data-model/database-safety.md): destructive commands refuse unsafe targets.
        // extend() works whether the framework's (deferred) provider registers the command before or after this one.
        $this->app->extend(FreshCommand::class, fn (FreshCommand $c, Application $app): FreshCommand => new GuardedFreshCommand($app->make('migrator')));
        $this->app->extend(RefreshCommand::class, fn (RefreshCommand $c): RefreshCommand => new GuardedRefreshCommand);
        $this->app->extend(ResetCommand::class, fn (ResetCommand $c, Application $app): ResetCommand => new GuardedResetCommand($app->make('migrator')));
        $this->app->extend(RollbackCommand::class, fn (RollbackCommand $c, Application $app): RollbackCommand => new GuardedRollbackCommand($app->make('migrator')));
        $this->app->extend(WipeCommand::class, fn (WipeCommand $c): WipeCommand => new GuardedWipeCommand);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Immutable dates everywhere; all stored instants are timestamptz in UTC (ADR 0008).
        Date::use(CarbonImmutable::class);

        // Database safety, layer 1: Laravel's own prohibition in production (layer 2 is registered in register()).
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
