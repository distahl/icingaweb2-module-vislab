<?php

namespace Icinga\Module\Vislab\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Vislab\MonitoringPie;
use Icinga\Web\Url;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;

class DashboardController extends Controller
{
    public function init()
    {
        $this->setAutorefreshInterval(60);
    }

    protected function addTitleTab($action, $title, $tip)
    {
        $this->getTabs()->add($action, array(
            'title' => $tip,
            'label' => $title,
            'url'   => Url::fromRequest()
        ))->activate($action);
        $this->view->title = $title;
    }

    public function indexAction()
    {
        if ($this->view->compact !== 'compact') {
            $this->getTabs()->extend(new DashboardAction())->extend(new MenuAction());
        }

        $hostname = $this->params->getRequired('host');
        $servicename = $this->params->getRequired('service');


        $object = new Service($this->backend, $hostname, $servicename);


        $this->applyRestriction('monitoring/filter/objects', $object);
        if ($object->fetch() === false) {
            $this->httpNotFound($this->translate('Host or Service not found'));
        }

        $this->addTitleTab(
            'vislab',
            $object->getName(),
            $object->getName()
        );


        $this->view->content = MonitoringPie::getPie($object,true);
    }

}
