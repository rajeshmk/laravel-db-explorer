<script setup>
import { inject, ref, computed, watch } from 'vue';

const state = inject('state');
const showDetail = ref(false);
const selectedRow = ref(null);
const search = ref('');
const showFormModal = ref(false);
const formMode = ref('create');
const formRecordId = ref(null);
const formData = ref({});
const formBusy = ref(false);
const formError = ref('');
const formFieldErrors = ref({});

const navigate = inject('navigate');
const fetchTableData = inject('fetchTableData');
const fetchRecordData = inject('fetchRecordData');
const navigateToRecord = inject('navigateToRecord');
const navigateToTableSchema = inject('navigateToTableSchema');
const performSearch = inject('performSearch');
const setTableTab = inject('setTableTab', () => {});
const updatePresentationType = inject('updatePresentationType');
const createRecord = inject('createRecord');
const updateRecord = inject('updateRecord');
const deleteRecord = inject('deleteRecord');

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

const isWritable = computed(() => !!state.value.writeEnabled);

const editableColumns = computed(() =>
    (state.value.columns || []).filter((column) => {
        const extra = (column.extra || '').toLowerCase();
        return !extra.includes('auto_increment');
    })
);

const getPresentationTypeForColumn = (column) => {
    const configured = state.value.presentationTypes?.[column.column_name];
    if (configured) return configured;
    if ((column.data_type || '').toLowerCase() === 'enum') return 'select';
    return 'text';
};

const getPresentationOptionsForColumn = (column) => {
    const map = state.value.presentationTypeOptionsByColumn || {};
    if (Object.prototype.hasOwnProperty.call(map, column.column_name)) {
        const options = map[column.column_name];
        return Array.isArray(options) ? options : [];
    }
    return state.value.presentationTypeOptions || [];
};

const getFieldOptionsForColumn = (column) => {
    return state.value.fieldOptions?.[column.column_name] || [];
};

const mapInputType = (presentationType) => {
    if (presentationType === 'number') return 'number';
    if (presentationType === 'date') return 'date';
    if (presentationType === 'time') return 'time';
    if (presentationType === 'datetime') return 'datetime-local';
    return 'text';
};

const toDatetimeLocal = (value) => {
    if (!value) return '';
    if (typeof value !== 'string') return value;
    return value.replace(' ', 'T').slice(0, 16);
};

const fromDatetimeLocal = (value) => {
    if (!value) return value;
    if (typeof value !== 'string') return value;
    return value.replace('T', ' ');
};

const toTimeInput = (value) => {
    if (!value || typeof value !== 'string') return '';
    return value.length >= 5 ? value.slice(0, 5) : value;
};

const fromTimeInput = (value) => {
    if (!value || typeof value !== 'string') return value;
    return value.length === 5 ? `${value}:00` : value;
};

const initializeForm = (row = null) => {
    const payload = {};
    editableColumns.value.forEach((column) => {
        const colName = column.column_name;
        const presentationType = getPresentationTypeForColumn(column);
        const rawValue = row ? row[colName] : null;

        if (presentationType === 'boolean') {
            payload[colName] = rawValue === 1 || rawValue === '1' || rawValue === true ? 'yes' : (rawValue === null ? '' : 'no');
            return;
        }

        if (presentationType === 'datetime') {
            payload[colName] = toDatetimeLocal(rawValue);
            return;
        }

        if (presentationType === 'time') {
            payload[colName] = toTimeInput(rawValue);
            return;
        }

        payload[colName] = rawValue ?? '';
    });
    formData.value = payload;
};

const openCreateModal = () => {
    formMode.value = 'create';
    formRecordId.value = null;
    formError.value = '';
    formFieldErrors.value = {};
    initializeForm(null);
    showFormModal.value = true;
};

const openEditModal = (row) => {
    formMode.value = 'edit';
    formRecordId.value = getPrimaryKeyValue(row);
    formError.value = '';
    formFieldErrors.value = {};
    initializeForm(row);
    showFormModal.value = true;
};

const closeFormModal = () => {
    if (formBusy.value) return;
    showFormModal.value = false;
    formError.value = '';
    formFieldErrors.value = {};
};

const transformFormPayload = () => {
    const payload = {};
    editableColumns.value.forEach((column) => {
        const colName = column.column_name;
        const presentationType = getPresentationTypeForColumn(column);
        const value = formData.value[colName];

        if (presentationType === 'boolean') {
            payload[colName] = value === 'yes' ? 1 : (value === 'no' ? 0 : null);
            return;
        }

        if (presentationType === 'datetime') {
            payload[colName] = fromDatetimeLocal(value);
            return;
        }

        if (presentationType === 'time') {
            payload[colName] = fromTimeInput(value);
            return;
        }

        payload[colName] = value;
    });
    return payload;
};

