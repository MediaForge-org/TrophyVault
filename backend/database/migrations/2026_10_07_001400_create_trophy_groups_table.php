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
        Schema::create('trophy_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('trophy_set_id')->constrained('trophy_sets')->cascadeOnDelete();
            // The group's key inside its trophy list as defined by the list itself ('default', '001', ...).
            $table->text('set_key');
            $table->text('name');
            $table->text('description')->nullable();
            $table->integer('ordinal');
            // TrophyVault's own semantic classification; independent of the list's structure. 'unknown' is a real state.
            $table->text('classification')->default('unknown');
            $table->text('classification_origin')->default('auto');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['trophy_set_id', 'set_key']);
            // Target for the composite foreign key from trophies (group must be in the trophy's set).
            $table->unique(['id', 'trophy_set_id']);
            $table->index(['trophy_set_id', 'ordinal']);
        });
        DB::statement("ALTER TABLE trophy_groups ADD CONSTRAINT trophy_groups_name_check CHECK (name ~ '^\S+(\s+\S+)*$')");
        DB::statement("ALTER TABLE trophy_groups ADD CONSTRAINT trophy_groups_set_key_check CHECK (set_key ~ '^\S+$')");
        DB::statement('ALTER TABLE trophy_groups ADD CONSTRAINT trophy_groups_ordinal_check CHECK (ordinal >= 0)');
        DB::statement("ALTER TABLE trophy_groups ADD CONSTRAINT trophy_groups_classification_check CHECK (classification IN ('base', 'dlc', 'expansion', 'free_update', 'online', 'challenge', 'other', 'unknown'))");
        DB::statement("ALTER TABLE trophy_groups ADD CONSTRAINT trophy_groups_classification_origin_check CHECK (classification_origin IN ('auto', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('trophy_groups');
    }
};
