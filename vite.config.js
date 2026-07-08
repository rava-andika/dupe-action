import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    server: {
        proxy: {
          '/assets/fonts': 'http://localhost:8000',
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    build: {
        minify: 'terser',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Split node_modules into chunck
                    if (id.includes('node_modules')) {
                        return id.split('node_modules/')[1].split('/')[0];
                    }
                },
            },
        },

    },
});