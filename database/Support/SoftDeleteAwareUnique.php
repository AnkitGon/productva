<?php

namespace Database\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SoftDeleteAwareUnique
{
    /**
     * Create a unique index that only applies to non-soft-deleted rows.
     *
     * @param  list<string>  $columns
     */
    public static function create(string $table, array $columns, string $indexName): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $columnList = implode(', ', $columns);

        $sql = match ($driver) {
            'sqlite', 'pgsql' => "CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columnList}) WHERE deleted_at IS NULL",
            'mysql', 'mariadb' => 'CREATE UNIQUE INDEX '.$indexName.' ON '.$table.' ('.implode(', ', array_map(
                fn (string $column): string => "(CASE WHEN deleted_at IS NULL THEN {$column} END)",
                $columns
            )).')',
            default => "CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columnList})",
        };

        DB::connection()->getPdo()->exec($sql);
    }
}
