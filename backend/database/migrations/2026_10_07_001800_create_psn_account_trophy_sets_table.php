<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "This PSN account has this trophy list" (library membership + sync metadata). Completion is derived, never stored here.
        Schema::create('psn_account_trophy_sets', function (Blueprint $table): void {
            $table->foreignUuid('psn_account_id')->constrained('psn_accounts')->cascadeOnDelete();
            $table->foreignUuid('trophy_set_id')->constrained('trophy_sets')->restrictOnDelete();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_synced_at')->nullable();
            // Progress percentage as reported by the provider; kept only to cross-check our derived value.
            $table->smallInteger('provider_reported_progress')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->primary(['psn_account_id', 'trophy_set_id']);
            $table->index('trophy_set_id');
        });
        DB::statement('ALTER TABLE psn_account_trophy_sets ADD CONSTRAINT psn_account_trophy_sets_progress_check CHECK (provider_reported_progress IS NULL OR provider_reported_progress BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('psn_account_trophy_sets');
    }
};
