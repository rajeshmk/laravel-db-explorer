<script setup>
import { inject, ref, computed, watch } from 'vue';

const state = inject('state');
const showDetail = ref(false);
const selectedRow = ref(null);
const search = ref('');

const navigate = inject('navigate');
const fetchTableData = inject('fetchTableData');
const fetchRecordData = inject('fetchRecordData');
const navigateToRecord = inject('navigateToRecord');
const navigateToTableSchema = inject('navigateToTableSchema');
const performSearch = inject('performSearch');
const setTableTab = inject('setTableTab', () => {});

const activeTab = computed({
    get: () => (state.value.tableTab === 'schema' ? 'schema' : 'data'),
    set: (tab) => setTableTab(tab === 'schema' ? 'schema' : 'records'),
});

let searchTimeout = null;

// Sync search input with state when navigating
watch(() => state.value.searchQuery, (newQuery) => {
    if (newQuery !== search.value) {
        search.value = newQuery;
    }
}, { immediate: true });

// Watch search input and trigger server-side search with debouncing
watch(search, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(() => {
        if (state.value.currentTable) {
            performSearch(state.value.currentTable, newValue);
        }
    }, 600); // 600ms debounce for smoother typing
});

const getForeignKey = (columnName) => {
    return state.value.foreignKeys.find(fk => fk.column_name === columnName);
};

const indexesByName = computed(() => {
    const grouped = {};
    (state.value.indexes || []).forEach(idx => {
        const name = idx.index_name || 'index';
        if (!grouped[name]) grouped[name] = [];
        grouped[name].push(idx);
    });
    return grouped;
});
const getForeignKeyDisplay = (columnName) => {
    return state.value.foreignKeyDisplay ? state.value.foreignKeyDisplay[columnName] : null;
};

