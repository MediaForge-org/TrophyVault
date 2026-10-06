<?php

namespace App\Support\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * Laravel binds every DateTimeInterface as 'Y-m-d H:i:s' - without the UTC offset. PostgreSQL then reads the string in
 * the session time zone, so a Carbon value in +02:00 silently becomes a different instant, in inserts AND in
 * comparisons (`where('earned_at', '>', $carbon)`), for models, pivots and the query builder alike.
 * Sending the offset makes every bound instant exact. The format has no fraction on purpose (columns have second
 * precision; PostgreSQL would round, PHP's format truncates), see ADR 0008.
 */
final class OffsetAwarePostgresGrammar extends PostgresGrammar
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }
}
