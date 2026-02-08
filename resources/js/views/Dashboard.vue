<script setup>
import { inject, computed } from 'vue';

const state = inject('state');

// Handle both ref and non-ref state (in case state is reactive ref)
const getTables = () => {
    if (!state) return [];
    // If state is a Ref with .value
    if (state.value && Array.isArray(state.value.tables)) {
        return state.value.tables;
    }
    // If state is a plain object
    if (Array.isArray(state.tables)) {
        return state.tables;
    }
    return [];
};

const baseTables = computed(() => {
    const tables = getTables();
    return tables.filter(t => (t.table_type || 'BASE TABLE') === 'BASE TABLE');
});

const databaseViews = computed(() => {
    const tables = getTables();
    return tables.filter(t => (t.table_type || '') === 'VIEW');
});

const totalTables = computed(() => baseTables.value.length + databaseViews.value.length);

const totalRecords = computed(() => {
    const tables = getTables();
    return tables.reduce((sum, table) => sum + (table.rows || 0), 0);
});

const showContent = computed(() => {
    const tables = getTables();
    return tables && tables.length > 0;
});
</script>

<template>
    <div class="dashboard-container" v-if="showContent">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="icon-container">
                    <svg class="icon-large" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.58 4 8 4s8-1.79 8-4M4 7c0-2.21 3.58-4 8-4s8 1.79 8 4m0 5c0 2.21-3.58 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div class="header-text">
                    <h1 class="header-title">Database Explorer</h1>
                    <p class="header-subtitle">High-performance inspection tool for your Laravel connection</p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Tables Card -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-tables">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ baseTables.length }}</div>
                    <div class="stat-label">Base Tables</div>
                    <p class="stat-description">Relational tables for data storage</p>
                </div>
            </div>

            <!-- Views Card -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-views">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ databaseViews.length }}</div>
                    <div class="stat-label">Database Views</div>
                    <p class="stat-description">Virtual tables with predefined queries</p>
                </div>
            </div>

            <!-- Total Tables Card -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ totalTables }}</div>
                    <div class="stat-label">Total Objects</div>
                    <p class="stat-description">All tables and views combined</p>
                </div>
            </div>
        </div>

        <!-- Description Section -->
        <div class="description-section">
            <h2 class="description-title">Powerful Database Inspection</h2>
            <p class="description-text">
                Browse schemas with ease, inspect individual records with detailed views, and navigate complex relationships with precision. Our advanced inspection tool provides comprehensive insights into your database structure and contents.
            </p>
            <div class="feature-list">
                <div class="feature-item">
                    <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 10 10.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <span>Full schema inspection</span>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 10 10.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <span>Advanced record filtering</span>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 10 10.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <span>Relationship navigation</span>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 10 10.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <span>Index information</span>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="dashboard-container" style="display: flex; align-items: center; justify-content: center; min-height: 400px;">
        <p style="color: var(--dbx-muted); font-size: 16px;">Loading database tables...</p>
    </div>
</template>

<style scoped>
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Header Section */
.header-section {
    margin-bottom: 60px;
    animation: slideInDown 0.6s ease-out;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 32px;
}

.icon-container {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--dbx-accent) 0%, rgba(91, 92, 246, 0.8) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 20px 40px rgba(91, 92, 246, 0.2);
    animation: scaleIn 0.6s ease-out;
}

.icon-large {
    width: 44px;
    height: 44px;
    color: white;
    stroke-width: 1.5;
}

.header-text {
    flex: 1;
}

.header-title {
    font-size: 40px;
    font-weight: 600;
    color: var(--dbx-text);
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}

.header-subtitle {
    font-size: 18px;
    color: var(--dbx-muted);
    margin: 0;
    font-weight: 400;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 60px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    border: 1px solid var(--dbx-border);
    display: flex;
    gap: 24px;
    align-items: flex-start;
    transition: all 0.3s ease;
    animation: slideInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }

.stat-card:hover {
    border-color: rgba(91, 92, 246, 0.3);
    box-shadow: 0 20px 40px rgba(91, 92, 246, 0.08);
    transform: translateY(-4px);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon svg {
    width: 32px;
    height: 32px;
    stroke-width: 1.5;
}

.stat-icon-tables {
    background: linear-gradient(135deg, rgba(91, 92, 246, 0.15) 0%, rgba(91, 92, 246, 0.05) 100%);
    color: var(--dbx-accent-ink);
}

.stat-icon-views {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
    color: #22c55e;
}

.stat-icon-total {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%);
    color: #a855f7;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--dbx-text);
    margin-bottom: 4px;
    letter-spacing: -1px;
}

.stat-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--dbx-text);
    margin-bottom: 6px;
}

.stat-description {
    font-size: 13px;
    color: var(--dbx-muted);
    margin: 0;
    font-weight: 400;
}

/* Description Section */
.description-section {
    background: linear-gradient(135deg, rgba(91, 92, 246, 0.08) 0%, rgba(91, 92, 246, 0.03) 100%);
    border: 1px solid rgba(91, 92, 246, 0.1);
    border-radius: 16px;
    padding: 48px;
    animation: fadeIn 0.8s ease-out;
    animation-delay: 0.4s;
    animation-fill-mode: both;
}

.description-title {
    font-size: 28px;
    font-weight: 600;
    color: var(--dbx-text);
    margin: 0 0 16px 0;
    letter-spacing: -0.3px;
}

.description-text {
    font-size: 16px;
    color: var(--dbx-muted);
    line-height: 1.7;
    margin: 0 0 32px 0;
    font-weight: 400;
}

.feature-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--dbx-text);
    font-size: 15px;
    font-weight: 500;
}

.feature-icon {
    width: 20px;
    height: 20px;
    color: var(--dbx-accent);
    flex-shrink: 0;
}

/* Animations */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 24px 16px;
    }

    .header-section {
        margin-bottom: 40px;
    }

    .header-content {
        flex-direction: column;
        text-align: center;
    }

    .icon-container {
        width: 64px;
        height: 64px;
    }

    .icon-large {
        width: 36px;
        height: 36px;
    }

    .header-title {
        font-size: 32px;
    }

    .header-subtitle {
        font-size: 16px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 40px;
    }

    .stat-card {
        padding: 24px;
    }

    .stat-icon {
        width: 56px;
        height: 56px;
    }

    .stat-icon svg {
        width: 28px;
        height: 28px;
    }

    .description-section {
        padding: 32px 24px;
    }

    .description-title {
        font-size: 24px;
    }

    .feature-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 16px;
    }

    .header-title {
        font-size: 28px;
    }

    .header-subtitle {
        font-size: 14px;
    }

    .stat-value {
        font-size: 32px;
    }

    .stat-card {
        gap: 16px;
        padding: 20px;
    }
}
</style>
