<?php

return [
    /*
    | Name of the one non-test database on which destructive artisan commands (migrate:fresh, db:wipe, ...) may run.
    | Empty by default. Set only for the duration of a deliberate reset (`make db-reset-dev`), never in .env.
    | See App\Support\Database\DestructiveCommandPolicy and docs/data-model/database-safety.md.
    */
    'allow_destructive_for' => env('TROPHYVAULT_ALLOW_DESTRUCTIVE_DB'),
];
