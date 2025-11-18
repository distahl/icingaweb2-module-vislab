# Icinga Web 2 Vislab Module Changelog

## What's New

### What's New in Version 0.9.9

* do not break in case the metric thresholds or units are not available
* min height of chart is now 20em which looks nice on a mobile device

### What's New in Version 0.9.8

* fix remove urlencode on metric because of possible + sign
* victoriametrix allow multiple metrics that matches query to be merged (json line export)

### What's New in Version 0.9.7

* Use own function in influxdb2 reader to escape strings for the query

### What's New in Version 0.9.6

* Use jsonencode in influxdb2 reader to escape strings for the query

### What's New in Version 0.9.5

* bugfix create new resource throws exception

### What's New in Version 0.9.4

* better rendering of roundable values in the left scale
* fix victoriametrics on some commandnames
* fix unit in thresholds displayed twice
* fix hidden threshold can show as undefined after metric change

### What's New in Version 0.9.3

* migrate command to migrate from old influxdb module
* fix urlencoding bug in zoom

### What's New in Version 0.9.2

* use PlainHtml directly and not from cpl

### What's New in Version 0.9.1

* cli actions to configure module
* changelog

### What's New in Version 0.9.0

* Initial release
* victoriametrics backend
* influxdb1 backend
* influxdb2 backend
* perfdata parser
* chartJs 4.4.9
* chartjs-zoom-plugin
* CSP compatible