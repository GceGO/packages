/**
 * gcego/widgets charts for the host application's Vite build:
 *
 *     // resources/js/app.js
 *     import '../../packages/widgets/resources/js/charts/index.js';
 *
 * Registers the Alpine component "gcegoChart" (Livewire ships Alpine). Each
 * chart element holds its configuration in <script type="application/json">;
 * the adapter (Chart.js) is imported lazily the first time a chart appears,
 * and every chart is destroyed when Alpine tears its element down (Livewire
 * re-keys the element when the data changes), so instances never pile up.
 */
const adapters = {
    chartjs: () => import('./chartjs.js'),
};

/**
 * @param {Record<string, () => Promise<{render: (canvas: HTMLCanvasElement, config: unknown) => {destroy(): void}}>>} registry
 */
export function gcegoChart(registry = adapters) {
    return {
        chart: null,
        destroyed: false,

        async init() {
            const script = this.$el.querySelector('script[data-gcego-chart-config]');
            const canvas = this.$el.querySelector('canvas');
            const load = registry[this.$el.dataset.gcegoChart];

            if (!script || !canvas || !load) {
                return;
            }

            let config;

            try {
                config = JSON.parse(script.textContent ?? '');
            } catch {
                return;
            }

            const adapter = await load();

            if (this.destroyed) {
                return;
            }

            this.chart?.destroy();
            this.chart = adapter.render(canvas, config);
        },

        destroy() {
            this.destroyed = true;
            this.chart?.destroy();
            this.chart = null;
        },
    };
}

export function registerGcegoCharts(Alpine) {
    Alpine.data('gcegoChart', () => gcegoChart());
}

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) {
            registerGcegoCharts(window.Alpine);
        }
    });
}
