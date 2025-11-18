# Usage <a id="module-vislab-usage"></a>

## Module Usage  <a id="module-vislab-usage-module"></a>

![detailview.png](img/detailview.png)

This module has works similar to the grafana module.

You can add the graph to a dashboard, choose a range and a metric.
By default, the first metric will be rendered with a range of -6 hours.

For measurements that produce less data like `check_interval = 1d` the interval will be extended
till there are more than 10 values.

Thresholds and the unit are generated using the current performance data if the unit is not provided by the backend, 
so this works on influxdb as well as victoriametrics

