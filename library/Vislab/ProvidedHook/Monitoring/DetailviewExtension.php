<?php

namespace Icinga\Module\Vislab\ProvidedHook\Monitoring;


use Icinga\Application\Logger;
use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Vislab\Helpers\GrapherHelper;
use ipl\Html\Html;
use Throwable;


class DetailviewExtension extends DetailviewExtensionHook
{

    protected $hasPreviews = true;


    public function init()
    {

        parent::init();
    }

    public function has(MonitoredObject $object)
    {
        if (($object instanceof Host) || ($object instanceof Service)) {
            return true;
        } else {
            return false;
        }
    }


    public function getHtmlForObject(MonitoredObject $object)
    {

        $service = null;

        if($object instanceof Host){
            $host = $object->getName();
        }else{
            $service = $object->getName();
            $host=$object->host_name;
        }
        $check_command = $object->check_command;
        try {
            $grapher = new GrapherHelper($host,$check_command,false,$object->perfdata,$service);
            return Html::tag('div',['name'=>'vislab-monitoring'],$grapher->getHtmlForObject())->render();
        }catch (Throwable $exception){
            Logger::error($exception->getMessage());
            Logger::error($exception->getTraceAsString());
        }


    }


}