const openDetail = (row) => {
    const recordId = getPrimaryKeyValue(row);
    if (recordId !== undefined && recordId !== null && state.value.currentTable) {
        fetchRecordData(
            state.value.currentTable,
            recordId,
            state.value.pagination.current_page,
            state.value.searchQuery,
            state.value.sort,
            state.value.direction
        );
    } else {
        selectedRow.value = row;
        showDetail.value = true;
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= state.value.pagination.last_page) {
        fetchTableData(
            state.value.currentTable,
            page,
            state.value.searchQuery,
            state.value.sort,
            state.value.direction
        );
        // Scroll to top of table
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const changeSort = (columnName) => {
    const currentSort = state.value.sort;
    const currentDir = state.value.direction || 'asc';
    const nextDir = currentSort === columnName && currentDir === 'asc' ? 'desc' : 'asc';

    fetchTableData(state.value.currentTable, 1, state.value.searchQuery, columnName, nextDir);
};

const getRawValue = (value) => {
    if (value === null) return '<span class="dbx-null">NULL</span>';
    if (typeof value === 'string' && value.length > 50) {
        return value.substring(0, 47) + '...';
    }
    return value;
};

const getPrimaryKeyValue = (row) => {
    const primaryKey = state.value.columns.find(col => col.column_key === 'PRI');
    const pkColumn = primaryKey ? primaryKey.column_name : 'id';
    return row[pkColumn];
};

const isEnumColumn = (column) => {
    return (column?.data_type || '').toLowerCase() === 'enum';
};

const getEnumValues = (column) => {
    if (!isEnumColumn(column)) return [];
    return Array.isArray(column?.enum_values) ? column.enum_values : [];
};

const formatColumnType = (column) => {
    if (!column) return '';
    return column.column_type || '';
};

// Watch for selectedRecord from deep link
watch(() => state.value.selectedRecord, (newRecord) => {
    if (newRecord) {
        selectedRow.value = newRecord;
        showDetail.value = true;
        // Clear selectedRecord after opening
        state.value.selectedRecord = null;
    }
}, { immediate: true });

const formatValue = (key, value, type, column) => {
    if (value === null) return '<span class="dbx-null">NULL</span>';
    
    // Boolean handling
    if (type === 'tinyint' && (column.column_type.includes('(1)') || value === 0 || value === 1 || typeof value === 'boolean')) {
        const isTrue = (value === 1 || value === true || value === '1');
        return isTrue 
            ? `<span class="dbx-pill dbx-pill--active">true</span>`
            : `<span class="dbx-pill dbx-pill--idle">false</span>`;
    }

    // Date handling
    if (type === 'date' || type === 'datetime' || type === 'timestamp' || (typeof value === 'string' && value.match(/^\d{4}-\d{2}-\d{2}/))) {
        try {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                const dateStr = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                
                if (type === 'date') {
                    return `<span class="font-normal">${dateStr}</span>`;
                }

                return `<div class="flex items-center space-x-2">
                    <span class="font-normal">${dateStr}</span>
                    <span class="dbx-muted font-normal">${timeStr}</span>
                </div>`;
            }
        } catch (e) {}
    }

    // Long text handling
    if (typeof value === 'string' && value.length > 60) {
        return `<div class="dbx-longtext">${value}</div>`;
    }

    return `<span class="font-normal">${value}</span>`;
};
</script>

<template>
    <div class="relative">
        <div class="space-y-8 pb-16">
            <!-- Header -->
            <div class="dbx-surface dbx-panel px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center space-x-4">
                    <div class="dbx-accent-bg p-2.5 rounded-2xl shadow-lg">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                       <div class="dbx-title">{{ state.currentTable }}</div>
                       <div class="flex items-center space-x-2 mt-2">
                           <span class="dbx-subtitle">Table Explorer</span>
                           <span class="h-1.5 w-1.5 rounded-full bg-cyan-200"></span>
                           <span class="dbx-accent">{{ state.pagination.total || 0 }} records</span>
                       </div>
                    </div>
                </div>
                
                <div class="dbx-tab-group">
                    <button @click="activeTab = 'data'" 
                            :class="activeTab === 'data' ? 'dbx-tab dbx-tab--active' : 'dbx-tab dbx-tab--idle'">
                        Records
                    </button>
                    <button @click="activeTab = 'schema'" 
                            :class="activeTab === 'schema' ? 'dbx-tab dbx-tab--active' : 'dbx-tab dbx-tab--idle'">
                        Structure
                    </button>
                </div>
                </div>
            </div>

            <!-- Data Browser -->
            <div v-if="activeTab === 'data'" class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="flex items-center justify-end">
                    <div class="relative w-full md:w-[420px]">
                        <input v-model="search" type="text" placeholder="Search records..." 
                               class="dbx-input pl-12">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 dbx-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="dbx-surface dbx-panel overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="dbx-table min-w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-4 py-4 text-left">Details</th>
                                    <th v-for="column in state.columns" :key="column.column_name" 
                                        class="px-6 py-4 text-left">
                                        <button class="dbx-sort" @click="changeSort(column.column_name)">
                                            <span>{{ column.column_name }}</span>
                                            <span v-if="state.sort === column.column_name" class="dbx-muted">
                                                {{ state.direction === 'asc' ? '▲' : '▼' }}
                                            </span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, idx) in state.data" :key="idx" 
                                    class="dbx-row">
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <button class="dbx-row-trigger" @click="openDetail(row)">
                                            View
                                        </button>
                                    </td>
                                    <td v-for="column in state.columns" :key="column.column_name" 
                                        class="px-6 py-3.5 whitespace-nowrap"
                                        v-html="getRawValue(row[column.column_name])">
                                    </td>
                                </tr>
                                <tr v-if="!state.data || state.data.length === 0">
                                    <td :colspan="state.columns.length + 1" class="px-6 py-16 text-center">
                                        <span class="dbx-subtitle">No matching records found</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Professional Pagination -->
                <div v-if="state.pagination && state.pagination.last_page > 1" 
                     class="dbx-surface dbx-panel flex flex-col md:flex-row md:items-center md:justify-between gap-4 px-6 py-5 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div class="flex items-center space-x-4">
                        <p>
                            Page <span class="dbx-chip">{{ state.pagination.current_page }}</span> of {{ state.pagination.last_page }}
                        </p>
                        <div class="h-1.5 w-1.5 rounded-full bg-cyan-200"></div>
                        <p class="dbx-muted">
                             Total {{ state.pagination.total }} records
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button @click="changePage(state.pagination.current_page - 1)" 
                                :disabled="state.pagination.current_page === 1"
                                :class="state.pagination.current_page === 1 ? 'opacity-30 cursor-not-allowed' : 'dbx-btn'"
                                class="dbx-btn group">
                            <svg class="h-5 w-5 transform group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <div class="flex items-center space-x-1">
                            <template v-for="page in state.pagination.last_page" :key="page">
                                <button v-if="Math.abs(page - state.pagination.current_page) < 3 || page === 1 || page === state.pagination.last_page"
                                        @click="changePage(page)"
                                        :class="state.pagination.current_page === page ? 'dbx-pill dbx-pill--active' : 'dbx-pill dbx-pill--idle'">
                                    {{ page }}
                                </button>
                                <span v-else-if="Math.abs(page - state.pagination.current_page) === 3" class="dbx-muted px-1">...</span>
                            </template>
                        </div>

                        <button @click="changePage(state.pagination.current_page + 1)" 
                                :disabled="state.pagination.current_page === state.pagination.last_page"
                                :class="state.pagination.current_page === state.pagination.last_page ? 'opacity-30 cursor-not-allowed' : 'dbx-btn'"
                                class="dbx-btn group">
                            <svg class="h-5 w-5 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Structure Tab -->
            <div v-else-if="activeTab === 'schema'" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="dbx-surface dbx-panel overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="dbx-table min-w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-left">Field Name</th>
                                <th class="px-6 py-4 text-left">Type Info</th>
                                <th class="px-6 py-4 text-left">Foreign Key</th>
                                <th class="px-6 py-4 text-center">Attributes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="column in state.columns" :key="column.column_name" class="dbx-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <div v-if="column.column_key === 'PRI'" class="h-2 w-2 rounded-full bg-cyan-500 shadow-sm ring-4 ring-cyan-50"></div>
                                        <span class="font-normal">{{ column.column_name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="isEnumColumn(column)">
                                        <div v-if="getEnumValues(column).length" class="dbx-enum-list">
                                            <span
                                                v-for="(enumValue, enumIndex) in getEnumValues(column)"
                                                :key="`${column.column_name}-enum-${enumIndex}`"
                                                class="dbx-enum-badge"
                                            >
                                                {{ enumValue }}
                                            </span>
                                        </div>
                                        <span v-else class="dbx-type">{{ formatColumnType(column) }}</span>
                                    </template>
                                    <span v-else class="dbx-type">{{ formatColumnType(column) }}</span>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <template v-if="getForeignKey(column.column_name)">
                                        <button @click="navigateToTableSchema(getForeignKey(column.column_name).referenced_table_name)" 
                                                class="inline-flex items-center space-x-2 dbx-accent font-normal transition-all group">
                                            <svg class="h-4 w-4 opacity-70 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.172 13.828a4 4 0 015.656 0l4 4a4 4 0 11-5.656 5.656l-1.102-1.101" />
                                            </svg>
                                            <span class="underline decoration-cyan-200 underline-offset-4">{{ getForeignKey(column.column_name).referenced_table_name }}</span>
                                            <span class="dbx-muted">→</span>
                                            <span class="dbx-muted">{{ getForeignKey(column.column_name).referenced_column_name }}</span>
                                        </button>
                                    </template>
                                    <span v-else class="dbx-muted">None</span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <span v-if="column.column_key === 'PRI'" class="font-normal text-[color:var(--dbx-accent-ink)]">Primary</span>
                                        <span v-if="column.is_nullable === 'YES'" class="dbx-muted">Nullable</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                </div>
                <div class="dbx-surface dbx-panel overflow-hidden mt-4">
                    <div class="px-6 py-3 border-b border-gray-100">Indexes</div>
                    <div v-if="Object.keys(indexesByName).length === 0" class="px-6 py-3 dbx-muted">No indexes found.</div>
                    <div v-else class="divide-y divide-gray-100">
                        <div v-for="(items, name) in indexesByName" :key="name" class="px-6 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span>{{ name }}</span>
                                <span class="dbx-muted">
                                    {{ name === 'PRIMARY' ? 'Primary' : (items[0].non_unique === 0 ? 'Unique' : 'Index') }}
                                </span>
                            </div>
                            <div class="dbx-muted">
                                {{ items.map(i => i.column_name).join(', ') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Offcanvas (Restructured to fix animation glitch) -->
        <div class="fixed inset-0 z-[100] flex justify-end overflow-hidden pointer-events-none">
            <!-- Backdrop -->
            <Transition name="fade">
                <div v-if="showDetail && selectedRow" 
                     class="absolute inset-0 bg-gray-900/60 backdrop-blur-[2px] pointer-events-auto" 
                     @click="showDetail = false"></div>
            </Transition>

            <!-- Panel -->
            <Transition name="slide">
                <div v-if="showDetail && selectedRow" 
                     class="relative w-full md:max-w-3xl lg:max-w-4xl xl:max-w-5xl dbx-surface flex flex-col h-full border-l border-gray-200 pointer-events-auto">
                    <!-- Premium Slim Header -->
                    <div class="px-8 py-3 flex items-center justify-between border-b border-gray-100 bg-white/70">
                         <div class="flex items-center space-x-4">
                            <div class="dbx-accent-bg p-2 rounded-xl shadow-lg">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <div class="dbx-title">{{ state.currentTable }}</div>
                                <div class="dbx-subtitle mt-1">Record Detail View</div>
                            </div>
                        </div>
                        <button @click="showDetail = false" 
                                class="dbx-icon-btn group">
                            <svg class="h-5 w-5 transform group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Professional Compact Two-Column Body -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <div class="divide-y divide-gray-100">
                            <div v-for="column in state.columns" :key="column.column_name" 
                                 class="flex flex-col md:flex-row py-3 px-8 hover:bg-white/70 transition-colors animate-in fade-in slide-in-from-right-4 duration-500"
                                 :style="{ transitionDelay: `${state.columns.indexOf(column) * 10}ms` }">
                                
                                <!-- Field Name Column -->
                                <div class="w-full md:w-1/3 mb-2 md:mb-0">
                                    <div class="flex items-center space-x-2">
                                        <div v-if="column.column_key === 'PRI'" class="h-1.5 w-1.5 rounded-full bg-cyan-500"></div>
                                        <label>{{ column.column_name }}</label>
                                    </div>
                                </div>
                                
                                <!-- Value Column -->
                                <div class="w-full md:w-2/3">
                                    <div class="leading-relaxed break-words font-normal">
                                        <template v-if="getForeignKey(column.column_name) && selectedRow[column.column_name] !== null">
                                            <div class="flex items-center space-x-3">
                                                <div class="font-normal">
                                                    <span v-html="formatValue(column.column_name, selectedRow[column.column_name], column.data_type, column)"></span>
                                                    <span v-if="getForeignKeyDisplay(column.column_name)" class="dbx-muted"> → {{ getForeignKeyDisplay(column.column_name) }}</span>
                                                </div>
                                                <button @click="navigateToRecord(getForeignKey(column.column_name).referenced_table_name, selectedRow[column.column_name])" 
                                                        class="dbx-fk-link inline-flex items-center space-x-2 group">
                                                    <svg class="h-3.5 w-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                    </svg>
                                                    <span>View Record in {{ getForeignKey(column.column_name).referenced_table_name }}</span>
                                                </button>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div v-html="formatValue(column.column_name, selectedRow[column.column_name], column.data_type, column)"></div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-enter-active { transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-leave-active { transition: transform 0.5s cubic-bezier(0.7, 0, 0.84, 0); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }

.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
  border: 2px solid transparent;
  background-clip: padding-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
  border: 2px solid transparent;
  background-clip: padding-box;
}

.animate-in {
    animation-fill-mode: both;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideInFromBottom { from { transform: translateY(1.5rem); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes slideInFromRight { from { transform: translateX(1.5rem); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.fade-in { animation: fadeIn 0.5s ease-out; }
.slide-in-from-bottom-4 { animation: slideInFromBottom 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-in-from-right-4 { animation: slideInFromRight 0.5s cubic-bezier(0.16, 1, 0.3, 1); }

.dbx-enum-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dbx-enum-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #c7d2fe;
  background: #eef2ff;
  color: #3730a3;
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
}
</style>
