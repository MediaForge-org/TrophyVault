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
        Schema::create('trophy_set_relations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('from_trophy_set_id')->constrained('trophy_sets')->cascadeOnDelete();
            $table->foreignUuid('to_trophy_set_id')->constrained('trophy_sets')->cascadeOnDelete();
            $table->text('kind');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('from_trophy_set_id');
            $table->index('to_trophy_set_id');
        });
        DB::statement("ALTER TABLE trophy_set_relations ADD CONSTRAINT trophy_set_relations_kind_check CHECK (kind IN ('platform_stack', 'regional_stack', 'remaster_stack', 'equivalent', 'related', 'autopop', 'partial_autopop'))");
        DB::statement('ALTER TABLE trophy_set_relations ADD CONSTRAINT trophy_set_relations_no_self_check CHECK (from_trophy_set_id <> to_trophy_set_id)');
        // Symmetric kinds are stored once, as an ordered pair.
        DB::statement("ALTER TABLE trophy_set_relations ADD CONSTRAINT trophy_set_relations_symmetric_order_check CHECK (kind NOT IN ('platform_stack', 'regional_stack', 'remaster_stack', 'equivalent', 'related') OR from_trophy_set_id < to_trophy_set_id)");
        // One row per kind and unordered pair: rules out contradictory A->B / B->A for directed kinds.
        DB::statement('CREATE UNIQUE INDEX trophy_set_relations_pair_unique ON trophy_set_relations (kind, LEAST(from_trophy_set_id, to_trophy_set_id), GREATEST(from_trophy_set_id, to_trophy_set_id))');
        // A pair is either fully or partially auto-popped, never both.
        DB::statement("CREATE UNIQUE INDEX trophy_set_relations_autopop_exclusive ON trophy_set_relations (LEAST(from_trophy_set_id, to_trophy_set_id), GREATEST(from_trophy_set_id, to_trophy_set_id)) WHERE kind IN ('autopop', 'partial_autopop')");
    }

    public function down(): void
    {
        Schema::dropIfExists('trophy_set_relations');
    }
};
