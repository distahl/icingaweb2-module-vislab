<?php
$this->providePermission('config/vislab', $this->translate('allow access to lshw configuration'));

//$this->provideJsFile('vendor/Chart.Js.js');
//this->provideJsFile('vendor/hammerjs.js');

//$this->provideJsFile('vendor/Chart.Js-plugin-zoom.min.js');
$this->provideJsFile('vendor/chartbundle.umd.js');
//$this->provideCssFile('vendor/Chart.css');


$this->provideConfigTab('config/backend', array(
    'title' => $this->translate('Backend Configuration'),
    'label' => $this->translate('Backend Configuration'),
    'url' => 'config/backend'
));


$this->provideConfigTab('config/resource', array(
    'title' => $this->translate('Resource Configuration'),
    'label' => $this->translate('Resource Configuration'),
    'url' => 'config/resource'
));


?>