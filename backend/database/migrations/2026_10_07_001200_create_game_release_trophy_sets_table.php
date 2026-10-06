<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which releases use which trophy list. game_id is carried so that both sides are forced (composite FKs)
        // to belong to the same game: a release can never be linked to a trophy set of a different game.
        Schema::create('game_release_trophy_sets', function (Blueprint $table): void {
            $table->uuid('game_release_id');
            $table->uuid('trophy_set_id');
            $table->uuid('game_id');

            $table->primary(['game_release_id', 'trophy_set_id']);
            $table->foreign(['game_release_id', 'game_id'])->references(['id', 'game_id'])->on('game_releases')->cascadeOnDelete();
            $table->foreign(['trophy_set_id', 'game_id'])->references(['id', 'game_id'])->on('trophy_sets')->cascadeOnDelete();
            $table->index('trophy_set_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_release_trophy_sets');
    }
};
