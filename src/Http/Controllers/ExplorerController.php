<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Http\Controllers;

use Hatchyu\DbExplorer\Inspectors\MySqlInspector;
use Hatchyu\DbExplorer\Models\ColumnPresentation;
use Hatchyu\DbExplorer\Support\PresentationTypeResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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
            'primaryKeyColumn' => $primaryKeyColumn,
            'presentationTypes' => $presentationTypes,
            'presentationTypeOptions' => $presentationTypeOptions,
            'presentationTypeOptionsByColumn' => $presentationTypeOptionsByColumn,
            'fieldOptions' => $fieldOptions,
        ] = $this->buildTableContext($table, $request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'table' => $table,
                'physical_table' => $physicalTable,
                'table_type' => $tableType,
                'columns' => $columns,
                'foreignKeys' => $foreignKeys,
                'indexes' => $indexes,
                'primaryKeyColumn' => $primaryKeyColumn,
                'writeEnabled' => $this->isWriteEnabled(),
                'presentationTypes' => $presentationTypes,
                'presentationTypeOptions' => $presentationTypeOptions,
                'presentationTypeOptionsByColumn' => $presentationTypeOptionsByColumn,
                'fieldOptions' => $fieldOptions,
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
            'primaryKeyColumn' => $primaryKeyColumn,
            'presentationTypes' => $presentationTypes,
            'presentationTypeOptions' => $presentationTypeOptions,
            'presentationTypeOptionsByColumn' => $presentationTypeOptionsByColumn,
            'fieldOptions' => $fieldOptions,
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
                'primaryKeyColumn' => $primaryKeyColumn,
                'writeEnabled' => $this->isWriteEnabled(),
                'presentationTypes' => $presentationTypes,
                'presentationTypeOptions' => $presentationTypeOptions,
                'presentationTypeOptionsByColumn' => $presentationTypeOptionsByColumn,
                'fieldOptions' => $fieldOptions,
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

    public function storeRecord(string $table, Request $request)
    {
        $this->abortIfWriteDisabled();

        [
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'tableType' => $tableType,
            'primaryKeyColumn' => $primaryKeyColumn,
            'presentationTypes' => $presentationTypes,
        ] = $this->buildTableContext($table, $request);

        if ($tableType === 'VIEW') {
            abort(422, 'Cannot create records in a view');
        }

        $recordData = $this->validateRecordInput($request, $columns, $foreignKeys, $presentationTypes, true, $primaryKeyColumn);

        $payload = $this->prepareRecordPayload($columns, $recordData, $presentationTypes, true, $primaryKeyColumn);

        $insertedId = DB::table($table)->insertGetId($payload);

        return response()->json([
            'ok' => true,
            'message' => 'Record created successfully',
            'recordId' => $insertedId,
        ]);
    }

    public function updateRecord(string $table, string $id, Request $request)
    {
        $this->abortIfWriteDisabled();

        [
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'tableType' => $tableType,
            'primaryKeyColumn' => $primaryKeyColumn,
            'presentationTypes' => $presentationTypes,
        ] = $this->buildTableContext($table, $request);

        if ($tableType === 'VIEW') {
            abort(422, 'Cannot update records in a view');
        }

        if (! $primaryKeyColumn) {
            abort(422, 'Table has no primary key');
        }

        $recordData = $this->validateRecordInput($request, $columns, $foreignKeys, $presentationTypes, false, $primaryKeyColumn);

        $payload = $this->prepareRecordPayload($columns, $recordData, $presentationTypes, false, $primaryKeyColumn);
        if ($payload === []) {
            abort(422, 'No fields to update');
        }

        $updated = DB::table($table)->where($primaryKeyColumn, $id)->update($payload);
        if ($updated === 0) {
            abort(404, 'Record not found');
        }

        return response()->json([
            'ok' => true,
            'message' => 'Record updated successfully',
        ]);
    }

    public function deleteRecord(string $table, string $id, Request $request)
    {
        $this->abortIfWriteDisabled();

        [
            'tableType' => $tableType,
            'primaryKeyColumn' => $primaryKeyColumn,
        ] = $this->buildTableContext($table, $request);

        if ($tableType === 'VIEW') {
            abort(422, 'Cannot delete records from a view');
        }

        if (! $primaryKeyColumn) {
            abort(422, 'Table has no primary key');
        }

        $deleted = DB::table($table)->where($primaryKeyColumn, $id)->delete();
        if ($deleted === 0) {
            abort(404, 'Record not found');
        }

        return response()->json([
            'ok' => true,
            'message' => 'Record deleted successfully',
        ]);
    }

    public function updatePresentationType(string $table, string $column, Request $request)
    {
        $this->abortIfWriteDisabled();
        if (! $this->hasPresentationTable()) {
            abort(422, 'Presentation mapping table is missing. Run package migrations first.');
        }

        [
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
        ] = $this->buildTableContext($table, $request);

        $columnNames = collect($columns)->pluck('column_name')->all();
        if (! in_array($column, $columnNames, true)) {
            abort(404, 'Column not found');
        }

        $allowedByColumn = $this->allowedPresentationTypesByColumn($columns, $foreignKeys);
        $allowedTypes = $allowedByColumn[$column] ?? [PresentationTypeResolver::TYPE_TEXT];
        if ($allowedTypes === []) {
            abort(422, 'Presentation type cannot be configured for this column');
        }

        $validated = $request->validate([
            'presentation_type' => ['required', 'string', Rule::in($allowedTypes)],
        ]);

        $databaseName = (string) DB::getDatabaseName();
        $userId = $this->currentUserId();
        $columnMeta = collect($columns)->firstWhere('column_name', $column);

        $record = ColumnPresentation::query()
            ->where($this->userColumn(), $userId)
            ->where('database_name', $databaseName)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first()
        ;

        if (! $record) {
            $record = new ColumnPresentation();
            $record->user_id = $userId;
            $record->database_name = $databaseName;
            $record->table_name = $table;
            $record->column_name = $column;
        }

        $record->mysql_data_type = (string) ($columnMeta->data_type ?? '');
        $record->presentation_type = (string) $validated['presentation_type'];
        $record->save();

        return response()->json([
            'ok' => true,
            'presentationType' => $record->presentation_type,
        ]);
    }

    public function schema()
    {
        $inspector = new MySqlInspector();
        $allTables = $inspector->tables();

        $schemaEntries = collect($allTables)
            ->map(function (object $table) use ($inspector): array {
                $logicalName = (string) $table->table_name;

                return [
                    'table_name' => $logicalName,
                    'display_name' => (string) ($table->display_name ?? $logicalName),
                    'table_type' => (string) ($table->table_type ?? 'BASE TABLE'),
                    'columns' => $inspector->columns($logicalName),
                    'foreignKeys' => $inspector->foreignKeys($logicalName),
                ];
            })
            ->all();

        return view('db-explorer::schema', [
            'allTables' => $allTables,
            'schemaEntries' => $schemaEntries,
            'database' => DB::getDatabaseName(),
            'connection' => config('database.default'),
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
        $allowedPresentationTypesByColumn = $this->allowedPresentationTypesByColumn($columns, $foreignKeys);
        $presentationTypes = $this->resolvePresentationTypes($table, $columns, $foreignKeys, $allowedPresentationTypesByColumn);
        $presentationTypeOptions = $this->presentationTypeOptions();
        $presentationTypeOptionsByColumn = $this->buildPresentationTypeOptionsByColumn($allowedPresentationTypesByColumn);
        $fieldOptions = $this->buildFieldOptions($columns, $foreignKeys);
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
            'data',
            'primaryKeyColumn',
            'presentationTypes',
            'presentationTypeOptions',
            'presentationTypeOptionsByColumn',
            'fieldOptions',
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

    private function isWriteEnabled(): bool
    {
        $configured = config('db-explorer.write_enabled');
        if ($configured === null) {
            return app()->environment('local');
        }

        return (bool) $configured;
    }

    private function abortIfWriteDisabled(): void
    {
        if (! $this->isWriteEnabled()) {
            abort(403, 'Write operations are disabled');
        }
    }

    private function currentUserId(): ?int
    {
        $id = auth()->id();
        if ($id === null) {
            return null;
        }

        return (int) $id;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePresentationTypes(
        string $table,
        array $columns,
        array $foreignKeys,
        array $allowedPresentationTypesByColumn
    ): array
    {
        $databaseName = (string) DB::getDatabaseName();
        $userId = $this->currentUserId();
        $foreignColumns = collect($foreignKeys)->pluck('column_name')->filter()->all();
        if (! $this->hasPresentationTable()) {
            $fallback = [];
            foreach ($columns as $column) {
                $columnName = (string) ($column->column_name ?? '');
                if ($columnName === '') {
                    continue;
                }
                $detected = PresentationTypeResolver::detect($column, in_array($columnName, $foreignColumns, true));
                $allowed = $allowedPresentationTypesByColumn[$columnName] ?? [PresentationTypeResolver::TYPE_TEXT];
                if ($allowed === []) {
                    $fallback[$columnName] = $detected;
                    continue;
                }

                $fallback[$columnName] = in_array($detected, $allowed, true) ? $detected : $allowed[0];
            }

            return $fallback;
        }

        $existing = ColumnPresentation::query()
            ->where($this->userColumn(), $userId)
            ->where('database_name', $databaseName)
            ->where('table_name', $table)
            ->get()
            ->keyBy('column_name')
        ;

        $result = [];
        foreach ($columns as $column) {
            $columnName = (string) ($column->column_name ?? '');
            if ($columnName === '') {
                continue;
            }

            $isForeignKey = in_array($columnName, $foreignColumns, true);
            $detected = PresentationTypeResolver::detect($column, $isForeignKey);
            $saved = $existing->get($columnName)?->presentation_type;
            $allowed = $allowedPresentationTypesByColumn[$columnName] ?? [PresentationTypeResolver::TYPE_TEXT];
            if ($allowed === []) {
                $result[$columnName] = $detected;
                continue;
            }

            $presentationType = is_string($saved) && in_array($saved, $allowed, true)
                ? $saved
                : (in_array($detected, $allowed, true) ? $detected : $allowed[0]);

            $result[$columnName] = $presentationType;

            if (! $existing->has($columnName)) {
                ColumnPresentation::query()->updateOrCreate(
                    [
                        'user_id' => $userId,
                        'database_name' => $databaseName,
                        'table_name' => $table,
                        'column_name' => $columnName,
                    ],
                    [
                        'mysql_data_type' => (string) ($column->data_type ?? ''),
                        'presentation_type' => $detected,
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function presentationTypeOptions(): array
    {
        return PresentationTypeResolver::optionsForTypes(PresentationTypeResolver::allowedTypes());
    }

    /**
     * @param array<int, object> $columns
     * @param array<int, object> $foreignKeys
     * @return array<string, array<int, string>>
     */
    private function allowedPresentationTypesByColumn(array $columns, array $foreignKeys): array
    {
        $foreignColumns = collect($foreignKeys)->pluck('column_name')->filter()->all();
        $result = [];

        foreach ($columns as $column) {
            $columnName = (string) ($column->column_name ?? '');
            if ($columnName === '') {
                continue;
            }

            $extra = strtolower((string) ($column->extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                $result[$columnName] = [];
                continue;
            }

            $result[$columnName] = PresentationTypeResolver::allowedForColumn(
                $column,
                in_array($columnName, $foreignColumns, true)
            );
        }

        return $result;
    }

    /**
     * @param array<string, array<int, string>> $allowedPresentationTypesByColumn
     * @return array<string, array<int, array{value:string,label:string}>>
     */
    private function buildPresentationTypeOptionsByColumn(array $allowedPresentationTypesByColumn): array
    {
        $result = [];
        foreach ($allowedPresentationTypesByColumn as $columnName => $types) {
            $result[$columnName] = PresentationTypeResolver::optionsForTypes($types);
        }

        return $result;
    }

    /**
     * @return array<string, array<int, array{value:mixed,label:string}>>
     */
    private function buildFieldOptions(array $columns, array $foreignKeys): array
    {
        $options = [];

        foreach ($columns as $column) {
            $columnName = (string) ($column->column_name ?? '');
            if ($columnName === '') {
                continue;
            }

            if (($column->data_type ?? '') === 'enum') {
                $enumValues = is_array($column->enum_values ?? null) ? $column->enum_values : [];
                if ($enumValues !== []) {
                    $options[$columnName] = array_map(
                        fn ($value) => ['value' => $value, 'label' => (string) $value],
                        $enumValues
                    );
                }
            }
        }

        foreach ($foreignKeys as $fk) {
            $columnName = $fk->column_name ?? null;
            $refTable = $fk->referenced_table_name ?? null;
            $refColumn = $fk->referenced_column_name ?? null;
            if (! $columnName || ! $refTable || ! $refColumn) {
                continue;
            }

            $displayColumn = $this->findDisplayColumnForTable($refTable) ?? $refColumn;
            $rows = DB::table($refTable)
                ->select($refColumn, $displayColumn)
                ->limit(200)
                ->get()
            ;

            $options[$columnName] = $rows
                ->map(fn ($row) => [
                    'value' => $row->{$refColumn},
                    'label' => (string) ($row->{$displayColumn} ?? $row->{$refColumn}),
                ])
                ->values()
                ->all()
            ;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $recordData
     * @param array<string, string> $presentationTypes
     * @return array<string, mixed>
     */
    private function prepareRecordPayload(
        array $columns,
        array $recordData,
        array $presentationTypes,
        bool $isCreate,
        ?string $primaryKeyColumn
    ): array {
        $payload = [];

        foreach ($columns as $column) {
            $columnName = (string) ($column->column_name ?? '');
            if ($columnName === '') {
                continue;
            }

            $extra = strtolower((string) ($column->extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                continue;
            }

            if (! $isCreate && $primaryKeyColumn && $columnName === $primaryKeyColumn) {
                continue;
            }

            if (! array_key_exists($columnName, $recordData)) {
                continue;
            }

            $value = $recordData[$columnName];
            $nullable = ($column->is_nullable ?? 'NO') === 'YES';
            if ($value === '' && $nullable) {
                $payload[$columnName] = null;
                continue;
            }

            $presentationType = $presentationTypes[$columnName] ?? PresentationTypeResolver::TYPE_TEXT;
            if ($presentationType === PresentationTypeResolver::TYPE_BOOLEAN) {
                if ($value === null || $value === '') {
                    $payload[$columnName] = $nullable ? null : 0;
                } else {
                    $payload[$columnName] = in_array($value, [1, '1', true, 'true', 'yes', 'on'], true) ? 1 : 0;
                }
                continue;
            }

            if ($presentationType === PresentationTypeResolver::TYPE_TIME) {
                if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                    $payload[$columnName] = "{$value}:00";
                } else {
                    $payload[$columnName] = $value;
                }
                continue;
            }

            $payload[$columnName] = $value;
        }

        return $payload;
    }

    /**
     * @param array<int, object> $columns
     * @param array<int, object> $foreignKeys
     * @param array<string, string> $presentationTypes
     * @return array<string, mixed>
     */
    private function validateRecordInput(
        Request $request,
        array $columns,
        array $foreignKeys,
        array $presentationTypes,
        bool $isCreate,
        ?string $primaryKeyColumn
    ): array {
        $rules = ['record' => ['required', 'array']];
        $foreignKeyMap = collect($foreignKeys)->keyBy('column_name');

        foreach ($columns as $column) {
            $columnName = (string) ($column->column_name ?? '');
            if ($columnName === '') {
                continue;
            }

            $extra = strtolower((string) ($column->extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                continue;
            }

            if (! $isCreate && $primaryKeyColumn && $columnName === $primaryKeyColumn) {
                continue;
            }

            $ruleKey = "record.{$columnName}";
            $columnRules = [];

            $nullable = ($column->is_nullable ?? 'NO') === 'YES';
            if ($nullable) {
                $columnRules[] = 'nullable';
            } else {
                $columnRules[] = 'required';
            }

            $presentationType = $presentationTypes[$columnName] ?? PresentationTypeResolver::TYPE_TEXT;
            if ($presentationType === PresentationTypeResolver::TYPE_BOOLEAN) {
                $columnRules[] = Rule::in(['yes', 'no', '1', '0', 1, 0, true, false, 'true', 'false', 'on', 'off', null, '']);
            }

            $dataType = strtolower((string) ($column->data_type ?? ''));
            $columnType = strtolower((string) ($column->column_type ?? ''));
            $unsigned = str_contains($columnType, 'unsigned');

            if ($dataType === 'enum') {
                $enumValues = is_array($column->enum_values ?? null) ? $column->enum_values : [];
                if ($enumValues !== []) {
                    $columnRules[] = Rule::in($enumValues);
                }
            } elseif (in_array($dataType, ['char', 'varchar'], true)) {
                $columnRules[] = 'string';
                $max = (int) ($column->character_maximum_length ?? 0);
                if ($max > 0) {
                    $columnRules[] = "max:{$max}";
                }
            } elseif (in_array($dataType, ['text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                $columnRules[] = 'string';
            } elseif ($dataType === 'date') {
                $columnRules[] = 'date';
            } elseif ($dataType === 'time') {
                $columnRules[] = 'regex:/^\d{2}:\d{2}(:\d{2})?$/';
            } elseif (in_array($dataType, ['datetime', 'timestamp'], true)) {
                $columnRules[] = 'date';
            } elseif (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer'], true)) {
                $columnRules[] = 'integer';
                [$min, $max] = $this->integerRangeForType($dataType, $unsigned);
                if ($min !== null) {
                    $columnRules[] = "min:{$min}";
                }
                if ($max !== null) {
                    $columnRules[] = "max:{$max}";
                }
            } elseif ($dataType === 'bigint') {
                $columnRules[] = $unsigned ? 'regex:/^\d+$/' : 'regex:/^-?\d+$/';
            } elseif (in_array($dataType, ['decimal', 'float', 'double', 'real'], true)) {
                $columnRules[] = 'numeric';
            } elseif (in_array($dataType, ['json'], true)) {
                $columnRules[] = 'nullable';
            }

            $fk = $foreignKeyMap->get($columnName);
            if ($fk) {
                $refTable = $fk->referenced_table_name ?? null;
                $refColumn = $fk->referenced_column_name ?? null;
                if (is_string($refTable) && $refTable !== '' && is_string($refColumn) && $refColumn !== '') {
                    $columnRules[] = "exists:{$refTable},{$refColumn}";
                }
            }

            $rules[$ruleKey] = $columnRules;
        }

        $validated = $request->validate($rules);

        return is_array($validated['record'] ?? null) ? $validated['record'] : [];
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function integerRangeForType(string $dataType, bool $unsigned): array
    {
        return match ($dataType) {
            'tinyint' => $unsigned ? [0, 255] : [-128, 127],
            'smallint' => $unsigned ? [0, 65535] : [-32768, 32767],
            'mediumint' => $unsigned ? [0, 16777215] : [-8388608, 8388607],
            'int', 'integer' => $unsigned ? [0, 4294967295] : [-2147483648, 2147483647],
            default => [null, null],
        };
    }

    private function userColumn(): string
    {
        return 'user_id';
    }

    private function hasPresentationTable(): bool
    {
        return Schema::hasTable('db_explorer_column_presentations');
    }
}
