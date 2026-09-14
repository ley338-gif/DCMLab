import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Bewusst minimal und von vite.config.ts getrennt: die Frontend-Tests
 * (Achievement-System, Auftrag Abschnitt 20/21) brauchen weder Inertia noch
 * den Laravel-Vite-Plugin, nur Vue-SFC-Kompilierung und den "@"-Alias aus
 * tsconfig.json.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.spec.ts'],
    },
});
