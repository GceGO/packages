/**
 * The Chart.js adapter, loaded on demand (only when a chart is on the page).
 * Only the controllers and elements gcego charts use are registered, so the
 * bundle stays small.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PieController,
    PointElement,
    Tooltip,
} from 'chart.js';
import { palette, toChartJs, validate } from './config.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PieController, PointElement, Tooltip);

/**
 * @param {HTMLCanvasElement} canvas
 * @param {unknown} description
 * @returns {{destroy(): void}}
 */
export function render(canvas, description) {
    Chart.getChart(canvas)?.destroy();

    return new Chart(canvas, toChartJs(validate(description), palette(canvas)));
}
