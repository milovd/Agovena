import Chart from 'chart.js/auto';

document.addEventListener('alpine:init', () => {
    Alpine.data('agChart', (config) => ({
        chart: null,
        init() {
            if (! this.$refs.canvas) {
                return;
            }

            this.chart = new Chart(this.$refs.canvas, {
                type: config.type || 'line',
                data: {
                    labels: config.labels || [],
                    datasets: (config.datasets || []).map((dataset) => ({
                        ...dataset,
                        borderWidth: dataset.borderWidth ?? 2,
                        pointRadius: dataset.pointRadius ?? 2,
                        pointHoverRadius: dataset.pointHoverRadius ?? 4,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#e2e8f0',
                            padding: 10,
                            cornerRadius: 8,
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: '#64748b',
                                maxRotation: 0,
                                autoSkipPadding: 12,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(15, 23, 42, 0.06)',
                            },
                            ticks: {
                                color: '#64748b',
                                precision: 0,
                            },
                        },
                    },
                },
            });
        },
        destroy() {
            this.chart?.destroy();
            this.chart = null;
        },
    }));
});
