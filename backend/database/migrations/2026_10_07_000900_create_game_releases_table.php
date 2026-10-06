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
        Schema::create('game_releases', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->uuid('edition_id')->nullable();
            $table->text('region_code')->default('unknown');
            // Release-specific title when it differs from the game title (e.g. localised box title).
            $table->text('title')->nullable();
            $table->date('release_date')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->jsonb('metadata')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('region_code')->references('code')->on('regions')->restrictOnDelete();
            // MATCH SIMPLE: skipped while edition_id is NULL; otherwise the edition must belong to the same game.
            $table->foreign(['edition_id', 'game_id'])->references(['id', 'game_id'])->on('editions')->restrictOnDelete();
            $table->unique(['id', 'game_id']);
            $table->index('game_id');
            $table->index('edition_id');
            $table->index('region_code');
        });
        DB::statement("ALTER TABLE game_releases ADD CONSTRAINT game_releases_title_check CHECK (title IS NULL OR title ~ '^\S+(\s+\S+)*$')");
        DB::statement('ALTER TABLE game_releases ADD CONSTRAINT game_releases_metadata_check CHECK (jsonb_is_safe_object(metadata))');

        // A release can span several platforms (cross-gen / bundled releases); a platform has many releases.
        Schema::create('game_release_platforms', function (Blueprint $table): void {
            $table->foreignUuid('game_release_id')->constrained('game_releases')->cascadeOnDelete();
            $table->text('platform_code');
            $table->foreign('platform_code')->references('code')->on('platforms')->restrictOnDelete();
            $table->primary(['game_release_id', 'platform_code']);
            $table->index('platform_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_release_platforms');
        Schema::dropIfExists('game_releases');
    }
};
