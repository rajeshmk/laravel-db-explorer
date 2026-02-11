<script setup>
import { ref, onMounted, provide, computed } from 'vue';
import Dashboard from './views/Dashboard.vue';
import Explorer from './views/Explorer.vue';

const loading = ref(true);
const error = ref(null);
let errorTimeout = null;
const isMobileSidebarOpen = ref(false);
const sidebarSearch = ref('');
const state = ref({
    tables: [],
    currentTable: null,
    view: 'dashboard',
    data: [],
    columns: [],
    foreignKeys: [],
    indexes: [],
    pagination: {},
    config: {},
    selectedRecord: null,
    foreignKeyDisplay: {},
    sort: null,
    direction: null,
    searchQuery: ''
});

onMounted(() => {
    // Inject initial data from window if available
    if (window.__DB_EXPLORER_DATA__) {
        state.value = { 
            ...state.value, 
            ...window.__DB_EXPLORER_DATA__ 
        };
    }
    
    // Check the current URL to determine what to load
    const path = window.location.pathname || '';
    const params = new URLSearchParams(window.location.search);
    const page = parseInt(params.get('page') || '1', 10);
    const search = params.get('search') || '';
    const sort = params.get('sort');
    const direction = params.get('direction') || 'asc';

    // Match record URL pattern
    const recordMatch = path.match(/\/db-explorer\/table\/([^/]+)\/record\/([^/]+)/);
    if (recordMatch) {
        const tableFromUrl = recordMatch[1];
        const recordId = recordMatch[2];
        fetchRecordData(tableFromUrl, recordId, page, search, sort, direction);
        return;
    }

    // Match table URL pattern
    const tableMatch = path.match(/\/db-explorer\/table\/([^/]+)/);
    if (tableMatch) {
        const tableFromUrl = tableMatch[1];
        fetchTableData(tableFromUrl, page, search, sort, direction);
        return;
    }

    // Default to dashboard
    loading.value = false;
});

const navigate = (view, table = null) => {
    state.value.view = view;
    if (view === 'table' && table) {
        fetchTableData(table);
    } else {
        state.value.currentTable = null;
        // Update URL without reload if possible
        window.history.pushState({}, '', '/db-explorer');
    }
};

