@extends('db-explorer::layout')

@section('content')
<div class="h-full flex flex-col items-center justify-center text-center space-y-8 p-12">
    <div class="bg-indigo-600 p-6 rounded-3xl shadow-xl shadow-indigo-200">
        <svg class="h-16 w-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
        </svg>
    </div>
    
    <div class="max-w-md space-y-4">
        <h2 class="text-4xl font-normal text-gray-900 font-space">Database Explorer</h2>
        <p class="text-gray-500 leading-relaxed">
            Welcome to your database inspection tool. Select a table or view from the sidebar to start exploring its schema and browsing its records.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-2xl mt-8">
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center">
            <span class="text-4xl font-normal text-indigo-600 mb-2">{{ count(array_filter($allTables, fn($t) => ($t->table_type ?? 'BASE TABLE') === 'BASE TABLE')) }}</span>
            <span class="text-sm font-normal text-gray-400">Total Tables</span>
        </div>
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center">
            <span class="text-4xl font-normal text-amber-500 mb-2">{{ count(array_filter($allTables, fn($t) => ($t->table_type ?? '') === 'VIEW')) }}</span>
            <span class="text-sm font-normal text-gray-400">Database Views</span>
        </div>
    </div>
    
    <div class="pt-8 text-[10px] font-normal text-gray-300">
        Connection: {{ config('database.default') }} • Database: {{ DB::getDatabaseName() }}
    </div>
</div>
@endsection
