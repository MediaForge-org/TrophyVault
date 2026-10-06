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
        Schema::create('psn_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(new Expression('uuidv7()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // PSN's own account id (opaque string). This table is PSN-specific by design; see ADR 0012.
            $table->text('psn_account_id');
            $table->text('online_id');
            $table->text('status')->default('active');
            $table->timestampTz('last_synced_at')->nullable();
            // Non-secret profile data only (avatar url, language, ...). Credentials never go here (ADR 0008).
            $table->jsonb('profile')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique('psn_account_id');
            $table->index('user_id');
        });
        DB::statement("ALTER TABLE psn_accounts ADD CONSTRAINT psn_accounts_psn_account_id_check CHECK (psn_account_id ~ '^\S+$')");
        DB::statement("ALTER TABLE psn_accounts ADD CONSTRAINT psn_accounts_online_id_check CHECK (online_id ~ '^\S+$')");
        DB::statement("ALTER TABLE psn_accounts ADD CONSTRAINT psn_accounts_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement('ALTER TABLE psn_accounts ADD CONSTRAINT psn_accounts_profile_check CHECK (jsonb_is_safe_object(profile))');
    }

    public function down(): void
    {
        Schema::dropIfExists('psn_accounts');
    }
};
