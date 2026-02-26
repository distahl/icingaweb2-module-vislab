<?php

namespace Icinga\Module\Vislab\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Alerthub\Model\Service;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Vislab\ProvidedHook\Icingadb\HostDetailExtension;
use Icinga\Module\Vislab\ProvidedHook\Icingadb\ServiceDetailExtension;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;


class DashboardController extends CompatController
{

    /** @var Host | Service The  object */
    protected $object;
    protected $grapher;
    use Database;
    use Auth;

    public function init()
    {
        //$this->assertPermission('grafana/graph');
        $this->setAutorefreshInterval(60);
        $this->getTabs()->add('graphs', array(
            'active' => true,
            'label' => $this->translate('Graphs'),
            'url' => $this->getRequest()->getUrl()
        ));
        $hostname = $this->params->getRequired('host');
        $servicename = $this->params->get('service');

        if ($servicename != null) {
            $object = Service::on($this->getDb())->with(['host','state']);

            $object->filter(Filter::equal('host.name',$hostname))->filter(Filter::equal('name',$servicename));
            $this->applyRestrictions($object);
            $this->object = $object->first();
            if ($this->object == null) {
                $this->httpNotFound($this->translate('Host or Service not found'));
            }
            $this->grapher = (new ServiceDetailExtension());
        } else {
            $object = Host::on($this->getDb())->with(['state']);

            $object->filter(Filter::equal('name',$hostname));
            $this->applyRestrictions($object);

            $this->object = $object->first();
            if ($this->object == null) {
                $this->httpNotFound($this->translate('Host or Service not found'));

            }
            $this->grapher = (new HostDetailExtension());


        }


    }
    public function indexAction()
    {
        $this->_helper->viewRenderer->setRender('dashboard/index', null, true);
        $this->view->content = $this->grapher->getHtmlForObject($this->object, false);
    }

}
