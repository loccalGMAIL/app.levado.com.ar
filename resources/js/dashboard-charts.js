// Gráficos del dashboard (gauge / barras / dona). Los datos llegan desde un
// <script type="application/json" id="dashboard-chart-data"> que emite la vista,
// así que este módulo no depende de variables globales ni de JS inline.
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

    const brown500 = '#9B6240';
    const brown300 = '#C49272';
    const brown200 = '#DEC4A8';
    const brown100 = '#EFE3D5';
    const textSecondary = '#6B5B45';
    const textMuted = '#a08873';
    const borderColor = '#F2EAD8';
    const green = '#2C9B6A';

    /* ── Gauge ── */
    new ApexCharts(gaugeEl, {
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
                track: { background: '#EFE3D5', strokeWidth: '100%', margin: 0 },
                dataLabels: {
                    name: { show: true, offsetY: 18, fontSize: '12px', color: textMuted, fontFamily: 'Inter, sans-serif' },
                    value: { offsetY: -10, fontSize: '28px', fontWeight: '700', fontFamily: 'Inter, sans-serif', color: '#3D2B1F', formatter: v => v + '%' }
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
    }).render();

    /* ── Bar chart ── */
    const barEl = document.querySelector('#barChart');
    const topRecipes = data.topRecipes || [];
    if (barEl && topRecipes.length > 0) {
        const names = topRecipes.map(r => r.name);
        const values = topRecipes.map(r => r.margin_pct);
        const colors = values.map(v =>
            v >= 60 ? '#2C9B6A' :
            v >= 40 ? '#D97706' :
            v >= 20 ? '#EA580C' : '#DC2626'
        );
        new ApexCharts(barEl, {
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
        }).render();
    }

    /* ── Donut ── */
    const donutEl = document.querySelector('#donutChart');
    const donutData = data.costDistribution || [];
    if (donutEl && donutData.some(v => v > 0)) {
        new ApexCharts(donutEl, {
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
                            value: { fontSize: '20px', fontWeight: '700', fontFamily: 'Inter, sans-serif', color: '#3D2B1F', formatter: v => v + '%' }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: ['#FFFFFF'] },
            tooltip: { y: { formatter: v => v + '%' } }
        }).render();
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initDashboardCharts();
        wireMarginFilter();
    });
} else {
    initDashboardCharts();
    wireMarginFilter();
}
