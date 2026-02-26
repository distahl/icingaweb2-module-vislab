<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Vislab\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Icingadb\Web\Controller;

use Icinga\Module\Vislab\ProvidedHook\Icingadb\HostDetailExtension;
use Icinga\Module\Vislab\ProvidedHook\Icingadb\ServiceDetailExtension;
use ipl\Stdlib\Filter;


class IcingadbDashboardController extends Controller
{

    /** @var Host The host object */
    protected $host;
    /** @var Service The service object */

    protected $service;

    public function init()
    {
        $this->setAutorefreshInterval(60);

        $hostname = $this->params->getRequired('host');

        $query = Host::on($this->getDb())->with(['state']);
        $query
            ->setResultSetClass(VolatileStateResults::class)
            ->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);

        /** @var Host $host */
        $host = $query->first();
        if ($host === null) {
            throw new NotFoundError(t('Host not found'));
        }

        $this->host = $host;
        $servicename = $this->params->get('service');
        if($servicename != null){
            $query = Service::on($this->getDb())->with([
                'state',
                'icon_image',
                'host',
                'host.state',
                'timeperiod'
            ]);
            $query
                ->setResultSetClass(VolatileStateResults::class)
                ->filter(Filter::all(
                    Filter::equal('service.name', $servicename),
                    Filter::equal('host.name', $hostname)
                ));

            $this->applyRestrictions($query);

            /** @var Service $service */
            $service = $query->first();
            if ($service === null) {
                throw new NotFoundError(t('Service not found'));
            }

            $this->service = $service;

        }

    }

    public function indexAction()
    {
        $this->getTabs()->add('graphs', array(
            'active' => true,
            'label' => $this->translate('Graphs'),
            'url' => $this->getRequest()->getUrl()
        ));


        if ($this->service != null) {
            $graph = new ServiceDetailExtension();
            $object = $this->service;
        } else {
            $graph = new HostDetailExtension();
            $object = $this->host;
        }
        $this->addContent($graph->getHtmlForObject($object, false));


    }

}