const submitForm = async () => {
    if (!state.value.currentTable) return;
    formBusy.value = true;
    formError.value = '';
    formFieldErrors.value = {};

    try {
        const payload = transformFormPayload();
        if (formMode.value === 'create') {
            await createRecord(state.value.currentTable, payload);
        } else {
            await updateRecord(state.value.currentTable, formRecordId.value, payload);
        }
        showFormModal.value = false;
    } catch (error) {
        formError.value = error?.message || 'Failed to save record';
        formFieldErrors.value = error?.fieldErrors || {};
    } finally {
        formBusy.value = false;
    }
};

const getFieldError = (columnName) => {
    return formFieldErrors.value?.[columnName] || '';
};

const removeRecord = async (row) => {
    if (!state.value.currentTable) return;
    const recordId = getPrimaryKeyValue(row);
    if (recordId === undefined || recordId === null) return;
    if (!window.confirm(`Delete record ${recordId}? This cannot be undone.`)) return;

    try {
        await deleteRecord(state.value.currentTable, recordId);
    } catch (error) {
        console.error(error);
        alert(error?.message || 'Failed to delete record');
    }
};

const onPresentationTypeChange = async (columnName, nextType) => {
    if (!state.value.currentTable || !nextType) return;
    try {
        await updatePresentationType(state.value.currentTable, columnName, nextType);
    } catch (error) {
        console.error(error);
        alert(error?.message || 'Failed to save presentation type');
    }
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
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <button v-if="isWritable" @click="openCreateModal" class="dbx-btn dbx-btn-primary">
                            + Create Record
                        </button>
                    </div>
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

                <div class="dbx-surface dbx-panel overflow-visible">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="dbx-table min-w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-4 py-4 text-left">Actions</th>
                                    <th v-for="column in state.columns" :key="column.column_name" 
                                        class="px-6 py-4 text-left">
                                        <button class="dbx-sort" @click="changeSort(column.column_name)">
                                            <span>{{ column.column_name }}</span>
                                            <span v-if="state.sort === column.column_name" class="dbx-muted">
                                                {{ state.direction === 'asc' ? '▲' : '▼' }}
                                            </span>
                                        </button>
                                        <div v-if="getPresentationOptionsForColumn(column).length > 0" class="mt-2">
                                            <select
                                                :value="getPresentationTypeForColumn(column)"
                                                @change="onPresentationTypeChange(column.column_name, $event.target.value)"
                                                class="dbx-presentation-select"
                                                :disabled="!isWritable"
                                            >
                                                <option v-for="opt in getPresentationOptionsForColumn(column)" :key="opt.value" :value="opt.value">
                                                    {{ opt.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, idx) in state.data" :key="idx" 
                                    class="dbx-row">
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="dbx-row-actions">
                                            <button
                                                class="dbx-icon-action"
                                                @click="openDetail(row)"
                                                aria-label="View record details"
                                                data-tooltip="View record details"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWritable"
                                                class="dbx-icon-action"
                                                @click="openEditModal(row)"
                                                aria-label="Edit this record"
                                                data-tooltip="Edit this record"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2M5 19h14M7 19l1.2-4.2a2 2 0 01.5-.82L15.5 7.2a2 2 0 012.83 0l.47.47a2 2 0 010 2.83l-6.78 6.78a2 2 0 01-.82.5L7 19z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWritable"
                                                class="dbx-icon-action dbx-icon-action--danger"
                                                @click="removeRecord(row)"
                                                aria-label="Delete this record"
                                                data-tooltip="Delete this record"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-8 0l.8 11.2A2 2 0 009.8 20h4.4a2 2 0 001.99-1.8L17 7" />
                                                </svg>
                                            </button>
                                        </div>
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

        <!-- Create/Edit Modal -->
        <div v-if="showFormModal" class="fixed inset-0 z-[120] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeFormModal"></div>
            <div class="relative w-full max-w-3xl dbx-surface dbx-panel max-h-[85vh] overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="dbx-title">{{ formMode === 'create' ? 'Create Record' : 'Edit Record' }}</div>
                    <button class="dbx-icon-btn" @click="closeFormModal">x</button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar max-h-[65vh]">
                    <div v-if="formError" class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ formError }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="column in editableColumns" :key="`form-${column.column_name}`" class="space-y-2">
                            <label class="text-sm font-medium">{{ column.column_name }}</label>

                            <template v-if="getPresentationTypeForColumn(column) === 'boolean'">
                                <div class="flex items-center gap-3">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="radio" :name="`bool-${column.column_name}`" value="yes" v-model="formData[column.column_name]">
                                        <span>Yes</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="radio" :name="`bool-${column.column_name}`" value="no" v-model="formData[column.column_name]">
                                        <span>No</span>
                                    </label>
                                </div>
                            </template>

                            <template v-else-if="['select', 'foreign-select'].includes(getPresentationTypeForColumn(column))">
                                <select
                                    v-model="formData[column.column_name]"
                                    :class="['dbx-input', getFieldError(column.column_name) ? 'dbx-input--error' : '']"
                                >
                                    <option value="">Select...</option>
                                    <option v-for="opt in getFieldOptionsForColumn(column)" :key="`${column.column_name}-${opt.value}`" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </template>

                            <template v-else-if="getPresentationTypeForColumn(column) === 'textarea'">
                                <textarea
                                    v-model="formData[column.column_name]"
                                    rows="3"
                                    :class="['dbx-input', getFieldError(column.column_name) ? 'dbx-input--error' : '']"
                                ></textarea>
                            </template>

                            <template v-else>
                                <input
                                    v-model="formData[column.column_name]"
                                    :type="mapInputType(getPresentationTypeForColumn(column))"
                                    :class="['dbx-input', getFieldError(column.column_name) ? 'dbx-input--error' : '']"
                                >
                            </template>
                            <p v-if="getFieldError(column.column_name)" class="text-xs text-red-600">
                                {{ getFieldError(column.column_name) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button class="dbx-btn" @click="closeFormModal" :disabled="formBusy">Cancel</button>
                    <button class="dbx-btn dbx-btn-primary" @click="submitForm" :disabled="formBusy">
                        {{ formBusy ? 'Saving...' : (formMode === 'create' ? 'Create' : 'Update') }}
                    </button>
                </div>
            </div>
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

.dbx-presentation-select {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 6px 8px;
  background: #fff;
  color: #334155;
  font-size: 12px;
  font-weight: 600;
}

.dbx-presentation-select:disabled {
  opacity: 0.55;
}

.dbx-row-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]::before,
.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]::after {
  left: 8px;
  transform: translateX(0) translateY(4px);
}

.dbx-icon-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid #d7ddea;
  background: #ffffff;
  color: #4f46e5;
  transition: all 0.18s ease;
}