const fetchTableData = async (table, page = 1, searchQuery = '', sort = null, direction = null) => {
    loading.value = true;
    try {
        let url = `/db-explorer/table/${table}?page=${page}`;
        if (searchQuery) {
            url += `&search=${encodeURIComponent(searchQuery)}`;
        }
        if (sort) {
            url += `&sort=${encodeURIComponent(sort)}&direction=${encodeURIComponent(direction || 'asc')}`;
        }
        const response = await fetch(url, {
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        // Check HTTP response status
        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            if (contentType?.includes('application/json')) {
                try {
                    const errData = await response.json();
                    throw new Error(errData.message || `HTTP ${response.status}: ${response.statusText}`);
                } catch (parseErr) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            } else {
                throw new Error(`HTTP ${response.status}: Table not found or invalid`);
            }
        }
        
        const data = await response.json();
        
        state.value.data = data.data;
        state.value.columns = data.columns;
        state.value.foreignKeys = data.foreignKeys || [];
        state.value.indexes = data.indexes || [];
        state.value.pagination = data.pagination;
        state.value.currentTable = table;
        state.value.view = 'table';
        state.value.searchQuery = searchQuery;
        state.value.foreignKeyDisplay = {};
        state.value.sort = sort;
        state.value.direction = sort ? (direction || 'asc') : null;
        
        // Update URL
        const urlParams = new URLSearchParams();
        urlParams.set('page', page);
        if (searchQuery) urlParams.set('search', searchQuery);
        if (sort) {
            urlParams.set('sort', sort);
            urlParams.set('direction', direction || 'asc');
        }
        window.history.pushState({}, '', `/db-explorer/table/${table}?${urlParams.toString()}`);
    } catch (e) {
        const msg = e?.message || 'Failed to load table data';
        setError(msg);
        console.error('Failed to fetch table data', e);
    } finally {
        loading.value = false;
    }
};

const fetchRecordData = async (table, recordId, page = 1, searchQuery = '', sort = null, direction = null) => {
    loading.value = true;
    try {
        let url = `/db-explorer/table/${table}/record/${recordId}?page=${page}`;
        if (searchQuery) {
            url += `&search=${encodeURIComponent(searchQuery)}`;
        }
        if (sort) {
            url += `&sort=${encodeURIComponent(sort)}&direction=${encodeURIComponent(direction || 'asc')}`;
        }
        const response = await fetch(url, {
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        // Check HTTP response status
        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            if (contentType?.includes('application/json')) {
                try {
                    const errData = await response.json();
                    throw new Error(errData.message || `HTTP ${response.status}: ${response.statusText}`);
                } catch (parseErr) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            } else {
                throw new Error(`HTTP ${response.status}: Record not found or invalid`);
            }
        }
        
        const data = await response.json();
        
        state.value.data = data.data;
        state.value.columns = data.columns;
        state.value.foreignKeys = data.foreignKeys || [];
        state.value.indexes = data.indexes || [];
        state.value.pagination = data.pagination;
        state.value.currentTable = table;
        state.value.view = 'table';
        state.value.selectedRecord = data.selectedRecord;
        state.value.foreignKeyDisplay = data.foreignKeyDisplay || {};
        state.value.searchQuery = searchQuery;
        state.value.sort = sort;
        state.value.direction = sort ? (direction || 'asc') : null;
        
        // Update URL
        const urlParams = new URLSearchParams();
        urlParams.set('page', page);
        if (searchQuery) urlParams.set('search', searchQuery);
        if (sort) {
            urlParams.set('sort', sort);
            urlParams.set('direction', direction || 'asc');
        }
        window.history.pushState({}, '', `/db-explorer/table/${table}/record/${recordId}?${urlParams.toString()}`);
    } catch (e) {
        const msg = e?.message || 'Failed to load record data';
        setError(msg);
        console.error('Failed to fetch record data', e);
    } finally {
        loading.value = false;
    }
};

// Error toast helper
const setError = (message) => {
    error.value = message;
    if (errorTimeout) clearTimeout(errorTimeout);
    errorTimeout = setTimeout(() => {
        error.value = null;
    }, 5000);
};

// Simple debounce utility to avoid extra dependency
const debounce = (fn, wait = 500) => {
    let t = null;
    return (...args) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
};

const debouncedFetchTable = debounce((table, q) => fetchTableData(table, 1, q, state.value.sort, state.value.direction), 500);

const performSearch = (table, searchQuery) => {
    debouncedFetchTable(table, searchQuery);
};

const navigateToRecord = (table, recordId) => {
    fetchRecordData(table, recordId);
};

provide('navigate', navigate);
provide('fetchTableData', fetchTableData);
provide('fetchRecordData', fetchRecordData);
provide('navigateToRecord', navigateToRecord);
provide('performSearch', performSearch);
provide('state', state);

const tables = computed(() => {
    const s = sidebarSearch.value.toLowerCase();
    return state.value.tables.filter(t => 
        (t.table_type || 'BASE TABLE') === 'BASE TABLE' && 
        t.display_name.toLowerCase().includes(s)
    );
});

const views = computed(() => {
    const s = sidebarSearch.value.toLowerCase();
    return state.value.tables.filter(t => 
        (t.table_type || '') === 'VIEW' && 
        t.display_name.toLowerCase().includes(s)
    );
});
</script>

<template>
    <div class="dbx-app flex h-screen overflow-hidden">
        <!-- Mobile Backdrop -->
        <div v-if="isMobileSidebarOpen" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm z-30 md:hidden" @click="isMobileSidebarOpen = false"></div>

        <!-- Error Toast -->
        <transition name="fade">
            <div v-if="error" class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">
                <div class="flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9V6a1 1 0 112 0v3a1 1 0 11-2 0zm0 4a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm font-medium">{{ error }}</div>
                </div>
            </div>
        </transition>

        <!-- Sidebar -->
        <aside :class="[
            'dbx-sidebar w-80 flex-shrink-0 flex flex-col z-40 md:static fixed inset-y-0 left-0 transition-transform duration-300 bg-white border-r border-gray-100',
            isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        ]">
            <!-- Header -->
            <div class="px-6 py-8 flex items-center space-x-3 cursor-pointer group transition-all" @click="navigate('dashboard')">
                <div class="dbx-accent-bg p-3 rounded-xl shadow-md group-hover:shadow-lg group-hover:scale-105 transition-all duration-200 flex-shrink-0">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.58 4 8 4s8-1.79 8-4M4 7c0-2.21 3.58-4 8-4s8 1.79 8 4m0 5c0 2.21-3.58 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg font-semibold text-gray-900 leading-tight">Hatchyu</h1>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">DB Explorer</p>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="px-6 pb-6 border-b border-gray-100">
                <div class="relative group">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 group-focus-within:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-model="sidebarSearch" 
                        type="text" 
                        placeholder="Search tables..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-0 focus:border-transparent focus:bg-white transition-all duration-200"
                    >
                    <button 
                        v-if="sidebarSearch" 
                        @click="sidebarSearch = ''"
                        class="absolute inset-y-0 right-0 mr-3 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto custom-scrollbar px-4 py-6 space-y-6">
                <div>
                    <a
                        href="/db-explorer/schema"
                        class="w-full text-left px-3 py-2.5 rounded-lg flex items-center space-x-3 transition-all duration-150 group text-gray-700 hover:bg-gray-50 border border-transparent"
                    >
                        <svg class="h-4 w-4 flex-shrink-0 transition-colors text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                        </svg>
                        <span class="truncate text-sm font-medium">Schema Report</span>
                    </a>
                </div>

                <!-- Tables Section -->
                <div>
                    <div class="px-2 mb-4 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-widest">Tables</h3>
                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                            {{ tables.length }}
                        </span>
                    </div>
                    <div class="space-y-1">
                        <button 
                            v-for="table in tables" 
                            :key="table.table_name"
                            @click="navigate('table', table.table_name)" 
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg flex items-center space-x-3 transition-all duration-150 group',
                                state.currentTable === table.table_name 
                                    ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' 
                                    : 'text-gray-700 hover:bg-gray-50 border border-transparent'
                            ]"
                        >
                            <svg class="h-4 w-4 flex-shrink-0 transition-colors" :class="[state.currentTable === table.table_name ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="truncate text-sm font-medium">{{ table.display_name }}</span>
                        </button>
                    </div>
                    <div v-if="tables.length === 0 && sidebarSearch" class="text-center py-6">
                        <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-xs text-gray-500">No tables found</p>
                    </div>
                </div>

                <!-- Views Section -->
                <div v-if="views.length > 0">
                    <div class="px-2 mb-4 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-widest">Views</h3>
                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                            {{ views.length }}
                        </span>
                    </div>
                    <div class="space-y-1">
                        <button 
                            v-for="table in views" 
                            :key="table.table_name"
                            @click="navigate('table', table.table_name)" 
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg flex items-center space-x-3 transition-all duration-150 group',
                                state.currentTable === table.table_name 
                                    ? 'bg-amber-50 text-amber-700 border border-amber-200' 
                                    : 'text-gray-700 hover:bg-gray-50 border border-transparent'
                            ]"
                        >
                            <svg class="h-4 w-4 flex-shrink-0 transition-colors" :class="[state.currentTable === table.table_name ? 'text-amber-600' : 'text-gray-400 group-hover:text-gray-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span class="truncate text-sm font-medium">{{ table.display_name }}</span>
                        </button>
                    </div>
                </div>
            </nav>

            <!-- Footer Info -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-t-lg">
                <p class="text-xs text-gray-500 text-center leading-relaxed">
                    <span class="font-semibold text-gray-700">{{ tables.length + views.length }}</span> object{{ tables.length + views.length !== 1 ? 's' : '' }} available
                </p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto custom-scrollbar">
             <div class="md:hidden sticky top-0 z-20 px-4 pt-4">
                <div class="dbx-surface dbx-panel px-4 py-3 flex items-center justify-between">
                    <button class="dbx-icon-btn" @click="isMobileSidebarOpen = true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="dbx-subtitle">Database Explorer</div>
                    <div class="w-8"></div>
                </div>
             </div>

             <div v-if="loading" class="h-full flex items-center justify-center">
                 <div class="flex flex-col items-center space-y-4">
                     <div class="w-12 h-12 border-4 border-cyan-500 border-t-transparent rounded-full animate-spin"></div>
                     <p class="dbx-subtitle">Loading Database...</p>
                 </div>
             </div>
             <div v-else class="p-6 md:p-8">
                <Dashboard v-if="state.view === 'dashboard'" />
                <Explorer v-else-if="state.view === 'table'" />
             </div>
        </main>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

