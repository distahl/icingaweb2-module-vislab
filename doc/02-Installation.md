# Installation <a id="module-vislab-installation"></a>

## Requirements <a id="module-vislab-installation-requirements"></a>

* Icinga Web 2 (&gt;= 2.12.3)
* PHP (&gt;= 7.3)
* php-json, gnuplot (optional)

The Icinga Web 2 `monitoring` or `icingadb` module needs to be configured and enabled.

## Installation from .tar.gz <a id="module-vislab-installation-manual"></a>

Download the latest version and extract it to a folder named `vislab`
in one of your Icinga Web 2 module path directories.

## Enable the newly installed module <a id="module-vislab-installation-enable"></a>

Enable the `vislab` module either on the CLI by running

```sh
icingacli module enable vislab
```

Or go to your Icinga Web 2 frontend, choose `Configuration` -&gt; `Modules`, chose the `vislab` module and `enable` it.

It might afterward be necessary to refresh your web browser to be sure that
newly provided styling is loaded.

## Optional, install gnuplot <a id="module-vislab-installation-gnuplot"></a>

To use the experimental javascript-less implementation you need to install gnuplot using your package-manager

## Optional, rebuild ChartJs Bundle <a id="module-vislab-installation-rebuild"></a>

This Module includes a bundle (microbundle) that contains:
* ChartJs 4.*
* chartjs-plugin-zoom
* hammerjs

Install your npm environment, after that you can rebuild the bundle using the following command

```sh
sudo icingacli vislab build chartjs
```