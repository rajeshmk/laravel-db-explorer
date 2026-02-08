<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Http\Controllers;

use Hatchyu\DbExplorer\Inspectors\MySqlInspector;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class ExplorerController extends Controller
{
    public function index()
    {
        $inspector = new MySqlInspector();
        $allTables = $inspector->tables();

        return view('db-explorer::layout', [
            'allTables' => $allTables,
            'table' => null,
            'initialState' => [
                'connection' => config('database.default'),
                'database' => DB::getDatabaseName(),
            ],
        ]);
    }

    public function table(string $table, Request $request)
    {
        // Validate query parameters to prevent injection attacks
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        [
            'inspector' => $inspector,
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'indexes' => $indexes,
            'physicalTable' => $physicalTable,
            'tableType' => $tableType,
            'data' => $data,
        ] = $this->buildTableContext($table, $request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'table' => $table,
                'physical_table' => $physicalTable,
                'table_type' => $tableType,
                'columns' => $columns,
                'foreignKeys' => $foreignKeys,
                'indexes' => $indexes,
                'data' => $data->items(),
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        }

        return view('db-explorer::layout', [
            'allTables' => $inspector->tables(),
            'table' => $table,
            'physicalTable' => $physicalTable,
            'tableType' => $tableType,
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'data' => $data,
        ]);
    }

    public function record(string $table, string $id, Request $request)
    {
        // Validate query parameters to prevent injection attacks
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);
        [
            'inspector' => $inspector,
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'indexes' => $indexes,
            'physicalTable' => $physicalTable,
            'tableType' => $tableType,
            'data' => $data,
        ] = $this->buildTableContext($table, $request);

        $recordArray = $this->findRecordOrFail($table, $columns, $id);
        $foreignKeyDisplay = $this->buildForeignKeyDisplayValues($foreignKeys, $recordArray);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'table' => $table,
                'physical_table' => $physicalTable,
                'table_type' => $tableType,
                'columns' => $columns,
                'foreignKeys' => $foreignKeys,
                'indexes' => $indexes,
                'data' => $data->items(),
                'selectedRecord' => $recordArray,
                'foreignKeyDisplay' => $foreignKeyDisplay,
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        }

        return view('db-explorer::layout', [
            'allTables' => $inspector->tables(),
            'table' => $table,
            'physicalTable' => $physicalTable,
            'tableType' => $tableType,
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'indexes' => $indexes,
            'data' => $data,
            'selectedRecord' => $recordArray,
            'foreignKeyDisplay' => $foreignKeyDisplay,
        ]);
    }

    private function buildTableContext(string $table, Request $request): array
    {
        $inspector = new MySqlInspector();

        // Validate table exists in the accessible tables list
        $allTables = $inspector->tables();
        $tableExists = collect($allTables)->pluck('table_name')->contains($table);
        if (! $tableExists) {
            abort(404, 'Table not found');
        }

        $tableMeta = $inspector->table($table);

        if (! $tableMeta) {
            abort(404, 'Table not found');
        }

        $columns = $inspector->columns($table);
        $foreignKeys = $inspector->foreignKeys($table);
        $indexes = $inspector->indexes($table);
        $conn = DB::connection();
        $prefix = method_exists($conn, 'getTablePrefix') ? $conn->getTablePrefix() : (DB::getConfig('prefix') ?? '');
        $physicalTable = ($prefix ?? '') . $table;
        $tableType = $tableMeta?->table_type ?? 'BASE TABLE';

        $perPage = $this->resolvePerPage();
        $query = DB::table($table);

        $columnNames = array_map(fn ($col) => $col->column_name, $columns);
        $autoIncrementColumn = $this->findAutoIncrementColumn($columns);
        $primaryKeyColumn = $this->findPrimaryKeyColumn($columns);
        $this->applySorting($query, $columnNames, $request, $autoIncrementColumn, $primaryKeyColumn);
        $this->applySearch($query, $columns, $request);

        $data = $query->paginate($perPage);

        return compact(
            'inspector',
            'columns',
            'foreignKeys',
            'indexes',
            'physicalTable',
            'tableType',
            'data'
        );
    }

    private function applySorting(
        Builder $query,
        array $columnNames,
        Request $request,
        ?string $autoIncrementColumn,
        ?string $primaryKeyColumn
    ): void {
        $sort = $request->get('sort');
        $defaultDir = strtolower(config('db-explorer.default_sort_direction', 'desc'));
        $direction = strtolower((string) $request->get('direction', $defaultDir));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $defaultDir;

        if ($sort && in_array($sort, $columnNames, true)) {
            $query->orderBy($sort, $direction);
            if ($primaryKeyColumn && $primaryKeyColumn !== $sort) {
                $query->orderBy($primaryKeyColumn, $direction);
            } elseif ($autoIncrementColumn && $autoIncrementColumn !== $sort) {
                $query->orderBy($autoIncrementColumn, $direction);
            }

            return;
        }

        if ($autoIncrementColumn && in_array($autoIncrementColumn, $columnNames, true)) {
            $query->orderBy($autoIncrementColumn, $defaultDir);

            return;
        }

        if (in_array('id', $columnNames, true)) {
            $query->orderBy('id', $defaultDir);
        }
    }

    private function applySearch(Builder $query, array $columns, Request $request): void
    {
        $search = $request->get('search');
        if ($search === null || $search === '') {
            return;
        }

        // Escape special characters for LIKE queries to prevent LIKE injection
        $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        $searchable = $this->searchableColumns($columns);
        if ($searchable === []) {
            return;
        }

        $query->where(function ($q) use ($searchable, $search) {
            foreach ($searchable as $col) {
                $q->orWhere($col, 'like', "%{$search}%");
            }
        });
    }

    private function searchableColumns(array $columns): array
    {
        $types = [
            'char',
            'varchar',
            'text',
            'tinytext',
            'mediumtext',
            'longtext',
            'enum',
            'set',
            'json',
            'date',
            'datetime',
            'timestamp',
        ];

        $filtered = array_filter($columns, fn ($col) => in_array($col->data_type ?? '', $types, true));

        return array_map(fn ($col) => $col->column_name, $filtered);
    }

    private function resolvePerPage(): int
    {
        $perPage = (int) config('db-explorer.per_page', 25);

        return $perPage > 0 ? $perPage : 25;
    }

    private function findRecordOrFail(string $table, array $columns, string $id): array
    {
        $primaryKey = collect($columns)->firstWhere('column_key', 'PRI');
        $pkColumn = $primaryKey ? $primaryKey->column_name : 'id';

        $record = DB::table($table)->where($pkColumn, $id)->first();
        if (! $record) {
            abort(404, 'Record not found');
        }

        return (array) $record;
    }

    private function findAutoIncrementColumn(array $columns): ?string
    {
        foreach ($columns as $column) {
            $extra = $column->extra ?? '';
            if (is_string($extra) && stripos($extra, 'auto_increment') !== false) {
                return $column->column_name ?? null;
            }
        }

        return null;
    }

    private function buildForeignKeyDisplayValues(array $foreignKeys, array $recordArray): array
    {
        $display = [];
        $queriesByTable = [];
        $displayColumnCache = [];

        // Group foreign keys by referenced table to batch queries (fixes N+1 problem)
        foreach ($foreignKeys as $fk) {
            $columnName = $fk->column_name ?? null;
            if (! $columnName || ! array_key_exists($columnName, $recordArray)) {
                continue;
            }

            $fkValue = $recordArray[$columnName];
            if ($fkValue === null || $fkValue === '') {
                continue;
            }

            $refTable = $fk->referenced_table_name ?? null;
            $refColumn = $fk->referenced_column_name ?? null;
            if (! $refTable || ! $refColumn) {
                continue;
            }

            if (! array_key_exists($refTable, $displayColumnCache)) {
                $displayColumnCache[$refTable] = $this->findDisplayColumnForTable($refTable);
            }

            $displayColumn = $displayColumnCache[$refTable];
            if (! $displayColumn) {
                continue;
            }

            // Initialize batch query structure
            if (! isset($queriesByTable[$refTable])) {
                $queriesByTable[$refTable] = [
                    'column' => $refColumn,
                    'display_column' => $displayColumn,
                    'values' => [],
                    'mapping' => [],
                ];
            }

            // Store value and its mapping
            $queriesByTable[$refTable]['values'][$fkValue] = $fkValue;
            $queriesByTable[$refTable]['mapping'][$columnName] = $fkValue;
        }

        // Execute batched queries instead of individual ones
        foreach ($queriesByTable as $table => $data) {
            $results = DB::table($table)
                ->select($data['column'], $data['display_column'])
                ->whereIn($data['column'], array_unique(array_values($data['values'])))
                ->get()
                ->keyBy($data['column'])
            ;

            foreach ($data['mapping'] as $columnName => $fkValue) {
                if (isset($results[$fkValue])) {
                    $display[$columnName] = $results[$fkValue]->{$data['display_column']};
                }
            }
        }

        return $display;
    }

    private function findDisplayColumnForTable(string $table): ?string
    {
        $inspector = new MySqlInspector();
        $columns = $inspector->columns($table);

        $stringTypes = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];
        $dateTypes = ['date', 'datetime', 'timestamp'];

        foreach ($columns as $column) {
            if (in_array($column->data_type ?? '', $stringTypes, true)) {
                return $column->column_name;
            }
        }

        foreach ($columns as $column) {
            if (in_array($column->data_type ?? '', $dateTypes, true)) {
                return $column->column_name;
            }
        }

        return null;
    }

    private function findPrimaryKeyColumn(array $columns): ?string
    {
        $primaryKey = collect($columns)->firstWhere('column_key', 'PRI');

        return $primaryKey?->column_name;
    }
}
