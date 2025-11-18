<?php
/** @var $this \Icinga\Application\Modules\Module */

use Icinga\Application\Config;
use Icinga\Application\Modules\Module;

require_once 'vendor/autoload.php';

$this->provideHook('Monitoring/DetailviewExtension');
$this->provideHook('Icingadb/ServiceDetailExtension');
$this->provideHook('Icingadb/HostDetailExtension');


$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\VictoriaMetricsConnection');
$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\InfluxDb1Connection');
$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\InfluxDb2Connection');

$dashboardBackend = Config::module('vislab')->get('settings','dashboardbackend','monitoring');

if (Module::exists('monitoring') && $dashboardBackend == 'monitoring' ) {

    $this->addRoute('vislab/dashboard', new Zend_Controller_Router_Route_Static(
        'vislab/dashboard',
        [
            'controller'    => 'ido-dashboard',
            'action'        => 'index',
            'module'        => 'vislab'
        ]
    ));
    $this->addRoute('vislab/dashboard/index', new Zend_Controller_Router_Route_Static(
        'vislab/dashboard/index',
        [
            'controller'    => 'ido-dashboard',
            'action'        => 'index',
            'module'        => 'vislab'
        ]
    ));
}
if (Module::exists('icingadb') && $dashboardBackend == 'icingadb' ) {

    $this->addRoute('vislab/dashboard', new Zend_Controller_Router_Route_Static(
        'vislab/dashboard',
        [
            'controller'    => 'icingadb-dashboard',
            'action'        => 'index',
            'module'        => 'vislab'
        ]
    ));
    $this->addRoute('vislab/dashboard/index', new Zend_Controller_Router_Route_Static(
        'vislab/dashboard/index',
        [
            'controller'    => 'icingadb-dashboard',
            'action'        => 'index',
            'module'        => 'vislab'
        ]
    ));
}