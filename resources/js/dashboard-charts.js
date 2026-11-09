// Gráficos del dashboard (gauge / barras / dona). Los datos llegan desde un
// <script type="application/json" id="dashboard-chart-data"> que emite la vista,
// así que este módulo no depende de variables globales ni de JS inline.

/**
 * Lee un token de color de app.css (`--c-corteza: 61 43 31`) y lo devuelve en hex:
 * ApexCharts calcula sombras y degradés a partir del color y no resuelve var().
 *
 * @param {string} name
 * @returns {string}
 */
function themeColor(name) {
    const channels = getComputedStyle(document.documentElement)
        .getPropertyValue(`--c-${name}`)
        .trim()
        .split(/\s+/)
        .map(Number);

    return '#' + channels.map(c => c.toString(16).padStart(2, '0')).join('');
}

/** Gráficos dibujados, para rehacerlos con los colores nuevos al cambiar de tema. */
let renderedCharts = [];

/**
 * @param {object} chart instancia de ApexCharts
 */
function renderChart(chart) {
    chart.render();
    renderedCharts.push(chart);
}

async function initDashboardCharts() {
    const dataEl = document.getElementById('dashboard-chart-data');
    const gaugeEl = document.querySelector('#gaugeChart');
    if (!dataEl || !gaugeEl) {
        return; // no estamos en el dashboard
    }

    let data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        return;
    }

    const { default: ApexCharts } = await import('apexcharts');

    renderedCharts.forEach(chart => chart.destroy());
    renderedCharts = [];

    const brown500 = themeColor('brown-500');
    const brown300 = themeColor('brown-300');
    const brown200 = themeColor('brown-200');
    const brown100 = themeColor('brown-100');
    const textPrimary = themeColor('corteza');
    const textSecondary = themeColor('masa-madre');
    const textMuted = themeColor('grafico-suave');
    const borderColor = themeColor('miga');
    const surface = themeColor('superficie');
    const green = themeColor('grafico-ok');

    /* ── Gauge ── */
    renderChart(new ApexCharts(gaugeEl, {
        series: [data.gaugeValue],
        chart: {
            type: 'radialBar',
            height: 200,
            sparkline: { enabled: true },
            animations: { enabled: true, easing: 'easeinout', speed: 900 },
            fontFamily: 'Inter, sans-serif',
        },
        plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: { margin: 0, size: '65%' },
                track: { background: brown100, strokeWidth: '100%', margin: 0 },
                dataLabels: {
                    name: { show: true, offsetY: 18, fontSize: '12px', color: textMuted, fontFamily: 'Inter, sans-serif' },
                    value: { offsetY: -10, fontSize: '28px', fontWeight: '700', fontFamily: 'Inter, sans-serif', color: textPrimary, formatter: v => v + '%' }
                }
            }
        },
        fill: {
            type: 'gradient',
            gradient: { shade: 'dark', type: 'horizontal', gradientToColors: [green], stops: [0, 100] }
        },
        stroke: { lineCap: 'round' },
        labels: ['Utilidad media'],
        colors: [brown500],
    }));

    /* ── Bar chart ── */
    const barEl = document.querySelector('#barChart');
    const topRecipes = data.topRecipes || [];
    if (barEl && topRecipes.length > 0) {
        const names = topRecipes.map(r => r.name);
        const values = topRecipes.map(r => r.margin_pct);
        const colors = values.map(v =>
            v >= 60 ? green :
            v >= 40 ? themeColor('amber-600') :
            v >= 20 ? themeColor('orange-600') : themeColor('red-600')
        );
        renderChart(new ApexCharts(barEl, {
            series: [{ name: 'Margen %', data: values }],
            chart: {
                type: 'bar', height: Math.max(220, names.length * 38),
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 700 },
                fontFamily: 'Inter, sans-serif',
            },
            plotOptions: {
                bar: { horizontal: true, borderRadius: 5, barHeight: '55%', distributed: true, dataLabels: { position: 'top' } }
            },
            colors,
            dataLabels: {
                enabled: true,
                formatter: v => v + '%',
                offsetX: 10,
                style: { fontSize: '12px', fontWeight: '600', colors: [textSecondary] }
            },
            xaxis: {
                categories: names, max: 100,
                labels: { style: { colors: textMuted, fontSize: '11.5px' }, formatter: v => v + '%' }
            },
            yaxis: { labels: { style: { colors: textSecondary, fontSize: '12.5px' } } },
            grid: { borderColor, strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            legend: { show: false },
            tooltip: { y: { formatter: v => v + '% de margen' } }
        }));
    }

    /* ── Donut ── */
    const donutEl = document.querySelector('#donutChart');
    const donutData = data.costDistribution || [];
    if (donutEl && donutData.some(v => v > 0)) {
        renderChart(new ApexCharts(donutEl, {
            series: donutData,
            chart: {
                type: 'donut', height: 200,
                animations: { enabled: true, easing: 'easeinout', speed: 800 },
                fontFamily: 'Inter, sans-serif',
            },
            labels: ['Ingredientes', 'Mano de obra', 'Gastos fijos', 'Descartables'],
            colors: [brown500, brown300, brown200, brown100],
            legend: { show: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            total: { show: true, label: 'Total', fontSize: '11px', fontFamily: 'Inter, sans-serif', color: textMuted, formatter: () => '100%' },
                            value: { fontSize: '20px', fontWeight: '700', fontFamily: 'Inter, sans-serif', color: textPrimary, formatter: v => v + '%' }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: [surface] },
            tooltip: { y: { formatter: v => v + '%' } }
        }));
    }
}

// Filtro rápido de margen: envía el form con el valor elegido.
function wireMarginFilter() {
    const input = document.getElementById('marginFilterInput');
    const form = document.getElementById('searchForm');
    if (!input || !form) {
        return;
    }
    document.querySelectorAll('.margin-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            input.value = btn.dataset.filter ?? '';
            form.submit();
        });
    });
}

// El script del <head> (components/theme-head) avisa cuando cambia el tema: desde
// el selector de Perfil o porque el sistema pasó a modo oscuro en «Automático».
window.addEventListener('theme-changed', () => {
    if (renderedCharts.length > 0) {
        initDashboardCharts();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initDashboardCharts();
        wireMarginFilter();
    });
} else {
    initDashboardCharts();
    wireMarginFilter();
}
