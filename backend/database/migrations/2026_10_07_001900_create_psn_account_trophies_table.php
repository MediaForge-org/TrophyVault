<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per EARNED trophy (absence = not earned). Definitions live in trophies; this is account state.
        Schema::create('psn_account_trophies', function (Blueprint $table): void {
            $table->uuid('psn_account_id');
            $table->uuid('trophy_id');
            $table->uuid('trophy_set_id');
            // NULL = earned, time not reported by the provider.
            $table->timestampTz('earned_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->primary(['psn_account_id', 'trophy_id']);
            // The trophy must belong to the stated set, and the account must have that set.
            $table->foreign(['trophy_id', 'trophy_set_id'])->references(['id', 'trophy_set_id'])->on('trophies')->restrictOnDelete();
            $table->foreign(['psn_account_id', 'trophy_set_id'])->references(['psn_account_id', 'trophy_set_id'])->on('psn_account_trophy_sets')->cascadeOnDelete();
            $table->index(['psn_account_id', 'trophy_set_id']);
            $table->index(['psn_account_id', 'earned_at']);
            $table->index('trophy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psn_account_trophies');
    }
};
