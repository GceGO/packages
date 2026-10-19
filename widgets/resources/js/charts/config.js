/**
 * Pure functions that turn the server's validated chart description into a
 * Chart.js configuration. The description is plain JSON (JSON.parse); it is
 * re-validated here, and every function (tooltips, ticks) is defined in this
 * file: nothing executable ever comes from the server or from users.
 */

export const TYPES = ['line', 'bar', 'area', 'pie', 'doughnut'];
export const TONES = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];
/** gcego/swatches categorical chart tokens (--gcego-chart-1 … 8): distinguishable in light and dark mode. */
export const SERIES = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6', 'chart-7', 'chart-8'];
export const STYLES = ['number', 'decimal', 'percent', 'currency', 'compact', 'duration', 'ratio'];

// The gcego/swatches light-mode defaults, used only when the tokens cannot be read.
const FALLBACK_COLORS = {
    primary: '#0b7570',
    success: '#127a3e',
    warning: '#a65308',
    danger: '#c02a2f',
    info: '#1f5fc4',
    neutral: '#4a5468',
    'chart-1': '#0b7570',
    'chart-2': '#2f62d4',
    'chart-3': '#c2410c',
    'chart-4': '#7c3aed',
    'chart-5': '#be185d',
    'chart-6': '#8a6100',
    'chart-7': '#4d7c0f',
    'chart-8': '#5b6577',
};

/**
 * Tone and series colors from the gcego/swatches tokens (CSS variables), so charts follow the theme and dark mode.
 *
 * @param {Element|null} element
 * @returns {Record<string, string>}
 */
export function palette(element) {
    const styles = element && typeof getComputedStyle === 'function' ? getComputedStyle(element) : null;
    const colors = {};

    for (const tone of [...TONES, ...SERIES]) {
        // The token itself (--gcego-*): @theme inline maps it into Tailwind without emitting --color-* variables.
        const tokens = tone === 'neutral' ? ['chart-neutral', 'text-muted'] : [tone];
        const value = styles ? tokens.map((token) => styles.getPropertyValue(`--gcego-${token}`).trim()).find((found) => found !== '') ?? '' : '';
        colors[tone] = value !== '' ? value : FALLBACK_COLORS[tone];
    }

    return colors;
}

/**
 * @param {unknown} config
 * @returns {{type: string, labels: string[], datasets: {label: string, data: (number|null)[], tone: string}[], horizontal: boolean, stacked: boolean, legend: boolean, format: {style: string, currency: string, decimals: number|null, locale: string}}}
 */
export function validate(config) {
    if (config === null || typeof config !== 'object' || Array.isArray(config)) {
        throw new TypeError('gcego chart: the configuration must be an object.');
    }

    const type = TYPES.includes(config.type) ? config.type : null;

    if (type === null) {
        throw new TypeError('gcego chart: unsupported chart type.');
    }

    const labels = Array.isArray(config.labels) ? config.labels.map((label) => String(label).slice(0, 80)) : [];
    const datasets = (Array.isArray(config.datasets) ? config.datasets : []).slice(0, 20).map((dataset) => ({
        label: typeof dataset?.label === 'string' ? dataset.label.slice(0, 80) : '',
        data: (Array.isArray(dataset?.data) ? dataset.data : []).slice(0, labels.length).map((value) => (typeof value === 'number' && Number.isFinite(value) ? value : null)),
        tone: TONES.includes(dataset?.tone) ? dataset.tone : 'primary',
    }));
    const format = config.format && typeof config.format === 'object' ? config.format : {};

    return {
        type,
        labels,
        datasets,
        horizontal: config.horizontal === true,
        stacked: config.stacked === true,
        legend: config.legend === true,
        format: {
            style: STYLES.includes(format.style) ? format.style : 'number',
            currency: typeof format.currency === 'string' && /^[A-Z]{3}$/.test(format.currency) ? format.currency : 'XAF',
            decimals: Number.isInteger(format.decimals) && format.decimals >= 0 && format.decimals <= 6 ? format.decimals : null,
            locale: typeof format.locale === 'string' && /^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/.test(format.locale) ? format.locale : 'en',
        },
    };
}

/**
 * A value formatter matching the widget's KPI format (Intl).
 *
 * @param {{style: string, currency: string, decimals: number|null, locale: string}} format
 * @returns {(value: number|null) => string}
 */
export function formatter(format) {
    const digits = format.decimals === null ? {} : { minimumFractionDigits: format.decimals, maximumFractionDigits: format.decimals };
    const options = {
        percent: { style: 'percent', ...digits },
        currency: { style: 'currency', currency: format.currency, ...digits },
        compact: { notation: 'compact', ...digits },
        decimal: { minimumFractionDigits: format.decimals ?? 2, maximumFractionDigits: format.decimals ?? 2 },
    }[format.style] ?? digits;
    let intl;

    try {
        intl = new Intl.NumberFormat(format.locale, options);
    } catch {
        intl = new Intl.NumberFormat('en', options);
    }

    return (value) => {
        if (value === null || value === undefined || !Number.isFinite(value)) {
            return '—';
        }

        if (format.style === 'duration') {
            const hours = Math.floor(value / 3600);
            const minutes = Math.round((value % 3600) / 60);

            return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
        }

        return format.style === 'ratio' ? `${intl.format(value)} : 1` : intl.format(value);
    };
}

/**
 * The Chart.js configuration for a validated description.
 *
 * @param {ReturnType<typeof validate>} chart
 * @param {Record<string, string>} colors
 */
export function toChartJs(chart, colors) {
    const circular = chart.type === 'pie' || chart.type === 'doughnut';
    const format = formatter(chart.format);
    // Slices are categories, not statuses: use the categorical series (status tones only as a fallback).
    const sliceColors = chart.labels.map((_, index) => colors[SERIES[index % SERIES.length]] ?? colors[TONES[index % TONES.length]]);

    return {
        type: chart.type === 'area' ? 'line' : chart.type,
        data: {
            labels: chart.labels,
            datasets: chart.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.data,
                backgroundColor: circular ? sliceColors : withAlpha(colors[dataset.tone], chart.type === 'bar' ? 0.85 : 0.2),
                borderColor: circular ? sliceColors : colors[dataset.tone],
                borderWidth: circular ? 0 : 2,
                fill: chart.type === 'area',
                tension: 0.3,
                pointRadius: chart.labels.length > 60 ? 0 : 3,
                spanGaps: true,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: matchMedia('(prefers-reduced-motion: reduce)') ? false : undefined,
            indexAxis: chart.horizontal ? 'y' : 'x',
            cutout: chart.type === 'doughnut' ? '62%' : undefined,
            interaction: { mode: circular ? 'nearest' : 'index', intersect: false },
            plugins: {
                legend: { display: chart.legend, position: 'bottom' },
                tooltip: { callbacks: { label: (item) => `${item.dataset.label || item.label}: ${format(circular ? item.raw : item.parsed[chart.horizontal ? 'x' : 'y'])}` } },
            },
            scales: circular ? {} : {
                x: { stacked: chart.stacked, ticks: chart.horizontal ? { callback: (value) => format(Number(value)) } : { autoSkip: true, maxRotation: 0 } },
                y: { stacked: chart.stacked, beginAtZero: true, ticks: chart.horizontal ? { autoSkip: true } : { callback: (value) => format(Number(value)) } },
            },
        },
    };
}

function matchMedia(query) {
    return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia(query).matches;
}

/**
 * @param {string} color
 * @param {number} alpha
 */
export function withAlpha(color, alpha) {
    return `color-mix(in srgb, ${color} ${Math.round(alpha * 100)}%, transparent)`;
}
