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
        Schema::create('trophy_sets', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->text('name');
            $table->timestampTz('retired_at')->nullable();
            $table->jsonb('metadata')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Target for composite foreign keys that must stay inside one game (game_release_trophy_sets).
            $table->unique(['id', 'game_id']);
            $table->index('game_id');
        });
        DB::statement("ALTER TABLE trophy_sets ADD CONSTRAINT trophy_sets_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement('ALTER TABLE trophy_sets ADD CONSTRAINT trophy_sets_metadata_check CHECK (jsonb_is_safe_object(metadata))');

        // A trophy list can carry several platform labels (e.g. PS4 + Vita cross-buy lists).
        Schema::create('trophy_set_platforms', function (Blueprint $table): void {
            $table->foreignUuid('trophy_set_id')->constrained('trophy_sets')->cascadeOnDelete();
            $table->text('platform_code');
            $table->foreign('platform_code')->references('code')->on('platforms')->restrictOnDelete();
            $table->primary(['trophy_set_id', 'platform_code']);
            $table->index('platform_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trophy_set_platforms');
        Schema::dropIfExists('trophy_sets');
    }
};
