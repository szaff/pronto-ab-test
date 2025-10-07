/**
 * Pronto A/B Analytics Dashboard JavaScript
 * 
 * Handles chart rendering, date filters, and CSV export
 */

(function ($) {
    'use strict';

    const ProntoAnalytics = {

        _initialized: false,

        /**
         * Initialize analytics dashboard
         */
        init: function () {

            if (this._initialized) {
                console.log('ProntoAnalytics already initialized, skipping');
                return;
            }

            console.log('ProntoAnalytics init called');

            if (typeof prontoAbAnalyticsData === 'undefined') {
                console.warn('prontoAbAnalyticsData not found');
                return;
            }

            console.log('Analytics data:', prontoAbAnalyticsData);

            this.initCharts();
            this.initDateFilters();
            this.initExport();
            this.initCampaignSelector();

            this._initialized = true;
        },

        /**
         * Initialize Chart.js charts
         */
        initCharts: function () {
            console.log('Initializing charts');

            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }

            // Conversion Rate Comparison Chart
            this.renderConversionRateChart();

            // Time Series Chart
            this.renderTimeSeriesChart();

            // Goal Charts
            this.renderGoalConversionChart();
            this.renderGoalRevenueChart();
        },

        /**
         * Render conversion rate comparison bar chart
         */
        renderConversionRateChart: function () {
            const ctx = document.getElementById('conversion-rate-chart');
            if (!ctx) {
                console.warn('Conversion rate chart canvas not found');
                return;
            }

            const data = prontoAbAnalyticsData.variations;

            const labels = data.map(v => v.name);
            const conversionRates = data.map(v => v.conversion_rate);
            const impressions = data.map(v => v.impressions);
            const conversions = data.map(v => v.conversions);

            // Color code: control = blue, winner = green, others = gray
            const backgroundColors = data.map(v => {
                if (v.is_winner) return 'rgba(70, 180, 80, 0.8)';
                if (v.is_control) return 'rgba(0, 115, 170, 0.8)';
                return 'rgba(128, 128, 128, 0.6)';
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Conversion Rate (%)',
                        data: conversionRates,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(c => c.replace('0.8', '1').replace('0.6', '1')),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const index = context.dataIndex;
                                    return [
                                        `Conversion Rate: ${context.parsed.y.toFixed(2)}%`,
                                        `Impressions: ${impressions[index].toLocaleString()}`,
                                        `Conversions: ${conversions[index].toLocaleString()}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Conversion Rate (%)'
                            }
                        }
                    }
                }
            });

            console.log('Conversion rate chart rendered');
        },

        /**
         * Render time series line chart
         */
        renderTimeSeriesChart: function () {
            const ctx = document.getElementById('time-series-chart');
            if (!ctx) {
                console.warn('Time series chart canvas not found');
                return;
            }

            const timeSeriesData = prontoAbAnalyticsData.timeSeries;

            const datasets = timeSeriesData.map(series => {
                const color = series.is_control ?
                    'rgb(0, 115, 170)' :
                    'rgb(' + Math.floor(Math.random() * 200) + ',' +
                    Math.floor(Math.random() * 200) + ',' +
                    Math.floor(Math.random() * 200) + ')';

                return {
                    label: series.variation_name,
                    data: series.data.map(d => ({
                        x: d.date,
                        y: d.conversion_rate
                    })),
                    borderColor: color,
                    backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
                    tension: 0.3,
                    fill: false
                };
            });

            new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                displayFormats: {
                                    day: 'MMM d'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Conversion Rate (%)'
                            }
                        }
                    }
                }
            });

            console.log('Time series chart rendered');
        },

        /**
         * Render goal conversion rate chart
         */
        renderGoalConversionChart: function () {
            const ctx = document.getElementById('goal-conversion-chart');
            if (!ctx) {
                console.log('Goal conversion chart canvas not found - skipping');
                return;
            }

            const data = prontoAbAnalyticsData.variations;

            // Check if there are any goals
            if (!data[0] || !data[0].goal_stats || data[0].goal_stats.length === 0) {
                console.log('No goal data available');
                return;
            }

            const labels = data.map(v => v.name);
            const goalConversionRates = data.map(v => v.goal_conversion_rate);

            const backgroundColors = data.map(v => {
                if (v.is_winner) return 'rgba(140, 200, 75, 0.8)';
                if (v.is_control) return 'rgba(75, 140, 200, 0.8)';
                return 'rgba(150, 150, 150, 0.6)';
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Goal Conversion Rate (%)',
                        data: goalConversionRates,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(c => c.replace('0.8', '1').replace('0.6', '1')),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const variation = data[context.dataIndex];
                                    return [
                                        'Goal Conversion Rate: ' + context.parsed.y.toFixed(2) + '%',
                                        'Goal Conversions: ' + variation.goal_conversions,
                                        'Total Impressions: ' + variation.impressions
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Goal Conversion Rate (%)'
                            }
                        }
                    }
                }
            });

            console.log('Goal conversion chart rendered');
        },

        /**
         * Render goal revenue chart
         */
        renderGoalRevenueChart: function () {
            const ctx = document.getElementById('goal-revenue-chart');
            if (!ctx) {
                console.log('Goal revenue chart canvas not found - skipping');
                return;
            }

            const data = prontoAbAnalyticsData.variations;

            // Check if there's any revenue data
            const hasRevenue = data.some(v => v.goal_revenue && v.goal_revenue > 0);
            if (!hasRevenue) {
                console.log('No goal revenue data available');
                return;
            }

            const labels = data.map(v => v.name);
            const revenues = data.map(v => v.goal_revenue || 0);

            const backgroundColors = data.map(v => {
                if (v.is_winner) return 'rgba(76, 175, 80, 0.8)';
                if (v.is_control) return 'rgba(33, 150, 243, 0.8)';
                return 'rgba(158, 158, 158, 0.6)';
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: revenues,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(c => c.replace('0.8', '1').replace('0.6', '1')),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const value = context.parsed.y;
                                    return 'Revenue: $' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            ticks: {
                                callback: function (value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });

            console.log('Goal revenue chart rendered');
        },

        /**
         * Initialize date filter buttons
         */
        initDateFilters: function () {
            console.log('Initializing date filters');

            $('.quick-date').on('click', function () {
                const days = $(this).data('days');
                const today = new Date();
                const fromDate = new Date(today.getTime() - (days * 24 * 60 * 60 * 1000));

                $('#date-from').val(fromDate.toISOString().split('T')[0]);
                $('#date-to').val(today.toISOString().split('T')[0]);

                $('#analytics-filter-form').submit();
            });
        },

        /**
         * Initialize CSV export
         */
        initExport: function () {
            console.log('Initializing CSV export');

            $('#export-csv').on('click', function (e) {
                e.preventDefault();
                console.log('Export button clicked!');

                if (typeof prontoAbAnalyticsData === 'undefined') {
                    alert('No data available to export');
                    console.error('prontoAbAnalyticsData is undefined');
                    return;
                }

                const campaignId = prontoAbAnalyticsData.campaignId;
                const dateFrom = prontoAbAnalyticsData.dateFrom;
                const dateTo = prontoAbAnalyticsData.dateTo;
                const variations = prontoAbAnalyticsData.variations;

                console.log('Exporting data for campaign:', campaignId);

                // Create CSV header
                let csv = 'Campaign Analytics Report\n';
                csv += 'Campaign ID: ' + campaignId + '\n';
                csv += 'Date Range: ' + dateFrom + ' to ' + dateTo + '\n';
                csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';

                // Column headers
                csv += 'Variation,Type,Impressions,Conversions,Conversion Rate (%),Unique Visitors,Traffic Split (%),Lift (%)' + '\n';

                // Data rows
                variations.forEach(function (v) {
                    const type = v.is_control ? 'Control' : 'Variation';
                    const lift = v.lift !== undefined ? v.lift.toFixed(2) : '0.00';

                    csv += '"' + v.name + '",' +
                        type + ',' +
                        v.impressions + ',' +
                        v.conversions + ',' +
                        v.conversion_rate.toFixed(2) + ',' +
                        v.unique_visitors + ',' +
                        v.weight.toFixed(1) + ',' +
                        lift + '\n';
                });

                console.log('CSV content created, length:', csv.length);

                // Create and download file
                try {
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'campaign-' + campaignId + '-analytics-' + dateFrom + '-to-' + dateTo + '.csv';

                    // Append to body, click, and remove
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Release the object URL
                    window.URL.revokeObjectURL(url);

                    console.log('CSV file download triggered successfully');

                    // Show success message if ProntoABAdmin is available
                    if (typeof ProntoABAdmin !== 'undefined' && ProntoABAdmin.showNotice) {
                        ProntoABAdmin.showNotice('success', 'CSV exported successfully!', 3000);
                    } else {
                        alert('CSV exported successfully!');
                    }
                } catch (error) {
                    console.error('Export error:', error);
                    alert('Error creating CSV file: ' + error.message);
                }
            });
        },

        /**
         * Initialize campaign selector auto-submit
         */
        initCampaignSelector: function () {
            console.log('Initializing campaign selector');

            $('#campaign-select').on('change', function () {
                console.log('Campaign changed, submitting form');
                $('#analytics-filter-form').submit();
            });
        }
    };

    // Initialize when document ready
    $(document).ready(function () {
        console.log('Document ready, initializing ProntoAnalytics');
        ProntoAnalytics.init();
    });

    // Expose to global scope for debugging
    window.ProntoAnalytics = ProntoAnalytics;

})(jQuery);