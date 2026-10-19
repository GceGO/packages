// Measures the browser bundle of the chart adapter (npm run build:measure):
// the entry (always loaded) and the lazily imported Chart.js adapter chunk.
import { defineConfig } from 'vite';

export default defineConfig({
    logLevel: 'info',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        reportCompressedSize: true,
        rollupOptions: {
            input: 'resources/js/charts/index.js',
            output: { entryFileNames: 'gcego-charts.js', chunkFileNames: '[name].js' },
            preserveEntrySignatures: 'exports-only',
        },
    },
});
