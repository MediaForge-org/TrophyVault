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
        Schema::create('trophy_relations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('from_trophy_id')->constrained('trophies')->cascadeOnDelete();
            $table->foreignUuid('to_trophy_id')->constrained('trophies')->cascadeOnDelete();
            $table->text('kind');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('from_trophy_id');
            $table->index('to_trophy_id');
        });
        DB::statement("ALTER TABLE trophy_relations ADD CONSTRAINT trophy_relations_kind_check CHECK (kind IN ('equivalent', 'similar', 'related', 'replaces', 'autopop'))");
        DB::statement('ALTER TABLE trophy_relations ADD CONSTRAINT trophy_relations_no_self_check CHECK (from_trophy_id <> to_trophy_id)');
        // Symmetric kinds are stored once, as an ordered pair.
        DB::statement("ALTER TABLE trophy_relations ADD CONSTRAINT trophy_relations_symmetric_order_check CHECK (kind NOT IN ('equivalent', 'similar', 'related') OR from_trophy_id < to_trophy_id)");
        // One row per kind and unordered pair: rules out contradictory A->B / B->A for directed kinds.
        DB::statement('CREATE UNIQUE INDEX trophy_relations_pair_unique ON trophy_relations (kind, LEAST(from_trophy_id, to_trophy_id), GREATEST(from_trophy_id, to_trophy_id))');
    }

    public function down(): void
    {
        Schema::dropIfExists('trophy_relations');
    }
};
