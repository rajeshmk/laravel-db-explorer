import { createApp } from 'vue';
import App from './App.vue';

const app = createApp(App);

// Mount with initial data injected from Blade
if (window.__DB_EXPLORER_DATA__) {
  app.config.globalProperties.$initialData = window.__DB_EXPLORER_DATA__;
}

app.mount('#db-explorer-app');
