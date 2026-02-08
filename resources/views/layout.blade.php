<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Database Explorer</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Compiled Assets -->
    <link rel="stylesheet" href="{{ asset('vendor/db-explorer/db-explorer.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        space: ['Sora', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body, body * { font-size: 14px !important; }
        .font-space, .font-space { font-family: 'Sora', sans-serif; }
        .rounded-3xl,
        .rounded-2xl,
        .rounded-xl,
        .rounded-lg,
        .rounded-md,
        .rounded,
        .rounded-full {
            border-radius: 8px !important;
        }
        .dbx-null {
            color: #5a5f7a;
            font-style: italic;
        }
    </style>
</head>
<body class="h-full bg-gray-50 overflow-hidden font-space">
    <div id="db-explorer-app"></div>

    <script>
        window.__DB_EXPLORER_DATA__ = {
            tables: {!! json_encode($allTables) !!},
            config: {
                per_page: {{ config('db-explorer.per_page') }},
                date_format: '{{ config('db-explorer.date_format') }}',
                datetime_format: '{{ config('db-explorer.datetime_format') }}'
            },
            view: '{{ request()->routeIs('db-explorer.table') || request()->routeIs('db-explorer.record') ? 'table' : 'dashboard' }}',
            currentTable: {!! json_encode($table ?? null) !!}
        };
    </script>
    
    <script src="{{ asset('vendor/db-explorer/db-explorer.iife.js') }}"></script>
</body>
</html>
