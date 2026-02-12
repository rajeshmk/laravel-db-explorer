<script setup>
import { inject, ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

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
const inlineEditRowKey = ref(null);
const inlineFormData = ref({});
const inlineFormBusy = ref(false);
const inlineFormError = ref('');
const foreignSelectState = ref({});
const foreignSearchTimers = {};
const inlineForeignSelectState = ref({});
const inlineForeignSearchTimers = {};

const navigate = inject('navigate');
const fetchTableData = inject('fetchTableData');
const fetchRecordData = inject('fetchRecordData');
const navigateToRecord = inject('navigateToRecord');
const navigateToTableSchema = inject('navigateToTableSchema');
const performSearch = inject('performSearch');
const setTableTab = inject('setTableTab', () => {});
const setGridMode = inject('setGridMode', () => {});
const isDesktopSidebarOpen = inject('isDesktopSidebarOpen', ref(true));
const toggleDesktopSidebar = inject('toggleDesktopSidebar', () => {});
const updatePresentationType = inject('updatePresentationType');
const fetchForeignOptions = inject('fetchForeignOptions');
const createRecord = inject('createRecord');
const updateRecord = inject('updateRecord');
const deleteRecord = inject('deleteRecord');

const activeTab = computed({
    get: () => (state.value.tableTab === 'schema' ? 'schema' : 'data'),
    set: (tab) => setTableTab(tab === 'schema' ? 'schema' : 'records'),
});

const gridMode = computed({
    get: () => (state.value.gridMode === 'editable' ? 'editable' : 'raw'),
    set: (mode) => setGridMode(mode === 'editable' ? 'editable' : 'raw'),
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

const isPrimaryKeyColumn = (column) => {
    const configuredPk = state.value.primaryKeyColumn || null;
    const columnName = String(column?.column_name || '');
    return columnName !== '' && (
        (configuredPk !== null && columnName === String(configuredPk))
        || String(column?.column_key || '') === 'PRI'
    );
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

const formColumns = computed(() =>
    editableColumns.value.filter((column) => {
        if (formMode.value !== 'edit') return true;
        return !isPrimaryKeyColumn(column);
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

const getForeignDisplayText = (columnName, value) => {
    if (value === null || value === undefined || value === '') return null;

    const options = state.value.fieldOptions?.[columnName] || [];
    const match = options.find((option) => String(option?.value) === String(value));
    if (!match) return null;

    const id = String(value);
    const label = String(match?.label ?? '').trim();
    if (label === '') return id;
    if (label === id) return id;
    if (label.startsWith(`${id} - `)) return label;

    return `${id} - ${label}`;
};

const ensureForeignSelectEntry = (columnName) => {
    if (!foreignSelectState.value[columnName]) {
        foreignSelectState.value[columnName] = {
            search: '',
            selectedLabel: '',
            items: [],
            loading: false,
            hasMore: false,
            nextCursor: null,
            open: false,
            openUp: false,
            highlightedIndex: -1,
            menuStyle: {},
        };
    }

    return foreignSelectState.value[columnName];
};

const mergeOptions = (current, incoming) => {
    const map = new Map();
    (current || []).forEach((option) => {
        map.set(String(option?.value), option);
    });
    (incoming || []).forEach((option) => {
        map.set(String(option?.value), option);
    });

    return Array.from(map.values());
};

const getForeignSelectOptions = (column) => {
    const columnName = column.column_name;
    const localItems = ensureForeignSelectEntry(columnName).items;
    if (localItems.length > 0) {
        return localItems;
    }

    return getFieldOptionsForColumn(column);
};

const getForeignHighlightIndex = (columnName) => {
    return ensureForeignSelectEntry(columnName).highlightedIndex ?? -1;
};

const getForeignMenuStyle = (columnName) => {
    return ensureForeignSelectEntry(columnName).menuStyle || {};
};

const makeInlineForeignSelectKey = (row, column) => {
    return `inline:${String(getPrimaryKeyValue(row) ?? '')}:${column.column_name}`;
};

const ensureInlineForeignSelectEntry = (key) => {
    if (!inlineForeignSelectState.value[key]) {
        inlineForeignSelectState.value[key] = {
            search: '',
            selectedLabel: '',
            items: [],
            loading: false,
            hasMore: false,
            nextCursor: null,
            open: false,
            openUp: false,
            highlightedIndex: -1,
            menuStyle: {},
        };
    }

    return inlineForeignSelectState.value[key];
};

const getInlineForeignMenuStyle = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);

    return ensureInlineForeignSelectEntry(key).menuStyle || {};
};

const findForeignComboboxElement = (columnName) => {
    const nodes = document.querySelectorAll('.dbx-foreign-combobox[data-column]');
    for (const node of nodes) {
        if (!(node instanceof HTMLElement)) continue;
        if (node.getAttribute('data-column') === String(columnName)) {
            return node;
        }
    }

    return null;
};

const findInlineForeignComboboxElement = (key) => {
    const nodes = document.querySelectorAll('.dbx-inline-foreign-combobox[data-inline-column]');
    for (const node of nodes) {
        if (!(node instanceof HTMLElement)) continue;
        if (node.getAttribute('data-inline-column') === String(key)) {
            return node;
        }
    }

    return null;
};

const setForeignHighlightIndex = (column, index) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    const options = getForeignSelectOptions(column);
    if (!options.length) {
        entry.highlightedIndex = -1;
        return;
    }

    const bounded = Math.max(0, Math.min(index, options.length - 1));
    entry.highlightedIndex = bounded;
    scrollHighlightedOptionIntoView(columnName, bounded);
};

const scrollHighlightedOptionIntoView = (columnName, index) => {
    nextTick(() => {
        const menu = document.querySelector(`.dbx-foreign-menu[data-column="${columnName}"]`);
        const option = document.querySelector(`.dbx-foreign-menu[data-column="${columnName}"] .dbx-foreign-option[data-index="${index}"]`);
        if (!(menu instanceof HTMLElement) || !(option instanceof HTMLElement)) {
            return;
        }

        const menuTop = menu.scrollTop;
        const menuBottom = menuTop + menu.clientHeight;
        const optionTop = option.offsetTop;
        const optionBottom = optionTop + option.offsetHeight;

        if (optionTop < menuTop) {
            menu.scrollTop = optionTop;
            return;
        }

        if (optionBottom > menuBottom) {
            menu.scrollTop = optionBottom - menu.clientHeight;
        }
    });
};

const updateForeignDropdownPlacementByName = (columnName) => {
    const entry = ensureForeignSelectEntry(columnName);
    const trigger = findForeignComboboxElement(columnName);
    if (!(trigger instanceof HTMLElement)) {
        entry.menuStyle = {
            maxHeight: '220px',
            zIndex: '260',
        };
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const formBody = trigger.closest('.dbx-form-body');
    const boundaryRect = formBody instanceof HTMLElement
        ? formBody.getBoundingClientRect()
        : null;
    const viewportHeight = window.innerHeight;
    const margin = 8;
    const preferredHeight = 260;
    const minHeight = 120;
    const spaceBelow = boundaryRect
        ? boundaryRect.bottom - rect.bottom - margin
        : viewportHeight - rect.bottom - margin;
    const spaceAbove = boundaryRect
        ? rect.top - boundaryRect.top - margin
        : rect.top - margin;

    let openUp = false;
    if (spaceBelow < minHeight && spaceAbove > spaceBelow) {
        openUp = true;
    } else if (spaceAbove > spaceBelow && spaceAbove >= minHeight) {
        openUp = true;
    }

    const availableSpace = openUp ? spaceAbove : spaceBelow;
    const fallbackSpace = Math.max(spaceAbove, spaceBelow);
    const maxHeight = Math.max(minHeight, Math.min(preferredHeight, Math.max(availableSpace, fallbackSpace)));

    entry.openUp = openUp;
    entry.menuStyle = {
        maxHeight: `${maxHeight}px`,
        zIndex: '260',
    };
};

const refreshOpenForeignDropdowns = () => {
    Object.entries(foreignSelectState.value).forEach(([columnName, entry]) => {
        if (entry?.open) {
            updateForeignDropdownPlacementByName(columnName);
        }
    });

    Object.entries(inlineForeignSelectState.value).forEach(([key, entry]) => {
        if (entry?.open) {
            updateInlineForeignDropdownPlacementByKey(key);
        }
    });
};

const syncForeignSearchFromSelection = (column) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    const selectedValue = formData.value[columnName];
    if (selectedValue === null || selectedValue === undefined || selectedValue === '') {
        entry.selectedLabel = '';
        entry.search = '';
        return;
    }

    const options = mergeOptions(getFieldOptionsForColumn(column), entry.items);
    const selected = options.find((option) => String(option?.value) === String(selectedValue));
    const label = selected ? String(selected.label ?? selected.value ?? '') : String(selectedValue);
    entry.selectedLabel = label;
    entry.search = label;
};

const loadForeignOptions = async (column, reset = false) => {
    if (!state.value.currentTable || !fetchForeignOptions) return;

    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    if (entry.loading) return;

    entry.loading = true;

    try {
        const response = await fetchForeignOptions(
            state.value.currentTable,
            columnName,
            entry.search || '',
            reset ? null : entry.nextCursor,
            100
        );

        const incoming = Array.isArray(response?.items) ? response.items : [];
        entry.items = reset ? incoming : mergeOptions(entry.items, incoming);
        entry.hasMore = !!response?.has_more;
        entry.nextCursor = response?.next_cursor || null;

        const selectedValue = formData.value[columnName];
        if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '') {
            const exists = entry.items.some((option) => String(option.value) === String(selectedValue));
            if (!exists) {
                entry.items = mergeOptions(
                    [{ value: selectedValue, label: String(selectedValue) }],
                    entry.items
                );
            }
        }

        const options = getForeignSelectOptions(column);
        if (!options.length) {
            entry.highlightedIndex = -1;
        } else if (entry.highlightedIndex < 0 || entry.highlightedIndex >= options.length) {
            entry.highlightedIndex = 0;
        }
    } catch (error) {
        console.error(error);
    } finally {
        entry.loading = false;
    }
};

const initForeignSelectOptions = async () => {
    foreignSelectState.value = {};
    const foreignColumns = editableColumns.value.filter(
        (column) => getPresentationTypeForColumn(column) === 'foreign-select'
    );

    await Promise.all(foreignColumns.map(async (column) => {
        const entry = ensureForeignSelectEntry(column.column_name);
        entry.search = '';
        entry.selectedLabel = '';
        entry.items = [];
        entry.hasMore = false;
        entry.nextCursor = null;
        entry.open = false;
        entry.openUp = false;
        entry.highlightedIndex = -1;
        await loadForeignOptions(column, true);
        syncForeignSearchFromSelection(column);
    }));
};

const openForeignDropdown = (column) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    Object.entries(foreignSelectState.value).forEach(([name, otherEntry]) => {
        if (name !== String(columnName) && otherEntry?.open) {
            otherEntry.open = false;
            otherEntry.openUp = false;
        }
    });
    entry.open = true;
    try {
        updateForeignDropdownPlacementByName(columnName);
    } catch (error) {
        console.error(error);
        entry.menuStyle = {
            maxHeight: '220px',
            zIndex: '260',
        };
    }
    const options = getForeignSelectOptions(column);
    if (options.length) {
        const selectedValue = formData.value[columnName];
        const selectedIndex = options.findIndex((option) => String(option?.value) === String(selectedValue));
        entry.highlightedIndex = selectedIndex >= 0 ? selectedIndex : 0;
        scrollHighlightedOptionIntoView(columnName, entry.highlightedIndex);
    }
    if (entry.items.length === 0) {
        loadForeignOptions(column, true);
    }
};

const toggleForeignDropdown = (column) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    if (entry.open) {
        entry.open = false;
        entry.openUp = false;
        return;
    }
    openForeignDropdown(column);
};

const closeForeignDropdowns = () => {
    Object.values(foreignSelectState.value).forEach((entry) => {
        entry.open = false;
        entry.openUp = false;
    });
};

const onDocumentClick = (event) => {
    const target = event?.target;
    if (!(target instanceof Element)) {
        closeForeignDropdowns();
        closeInlineForeignDropdowns();
        return;
    }

    if (
        target.closest('.dbx-foreign-combobox') ||
        target.closest('.dbx-foreign-menu') ||
        target.closest('.dbx-inline-foreign-combobox') ||
        target.closest('.dbx-inline-foreign-menu')
    ) {
        return;
    }

    closeForeignDropdowns();
    closeInlineForeignDropdowns();
};

const onForeignSearchInput = (column) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    entry.open = true;
    if (entry.selectedLabel !== '' && entry.search !== entry.selectedLabel) {
        entry.selectedLabel = '';
        formData.value[columnName] = '';
    }
    entry.highlightedIndex = 0;
    if (foreignSearchTimers[columnName]) {
        clearTimeout(foreignSearchTimers[columnName]);
    }

    foreignSearchTimers[columnName] = setTimeout(() => {
        loadForeignOptions(column, true);
    }, 350);
};

