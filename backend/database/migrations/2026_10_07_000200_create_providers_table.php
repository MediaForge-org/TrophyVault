<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->text('code')->primary();
            $table->text('name');
            $table->text('kind');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE providers ADD CONSTRAINT providers_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement("ALTER TABLE providers ADD CONSTRAINT providers_code_check CHECK (code ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE providers ADD CONSTRAINT providers_kind_check CHECK (kind IN ('platform', 'third_party', 'community', 'manual'))");

        // Reference data: seeded here so every environment (and every test database) has it.

        $now = now();
        DB::table('providers')->insert(array_map(
            fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'psn', 'name' => 'PlayStation Network', 'kind' => 'platform'],
                ['code' => 'psnprofiles', 'name' => 'PSNProfiles', 'kind' => 'third_party'],
                ['code' => 'powerpyx', 'name' => 'PowerPyx', 'kind' => 'third_party'],
                ['code' => 'playstationtrophies', 'name' => 'PlayStationTrophies', 'kind' => 'third_party'],
                ['code' => 'community', 'name' => 'Community', 'kind' => 'community'],
                ['code' => 'manual', 'name' => 'Manual', 'kind' => 'manual'],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
