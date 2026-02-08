import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    lib: {
      entry: path.resolve(__dirname, 'resources/js/app.js'),
      name: 'DbExplorer',
      fileName: 'db-explorer',
      formats: ['iife'],
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') return 'db-explorer.css';
          return assetInfo.name;
        },
      },
    },
  },
});
