<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->text('code')->primary();
            $table->text('name');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE regions ADD CONSTRAINT regions_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement("ALTER TABLE regions ADD CONSTRAINT regions_code_check CHECK (code ~ '^[a-z][a-z0-9_]*$')");

        // 'global' = valid everywhere, 'unknown' = not (yet) determined. Both are real values, never NULL.

        $now = now();
        DB::table('regions')->insert(array_map(
            fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'global', 'name' => 'Global / region-free', 'sort_order' => 0],
                ['code' => 'unknown', 'name' => 'Unknown', 'sort_order' => 1],
                ['code' => 'na', 'name' => 'North America', 'sort_order' => 10],
                ['code' => 'eu', 'name' => 'Europe', 'sort_order' => 20],
                ['code' => 'jp', 'name' => 'Japan', 'sort_order' => 30],
                ['code' => 'asia', 'name' => 'Asia', 'sort_order' => 40],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
