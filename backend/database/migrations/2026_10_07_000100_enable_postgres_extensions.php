<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pg_trgm: substring/fuzzy title search on games (see docs/data-model/indexes-and-constraints.md).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Extensions are intentionally kept on rollback: they are database-level and may be used by other objects.
    }
};
