<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Database Schema Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #243247;
            --ink-soft: #4d5f79;
            --muted: #6b7c95;
            --line: #d8e0ea;
            --panel: #ffffff;
            --panel-soft: #f8fbff;
            --table-accent: #0f766e;
            --table-bg: #dcfce7;
            --view-accent: #c2410c;
            --view-bg: #ffedd5;
            --yes-bg: #dcfce7;
            --yes-ink: #166534;
            --no-bg: #fee2e2;
            --no-ink: #991b1b;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px circle at 0% -10%, #dbeafe 0%, transparent 56%),
                radial-gradient(900px circle at 100% 0%, #ffedd5 0%, transparent 50%),
                #f3f7fc;
            font-size: 14px;
        }

        .shell {
            max-width: 1320px;
            margin: 28px auto 56px;
            padding: 0 22px;
        }

        .hero {
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #d6e2f0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }

        .title {
            margin: 0;
            font-size: 46px;
            line-height: 1.03;
            letter-spacing: -0.02em;
            font-weight: 700;
            color: #2b3b57;
        }

        .meta {
            margin-top: 10px;
            color: var(--ink-soft);
            font-size: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 10px;
        }

        .meta strong {
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            border: 1px solid #c8d4e4;
            background: #fff;
            color: var(--ink);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 9px 20px rgba(15, 23, 42, 0.12);
            background: #f8fafc;
        }

        .btn-primary {
            background: #0f766e;
            border-color: #0d6a63;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0d6a63;
        }

        .stats {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            border: 1px solid #d6e2f0;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }

        .stat-label {
            margin: 0;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            font-weight: 700;
        }

        .stat-value {
            margin-top: 4px;
            font-size: 26px;
            line-height: 1;
            font-weight: 700;
            color: var(--ink);
        }

        .toolbar {
            position: sticky;
            top: 12px;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 12px;
            margin-bottom: 16px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #d6e2f0;
            border-radius: 12px;
            backdrop-filter: blur(8px);
        }

        .search {
            flex: 1 1 340px;
            position: relative;
        }

        .search-input,
        .select {
            width: 100%;
            border: 1px solid #c8d4e4;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
            color: var(--ink);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
        }

        .select {
            width: auto;
            min-width: 170px;
        }

        .search-input:focus,
        .select:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        .visible-count {
            margin-left: auto;
            color: var(--ink-soft);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .cards {
            display: grid;
            gap: 14px;
        }

        .schema-card {
            border: 1px solid #d6e2f0;
            border-radius: 14px;
            background: var(--panel);
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .schema-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid #d6e2f0;
            background: var(--panel-soft);
        }

        .schema-card-title {
            margin: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 24px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #2d3d59;
        }

        .mono {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.9em;
            color: #334861;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .type-value {
            display: block;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            line-height: 1.4;
        }

        .enum-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .enum-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 8px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.06em;
            font-weight: 800;
            border: 1px solid transparent;
        }

        .badge-table {
            background: var(--table-bg);
            color: var(--table-accent);
            border-color: #86efac;
        }

        .badge-view {
            background: var(--view-bg);
            color: var(--view-accent);
            border-color: #fdba74;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .chip {
            font-size: 12px;
            color: var(--ink-soft);
            background: #eff5fc;
            border: 1px solid #d6e2f0;
            border-radius: 999px;
            padding: 4px 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .toggle {
            border: 1px solid #c8d4e4;
            background: #fff;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            cursor: pointer;
        }

        .toggle:hover {
            background: #f8fafc;
        }

        .schema-card-body {
            display: block;
        }

        .schema-card.collapsed .schema-card-body {
            display: none;
        }

        .grid-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .schema-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 860px;
        }

        .schema-table th,
        .schema-table td {
            border: 1px solid var(--line);
            padding: 9px 10px;
            text-align: left;
            vertical-align: top;
        }

        .schema-table th {
            background: #edf3fa;
            color: #3d5170;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 700;
        }

        .schema-table tbody tr:nth-child(even) {
            background: #fbfdff;
        }

        .schema-table td {
            font-size: 14px;
            color: #415673;
            font-weight: 500;
        }

        .null-pill {
            display: inline-flex;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .null-yes {
            background: var(--yes-bg);
            color: var(--yes-ink);
            border-color: #86efac;
        }

        .null-no {
            background: var(--no-bg);
            color: var(--no-ink);
            border-color: #fca5a5;
        }

        .fk-pill {
            display: inline-flex;
            max-width: 100%;
            padding: 3px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #d7e1ee;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-cell {
            color: #94a3b8;
        }

        .empty-state {
            border: 1px dashed #c8d4e4;
            border-radius: 14px;
            padding: 26px;
            text-align: center;
            color: var(--muted);
            font-weight: 700;
            background: #fff;
            display: none;
        }

        .footnote {
            margin-top: 14px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        @media (max-width: 980px) {
            .shell {
                padding: 0 14px;
                margin-top: 16px;
            }

            .hero {
                padding: 16px;
                border-radius: 14px;
            }

            .hero-top {
                flex-direction: column;
            }

            .title {
                font-size: 32px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .schema-card-title {
                font-size: 18px;
            }

            .card-actions .chip {
                display: none;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                background: #fff;
            }

            .toolbar,
            .actions,
            .toggle {
                display: none !important;
            }

            .hero {
                box-shadow: none;
                border-radius: 0;
                border: 1px solid #b8c6d8;
                margin-bottom: 10px;
            }

            .title {
                font-size: 30px;
            }

            .schema-card {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
                margin-bottom: 8px;
            }

            .schema-card-body {
                display: block !important;
            }

            .schema-table {
                min-width: 0;
            }

            .schema-table th,
            .schema-table td {
                padding: 6px 7px;
                font-size: 11px;
            }

            .badge {
                font-size: 9px;
            }

            .stats {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
@php
    $entryCollection = collect($schemaEntries);
    $tableCount = $entryCollection->where('table_type', 'BASE TABLE')->count();
    $viewCount = $entryCollection->where('table_type', 'VIEW')->count();
    $columnCount = $entryCollection->sum(fn (array $entry) => count($entry['columns'] ?? []));
    $foreignCount = $entryCollection->sum(fn (array $entry) => count($entry['foreignKeys'] ?? []));
@endphp
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div>
                <h1 class="title">Database Schema</h1>
                <div class="meta">
                    <span>Database: <strong>{{ $database }}</strong></span>
                    <span>|</span>
                    <span>Connection: <strong>{{ $connection }}</strong></span>
                    <span>|</span>
                    <span>Generated: <strong>{{ now()->format('Y-m-d H:i:s') }}</strong></span>
                </div>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('db-explorer.index') }}">Back to Explorer</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <p class="stat-label">Total Objects</p>
                <div class="stat-value">{{ $entryCollection->count() }}</div>
            </div>
            <div class="stat">
                <p class="stat-label">Tables</p>
                <div class="stat-value">{{ $tableCount }}</div>
            </div>
            <div class="stat">
                <p class="stat-label">Views</p>
                <div class="stat-value">{{ $viewCount }}</div>
            </div>
            <div class="stat">
                <p class="stat-label">Columns / FKs</p>
                <div class="stat-value">{{ $columnCount }} / {{ $foreignCount }}</div>
            </div>
        </div>
    </section>

    <section class="toolbar" id="schema-toolbar">
        <div class="search">
            <input id="schema-search" class="search-input" type="text" placeholder="Search by object, column, or type...">
        </div>
        <select id="schema-type-filter" class="select">
            <option value="all">All Objects</option>
            <option value="BASE TABLE">Tables Only</option>
            <option value="VIEW">Views Only</option>
        </select>
        <button id="expand-all" class="btn" type="button">Expand All</button>
        <button id="collapse-all" class="btn" type="button">Collapse All</button>
        <div class="visible-count"><span id="visible-count">{{ $entryCollection->count() }}</span> shown</div>
    </section>

    <section id="schema-cards" class="cards">
        @foreach($schemaEntries as $entry)
            @php
                $columns = $entry['columns'] ?? [];
                $foreignKeys = $entry['foreignKeys'] ?? [];
                $foreignMap = collect($foreignKeys)->keyBy('column_name');
                $isView = ($entry['table_type'] ?? '') === 'VIEW';
                $searchBlob = strtolower(trim(
                    ($entry['display_name'] ?? '') . ' ' .
                    collect($columns)->pluck('column_name')->implode(' ') . ' ' .
                    collect($columns)->pluck('column_type')->implode(' ') . ' ' .
                    collect($foreignKeys)->pluck('referenced_table_name')->implode(' ')
                ));
            @endphp
            <article
                class="schema-card"
                data-schema-card="1"
                data-type="{{ $entry['table_type'] }}"
                data-search="{{ $searchBlob }}"
            >
                <header class="schema-card-header">
                    <h2 class="schema-card-title">
                        {{ $isView ? 'View' : 'Table' }}:
                        <span class="mono">`{{ $entry['display_name'] }}`</span>
                        <span class="badge {{ $isView ? 'badge-view' : 'badge-table' }}">{{ $entry['table_type'] }}</span>
                    </h2>
                    <div class="card-actions">
                        <span class="chip">{{ count($columns) }} columns</span>
                        <span class="chip">{{ count($foreignKeys) }} foreign keys</span>
                        <button class="toggle" type="button" data-schema-toggle="1">Collapse</button>
                    </div>
                </header>

                <div class="schema-card-body">
                    <div class="grid-scroll">
                        <table class="schema-table">
                            <thead>
                                <tr>
                                    <th style="width: 26%">Field</th>
                                    <th style="width: 26%">Type</th>
                                    <th style="width: 14%">NULL</th>
                                    <th style="width: 34%">Foreign</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($columns as $column)
                                    @php
                                        $fk = $foreignMap->get($column->column_name);
                                        $nullable = ($column->is_nullable ?? 'NO') === 'YES';
                                        $columnType = $column->column_type ?? $column->data_type ?? '';
                                    @endphp
                                    <tr>
                                        <td><span class="mono">{{ $column->column_name }}</span></td>
                                        <td>
                                            @if(($column->data_type ?? '') === 'enum')
                                                @php $enumValues = $column->enum_values ?? []; @endphp
                                                @if(!empty($enumValues))
                                                    <div class="enum-list">
                                                        @foreach($enumValues as $enumValue)
                                                            <span class="enum-badge">{{ $enumValue }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="type-value">{{ $columnType }}</span>
                                                @endif
                                            @else
                                                <span class="type-value">{{ $columnType }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="null-pill {{ $nullable ? 'null-yes' : 'null-no' }}">
                                                {{ $nullable ? 'YES' : 'NO' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($fk)
                                                <span class="fk-pill">
                                                    {{ $fk->referenced_table_display_name ?? $fk->referenced_table_name }} -> {{ $fk->referenced_column_name }}
                                                </span>
                                            @else
                                                <span class="empty-cell">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="empty-cell">No columns found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <div id="schema-empty" class="empty-state">No objects match your current filter.</div>

    <p class="footnote">This report includes all MySQL base tables and views visible in the active schema.</p>
</div>

<script>
    (function () {
        const cards = Array.from(document.querySelectorAll('[data-schema-card="1"]'));
        const searchInput = document.getElementById('schema-search');
        const typeFilter = document.getElementById('schema-type-filter');
        const visibleCount = document.getElementById('visible-count');
        const emptyState = document.getElementById('schema-empty');
        const expandAllBtn = document.getElementById('expand-all');
        const collapseAllBtn = document.getElementById('collapse-all');

        function setCollapsed(card, collapsed) {
            card.classList.toggle('collapsed', collapsed);
            const toggle = card.querySelector('[data-schema-toggle="1"]');
            if (toggle) {
                toggle.textContent = collapsed ? 'Expand' : 'Collapse';
            }
        }

        function applyFilters() {
            const query = (searchInput.value || '').trim().toLowerCase();
            const selectedType = typeFilter.value;
            let shown = 0;

            cards.forEach((card) => {
                const haystack = (card.getAttribute('data-search') || '').toLowerCase();
                const type = card.getAttribute('data-type') || '';
                const textMatch = query === '' || haystack.includes(query);
                const typeMatch = selectedType === 'all' || type === selectedType;
                const visible = textMatch && typeMatch;
                card.style.display = visible ? '' : 'none';
                if (visible) {
                    shown += 1;
                }
            });

            visibleCount.textContent = String(shown);
            emptyState.style.display = shown === 0 ? 'block' : 'none';
        }

        cards.forEach((card) => {
            const toggle = card.querySelector('[data-schema-toggle="1"]');
            if (!toggle) {
                return;
            }
            toggle.addEventListener('click', function () {
                setCollapsed(card, !card.classList.contains('collapsed'));
            });
        });

        expandAllBtn.addEventListener('click', function () {
            cards.forEach((card) => setCollapsed(card, false));
        });

        collapseAllBtn.addEventListener('click', function () {
            cards.forEach((card) => setCollapsed(card, true));
        });

        searchInput.addEventListener('input', applyFilters);
        typeFilter.addEventListener('change', applyFilters);

        applyFilters();
    })();
</script>
</body>
</html>