const loadMoreForeignOptions = (column) => {
    loadForeignOptions(column, false);
};

const selectForeignOption = (column, option) => {
    const columnName = column.column_name;
    const entry = ensureForeignSelectEntry(columnName);
    formData.value[columnName] = option?.value ?? '';
    const label = String(option?.label ?? option?.value ?? '');
    entry.selectedLabel = label;
    entry.search = label;
    entry.open = false;
    entry.openUp = false;
    entry.highlightedIndex = -1;
};

const onForeignKeydown = (column, event) => {
    const entry = ensureForeignSelectEntry(column.column_name);
    const options = getForeignSelectOptions(column);

    if (event.key === 'Escape') {
        event.preventDefault();
        entry.open = false;
        return;
    }

    if (event.key === 'Tab') {
        entry.open = false;
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!entry.open) {
            openForeignDropdown(column);
            return;
        }
        if (!options.length) return;
        const current = entry.highlightedIndex < 0 ? -1 : entry.highlightedIndex;
        setForeignHighlightIndex(column, current + 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!entry.open) {
            openForeignDropdown(column);
            return;
        }
        if (!options.length) return;
        const current = entry.highlightedIndex < 0 ? 0 : entry.highlightedIndex;
        setForeignHighlightIndex(column, current - 1);
        return;
    }

    if (event.key === 'Enter') {
        if (!entry.open) return;
        event.preventDefault();
        const activeIndex = entry.highlightedIndex;
        if (activeIndex >= 0 && activeIndex < options.length) {
            selectForeignOption(column, options[activeIndex]);
        }
    }
};

