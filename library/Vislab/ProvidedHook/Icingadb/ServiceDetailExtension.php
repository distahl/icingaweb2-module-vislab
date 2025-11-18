<?php

namespace Icinga\Module\Vislab\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\ServiceDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Service;

use Icinga\Module\Vislab\Helpers\GrapherHelper;

use ipl\Html\Html;
use ipl\Html\ValidHtml;

class ServiceDetailExtension extends ServiceDetailExtensionHook
{
    public function getHtmlForObject(Service $service): ValidHtml
    {
        $hostname = $service->host->name;
        $servicename = $service->name;
        $perfdata = $service->state->performance_data;
        $command_name = $service->checkcommand_name;


        $grapher = new GrapherHelper($hostname,$command_name,true,$perfdata,$servicename);
        return Html::tag('div',['name'=>'vislab-icingadb'],$grapher->getHtmlForObject());
    }
}