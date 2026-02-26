<?php

namespace Icinga\Module\Vislab\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Vislab\Helpers\DashboardBackendHelper;
use Icinga\Module\Vislab\Helpers\GrapherHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class HostDetailExtension extends HostDetailExtensionHook
{
    public function getHtmlForObject(Host $host, bool $isHookContext = true): ValidHtml
    {
        if (!DashboardBackendHelper::isEnabled('icingadb')) {
            return Html::tag('div', ['name' => 'vislab-icingadb']);
        }
        $hostname = $host->name;
        $servicename = null;
        $perfdata = $host->state->performance_data;
        $command_name = $host->checkcommand_name;


        $grapher = new GrapherHelper($hostname,$command_name,true,$perfdata,$servicename);
        $attrs = ['name'=>'vislab-icingadb'];
        if (($s = DashboardBackendHelper::getHookContainerStyle($isHookContext)) !== '') $attrs['style'] = $s;
        return Html::tag('div',$attrs,$grapher->getHtmlForObject());
    }
}