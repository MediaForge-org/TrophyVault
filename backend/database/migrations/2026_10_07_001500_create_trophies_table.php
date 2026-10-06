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
        Schema::create('trophies', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('trophy_set_id')->constrained('trophy_sets')->cascadeOnDelete();
            $table->uuid('trophy_group_id');
            // The trophy's key inside its trophy list as defined by the list itself (0, 1, 2, ...).
            $table->integer('set_key');
            $table->text('grade');
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->integer('ordinal');
            $table->jsonb('metadata')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign(['trophy_group_id', 'trophy_set_id'])->references(['id', 'trophy_set_id'])->on('trophy_groups')->cascadeOnDelete();
            $table->unique(['trophy_set_id', 'set_key']);
            // Target for composite foreign keys from earned-trophy rows.
            $table->unique(['id', 'trophy_set_id']);
            $table->index(['trophy_group_id', 'ordinal']);
            $table->index(['trophy_set_id', 'ordinal']);
        });
        DB::statement('ALTER TABLE trophies ADD CONSTRAINT trophies_set_key_check CHECK (set_key >= 0)');
        DB::statement('ALTER TABLE trophies ADD CONSTRAINT trophies_ordinal_check CHECK (ordinal >= 0)');
        DB::statement("ALTER TABLE trophies ADD CONSTRAINT trophies_grade_check CHECK (grade IN ('bronze', 'silver', 'gold', 'platinum'))");
        DB::statement("ALTER TABLE trophies ADD CONSTRAINT trophies_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement('ALTER TABLE trophies ADD CONSTRAINT trophies_metadata_check CHECK (jsonb_is_safe_object(metadata))');
        // A trophy list has at most one Platinum. Zero is valid (platinum status 'not_applicable').
        DB::statement("CREATE UNIQUE INDEX trophies_one_platinum_per_set ON trophies (trophy_set_id) WHERE grade = 'platinum'");
    }

    public function down(): void
    {
        Schema::dropIfExists('trophies');
    }
};