const getInlineForeignSelectOptions = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    const fallback = getInlineForeignOptions(column);

    return entry.items.length > 0 ? entry.items : fallback;
};

const setInlineForeignHighlightIndex = (row, column, index) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    const options = getInlineForeignSelectOptions(row, column);
    if (!options.length) {
        entry.highlightedIndex = -1;
        return;
    }

    const bounded = Math.max(0, Math.min(index, options.length - 1));
    entry.highlightedIndex = bounded;
    nextTick(() => {
        const menu = document.querySelector(`.dbx-inline-foreign-menu[data-inline-column="${key}"]`);
        const option = document.querySelector(`.dbx-inline-foreign-menu[data-inline-column="${key}"] .dbx-foreign-option[data-index="${bounded}"]`);
        if (!(menu instanceof HTMLElement) || !(option instanceof HTMLElement)) {
            return;
        }

        const menuTop = menu.scrollTop;
        const menuBottom = menuTop + menu.clientHeight;
        const optionTop = option.offsetTop;
        const optionBottom = optionTop + option.offsetHeight;

        if (optionTop < menuTop) {
            menu.scrollTop = optionTop;
            return;
        }

        if (optionBottom > menuBottom) {
            menu.scrollTop = optionBottom - menu.clientHeight;
        }
    });
};