.dbx-icon-action:hover {
  border-color: #c7d2fe;
  background: #eef2ff;
  color: #4338ca;
}

.dbx-icon-action:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.dbx-icon-action[data-tooltip]::before {
  content: attr(data-tooltip);
  position: absolute;
  left: 50%;
  bottom: calc(100% + 10px);
  transform: translateX(-50%) translateY(4px);
  padding: 6px 9px;
  border-radius: 6px;
  background: #111827;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: opacity 0.14s ease, transform 0.14s ease, visibility 0.14s ease;
  z-index: 30;
}

.dbx-icon-action[data-tooltip]::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: calc(100% + 4px);
  transform: translateX(-50%) translateY(4px);
  width: 8px;
  height: 8px;
  background: #111827;
  rotate: 45deg;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: opacity 0.14s ease, transform 0.14s ease, visibility 0.14s ease;
  z-index: 29;
}

.dbx-icon-action[data-tooltip]:hover::before,
.dbx-icon-action[data-tooltip]:hover::after,
.dbx-icon-action[data-tooltip]:focus-visible::before,
.dbx-icon-action[data-tooltip]:focus-visible::after {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]:hover::before,
.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]:hover::after,
.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]:focus-visible::before,
.dbx-row-actions .dbx-icon-action:first-child[data-tooltip]:focus-visible::after {
  transform: translateX(0) translateY(0);
}

.dbx-icon-action--danger {
  color: #dc2626;
}

.dbx-icon-action--danger:hover {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.dbx-input--error {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.15);
}

.dbx-btn-primary {
  background: var(--dbx-accent) !important;
  border-color: rgba(91, 92, 246, 0.7) !important;
  color: #ffffff !important;
}

.dbx-btn-primary:hover {
  background: #4b4ddd !important;
  color: #ffffff !important;
}
</style>
