<?php

namespace Icinga\Module\Vislab\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Vislab\ProvidedHook\Monitoring\DetailviewExtension;


class IdoDashboardController extends Controller
{

    /** @var Host | Service The  object */
    protected $object;


    public function init()
    {
        //$this->assertPermission('grafana/graph');
        $this->setAutorefreshInterval(60);

        $hostname = $this->params->getRequired('host');
        $servicename = $this->params->get('service');

        if ($servicename != null) {
            $this->object = new Service($this->backend, $hostname, $servicename);
        } else {
            $this->object = new Host($this->backend, $this->params->getRequired('host'));
        }

        $this->applyRestriction('monitoring/filter/objects', $this->object);
        if ($this->object->fetch() === false) {
            $this->httpNotFound($this->translate('Host or Service not found'));
        }
    }

    public function indexAction()
    {
        $this->_helper->viewRenderer->setRender('dashboard/index', null, true);


        $this->getTabs()->add('graphs', array(
            'active' => true,
            'label' => $this->translate('Graphs'),
            'url' => $this->getRequest()->getUrl()
        ));

        $graph = new DetailviewExtension();
        $this->view->content = $graph->getHtmlForObject($this->object);
    }


}
