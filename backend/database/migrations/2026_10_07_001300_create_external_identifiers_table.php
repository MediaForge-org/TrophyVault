<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exclusive arc: exactly one of the entity columns is set, each with a real foreign key.
        Schema::create('external_identifiers', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->text('provider_code');
            $table->text('namespace');
            $table->text('value');
            $table->foreignUuid('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('game_release_id')->nullable()->constrained('game_releases')->cascadeOnDelete();
            $table->foreignUuid('trophy_set_id')->nullable()->constrained('trophy_sets')->cascadeOnDelete();
            // Mappings are retired, not deleted, when a provider re-assigns or withdraws an id.
            $table->timestampTz('retired_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('provider_code')->references('code')->on('providers')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE external_identifiers ADD CONSTRAINT external_identifiers_namespace_check CHECK (namespace ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE external_identifiers ADD CONSTRAINT external_identifiers_value_check CHECK (value ~ '^\S+$')");
        DB::statement('ALTER TABLE external_identifiers ADD CONSTRAINT external_identifiers_one_entity_check CHECK (num_nonnulls(game_id, game_release_id, trophy_set_id) = 1)');
        // An active (provider, namespace, value) identifies exactly one entity.
        DB::statement('CREATE UNIQUE INDEX external_identifiers_active_unique ON external_identifiers (provider_code, namespace, value) WHERE retired_at IS NULL');
        DB::statement('CREATE INDEX external_identifiers_game_idx ON external_identifiers (game_id) WHERE game_id IS NOT NULL');
        DB::statement('CREATE INDEX external_identifiers_game_release_idx ON external_identifiers (game_release_id) WHERE game_release_id IS NOT NULL');
        DB::statement('CREATE INDEX external_identifiers_trophy_set_idx ON external_identifiers (trophy_set_id) WHERE trophy_set_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('external_identifiers');
    }
};