const updateInlineForeignDropdownPlacementByKey = (key) => {
    const entry = ensureInlineForeignSelectEntry(key);
    const trigger = findInlineForeignComboboxElement(key);
    if (!(trigger instanceof HTMLElement)) {
        entry.menuStyle = {
            maxHeight: '220px',
            zIndex: '260',
        };
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const margin = 8;
    const preferredHeight = 260;
    const minHeight = 120;
    const spaceBelow = viewportHeight - rect.bottom - margin;
    const spaceAbove = rect.top - margin;

    let openUp = false;
    if (spaceBelow < minHeight && spaceAbove > spaceBelow) {
        openUp = true;
    } else if (spaceAbove > spaceBelow && spaceAbove >= minHeight) {
        openUp = true;
    }

    const availableSpace = openUp ? spaceAbove : spaceBelow;
    const fallbackSpace = Math.max(spaceAbove, spaceBelow);
    const maxHeight = Math.max(minHeight, Math.min(preferredHeight, Math.max(availableSpace, fallbackSpace)));

    entry.openUp = openUp;
    entry.menuStyle = {
        maxHeight: `${maxHeight}px`,
        zIndex: '260',
    };
};

const closeInlineForeignDropdowns = () => {
    Object.values(inlineForeignSelectState.value).forEach((entry) => {
        entry.open = false;
        entry.openUp = false;
    });
};

const syncInlineForeignSearchFromSelection = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    const columnName = column.column_name;
    const selectedValue = inlineFormData.value[columnName];
    if (selectedValue === null || selectedValue === undefined || selectedValue === '') {
        entry.selectedLabel = '';
        entry.search = '';
        return;
    }

    const options = mergeOptions(getInlineForeignOptions(column), entry.items);
    const selected = options.find((option) => String(option?.value) === String(selectedValue));
    const label = selected ? String(optionOrValue(selected)) : String(selectedValue);
    entry.selectedLabel = label;
    entry.search = label;
};

const optionOrValue = (option) => String(option?.label ?? option?.value ?? '');

const loadInlineForeignOptions = async (row, column, reset = false) => {
    if (!state.value.currentTable || !fetchForeignOptions) return;

    const columnName = column.column_name;
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    if (entry.loading) return;

    entry.loading = true;
    try {
        const response = await fetchForeignOptions(
            state.value.currentTable,
            columnName,
            entry.search || '',
            reset ? null : entry.nextCursor,
            100
        );

        const incoming = Array.isArray(response?.items) ? response.items : [];
        entry.items = reset ? incoming : mergeOptions(entry.items, incoming);
        entry.hasMore = !!response?.has_more;
        entry.nextCursor = response?.next_cursor || null;

        const selectedValue = inlineFormData.value[columnName];
        if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '') {
            const exists = entry.items.some((option) => String(option.value) === String(selectedValue));
            if (!exists) {
                entry.items = mergeOptions([{ value: selectedValue, label: String(selectedValue) }], entry.items);
            }
        }

        const options = getInlineForeignSelectOptions(row, column);
        if (!options.length) {
            entry.highlightedIndex = -1;
        } else if (entry.highlightedIndex < 0 || entry.highlightedIndex >= options.length) {
            entry.highlightedIndex = 0;
        }
    } catch (error) {
        console.error(error);
    } finally {
        entry.loading = false;
    }
};

const openInlineForeignDropdown = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    closeForeignDropdowns();
    Object.entries(inlineForeignSelectState.value).forEach(([name, otherEntry]) => {
        if (name !== key && otherEntry?.open) {
            otherEntry.open = false;
            otherEntry.openUp = false;
        }
    });
    entry.open = true;
    updateInlineForeignDropdownPlacementByKey(key);

    const options = getInlineForeignSelectOptions(row, column);
    if (options.length) {
        const selectedValue = inlineFormData.value[column.column_name];
        const selectedIndex = options.findIndex((option) => String(option?.value) === String(selectedValue));
        entry.highlightedIndex = selectedIndex >= 0 ? selectedIndex : 0;
        setInlineForeignHighlightIndex(row, column, entry.highlightedIndex);
    }

    if (entry.items.length === 0) {
        loadInlineForeignOptions(row, column, true);
    }
};

const toggleInlineForeignDropdown = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    if (entry.open) {
        entry.open = false;
        entry.openUp = false;
        return;
    }
    openInlineForeignDropdown(row, column);
};

const onInlineForeignSearchInput = (row, column) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    entry.open = true;
    if (entry.selectedLabel !== '' && entry.search !== entry.selectedLabel) {
        entry.selectedLabel = '';
        inlineFormData.value[column.column_name] = '';
    }
    entry.highlightedIndex = 0;

    if (inlineForeignSearchTimers[key]) {
        clearTimeout(inlineForeignSearchTimers[key]);
    }

    inlineForeignSearchTimers[key] = setTimeout(() => {
        loadInlineForeignOptions(row, column, true);
    }, 350);
};

const loadMoreInlineForeignOptions = (row, column) => {
    loadInlineForeignOptions(row, column, false);
};

const selectInlineForeignOption = (row, column, option) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    inlineFormData.value[column.column_name] = option?.value ?? '';
    const label = optionOrValue(option);
    entry.selectedLabel = label;
    entry.search = label;
    entry.open = false;
    entry.openUp = false;
    entry.highlightedIndex = -1;
};

const onInlineForeignKeydown = (row, column, event) => {
    const key = makeInlineForeignSelectKey(row, column);
    const entry = ensureInlineForeignSelectEntry(key);
    const options = getInlineForeignSelectOptions(row, column);

    if (event.key === 'Escape') {
        event.preventDefault();
        entry.open = false;
        return;
    }

    if (event.key === 'Tab') {
        entry.open = false;
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!entry.open) {
            openInlineForeignDropdown(row, column);
            return;
        }
        if (!options.length) return;
        const current = entry.highlightedIndex < 0 ? -1 : entry.highlightedIndex;
        setInlineForeignHighlightIndex(row, column, current + 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!entry.open) {
            openInlineForeignDropdown(row, column);
            return;
        }
        if (!options.length) return;
        const current = entry.highlightedIndex < 0 ? 0 : entry.highlightedIndex;
        setInlineForeignHighlightIndex(row, column, current - 1);
        return;
    }

    if (event.key === 'Enter') {
        if (!entry.open) return;
        event.preventDefault();
        const activeIndex = entry.highlightedIndex;
        if (activeIndex >= 0 && activeIndex < options.length) {
            selectInlineForeignOption(row, column, options[activeIndex]);
        }
    }
};