:root {
    --dbx-bg: #f6f7fb;
    --dbx-surface: rgba(255, 255, 255, 0.86);
    --dbx-border: rgba(10, 14, 45, 0.1);
    --dbx-text: #0b0f2b;
    --dbx-muted: #5a5f7a;
    --dbx-accent: #5b5cf6;
    --dbx-accent-ink: #2d2f9b;
}

.dbx-app {
    font-family: 'Sora', sans-serif;
    font-size: 14px;
    color: var(--dbx-text);
    line-height: 1.45;
    font-weight: 400;
    background:
        radial-gradient(1200px circle at 15% 10%, rgba(91, 92, 246, 0.16), transparent 60%),
        radial-gradient(900px circle at 85% 20%, rgba(37, 99, 235, 0.14), transparent 55%),
        radial-gradient(1100px circle at 50% 85%, rgba(15, 23, 42, 0.08), transparent 60%),
        var(--dbx-bg);
}

.dbx-app * {
    font-size: inherit !important;
    font-weight: 400 !important;
}

.dbx-surface {
    background: var(--dbx-surface);
    border: 1px solid var(--dbx-border);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(12px);
}

.dbx-sidebar {
    background: rgba(255, 255, 255, 0.75);
    border-right: 1px solid var(--dbx-border);
    backdrop-filter: blur(16px);
}

