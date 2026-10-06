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
        Schema::create('editions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->text('slug');
            $table->text('name');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['game_id', 'slug']);
            // Target for composite foreign keys that must stay inside one game (game_releases).
            $table->unique(['id', 'game_id']);
        });
        DB::statement("ALTER TABLE editions ADD CONSTRAINT editions_slug_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE editions ADD CONSTRAINT editions_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};