const mapInputType = (presentationType) => {
    if (presentationType === 'number') return 'number';
    if (presentationType === 'color') return 'color';
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

const initializePayloadFromRow = (row = null) => {
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

    return payload;
};

const transformRecordPayload = (source) => {
    const payload = {};
    editableColumns.value.forEach((column) => {
        const colName = column.column_name;
        const presentationType = getPresentationTypeForColumn(column);
        const value = source[colName];

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

const initializeForm = (row = null) => {
    formData.value = initializePayloadFromRow(row);
};

const isInlineEditingRow = (row) => {
    return inlineEditRowKey.value !== null && String(inlineEditRowKey.value) === String(getPrimaryKeyValue(row));
};

const getInlineValue = (columnName) => {
    return inlineFormData.value[columnName];
};

const getInlineForeignOptions = (column) => {
    const current = getInlineValue(column.column_name);
    const options = getFieldOptionsForColumn(column) || [];
    if (current === null || current === undefined || current === '') {
        return options;
    }

    const hasCurrent = options.some((opt) => String(opt?.value) === String(current));
    if (hasCurrent) {
        return options;
    }

    return [{ value: current, label: String(current) }, ...options];
};

const startInlineEdit = (row) => {
    inlineEditRowKey.value = getPrimaryKeyValue(row);
    inlineFormData.value = initializePayloadFromRow(row);
    inlineFormBusy.value = false;
    inlineFormError.value = '';

    inlineForeignSelectState.value = {};
    editableColumns.value
        .filter((column) => getPresentationTypeForColumn(column) === 'foreign-select')
        .forEach((column) => {
            const key = makeInlineForeignSelectKey(row, column);
            const entry = ensureInlineForeignSelectEntry(key);
            entry.search = '';
            entry.selectedLabel = '';
            entry.items = [];
            entry.hasMore = false;
            entry.nextCursor = null;
            entry.open = false;
            entry.openUp = false;
            entry.highlightedIndex = -1;
            loadInlineForeignOptions(row, column, true).then(() => {
                syncInlineForeignSearchFromSelection(row, column);
            });
        });
};

const cancelInlineEdit = () => {
    inlineEditRowKey.value = null;
    inlineFormData.value = {};
    inlineFormBusy.value = false;
    inlineFormError.value = '';
    inlineForeignSelectState.value = {};
    Object.values(inlineForeignSearchTimers).forEach((timer) => clearTimeout(timer));
    Object.keys(inlineForeignSearchTimers).forEach((key) => {
        delete inlineForeignSearchTimers[key];
    });
};

const saveInlineEdit = async (row) => {
    if (!state.value.currentTable || inlineFormBusy.value) return;

    const recordId = getPrimaryKeyValue(row);
    if (recordId === undefined || recordId === null) return;

    inlineFormBusy.value = true;
    inlineFormError.value = '';

    try {
        const payload = transformRecordPayload(inlineFormData.value);
        await updateRecord(state.value.currentTable, recordId, payload);
        cancelInlineEdit();
    } catch (error) {
        inlineFormError.value = error?.message || 'Failed to save inline changes';
    } finally {
        inlineFormBusy.value = false;
    }
};

const openCreateModal = () => {
    formMode.value = 'create';
    formRecordId.value = null;
    formError.value = '';
    formFieldErrors.value = {};
    initializeForm(null);
    initForeignSelectOptions();
    showFormModal.value = true;
};

const openEditModal = (row) => {
    formMode.value = 'edit';
    formRecordId.value = getPrimaryKeyValue(row);
    formError.value = '';
    formFieldErrors.value = {};
    initializeForm(row);
    initForeignSelectOptions();
    showFormModal.value = true;
};

const closeFormModal = () => {
    if (formBusy.value) return;
    showFormModal.value = false;
    formError.value = '';
    formFieldErrors.value = {};
    foreignSelectState.value = {};
};

onMounted(() => {
    window.addEventListener('resize', refreshOpenForeignDropdowns);
    window.addEventListener('scroll', refreshOpenForeignDropdowns, true);
    document.addEventListener('click', onDocumentClick, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', refreshOpenForeignDropdowns);
    window.removeEventListener('scroll', refreshOpenForeignDropdowns, true);
    document.removeEventListener('click', onDocumentClick, true);
    Object.values(foreignSearchTimers).forEach((timer) => clearTimeout(timer));
    Object.values(inlineForeignSearchTimers).forEach((timer) => clearTimeout(timer));
});

const transformFormPayload = () => {
    return transformRecordPayload(formData.value);
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

watch(() => state.value.data, () => {
    cancelInlineEdit();
});

watch(isWritable, (canWrite) => {
    if (!canWrite) {
        gridMode.value = 'raw';
        cancelInlineEdit();
    }
}, { immediate: true });

const formatValue = (key, value, type, column, compact = false) => {
    if (value === null) return '<span class="dbx-null">NULL</span>';
    const truncateText = (input, limit = 50) => {
        const text = String(input ?? '');
        if (text.length <= limit) return text;
        return `${text.slice(0, Math.max(0, limit - 3))}...`;
    };

    const presentationType = getPresentationTypeForColumn(column);
    const normalizeHexColor = (input) => {
        const raw = String(input || '').trim();
        const shortMatch = raw.match(/^#([A-Fa-f0-9]{3})$/);
        if (shortMatch) {
            const [r, g, b] = shortMatch[1].split('');
            return `#${r}${r}${g}${g}${b}${b}`.toUpperCase();
        }

        const longMatch = raw.match(/^#([A-Fa-f0-9]{6})$/);
        if (longMatch) {
            return `#${longMatch[1].toUpperCase()}`;
        }

        return null;
    };
    const invertHexColor = (hexColor) => {
        const hex = hexColor.replace('#', '');
        const r = 255 - parseInt(hex.slice(0, 2), 16);
        const g = 255 - parseInt(hex.slice(2, 4), 16);
        const b = 255 - parseInt(hex.slice(4, 6), 16);
        const toHex = (num) => num.toString(16).padStart(2, '0').toUpperCase();

        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    };
    const hexToRgb = (hexColor) => {
        const hex = hexColor.replace('#', '');
        return {
            r: parseInt(hex.slice(0, 2), 16),
            g: parseInt(hex.slice(2, 4), 16),
            b: parseInt(hex.slice(4, 6), 16),
        };
    };
    const rgbToHex = ({ r, g, b }) => {
        const toHex = (num) => Math.max(0, Math.min(255, Math.round(num))).toString(16).padStart(2, '0').toUpperCase();
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    };
    const relativeLuminance = ({ r, g, b }) => {
        const normalize = (channel) => {
            const c = channel / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        };
        const rl = normalize(r);
        const gl = normalize(g);
        const bl = normalize(b);
        return 0.2126 * rl + 0.7152 * gl + 0.0722 * bl;
    };
    const contrastRatio = (a, b) => {
        const la = relativeLuminance(a);
        const lb = relativeLuminance(b);
        const lighter = Math.max(la, lb);
        const darker = Math.min(la, lb);
        return (lighter + 0.05) / (darker + 0.05);
    };
    const getReadableColorText = (bgHex) => {
        const bg = hexToRgb(bgHex);
        const inv = hexToRgb(invertHexColor(bgHex));
        // "Almost inverse": blend 85% inverse + 15% black/white anchor for smoother tone.
        const anchor = relativeLuminance(bg) > 0.5 ? { r: 0, g: 0, b: 0 } : { r: 255, g: 255, b: 255 };
        const almostInv = {
            r: inv.r * 0.85 + anchor.r * 0.15,
            g: inv.g * 0.85 + anchor.g * 0.15,
            b: inv.b * 0.85 + anchor.b * 0.15,
        };

        const candidate = rgbToHex(almostInv);
        const black = '#111827';
        const white = '#F8FAFC';
        const candidateContrast = contrastRatio(bg, hexToRgb(candidate));
        const blackContrast = contrastRatio(bg, hexToRgb(black));
        const whiteContrast = contrastRatio(bg, hexToRgb(white));

        if (candidateContrast >= 3.5) {
            return candidate;
        }

        return blackContrast >= whiteContrast ? black : white;
    };

    if (presentationType === 'color') {
        const normalizedColor = normalizeHexColor(value);
        if (normalizedColor) {
            const textColor = getReadableColorText(normalizedColor);
            return `<span class="dbx-color-chip" style="background:${normalizedColor};color:${textColor};border-color:${textColor}33">${normalizedColor}</span>`;
        }
    }

    // Foreign-key display: show "id - label" when options are available.
    if (getForeignKey(key)) {
        const foreignLabel = getForeignDisplayText(key, value);
        if (foreignLabel !== null) {
            const text = compact ? truncateText(foreignLabel) : foreignLabel;
            return `<span class="font-normal">${text}</span>`;
        }
    }
    
    // Boolean handling
    if (type === 'tinyint' && (column.column_type.includes('(1)') || value === 0 || value === 1 || typeof value === 'boolean')) {
        const isTrue = (value === 1 || value === true || value === '1');
        return isTrue 
            ? `<span class="dbx-bool-chip dbx-bool-chip--true">true</span>`
            : `<span class="dbx-bool-chip dbx-bool-chip--false">false</span>`;
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

    // Compact mode for editable grid list view: match readonly truncation behavior.
    if (compact && typeof value === 'string') {
        return `<span class="font-normal">${truncateText(value)}</span>`;
    }

    // Long text handling (non-compact contexts such as record detail).
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
                    <button
                        class="dbx-icon-btn hidden md:inline-flex"
                        @click="toggleDesktopSidebar"
                        :aria-label="isDesktopSidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                    >
                        <svg v-if="isDesktopSidebarOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
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
                    <div class="flex items-center gap-3">
                        <button v-if="isWritable" @click="openCreateModal" class="dbx-btn dbx-btn-primary">
                            + Create Record
                        </button>
                        <div class="dbx-tab-group">
                            <button
                                @click="gridMode = 'raw'"
                                :class="gridMode === 'raw' ? 'dbx-tab dbx-tab--active' : 'dbx-tab dbx-tab--idle'"
                            >
                                Raw
                            </button>
                            <button
                                v-if="isWritable"
                                @click="gridMode = 'editable'"
                                :class="gridMode === 'editable' ? 'dbx-tab dbx-tab--active' : 'dbx-tab dbx-tab--idle'"
                            >
                                Editable
                            </button>
                        </div>
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
                    <div v-if="gridMode === 'editable' && inlineFormError" class="mx-4 mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ inlineFormError }}
                    </div>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table v-if="gridMode === 'raw'" class="dbx-table min-w-full border-collapse">
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
                                <tr v-for="(row, idx) in state.data" :key="getPrimaryKeyValue(row) ?? idx" 
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
                        <table v-else class="dbx-table dbx-editable-grid min-w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-4 py-4 text-left">Actions</th>
                                    <th v-for="column in state.columns" :key="column.column_name" class="px-6 py-4 text-left dbx-editable-col">
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
                                <tr v-for="(row, idx) in state.data" :key="getPrimaryKeyValue(row) ?? idx" class="dbx-row">
                                    <td class="px-4 py-3.5 whitespace-nowrap align-top">
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
                                                v-if="isWritable && !isInlineEditingRow(row)"
                                                class="dbx-icon-action"
                                                @click="startInlineEdit(row)"
                                                aria-label="Inline edit this record"
                                                data-tooltip="Inline edit this record"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2M5 19h14M7 19l1.2-4.2a2 2 0 01.5-.82L15.5 7.2a2 2 0 012.83 0l.47.47a2 2 0 010 2.83l-6.78 6.78a2 2 0 01-.82.5L7 19z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWritable && isInlineEditingRow(row)"
                                                class="dbx-icon-action"
                                                @click="saveInlineEdit(row)"
                                                :disabled="inlineFormBusy"
                                                aria-label="Save inline changes"
                                                data-tooltip="Save changes"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWritable && isInlineEditingRow(row)"
                                                class="dbx-icon-action"
                                                @click="cancelInlineEdit"
                                                :disabled="inlineFormBusy"
                                                aria-label="Cancel inline changes"
                                                data-tooltip="Cancel changes"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWritable && !isInlineEditingRow(row)"
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
                                    <td v-for="column in state.columns" :key="column.column_name" class="px-6 py-3.5 align-top dbx-editable-col">
                                        <template v-if="isWritable && isInlineEditingRow(row) && !isPrimaryKeyColumn(column)">
                                            <template v-if="getPresentationTypeForColumn(column) === 'boolean'">
                                                <select v-model="inlineFormData[column.column_name]" class="dbx-input">
                                                    <option value="">Select...</option>
                                                    <option value="yes">Yes</option>
                                                    <option value="no">No</option>
                                                </select>
                                            </template>
                                            <template v-else-if="getPresentationTypeForColumn(column) === 'foreign-select'">
                                                <div
                                                    class="dbx-foreign-combobox dbx-inline-foreign-combobox"
                                                    :data-inline-column="makeInlineForeignSelectKey(row, column)"
                                                    @click.stop
                                                >
                                                    <div class="relative">
                                                        <input
                                                            v-model="ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).search"
                                                            type="text"
                                                            placeholder="Search and select..."
                                                            class="dbx-input pr-10"
                                                            @focus="openInlineForeignDropdown(row, column)"
                                                            @click.stop="openInlineForeignDropdown(row, column)"
                                                            @input="onInlineForeignSearchInput(row, column)"
                                                            @keydown="onInlineForeignKeydown(row, column, $event)"
                                                        >
                                                        <button
                                                            type="button"
                                                            class="dbx-foreign-toggle"
                                                            @click.stop="toggleInlineForeignDropdown(row, column)"
                                                            aria-label="Open options"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div
                                                        v-if="ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).open"
                                                        class="dbx-foreign-menu dbx-inline-foreign-menu"
                                                        :class="{ 'dbx-foreign-menu--up': ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).openUp }"
                                                        :data-inline-column="makeInlineForeignSelectKey(row, column)"
                                                        :style="getInlineForeignMenuStyle(row, column)"
                                                        @click.stop
                                                    >
                                                        <button
                                                            v-for="(opt, idx) in getInlineForeignSelectOptions(row, column)"
                                                            :key="`${column.column_name}-inline-fk-${opt.value}`"
                                                            type="button"
                                                            class="dbx-foreign-option"
                                                            :class="{ 'dbx-foreign-option--active': ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).highlightedIndex === idx }"
                                                            :data-index="idx"
                                                            @mouseenter="setInlineForeignHighlightIndex(row, column, idx)"
                                                            @click="selectInlineForeignOption(row, column, opt)"
                                                        >
                                                            {{ opt.label }}
                                                        </button>
                                                        <div v-if="ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).loading" class="dbx-foreign-state">
                                                            Loading...
                                                        </div>
                                                        <div
                                                            v-else-if="getInlineForeignSelectOptions(row, column).length === 0"
                                                            class="dbx-foreign-state"
                                                        >
                                                            No matches found
                                                        </div>
                                                        <button
                                                            v-if="ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).hasMore"
                                                            type="button"
                                                            class="dbx-foreign-more"
                                                            :disabled="ensureInlineForeignSelectEntry(makeInlineForeignSelectKey(row, column)).loading"
                                                            @click="loadMoreInlineForeignOptions(row, column)"
                                                        >
                                                            Load more
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else-if="getPresentationTypeForColumn(column) === 'select'">
                                                <select v-model="inlineFormData[column.column_name]" class="dbx-input">
                                                    <option value="">Select...</option>
                                                    <option v-for="opt in getFieldOptionsForColumn(column)" :key="`${column.column_name}-inline-${opt.value}`" :value="opt.value">
                                                        {{ opt.label }}
                                                    </option>
                                                </select>
                                            </template>
                                            <template v-else-if="getPresentationTypeForColumn(column) === 'textarea'">
                                                <textarea v-model="inlineFormData[column.column_name]" rows="2" class="dbx-input"></textarea>
                                            </template>
                                            <template v-else-if="getPresentationTypeForColumn(column) === 'color'">
                                                <input
                                                    v-model="inlineFormData[column.column_name]"
                                                    type="color"
                                                    class="dbx-input dbx-input--color-inline"
                                                >
                                            </template>
                                            <template v-else>
                                                <input
                                                    v-model="inlineFormData[column.column_name]"
                                                    :type="mapInputType(getPresentationTypeForColumn(column))"
                                                    class="dbx-input"
                                                >
                                            </template>
                                        </template>
                                        <template v-else>
                                            <div v-html="formatValue(column.column_name, row[column.column_name], column.data_type, column, true)"></div>
                                        </template>
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
            <div class="relative w-full max-w-3xl min-h-[540px] max-h-[85vh] dbx-surface dbx-panel overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="dbx-title">{{ formMode === 'create' ? 'Create Record' : 'Edit Record' }}</div>
                    <button class="dbx-icon-btn" @click="closeFormModal">x</button>
                </div>

                <div class="dbx-form-body p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 relative z-10">
                    <div v-if="formError" class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ formError }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="column in formColumns" :key="`form-${column.column_name}`" class="space-y-2">
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

                            <template v-else-if="getPresentationTypeForColumn(column) === 'foreign-select'">
                                <div class="dbx-foreign-combobox" :data-column="column.column_name" @click.stop>
                                    <div class="relative">
                                        <input
                                            v-model="ensureForeignSelectEntry(column.column_name).search"
                                            type="text"
                                            placeholder="Search and select..."
                                            :class="['dbx-input pr-10', getFieldError(column.column_name) ? 'dbx-input--error' : '']"
                                            @focus="openForeignDropdown(column)"
                                            @click.stop="openForeignDropdown(column)"
                                            @input="onForeignSearchInput(column)"
                                            @keydown="onForeignKeydown(column, $event)"
                                        >
                                        <button
                                            type="button"
                                            class="dbx-foreign-toggle"
                                            @click.stop="toggleForeignDropdown(column)"
                                            aria-label="Open options"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div
                                        v-if="ensureForeignSelectEntry(column.column_name).open"
                                        class="dbx-foreign-menu"
                                        :class="{ 'dbx-foreign-menu--up': ensureForeignSelectEntry(column.column_name).openUp }"
                                        :data-column="column.column_name"
                                        :style="getForeignMenuStyle(column.column_name)"
                                        @click.stop
                                    >
                                        <button
                                            v-for="(opt, idx) in getForeignSelectOptions(column)"
                                            :key="`${column.column_name}-${opt.value}`"
                                            type="button"
                                            class="dbx-foreign-option"
                                            :class="{ 'dbx-foreign-option--active': getForeignHighlightIndex(column.column_name) === idx }"
                                            :data-index="idx"
                                            @mouseenter="setForeignHighlightIndex(column, idx)"
                                            @click="selectForeignOption(column, opt)"
                                        >
                                            {{ opt.label }}
                                        </button>
                                        <div v-if="ensureForeignSelectEntry(column.column_name).loading" class="dbx-foreign-state">
                                            Loading...
                                        </div>
                                        <div
                                            v-else-if="getForeignSelectOptions(column).length === 0"
                                            class="dbx-foreign-state"
                                        >
                                            No matches found
                                        </div>
                                        <button
                                            v-if="ensureForeignSelectEntry(column.column_name).hasMore"
                                            type="button"
                                            class="dbx-foreign-more"
                                            :disabled="ensureForeignSelectEntry(column.column_name).loading"
                                            @click="loadMoreForeignOptions(column)"
                                        >
                                            Load more
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="getPresentationTypeForColumn(column) === 'select'">
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

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3 relative z-0">
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

.dbx-foreign-combobox {
  position: relative;
}

.dbx-editable-grid .dbx-editable-col {
  min-width: 170px;
}

.dbx-editable-grid .dbx-editable-col .dbx-input,
.dbx-editable-grid .dbx-editable-col .dbx-foreign-combobox {
  min-width: 150px;
}

.dbx-foreign-toggle {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

.dbx-foreign-menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 6px);
  border: 1px solid #d1d5db;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
  max-height: 240px;
  overflow-y: auto;
}

.dbx-foreign-menu--up {
  top: auto;
  bottom: calc(100% + 6px);
  transform-origin: bottom center;
}

.dbx-foreign-option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  color: #0f172a;
}

.dbx-foreign-option:hover {
  background: #f8fafc;
}

.dbx-foreign-option--active {
  background: #eef2ff;
  color: #3730a3;
}

.dbx-foreign-state {
  padding: 10px 12px;
  color: #64748b;
  font-size: 12px;
}

.dbx-foreign-more {
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  border-top: 1px solid #e2e8f0;
  color: var(--dbx-accent);
  font-weight: 600;
  font-size: 12px;
  background: #f8fafc;
}

.dbx-foreign-more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.dbx-input--error {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.15);
}

.dbx-input--color-inline {
  height: 56px;
  padding: 6px;
}

.dbx-input--color-inline::-webkit-color-swatch-wrapper {
  padding: 0;
}

.dbx-input--color-inline::-webkit-color-swatch {
  border: 0;
  border-radius: 8px;
}

:deep(.dbx-color-chip) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 7px 14px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.03em;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}

:deep(.dbx-bool-chip) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  padding: 7px 12px;
  border-radius: 10px;
  border: 1px solid transparent;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1;
}

:deep(.dbx-bool-chip--true) {
  background: #dcfce7;
  color: #166534;
  border-color: #86efac;
}

:deep(.dbx-bool-chip--false) {
  background: #f3f4f6;
  color: #4b5563;
  border-color: #d1d5db;
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
