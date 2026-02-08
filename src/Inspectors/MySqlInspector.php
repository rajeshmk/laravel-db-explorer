<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Inspectors;

use Illuminate\Support\Facades\DB;

class MySqlInspector
{
    public function tables(): array
    {
        $db = DB::getDatabaseName();
        $prefix = $this->getPrefix();

        $tables = DB::select('
            SELECT table_name, table_type
            FROM information_schema.tables
            WHERE table_schema = ?
            ORDER BY table_name ASC
        ', [$db]);

        if ($prefix) {
            foreach ($tables as $table) {
                // Keep the original physical name for display
                $table->display_name = $table->table_name;

                if (str_starts_with($table->table_name, $prefix)) {
                    // Logic name for routing (without prefix)
                    $table->table_name = substr($table->table_name, strlen($prefix));
                }
            }
        } else {
            foreach ($tables as $table) {
                $table->display_name = $table->table_name;
            }
        }

        return $tables;
    }

    public function table(string $table): ?object
    {
        $db = DB::getDatabaseName();
        $physicalTable = $this->getPrefix() . $table;

        $results = DB::select('
            SELECT table_name, table_type
            FROM information_schema.tables
            WHERE table_schema = ? AND table_name = ?
            LIMIT 1
        ', [$db, $physicalTable]);

        return $results[0] ?? null;
    }

    public function columns(string $table): array
    {
        $db = DB::getDatabaseName();
        $physicalTable = $this->getPrefix() . $table;

        return DB::select('
            SELECT column_name, data_type, column_type, is_nullable, column_key, extra
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ?
        ', [$db, $physicalTable]);
    }

    public function foreignKeys(string $table): array
    {
        $db = DB::getDatabaseName();
        $prefix = $this->getPrefix();
        $physicalTable = $prefix . $table;

        $keys = DB::select('
            SELECT
                column_name,
                referenced_table_name,
                referenced_column_name
            FROM information_schema.key_column_usage
            WHERE table_schema = ?
              AND table_name = ?
              AND referenced_table_name IS NOT NULL
        ', [$db, $physicalTable]);

        if ($prefix) {
            foreach ($keys as $key) {
                // Keep physical name for display
                $key->referenced_table_display_name = $key->referenced_table_name;

                if (str_starts_with($key->referenced_table_name, $prefix)) {
                    // Logical name for routing
                    $key->referenced_table_name = substr($key->referenced_table_name, strlen($prefix));
                }
            }
        } else {
            foreach ($keys as $key) {
                $key->referenced_table_display_name = $key->referenced_table_name;
            }
        }

        return $keys;
    }

    public function indexes(string $table): array
    {
        $db = DB::getDatabaseName();
        $physicalTable = $this->getPrefix() . $table;

        return DB::select('
            SELECT index_name, column_name, non_unique, seq_in_index, index_type
            FROM information_schema.statistics
            WHERE table_schema = ? AND table_name = ?
            ORDER BY index_name, seq_in_index
        ', [$db, $physicalTable]);
    }

    private function getPrefix(): string
    {
        return DB::getConfig('prefix') ?? '';
    }
}
