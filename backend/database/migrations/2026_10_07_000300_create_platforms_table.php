<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table): void {
            $table->text('code')->primary();
            $table->text('name');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE platforms ADD CONSTRAINT platforms_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement("ALTER TABLE platforms ADD CONSTRAINT platforms_code_check CHECK (code ~ '^[a-z][a-z0-9_]*$')");

        $now = now();
        DB::table('platforms')->insert(array_map(
            fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'psp', 'name' => 'PlayStation Portable', 'sort_order' => 10],
                ['code' => 'ps3', 'name' => 'PlayStation 3', 'sort_order' => 20],
                ['code' => 'vita', 'name' => 'PlayStation Vita', 'sort_order' => 30],
                ['code' => 'ps4', 'name' => 'PlayStation 4', 'sort_order' => 40],
                ['code' => 'ps5', 'name' => 'PlayStation 5', 'sort_order' => 50],
                ['code' => 'pc', 'name' => 'PC (PlayStation PC trophy lists)', 'sort_order' => 60],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
