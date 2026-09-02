import Chart from 'chart.js/auto';

function cssToken(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function resolveCssColor(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const match = value.match(/^var\((--[^)]+)\)$/);

    return match ? cssToken(match[1], value) : value;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('agChart', (config) => ({
        chart: null,
        themeListener: null,
        init() {
            if (! this.$refs.canvas) {
                return;
            }

            this.renderChart();
            this.themeListener = () => this.renderChart();
            window.addEventListener('agovena-theme-changed', this.themeListener);
        },
        renderChart() {
            this.chart?.destroy();

            const muted = cssToken('--ag-color-text-muted', '#64748b');
            const border = cssToken('--ag-color-border', '#e2e8f0');
            const text = cssToken('--ag-color-text', '#0f172a');
            const surface = cssToken('--ag-color-surface', '#ffffff');
            const scaleDefaults = {
                beginAtZero: true,
                border: {
                    display: false,
                },
                ticks: {
                    color: muted,
                    precision: 0,
                },
            };
            const scales = config.dualAxis
                ? {
                    y: {
                        ...scaleDefaults,
                        title: {
                            display: true,
                            color: muted,
                            text: config.axisLabels?.revenue || '',
                        },
                        grid: {
                            color: border,
                            borderDash: [4, 4],
                        },
                    },
                    y1: {
                        ...scaleDefaults,
                        position: 'right',
                        title: {
                            display: true,
                            color: muted,
                            text: config.axisLabels?.orders || '',
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
                : {
                    y: {
                        ...scaleDefaults,
                        grid: {
                            color: border,
                            borderDash: [4, 4],
                        },
                    },
                };

            this.chart = new Chart(this.$refs.canvas, {
                type: config.type || 'line',
                data: {
                    labels: config.labels || [],
                    datasets: (config.datasets || []).map((dataset) => ({
                        ...dataset,
                        borderColor: resolveCssColor(dataset.borderColor),
                        backgroundColor: resolveCssColor(dataset.backgroundColor),
                        pointBackgroundColor: resolveCssColor(dataset.pointBackgroundColor),
                        pointHoverBackgroundColor: resolveCssColor(dataset.pointHoverBackgroundColor),
                        borderWidth: dataset.borderWidth ?? 2,
                        pointRadius: dataset.pointRadius ?? 2,
                        pointHoverRadius: dataset.pointHoverRadius ?? 4,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 4,
                            right: 4,
                        },
                    },
                    animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                        ? false
                        : { duration: 220 },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: config.showLegend === true,
                            position: 'bottom',
                            labels: {
                                color: muted,
                                boxWidth: 18,
                                boxHeight: 8,
                                padding: 16,
                                usePointStyle: false,
                            },
                        },
                        tooltip: {
                            backgroundColor: surface,
                            titleColor: text,
                            bodyColor: text,
                            borderColor: border,
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: true,
                        },
                    },
                    scales: {
                        x: {
                            border: {
                                display: false,
                            },
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: muted,
                                maxRotation: 0,
                                autoSkipPadding: 12,
                            },
                        },
                        ...scales,
                    },
                },
            });
        },
        destroy() {
            if (this.themeListener) {
                window.removeEventListener('agovena-theme-changed', this.themeListener);
            }
            this.chart?.destroy();
            this.chart = null;
        },
    }));
});
