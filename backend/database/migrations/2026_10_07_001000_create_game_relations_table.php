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
        Schema::create('game_relations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('from_game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('to_game_id')->constrained('games')->cascadeOnDelete();
            $table->text('kind');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('from_game_id');
            $table->index('to_game_id');
        });
        DB::statement("ALTER TABLE game_relations ADD CONSTRAINT game_relations_kind_check CHECK (kind IN ('remake_of', 'remaster_of', 'sequel_to', 'collection_contains', 'related'))");
        DB::statement('ALTER TABLE game_relations ADD CONSTRAINT game_relations_no_self_check CHECK (from_game_id <> to_game_id)');
        // Symmetric kinds are stored once, as an ordered pair.
        DB::statement("ALTER TABLE game_relations ADD CONSTRAINT game_relations_symmetric_order_check CHECK (kind NOT IN ('related') OR from_game_id < to_game_id)");
        // One row per kind and unordered pair: rules out contradictory A->B / B->A for directed kinds.
        DB::statement('CREATE UNIQUE INDEX game_relations_pair_unique ON game_relations (kind, LEAST(from_game_id, to_game_id), GREATEST(from_game_id, to_game_id))');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_relations');
    }
};