.dbx-panel {
    border-radius: 8px;
}

.dbx-muted { color: var(--dbx-muted); }
.dbx-accent { color: var(--dbx-accent); }

.dbx-accent-bg {
    background: linear-gradient(135deg, #5b5cf6, #3b82f6);
}

.dbx-title {
    font-weight: 400;
    letter-spacing: 0.015em;
}

.dbx-subtitle {
    font-weight: 400;
    letter-spacing: 0.02em;
    color: var(--dbx-muted);
}

.dbx-section-title {
    font-weight: 400;
    letter-spacing: 0.02em;
    color: var(--dbx-muted);
}

.dbx-hero {
    position: relative;
    overflow: hidden;
}
.dbx-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(91, 92, 246, 0.12), transparent 60%);
    pointer-events: none;
}

.dbx-chip {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid rgba(91, 92, 246, 0.25);
    color: var(--dbx-accent-ink);
    background: rgba(91, 92, 246, 0.08);
    font-weight: 400;
    letter-spacing: 0.02em;
}

.dbx-card {
    background: var(--dbx-surface);
    border: 1px solid var(--dbx-border);
    border-radius: 8px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dbx-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.12);
}

.dbx-kpi {
    font-weight: 400;
    letter-spacing: 0.02em;
    color: var(--dbx-text);
}

