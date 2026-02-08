@extends('db-explorer::layout')

@section('content')
<div class="space-y-6 max-w-full overflow-hidden" 
     x-data="explorerData()"
     @keydown.escape.window="showDetail = false">

    <script>
        function explorerData() {
            return {
                activeTab: 'data',
                showDetail: false,
                selectedRow: null,
                dateFormats: {
                    date: '{{ config('db-explorer.date_format') }}',
                    datetime: '{{ config('db-explorer.datetime_format') }}'
                },
                formatValue(key, value, type, column) {
                    if (value === null) return '<span class="dbx-null">NULL</span>';
                    
                    // Boolean handling
                    if (type === 'tinyint' && (column.column_type.includes('(1)') || value === 0 || value === 1 || typeof value === 'boolean')) {
                        const isTrue = (value === 1 || value === true || value === '1');
                        return isTrue 
                            ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-normal bg-emerald-100 text-emerald-700 border border-emerald-200">true</span>`
                            : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-normal bg-rose-100 text-rose-700 border border-rose-200">false</span>`;
                    }

                    // Date handling
                    if (type === 'date' || type === 'datetime' || type === 'timestamp' || (typeof value === 'string' && value.match(/^\d{4}-\d{2}-\d{2}/))) {
                        try {
                            const date = new Date(value);
                            if (!isNaN(date.getTime())) {
                                return date.toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: 'numeric',
                                    hour: (type !== 'date' && value.includes(':')) ? '2-digit' : undefined,
                                    minute: (type !== 'date' && value.includes(':')) ? '2-digit' : undefined
                                });
                            }
                        } catch (e) {}
                    }

                    // Long text handling
                    if (typeof value === 'string' && value.length > 100) {
                        return `<div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-xs leading-relaxed max-h-48 overflow-y-auto">${value}</div>`;
                    }

                    return value;
                }
            }
        }
    </script>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
            <h2 class="text-3xl font-normal text-gray-900 font-space">{{ $physicalTable ?? $table }}</h2>
            @if(($tableType ?? '') === 'VIEW')
                <span class="px-2.5 py-1 rounded-full text-xs font-normal bg-amber-100 text-amber-700 border border-amber-200">View</span>
            @else
                <span class="px-2.5 py-1 rounded-full text-xs font-normal bg-indigo-100 text-indigo-700 border border-indigo-200">Table</span>
            @endif
        </div>
        
        <div class="flex items-center space-x-2 bg-gray-100 p-1 rounded-xl border border-gray-200">
            <button @click="activeTab = 'data'" 
                    :class="activeTab === 'data' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-xs font-normal rounded-lg transition-all duration-200">
                Data Browser
            </button>
            <button @click="activeTab = 'schema'" 
                    :class="activeTab === 'schema' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-xs font-normal rounded-lg transition-all duration-200">
                Table Schema
            </button>
        </div>
    </div>

    <!-- Data Browser Tab -->
    <div x-show="activeTab === 'data'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="bg-white px-4 py-2 rounded-xl border border-gray-200 shadow-sm">
                    <span class="block text-[10px] font-normal text-gray-400">Total Records</span>
                    <span class="text-sm font-normal text-gray-800 font-space">{{ $data->total() }}</span>
                </div>
            </div>

            <form action="{{ url()->current() }}" method="GET" class="relative group">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search rows..." 
                       class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-80 shadow-sm transition-all group-hover:border-indigo-300">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="h-4.5 w-4.5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                @if(request('direction')) <input type="hidden" name="direction" value="{{ request('direction') }}"> @endif
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
            <div class="p-0 overflow-x-auto custom-scrollbar">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/70 sticky top-0 z-10 backdrop-blur-sm">
                        <tr>
                            @foreach($columns as $column)
                                @php
                                    $currentSort = request('sort') === $column->column_name;
                                    $nextDir = $currentSort && request('direction') === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="px-6 py-4 text-left border-r border-gray-50 last:border-r-0">
                                    <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->query(), ['sort' => $column->column_name, 'direction' => $nextDir])) }}" 
                                       class="group flex items-center justify-between text-[10px] font-normal text-gray-500 hover:text-indigo-600 transition-colors">
                                       <span>{{ $column->column_name }}</span>
                                       <span class="flex flex-col -space-y-1 opacity-20 group-hover:opacity-100 transition-opacity {{ $currentSort ? 'opacity-100' : '' }}">
                                            <svg class="h-2 w-2 {{ $currentSort && request('direction') === 'asc' ? 'text-indigo-600' : '' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l-7 7h14l-7-7z"/></svg>
                                            <svg class="h-2 w-2 {{ $currentSort && request('direction') === 'desc' ? 'text-indigo-600' : '' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l7-7H3l7 7z"/></svg>
                                       </span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-50">
                        @forelse($data as $row)
                            <tr class="hover:bg-indigo-50/40 transition-colors cursor-pointer group/row"
                                @click="selectedRow = {{ json_encode($row) }}; showDetail = true">
                                @foreach($columns as $column)
                                    <td class="px-6 py-3.5 whitespace-nowrap text-sm text-gray-600 border-r border-gray-50 last:border-r-0">
                                        @php $val = $row->{$column->column_name}; @endphp
                                        @if($val === null)
                                            <span class="dbx-null text-xs">NULL</span>
                                        @elseif(is_string($val) && strlen($val) > 40)
                                            <span title="{{ $val }}">{{ substr($val, 0, 40) }}...</span>
                                        @else
                                            {{ $val }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="bg-gray-100 p-4 rounded-full">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-gray-500 font-medium">No records found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($data->hasPages())
                <div class="px-6 border-t border-gray-100 bg-gray-50/30">
                    {{ $data->appends(request()->query())->links('db-explorer::pagination') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Table Schema Tab -->
    <div x-show="activeTab === 'schema'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-lg bg-indigo-50">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-normal text-gray-900">Definition for {{ $physicalTable ?? $table }}</h3>
                </div>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-normal bg-indigo-50 text-indigo-600 border border-indigo-100">
                    {{ count($columns) }} Fields
                </span>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-6 py-4 text-left text-[10px] font-normal text-gray-500">Field Name</th>
                            <th class="px-6 py-4 text-left text-[10px] font-normal text-gray-500">Data Type</th>
                            <th class="px-6 py-4 text-center text-[10px] font-normal text-gray-500">Nullable</th>
                            <th class="px-6 py-4 text-center text-[10px] font-normal text-gray-500">Index</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($columns as $column)
                            @php
                                $fk = collect($foreignKeys)->firstWhere('column_name', $column->column_name);
                            @endphp
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-normal text-gray-800">{{ $column->column_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $columnType = $column->column_type;
                                        if (($column->data_type ?? '') === 'enum') {
                                            preg_match_all("/'((?:\\\\'|[^'])*)'/", $columnType, $matches);
                                            if (!empty($matches[1])) {
                                                $values = array_map(fn($v) => preg_replace('/[-_]+/', ' ', $v), $matches[1]);
                                                $columnType = 'enum(' . implode(', ', $values) . ')';
                                            }
                                        }
                                    @endphp
                                    <span class="text-[10px] text-gray-600" title="{{ $column->data_type }}">
                                        {{ $columnType }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($column->is_nullable === 'YES')
                                        <span class="text-emerald-500" title="Nullable">
                                            <svg class="h-5 w-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    @else
                                        <span class="text-gray-200" title="Not Nullable">
                                            <svg class="h-5 w-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center space-y-1">
                                        @if($column->column_key === 'PRI')
                                            <span class="text-[10px] font-normal text-yellow-700">
                                                Primary Key
                                            </span>
                                        @endif

                                        @if($fk)
                                            <a href="{{ route('db-explorer.table', ['table' => $fk->referenced_table_name]) }}" 
                                               class="text-[10px] text-blue-600 hover:text-blue-800 transition-colors">
                                                FK -> {{ $fk->referenced_table_display_name }}.{{ $fk->referenced_column_name }}
                                            </a>
                                        @elseif($column->column_key === 'MUL')
                                            <span class="text-[10px] text-blue-600">
                                                Index
                                            </span>
                                        @endif

                                        @if($column->column_key === 'UNI')
                                            <span class="text-[10px] text-emerald-600">
                                                Unique
                                            </span>
                                        @endif

                                        @if(!$column->column_key && !$fk)
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 text-center">
                <p class="text-[10px] text-gray-400 font-medium italic">Database: {{ DB::getDatabaseName() }} • Physical Table: {{ (DB::getConfig('prefix') ?? '') . $table }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mt-4">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">Indexes</div>
            @php
                $indexesByName = [];
                foreach (($indexes ?? []) as $idx) {
                    $name = $idx->index_name ?? 'index';
                    $indexesByName[$name] = $indexesByName[$name] ?? [];
                    $indexesByName[$name][] = $idx;
                }
            @endphp
            @if(empty($indexesByName))
                <div class="px-6 py-4 text-gray-500">No indexes found.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($indexesByName as $name => $items)
                        <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span>{{ $name }}</span>
                                <span class="text-gray-500">
                                    {{ $name === 'PRIMARY' ? 'Primary' : (($items[0]->non_unique ?? 1) == 0 ? 'Unique' : 'Index') }}
                                </span>
                            </div>
                            <div class="text-gray-500">
                                {{ implode(', ', array_map(fn($i) => $i->column_name, $items)) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <!-- Offcanvas Detail Panel -->
    <div x-show="showDetail" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] overflow-hidden" 
         style="display: none;"
         @keydown.escape.window="showDetail = false">
        
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="showDetail = false"></div>
        
        <div class="absolute inset-y-0 right-0 w-full max-w-xl bg-white shadow-2xl flex flex-col transform transition-transform duration-300"
             x-show="showDetail"
             x-transition:enter="translate-x-full"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="translate-x-0"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            
            <!-- Panel Header -->
            <div class="px-6 py-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="bg-indigo-600 p-2 rounded-lg shadow-sm">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-normal text-gray-900 font-space">Record Details</h3>
                        <p class="text-[10px] font-normal text-gray-400">Table: {{ $physicalTable ?? $table }}</p>
                    </div>
                </div>
                <button @click="showDetail = false" class="p-2 rounded-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Panel Content -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
                <div class="divide-y divide-gray-50">
                    <template x-if="selectedRow">
                        @foreach($columns as $column)
                            <div class="px-6 py-4 hover:bg-gray-50/50 transition-colors">
                                <label class="block text-[10px] font-normal text-gray-400 mb-1.5">{{ $column->column_name }}</label>
                                <div class="text-sm text-gray-800 break-words font-medium" 
                                     x-html="formatValue('{{ $column->column_name }}', selectedRow['{{ $column->column_name }}'], '{{ $column->data_type }}', {{ json_encode($column) }})">
                                </div>
                            </div>
                        @endforeach
                    </template>
                </div>
            </div>

            <!-- Panel Footer -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end">
                <button @click="showDetail = false" class="px-6 py-2 bg-white border border-gray-200 rounded-xl text-sm font-normal text-gray-700 hover:bg-gray-50 shadow-sm transition-all focus:ring-2 focus:ring-indigo-500">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
