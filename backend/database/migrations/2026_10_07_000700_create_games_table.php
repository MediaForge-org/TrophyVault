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
        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->text('slug');
            $table->text('title');
            $table->text('sort_title');
            $table->timestampTz('retired_at')->nullable();
            $table->jsonb('metadata')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique('slug');
            $table->index('sort_title');
        });
        DB::statement("ALTER TABLE games ADD CONSTRAINT games_slug_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE games ADD CONSTRAINT games_title_check CHECK (title ~ '^\S+(\s+\S+)*$' AND sort_title ~ '^\S+(\s+\S+)*$')");
        DB::statement('ALTER TABLE games ADD CONSTRAINT games_metadata_check CHECK (jsonb_is_safe_object(metadata))');
        DB::statement('CREATE INDEX games_title_trgm_idx ON games USING gin (lower(title) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
