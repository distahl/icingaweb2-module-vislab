;(function (Icinga) {

    'use strict';

    var Vislab = function (module) {
        this.module = module;
        this.initialize();
    };

    Vislab.prototype = {
        initialize: function () {

            this.module.on('rendered', this.rendered);
            ChartBundle.disableCSSInjection = true;

        },

        rendered: function (ev) {


            var $container = $(ev.currentTarget);


            $container.find('.chartjs-icingachart').each(function (i, el) {
                let $chartElement = $(el);
                let chartDataJson = $chartElement.attr("data-json");
                let ctx = $chartElement[0].getContext("2d");
                let chartId = $chartElement.attr("id");
                var aChart = ChartBundle.getChart(ctx);
                if (aChart) {
                    aChart.destroy();
                }
                // Parse config from data-json
                let chartConfig = JSON.parse(chartDataJson);

                // Update legend.onClick — no structural change needed for Chart.js 4
                chartConfig.options.plugins = chartConfig.options.plugins || {};
                chartConfig.options.plugins.legend = chartConfig.options.plugins.legend || {};
                chartConfig.options.plugins.legend.onClick = function(e, legendItem, legend) {
                    e.native.stopPropagation(); // v4: access native event

                    const index = legendItem.datasetIndex;
                    const chart = legend.chart;

                    chart.data.datasets[index].hidden = !chart.data.datasets[index].hidden;
                    chart.update();

                    const id = e.native.target.id;
                    const name = "#" + id + "_href_" + index;
                    jQuery(name).click();
                };


                chartConfig.options.plugins.zoom.zoom.onZoomComplete = function( {chart} ) {
                    const id = $(chart.canvas).attr('id');

                    const name = "#" + id + "_href_" + "zoom";

                    const url = new URL(jQuery(name).attr('href'), window.location.origin);


                    url.searchParams.set('zoomFrom', chart.scales.x.min);
                    url.searchParams.set('zoomTo', chart.scales.x.max);
                    const query = Array.from(url.searchParams.entries())
                        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
                        .join('&');

                    const relativeUrl = url.pathname + '?' + query;
                    jQuery(name).attr('href',relativeUrl)

                    jQuery(name).click();

// Redirect to the new URL

                    //window.location.href = url.toString();
                };


                chartConfig.options.scales = chartConfig.options.scales || {};
                chartConfig.options.scales.y = chartConfig.options.scales.y || {};
                chartConfig.options.scales.y.ticks = {
                    callback: function(value, index, ticks) {
                        // Determine max precision from the tick values
                        let maxDecimals = 0;
                        for (const tick of ticks) {
                            const val = tick.value.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 6,
                                useGrouping: false
                            });
                            const parts = val.toString().split('.');
                            if (parts.length === 2) {
                                const decimals = parts[1].replace(/0+$/, '').length;
                                maxDecimals = Math.max(maxDecimals, decimals);
                            }
                        }

                        // Always show at least 3 decimal places
                        const decimalsToShow = Math.max(maxDecimals, 0);

                        return value.toFixed(decimalsToShow);
                    }
                };
                chartConfig.options.plugins = chartConfig.options.plugins || {};
                chartConfig.options.plugins.tooltip = chartConfig.options.plugins.tooltip || {};
                chartConfig.options.plugins.tooltip.callbacks = chartConfig.options.plugins.tooltip.callbacks || {};
                chartConfig.options.plugins.tooltip.callbacks.label = function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    return label + Number(context.raw).toLocaleString('en-US', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 6,
                        useGrouping: false
                    });
                };
                // Initialize Chart.js
                aChart = new ChartBundle(ctx, {

                    type: chartConfig.type,
                    data: chartConfig.data,
                    options: chartConfig.options
                });

                const urlParams = new URLSearchParams(location.href);
                const zoomFrom = parseInt($chartElement.attr("data-zoom-from"));
                const zoomTo = parseInt($chartElement.attr("data-zoom-to"));
                if (!isNaN(zoomFrom) && !isNaN(zoomTo)  && zoomTo > 0) {
                    aChart.zoomScale('x', {
                        min: zoomFrom,
                        max: zoomTo,
                    });


                }


                console.log(`Initialized chart: ${chartId}`);
            });
        },


    };

    Icinga.availableModules.vislab = Vislab;


}(Icinga));
