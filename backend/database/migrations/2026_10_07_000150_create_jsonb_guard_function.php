<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One definition of "acceptable provider/metadata JSON" for every JSONB column (ADR 0008):
        //  - must be a JSON object, at most 64 KiB as text,
        //  - no object key at ANY depth (arrays included) that looks like a credential, compared case-insensitively.
        // CREATE OR REPLACE: migrate:fresh drops tables but not functions, so this must be re-runnable.
        // This is defence in depth against accidents, not a secret store and not a filter for secret *values*.
        // unprepared(): the jsonpath filter operator "?" would otherwise be read as a PDO placeholder.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION jsonb_is_safe_object(j jsonb) RETURNS boolean
            LANGUAGE sql IMMUTABLE PARALLEL SAFE
            AS $$
                SELECT jsonb_typeof(j) = 'object'
                   AND octet_length(j::text) <= 65536
                   AND NOT jsonb_path_exists(
                       j,
                       '$.** ? (@.type() == "object").keyvalue() ? (@.key like_regex "^(password|passwd|token|access_token|refresh_token|id_token|npsso|cookie|cookies|set_cookie|secret|client_secret|api_key|apikey|authorization|bearer)$" flag "i")'
                   )
            $$
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS jsonb_is_safe_object(jsonb)');
    }
};
