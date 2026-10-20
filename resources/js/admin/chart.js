import Chart from 'chart.js/auto';

/*
 * Area fill opacities follow the shadcn/ui "Chart Area Interactive" block: a vertical
 * gradient from 0.8 to 0.1 of the series color, drawn at a 0.4 fill opacity.
 */
export const AREA_GRADIENT_TOP = 0.8;
export const AREA_GRADIENT_BOTTOM = 0.1;
export const AREA_FILL_OPACITY = 0.4;
export const BAR_RADIUS = 4;

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

/** Returns the color with the given alpha; understands hex and rgb()/rgba() and falls back to color-mix(). */
export function withAlpha(color, alpha) {
    if (typeof color !== 'string' || color === '') {
        return color;
    }

    const value = color.trim();
    const hex = value.match(/^#([0-9a-f]{3,8})$/i);

    if (hex) {
        let digits = hex[1];

        if (digits.length === 3 || digits.length === 4) {
            digits = digits.slice(0, 3).split('').map((digit) => digit + digit).join('');
        }

        if (digits.length === 6 || digits.length === 8) {
            const red = parseInt(digits.slice(0, 2), 16);
            const green = parseInt(digits.slice(2, 4), 16);
            const blue = parseInt(digits.slice(4, 6), 16);

            return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
        }
    }

    const rgb = value.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/i);

    if (rgb) {
        return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alpha})`;
    }

    return `color-mix(in srgb, ${value} ${Math.round(alpha * 100)}%, transparent)`;
}

/** Scriptable backgroundColor that paints a top-to-bottom gradient inside the chart area. */
export function areaGradient(color) {
    let cache = null;

    return (context) => {
        const chart = context?.chart;
        const area = chart?.chartArea;

        if (!area) {
            return withAlpha(color, AREA_GRADIENT_BOTTOM * AREA_FILL_OPACITY);
        }

        if (cache && cache.top === area.top && cache.bottom === area.bottom) {
            return cache.gradient;
        }

        const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
        gradient.addColorStop(0, withAlpha(color, AREA_GRADIENT_TOP * AREA_FILL_OPACITY));
        gradient.addColorStop(1, withAlpha(color, AREA_GRADIENT_BOTTOM * AREA_FILL_OPACITY));
        cache = { top: area.top, bottom: area.bottom, gradient };

        return gradient;
    };
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

/**
 * Dataset options for the shadcn-style interactive variant: a monotone area with a
 * gradient fill and hover-only dots, or slim grouped bars with rounded top corners.
 */
/*
 * Chart.js resolves bar options with a `bar` prefix and point options with a `point` prefix
 * before the plain key, so a server-side `barBackgroundColor: 'var(...)'` would win over the
 * resolved color and paint the bars black. The interactive variant sets every color itself.
 */
const SERVER_MODE_KEYS = [
    'barBackgroundColor',
    'barBorderColor',
    'barBorderWidth',
    'barBorderRadius',
    'pointBackgroundColor',
    'pointBorderColor',
    'pointHoverBackgroundColor',
    'pointHoverBorderColor',
];

export function interactiveDataset(source, { isBar, surface, resolve = resolveCssColor }) {
    const color = resolve(source.borderColor ?? source.barBackgroundColor ?? source.backgroundColor);
    const dataset = Object.fromEntries(Object.entries(source).filter(([key]) => !SERVER_MODE_KEYS.includes(key)));

    if (isBar) {
        return {
            ...dataset,
            type: 'bar',
            borderColor: color,
            backgroundColor: color,
            hoverBackgroundColor: withAlpha(color, 0.85),
            borderWidth: 0,
            borderRadius: BAR_RADIUS,
            borderSkipped: 'start',
            categoryPercentage: 0.72,
            barPercentage: 0.82,
            maxBarThickness: 18,
            pointRadius: 0,
        };
    }

    return {
        ...dataset,
        type: 'line',
        borderColor: color,
        backgroundColor: areaGradient(color),
        fill: 'origin',
        cubicInterpolationMode: 'monotone',
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBorderWidth: 2,
        pointHoverBackgroundColor: color,
        pointHoverBorderColor: surface,
        pointHitRadius: 12,
        // Let the hover dot (4px radius plus 2px ring) draw past the plot edge at the series maximum.
        clip: 8,
    };
}

function tooltipHost(chart) {
    const host = chart.canvas.parentNode;
    let element = host.querySelector(':scope > .ag-chart-card__tooltip');

    if (!element) {
        element = document.createElement('div');
        element.className = 'ag-chart-card__tooltip';
        element.setAttribute('aria-hidden', 'true');
        element.dataset.state = 'hidden';
        host.appendChild(element);
    }

    return element;
}

function createNode(tag, className, text) {
    const node = document.createElement(tag);
    node.className = className;

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

/** HTML tooltip in the shadcn style: muted title, colored indicator, muted label, right-aligned value. */
function externalTooltip(formatValue) {
    return ({ chart, tooltip }) => {
        const element = tooltipHost(chart);

        if (tooltip.opacity === 0 || !tooltip.dataPoints?.length) {
            element.dataset.state = 'hidden';

            return;
        }

        const rows = tooltip.dataPoints.map((point) => {
            const row = createNode('div', 'ag-chart-card__tooltip-row');
            const indicator = createNode('span', 'ag-chart-card__tooltip-indicator');
            indicator.style.backgroundColor = point.dataset.borderColor;
            row.append(
                indicator,
                createNode('span', 'ag-chart-card__tooltip-label', point.dataset.label || ''),
                createNode('span', 'ag-chart-card__tooltip-value', formatValue(point.dataset, point.parsed.y)),
            );

            return row;
        });

        element.replaceChildren(createNode('p', 'ag-chart-card__tooltip-title', tooltip.title.join(' ')), ...rows);
        element.dataset.state = 'visible';

        const offset = 12;
        const hostWidth = chart.canvas.parentNode.clientWidth;
        const width = element.offsetWidth;
        const height = element.offsetHeight;
        const bottom = chart.chartArea?.bottom ?? height;
        let left = tooltip.caretX + offset;

        if (left + width > hostWidth) {
            left = Math.max(0, tooltip.caretX - offset - width);
        }

        const top = Math.max(0, Math.min(tooltip.caretY - height / 2, bottom - height));
        element.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
    };
}

/** Draws a thin vertical cursor line at the active index (area charts only). */
const cursorPlugin = {
    id: 'agCursor',
    afterDatasetsDraw(chart, _args, options) {
        const active = chart.tooltip?.getActiveElements?.() ?? [];

        if (!options?.enabled || active.length === 0) {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = active[0].element.x;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = options.color;
        ctx.stroke();
        ctx.restore();
    },
};

function prefersReducedMotion() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function parseIsoDate(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** Chart.js wrapper used by Admin dashboards and reports (x-data="agChart"). */
export function registerChartComponents(Alpine) {
    Alpine.data('agChart', (config) => ({
        chart: null,
        themeObserver: null,
        themeFrame: null,
        init() {
            if (!this.$refs.canvas) {
                return;
            }

            if (!config) {
                const configElement = this.$root.matches('[data-chart-config]')
                    ? this.$root
                    : this.$root.querySelector('[data-chart-config]');
                const rawConfig = configElement?.dataset.chartConfig;

                try {
                    config = rawConfig ? JSON.parse(rawConfig) : {};
                } catch {
                    config = {};
                }
            }

            this.renderChart();

            // Re-read the color tokens whenever the Admin theme toggles (html[data-theme]).
            if (typeof MutationObserver === 'function') {
                this.themeObserver = new MutationObserver(() => this.scheduleThemeRender());
                this.themeObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-theme'],
                });
            }
        },
        scheduleThemeRender() {
            if (this.themeFrame !== null) {
                return;
            }

            this.themeFrame = window.requestAnimationFrame(() => {
                this.themeFrame = null;
                this.renderChart();
            });
        },
        renderChart() {
            this.chart?.destroy();

            const canvas = this.$refs.canvas;
            const muted = cssToken('--ag-color-text-muted', '#64748b');
            const border = cssToken('--ag-color-border', '#e2e8f0');
            const surface = cssToken('--ag-color-surface', '#ffffff');
            const fontFamily = cssToken('--ag-font-sans', 'system-ui, sans-serif');
            const locale = document.documentElement.lang || 'en-US';
            const interactive = config.variant === 'interactive';
            // Money series arrive in integer minor units; currencyPrecision is the currency's exponent.
            const hasMinorUnits = Number.isInteger(config.currencyPrecision) && config.currencyPrecision >= 0;
            const fractionDigits = hasMinorUnits ? config.currencyPrecision : 2;
            const minorUnitScale = hasMinorUnits ? 10 ** config.currencyPrecision : 1;
            const currencyFormatter = new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: config.currency || 'EUR',
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits,
            });
            const currencyAxisFormatter = new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: config.currency || 'EUR',
                maximumFractionDigits: interactive ? 1 : 0,
                ...(interactive ? { notation: 'compact' } : {}),
            });
            const formatMoney = (value) => currencyFormatter.format(Number(value) / minorUnitScale);
            const numberFormatter = new Intl.NumberFormat(locale);
            const formatValue = (dataset, value) => {
                if (value === null || value === undefined) {
                    return '';
                }

                return dataset.yAxisID === 'y' ? formatMoney(value) : numberFormatter.format(value);
            };
            const isBarChart = config.type === 'bar';
            const dates = Array.isArray(config.dates) ? config.dates.map(parseIsoDate) : [];
            const shortDate = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric' });
            const longDate = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric', year: 'numeric' });
            const labels = (config.labels || []).map((label, index) => (dates[index] ? shortDate.format(dates[index]) : label));
            const tickFont = { family: fontFamily, size: 12 };
            const scaleDefaults = {
                beginAtZero: true,
                border: {
                    display: false,
                },
                ticks: {
                    color: muted,
                    precision: 0,
                    font: tickFont,
                    padding: 8,
                    ...(interactive ? { maxTicksLimit: 5 } : {}),
                },
            };
            const valueGrid = {
                color: border,
                drawTicks: false,
            };
            const scales = config.dualAxis
                ? {
                    y: {
                        ...scaleDefaults,
                        ticks: {
                            ...scaleDefaults.ticks,
                            callback: (value) => (hasMinorUnits
                                ? currencyAxisFormatter.format(Number(value) / minorUnitScale)
                                : value),
                        },
                        title: {
                            display: !interactive,
                            color: muted,
                            text: config.axisLabels?.revenue || '',
                        },
                        grid: valueGrid,
                    },
                    y1: {
                        ...scaleDefaults,
                        position: 'right',
                        title: {
                            display: !interactive,
                            color: muted,
                            text: config.axisLabels?.orders || '',
                        },
                        grid: {
                            drawOnChartArea: false,
                            drawTicks: false,
                        },
                    },
                }
                : {
                    y: {
                        ...scaleDefaults,
                        grid: valueGrid,
                    },
                };

            const datasets = (config.datasets || []).map((dataset) => {
                const datasetIsBar = dataset.type === 'bar' || (!dataset.type && isBarChart);

                if (interactive) {
                    return interactiveDataset(dataset, { isBar: datasetIsBar, surface });
                }

                const backgroundColor = datasetIsBar
                    ? dataset.barBackgroundColor ?? dataset.backgroundColor
                    : dataset.backgroundColor;
                const borderColor = datasetIsBar
                    ? dataset.barBorderColor ?? dataset.borderColor
                    : dataset.borderColor;
                const pointRadius = datasetIsBar
                    ? 0
                    : buildLinePointRadii(dataset.data, dataset.pointMarkerRadius ?? 3);

                return {
                    ...dataset,
                    borderColor: resolveCssColor(borderColor),
                    backgroundColor: resolveCssColor(backgroundColor),
                    barBackgroundColor: resolveCssColor(dataset.barBackgroundColor),
                    barBorderColor: resolveCssColor(dataset.barBorderColor),
                    pointBackgroundColor: resolveCssColor(dataset.pointBackgroundColor),
                    pointBorderColor: resolveCssColor(dataset.pointBorderColor),
                    pointHoverBackgroundColor: resolveCssColor(dataset.pointHoverBackgroundColor),
                    pointHoverBorderColor: resolveCssColor(dataset.pointHoverBorderColor),
                    borderWidth: datasetIsBar ? dataset.barBorderWidth ?? 0 : dataset.borderWidth ?? 2,
                    pointRadius,
                    pointHoverRadius: datasetIsBar ? 0 : dataset.pointHoverRadius ?? 5,
                    pointHitRadius: datasetIsBar ? 0 : dataset.pointHitRadius ?? 10,
                    borderRadius: datasetIsBar ? dataset.barBorderRadius ?? 0 : dataset.borderRadius,
                    borderSkipped: datasetIsBar ? false : dataset.borderSkipped,
                };
            });

            try {
                this.chart = new Chart(canvas, {
                    type: config.type || 'line',
                    data: {
                        labels,
                        datasets,
                    },
                    plugins: [cursorPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 8,
                                right: 4,
                            },
                        },
                        animation: config.animate === false || prefersReducedMotion()
                            ? false
                            : { duration: 220 },
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            agCursor: {
                                enabled: interactive && !isBarChart,
                                color: border,
                            },
                            legend: {
                                display: config.showLegend === true,
                                position: 'bottom',
                                labels: {
                                    color: muted,
                                    font: tickFont,
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    padding: 16,
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                },
                            },
                            tooltip: {
                                enabled: false,
                                external: externalTooltip(formatValue),
                                callbacks: {
                                    title(items) {
                                        const index = items[0]?.dataIndex;

                                        return dates[index] ? longDate.format(dates[index]) : (items[0]?.label ?? '');
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
                                    font: tickFont,
                                    maxRotation: 0,
                                    autoSkipPadding: 16,
                                    padding: 8,
                                },
                            },
                            ...scales,
                        },
                    },
                });
            } finally {
                // Hide the boot skeleton once Chart.js drew (or failed), so it never lingers.
                if (canvas.parentElement) {
                    canvas.parentElement.dataset.chartState = 'ready';
                }
            }
        },
        destroy() {
            this.themeObserver?.disconnect();
            this.themeObserver = null;

            if (this.themeFrame !== null) {
                window.cancelAnimationFrame(this.themeFrame);
                this.themeFrame = null;
            }

            this.chart?.destroy();
            this.chart = null;
        },
    }));
}
