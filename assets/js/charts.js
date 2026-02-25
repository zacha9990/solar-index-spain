/* global sisChartData, Chart */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    if (typeof sisChartData === 'undefined') return;

    var isCapacity = sisChartData.type === 'capacity';
    var labels     = sisChartData.labels  || [];
    var values     = sisChartData.values  || [];

    var colorPrimary   = '#2E86C1';
    var colorSecondary = '#AED6F1';
    var colorGreen     = '#1E8449';
    var colorGreenFade = '#ABEBC6';
    var colorOrange    = '#E67E22';

    // Shared defaults
    Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
    Chart.defaults.font.size   = 13;

    // ── CHART 1: 24-Month Trend ──────────────────────────────────────────
    var el1 = document.getElementById('chart-trend-24m');
    if (el1 && labels.length) {
        new Chart(el1, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: isCapacity ? 'Installed Capacity (GW)' : 'Solar Generation (GWh)',
                    data: values,
                    borderColor: colorPrimary,
                    backgroundColor: 'rgba(46, 134, 193, 0.08)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' +
                                    ctx.parsed.y.toLocaleString(undefined, { minimumFractionDigits: isCapacity ? 3 : 0, maximumFractionDigits: isCapacity ? 3 : 0 }) +
                                    (isCapacity ? ' GW' : ' GWh');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function (v) {
                                return isCapacity ? v.toLocaleString() + ' GW' : v.toLocaleString() + ' GWh';
                            }
                        }
                    }
                }
            }
        });
    }

    // ── CHART 2: YoY Comparison (generation) / Monthly Additions (capacity)
    var el2 = document.getElementById('chart-yoy');
    if (el2) {
        var monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        if (isCapacity) {
            // Bar chart of monthly additions over 24m
            new Chart(el2, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Addition (GW)',
                        data: sisChartData.additions || [],
                        backgroundColor: function (ctx) {
                            var v = ctx.raw;
                            return v === null ? 'transparent' : (v >= 0 ? colorPrimary : colorOrange);
                        },
                        borderRadius: 3,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return 'Addition: ' + (ctx.parsed.y >= 0 ? '+' : '') + ctx.parsed.y.toFixed(3) + ' GW';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: { callback: function (v) { return v.toFixed(2) + ' GW'; } }
                        }
                    }
                }
            });
        } else {
            // YoY bar chart for generation
            var thisYear = (sisChartData.yoy || {}).thisYear || Array(12).fill(null);
            var lastYear = (sisChartData.yoy || {}).lastYear || Array(12).fill(null);

            new Chart(el2, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [
                        {
                            label: String(sisChartData.currentYear - 1),
                            data: lastYear,
                            backgroundColor: colorSecondary,
                            borderRadius: 2,
                        },
                        {
                            label: String(sisChartData.currentYear),
                            data: thisYear,
                            backgroundColor: colorPrimary,
                            borderRadius: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function (v) { return v.toLocaleString() + ' GWh'; } }
                        }
                    }
                }
            });
        }
    }

    // ── CHART 3: YTD Cumulative ──────────────────────────────────────────
    var el3 = document.getElementById('chart-ytd');
    if (el3) {
        var monthLabels3  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var ytdLabels     = monthLabels3.slice(0, sisChartData.currentMonth || 12);
        var ytdThisYear   = ((sisChartData.ytd || {}).thisYear || []);
        var ytdLastYear   = ((sisChartData.ytd || {}).lastYear || []);
        var ytdUnit       = isCapacity ? ' GW added' : ' GWh';
        var ytdLabel1     = (isCapacity ? 'YTD additions ' : 'YTD generation ') + String(sisChartData.currentYear);
        var ytdLabel2     = (isCapacity ? 'YTD additions ' : 'YTD generation ') + String(sisChartData.currentYear - 1);

        new Chart(el3, {
            type: 'line',
            data: {
                labels: ytdLabels,
                datasets: [
                    {
                        label: ytdLabel1,
                        data: ytdThisYear,
                        borderColor: colorGreen,
                        backgroundColor: 'transparent',
                        pointRadius: 3,
                        tension: 0.2,
                    },
                    {
                        label: ytdLabel2,
                        data: ytdLastYear,
                        borderColor: colorGreenFade,
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        pointRadius: 3,
                        tension: 0.2,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: isCapacity,
                        ticks: {
                            callback: function (v) {
                                return v.toLocaleString() + ytdUnit;
                            }
                        }
                    }
                }
            }
        });
    }
});
