<?php
/** @var $this \Icinga\Application\Modules\Module */

use Icinga\Application\Modules\Module;
use Icinga\Module\Vislab\Helpers\DashboardBackendHelper;

require_once 'vendor/autoload.php';

$this->provideHook('Monitoring/DetailviewExtension');
$this->provideHook('Icingadb/ServiceDetailExtension');
$this->provideHook('Icingadb/HostDetailExtension');


$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\VictoriaMetricsConnection');
$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\InfluxDb1Connection');
$this->provideHook('Vislab\\ResourceConnection', '\Icinga\Module\Vislab\ProvidedHook\Vislab\InfluxDb2Connection');

$dashboardBackends = DashboardBackendHelper::getFromConfig();

$backendDefinitions = [
    'monitoring' => [
        'module'     => 'monitoring',
        'route'      => 'vislab/ido-dashboard',
        'controller' => 'ido-dashboard',
    ],
    'icingadb' => [
        'module'     => 'icingadb',
        'route'      => 'vislab/icingadb-dashboard',
        'controller' => 'icingadb-dashboard',
    ],
];

$preferredController = null;

foreach (DashboardBackendHelper::VALID_BACKENDS as $key) {
    if (!isset($dashboardBackends[$key]) || !isset($backendDefinitions[$key]) || !Module::exists($backendDefinitions[$key]['module'])) {
        continue;
    }
    $def = $backendDefinitions[$key];
    $routeParams = [
        'controller' => $def['controller'],
        'action'     => 'index',
        'module'     => 'vislab',
    ];
    $this->addRoute($def['route'], new Zend_Controller_Router_Route_Static($def['route'], $routeParams));
    $this->addRoute($def['route'] . '/index', new Zend_Controller_Router_Route_Static(
        $def['route'] . '/index',
        $routeParams
    ));
    if ($preferredController === null) {
        $preferredController = $def['controller'];
    }
}

if ($preferredController !== null) {
    $defaultParams = [
        'controller' => $firstController,
        'action'     => 'index',
        'module'     => 'vislab',
    ];
    $this->addRoute('vislab/dashboard', new Zend_Controller_Router_Route_Static('vislab/dashboard', $defaultParams));
    $this->addRoute('vislab/dashboard/index', new Zend_Controller_Router_Route_Static(
        'vislab/dashboard/index',
        $defaultParams
    ));
}