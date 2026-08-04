import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    // roda dentro do container: precisa escutar em 0.0.0.0 e anunciar o host da máquina
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost', clientPort: 5183 },
        watch: { usePolling: true },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});