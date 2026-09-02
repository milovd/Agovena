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

function buildLinePointRadii(data, radius) {
    if (!Array.isArray(data) || data.length === 0 || radius <= 0) {
        return [];
    }

    const markerStride = data.length <= 14 ? 1 : Math.ceil(data.length / 7);

    return data.map((value, index) => {
        const current = Number(value);

        if (!Number.isFinite(current) || current === 0) {
            return 0;
        }

        const previous = Number(data[index - 1]);
        const next = Number(data[index + 1]);
        const hasPrevious = Number.isFinite(previous);
        const hasNext = Number.isFinite(next);
        const isBoundary = index === 0 || index === data.length - 1;
        const isLocalPeak = (! hasPrevious || current >= previous) && (! hasNext || current >= next);
        const isInterval = index % markerStride === 0;

        return isBoundary || isLocalPeak || isInterval ? radius : 0;
    });
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
            const locale = document.documentElement.lang || 'en-US';
            const currencyFormatter = new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: config.currency || 'EUR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            const numberFormatter = new Intl.NumberFormat(locale);
            const isBarChart = config.type === 'bar';
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
                    datasets: (config.datasets || []).map((dataset) => {
                        const backgroundColor = isBarChart
                            ? dataset.barBackgroundColor ?? dataset.backgroundColor
                            : dataset.backgroundColor;
                        const borderColor = isBarChart
                            ? dataset.barBorderColor ?? dataset.borderColor
                            : dataset.borderColor;
                        const pointRadius = isBarChart
                            ? 0
                            : buildLinePointRadii(dataset.data, dataset.pointMarkerRadius ?? 3);

                        return {
                            ...dataset,
                            borderColor: resolveCssColor(borderColor),
                            backgroundColor: resolveCssColor(backgroundColor),
                            pointBackgroundColor: resolveCssColor(dataset.pointBackgroundColor),
                            pointBorderColor: resolveCssColor(dataset.pointBorderColor),
                            pointHoverBackgroundColor: resolveCssColor(dataset.pointHoverBackgroundColor),
                            pointHoverBorderColor: resolveCssColor(dataset.pointHoverBorderColor),
                            borderWidth: isBarChart ? dataset.barBorderWidth ?? 0 : dataset.borderWidth ?? 2,
                            pointRadius,
                            pointHoverRadius: isBarChart ? 0 : dataset.pointHoverRadius ?? 5,
                            pointHitRadius: isBarChart ? 0 : dataset.pointHitRadius ?? 10,
                            borderRadius: isBarChart ? dataset.barBorderRadius ?? 0 : dataset.borderRadius,
                            borderSkipped: isBarChart ? false : dataset.borderSkipped,
                        };
                    }),
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
                            callbacks: {
                                label(context) {
                                    const value = context.parsed.y;

                                    if (value === null || value === undefined) {
                                        return context.dataset.label || '';
                                    }

                                    const formattedValue = context.dataset.yAxisID === 'y'
                                        ? currencyFormatter.format(value)
                                        : numberFormatter.format(value);

                                    return `${context.dataset.label}: ${formattedValue}`;
                                },
                            },
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
