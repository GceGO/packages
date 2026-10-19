import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatter, palette, toChartJs, validate, TONES } from '../../resources/js/charts/config.js';
import { gcegoChart } from '../../resources/js/charts/index.js';

const colors = Object.fromEntries(TONES.map((tone) => [tone, `#${tone.length}${tone.length}${tone.length}`]));

test('validates the description and drops anything unexpected', () => {
    const chart = validate({
        type: 'line',
        labels: ['Jan', 'Feb', { evil: true }],
        datasets: [{ label: 'Enrolments', data: [1, 'x', Infinity, 4], tone: 'url(javascript:alert(1))', onClick: 'alert(1)' }],
        options: { plugins: { tooltip: { callbacks: { label: 'alert(1)' } } } },
        format: { style: 'currency', currency: 'XAF', locale: 'fr-CM' },
    });

    assert.deepEqual(chart.labels, ['Jan', 'Feb', '[object Object]']);
    assert.deepEqual(chart.datasets[0].data, [1, null, null]);
    assert.equal(chart.datasets[0].tone, 'primary');
    assert.equal('options' in chart, false);
    assert.throws(() => validate({ type: 'radar' }), /unsupported chart type/);
    assert.throws(() => validate(null), /must be an object/);
});

test('maps area, horizontal bars and doughnuts to Chart.js', () => {
    const area = toChartJs(validate({ type: 'area', labels: ['a'], datasets: [{ label: 'x', data: [1], tone: 'success' }] }), colors);
    const bars = toChartJs(validate({ type: 'bar', horizontal: true, stacked: true, labels: ['a'], datasets: [{ label: 'x', data: [1], tone: 'info' }] }), colors);
    const doughnut = toChartJs(validate({ type: 'doughnut', legend: true, labels: ['a', 'b'], datasets: [{ label: 'x', data: [1, 2] }] }), colors);

    assert.equal(area.type, 'line');
    assert.equal(area.data.datasets[0].fill, true);
    assert.equal(bars.options.indexAxis, 'y');
    assert.equal(bars.options.scales.x.stacked, true);
    assert.equal(doughnut.options.cutout, '62%');
    assert.equal(doughnut.options.plugins.legend.display, true);
    assert.equal(doughnut.data.datasets[0].backgroundColor.length, 2);

    const series = toChartJs(validate({ type: 'pie', labels: ['a', 'b'], datasets: [{ label: 'x', data: [1, 2] }] }), { ...colors, 'chart-1': '#c1', 'chart-2': '#c2' });
    assert.deepEqual(series.data.datasets[0].backgroundColor, ['#c1', '#c2']);
});

test('formats values like the server', () => {
    assert.equal(formatter({ style: 'currency', currency: 'XAF', decimals: null, locale: 'en' })(25000), 'FCFA 25,000');
    assert.equal(formatter({ style: 'percent', currency: 'XAF', decimals: null, locale: 'en' })(0.72), '72%');
    assert.equal(formatter({ style: 'compact', currency: 'XAF', decimals: null, locale: 'en' })(1530), '1.5K');
    assert.equal(formatter({ style: 'duration', currency: 'XAF', decimals: null, locale: 'en' })(3900), '1h 5m');
    assert.equal(formatter({ style: 'number', currency: 'XAF', decimals: null, locale: 'xx-INVALID!!' })(1234), '1,234');
    assert.equal(formatter({ style: 'number', currency: 'XAF', decimals: null, locale: 'en' })(null), '—');
});

function element(json, adapter = 'fake') {
    const canvas = {};

    return {
        dataset: { gcegoChart: adapter },
        querySelector: (selector) => (selector === 'canvas' ? canvas : { textContent: json }),
    };
}

test('creates one chart per element and destroys it on teardown', async () => {
    const created = [];
    const registry = {
        fake: async () => ({
            render: (canvas, config) => {
                const instance = { config, destroyed: false, destroy() { this.destroyed = true; } };
                created.push(instance);

                return instance;
            },
        }),
    };
    const component = gcegoChart(registry);
    component.$el = element(JSON.stringify({ type: 'line' }));

    await component.init();
    await component.init();

    assert.equal(created.length, 2);
    assert.equal(created[0].destroyed, true, 'a re-initialised element replaces its previous chart');

    component.destroy();

    assert.equal(created[1].destroyed, true);
    assert.equal(component.chart, null);
});

test('never renders unparsable configuration or unknown adapters, and skips charts destroyed while loading', async () => {
    let renders = 0;
    const registry = { fake: async () => ({ render: () => { renders++; return { destroy() {} }; } }) };

    const broken = gcegoChart(registry);
    broken.$el = element('{"type": "line", <script>');
    await broken.init();

    const unknown = gcegoChart(registry);
    unknown.$el = element('{}', 'eval');
    await unknown.init();

    const early = gcegoChart(registry);
    early.$el = element('{"type":"line"}');
    const pending = early.init();
    early.destroy();
    await pending;

    assert.equal(renders, 0);
});

test('reads tone colors from the gcego token variables', () => {
    const tokens = { '--gcego-primary': ' #0b7570 ', '--gcego-text-muted': '#4a5468', '--gcego-chart-3': '#c2410c' };
    globalThis.getComputedStyle = () => ({ getPropertyValue: (name) => tokens[name] ?? '' });

    try {
        const colors = palette({});

        assert.equal(colors.primary, '#0b7570');
        assert.equal(colors.neutral, '#4a5468');
        assert.match(colors.danger, /^#[0-9a-f]{6}$/);
        assert.equal(colors['chart-3'], '#c2410c');
        assert.match(colors['chart-8'], /^#[0-9a-f]{6}$/);
    } finally {
        delete globalThis.getComputedStyle;
    }
});
