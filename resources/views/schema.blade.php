<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Database Schema</title>
    <style>
        :root {
            color-scheme: light;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            color: #111827;
            background: #f9fafb;
            font-size: 14px;
        }
        .schema-container {
            max-width: 1280px;
            margin: 0 auto;
        }
        .schema-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }
        .schema-title {
            margin: 0;
            font-size: 44px;
            line-height: 1.1;
            font-weight: 700;
            color: #111827;
        }
        .schema-meta {
            margin-top: 8px;
            color: #4b5563;
            font-size: 13px;
        }
        .schema-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .schema-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }
        .schema-button:hover {
            background: #f3f4f6;
        }
        .schema-block {
            margin-bottom: 26px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }
        .schema-block-header {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            background: #f3f4f6;
        }
        .schema-block-title {
            margin: 0;
            font-size: 34px;
            font-weight: 700;
            line-height: 1.2;
            color: #111827;
        }
        .schema-badge {
            display: inline-flex;
            margin-left: 8px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            line-height: 1.6;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            vertical-align: middle;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .schema-badge-view {
            background: #ffedd5;
            color: #9a3412;
        }
        .schema-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .schema-table th,
        .schema-table td {
            border: 1px solid #d1d5db;
            text-align: left;
            padding: 8px 10px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .schema-table th {
            background: #f3f4f6;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }
        .schema-table td {
            font-size: 16px;
            line-height: 1.35;
            color: #111827;
        }
        .schema-empty {
            padding: 12px 14px;
            color: #6b7280;
            font-size: 13px;
        }
        .schema-footnote {
            margin-top: 20px;
            color: #6b7280;
            font-size: 12px;
        }
        @media (max-width: 900px) {
            body {
                padding: 14px;
            }
            .schema-title {
                font-size: 28px;
            }
            .schema-block-title {
                font-size: 24px;
            }
            .schema-table th {
                font-size: 16px;
            }
            .schema-table td {
                font-size: 14px;
            }
            .schema-header {
                flex-direction: column;
                align-items: stretch;
            }
            .schema-actions {
                width: 100%;
            }
            .schema-button {
                flex: 1;
            }
        }
        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }
            body {
                background: #fff;
                padding: 0;
            }
            .schema-actions {
                display: none !important;
            }
            .schema-block {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .schema-table th,
            .schema-table td {
                padding: 6px 8px;
            }
            a {
                color: inherit;
                text-decoration: none;
            }
        }
    </style>
</head>
<body>
<div class="schema-container">
    <header class="schema-header">
        <div>
            <h1 class="schema-title">Database Schema</h1>
            <div class="schema-meta">
                Database: <strong>{{ $database }}</strong> |
                Connection: <strong>{{ $connection }}</strong> |
                Generated: <strong>{{ now()->format('Y-m-d H:i:s') }}</strong>
            </div>
        </div>
        <div class="schema-actions">
            <a class="schema-button" href="{{ route('db-explorer.index') }}">Back to Explorer</a>
            <button class="schema-button" type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </header>

    @foreach($schemaEntries as $entry)
        @php
            $foreignMap = collect($entry['foreignKeys'] ?? [])->keyBy('column_name');
            $isView = ($entry['table_type'] ?? '') === 'VIEW';
        @endphp
        <section class="schema-block">
            <div class="schema-block-header">
                <h2 class="schema-block-title">
                    {{ $isView ? 'View' : 'Table' }}: `{{ $entry['display_name'] }}`
                    <span class="schema-badge {{ $isView ? 'schema-badge-view' : '' }}">{{ $entry['table_type'] }}</span>
                </h2>
            </div>

            <table class="schema-table">
                <thead>
                <tr>
                    <th style="width: 30%">Field</th>
                    <th style="width: 30%">Type</th>
                    <th style="width: 20%">NULL</th>
                    <th style="width: 20%">Foreign</th>
                </tr>
                </thead>
                <tbody>
                @forelse($entry['columns'] as $column)
                    @php
                        $fk = $foreignMap->get($column->column_name);
                    @endphp
                    <tr>
                        <td>{{ $column->column_name }}</td>
                        <td>{{ $column->column_type ?? $column->data_type }}</td>
                        <td>{{ ($column->is_nullable ?? 'NO') === 'YES' ? 'YES' : 'NO' }}</td>
                        <td>
                            @if($fk)
                                {{ $fk->referenced_table_display_name ?? $fk->referenced_table_name }}->{{ $fk->referenced_column_name }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="schema-empty">No columns found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endforeach

    <div class="schema-footnote">
        Includes all MySQL base tables and views visible in the current connection schema.
    </div>
</div>
</body>
</html>