.dbx-input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--dbx-border);
    outline: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.02);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.dbx-input:focus {
    border-color: rgba(91, 92, 246, 0.4);
    box-shadow: 0 0 0 4px rgba(91, 92, 246, 0.1);
}

.dbx-tab-group {
    display: inline-flex;
    gap: 6px;
    padding: 6px;
    border-radius: 8px;
    border: 1px solid var(--dbx-border);
    background: rgba(255, 255, 255, 0.7);
}
.dbx-tab {
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 400;
    letter-spacing: 0.02em;
    transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}
.dbx-tab--idle {
    color: var(--dbx-muted);
}
.dbx-tab--active {
    background: white;
    color: var(--dbx-accent-ink);
    box-shadow: 0 10px 20px rgba(45, 47, 155, 0.12);
}

.dbx-table thead {
    background: rgba(255, 255, 255, 0.7);
    border-bottom: 1px solid var(--dbx-border);
}
.dbx-table th {
    font-weight: 400;
    letter-spacing: 0.02em;
    color: var(--dbx-muted);
}
.dbx-table td {
    color: var(--dbx-text);
    font-weight: 400;
}
.dbx-row:hover {
    background: rgba(91, 92, 246, 0.06);
}

.dbx-btn {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid var(--dbx-border);
    background: white;
    color: var(--dbx-text);
    transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}
.dbx-btn:hover {
    background: var(--dbx-accent);
    color: white;
    box-shadow: 0 12px 24px rgba(91, 92, 246, 0.22);
    transform: translateY(-1px);
}

.dbx-pill {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid var(--dbx-border);
    background: rgba(255, 255, 255, 0.8);
    font-weight: 400;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.dbx-type {
    display: inline;
    font-weight: 400;
    color: var(--dbx-text);
    white-space: normal;
    word-break: break-word;
    max-width: 420px;
    line-height: 1.45;
}
.dbx-pill--active {
    background: rgba(91, 92, 246, 0.14);
    border-color: rgba(91, 92, 246, 0.4);
    color: var(--dbx-accent-ink);
}
.dbx-pill--idle {
    color: var(--dbx-muted);
}

.dbx-longtext {
    color: var(--dbx-text);
    line-height: 1.7;
    max-height: 16rem;
    overflow-y: auto;
    white-space: pre-wrap;
}

.dbx-null {
    color: var(--dbx-muted);
    font-style: italic;
}

.dbx-sort {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: inherit;
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
}
.dbx-sort:hover {
    color: var(--dbx-accent-ink);
}

.dbx-row-trigger {
    border: 1px solid var(--dbx-border);
    background: white;
    color: var(--dbx-accent-ink);
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
}
.dbx-row-trigger:hover {
    background: rgba(91, 92, 246, 0.08);
}

.dbx-fk-link {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid rgba(91, 92, 246, 0.35);
    background: rgba(91, 92, 246, 0.08);
    color: var(--dbx-accent-ink);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.dbx-fk-link:hover {
    background: rgba(91, 92, 246, 0.16);
    border-color: rgba(91, 92, 246, 0.5);
}

.dbx-nav-item {
    width: 100%;
    text-align: left;
    padding: 12px 14px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 400;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.dbx-nav-item--idle {
    color: var(--dbx-muted);
    background: transparent;
}
.dbx-nav-item--idle:hover {
    color: var(--dbx-text);
    background: rgba(91, 92, 246, 0.08);
}
.dbx-nav-item--active {
    color: var(--dbx-accent-ink);
    background: rgba(91, 92, 246, 0.12);
}

.dbx-icon-btn {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    border: 1px solid var(--dbx-border);
    background: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dbx-icon-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(45, 47, 155, 0.12);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.12);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 23, 42, 0.18);
}

/* Error Toast Transition */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
