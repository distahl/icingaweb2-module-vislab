<?php

/* Icinga Reporting | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Vislab\Clicommands;

use Icinga\Application\Hook;
use Icinga\Application\Modules\Module;

use Icinga\Module\Reporting\Cli\Command;
use Icinga\Module\Vislab\Hook\ResourceFormHook;


class MigrateCommand extends Command
{
    /* @var Module */
    protected $module;
    /* @var Module */
    protected $influxdbModule;
    public function init()
    {
        parent::init();
        $this->module = Module::get($this->getModuleName());
        $this->influxdbModule = Module::get("influxdb");
    }

    public function defaultAction()
    {
        $schema = $this->params->get("schema","http");
        $oldData = $this->influxdbModule->getConfig()->getSection('settings')->toArray();
        $data =[];
        if($oldData['apiversion'] == "v2"){
            $connectionString = $schema."://".$oldData['host'].":".$oldData['port'];
            $data['connectionstring']=$connectionString;
            $data['token']=$oldData['token'];
            $data['bucket']=$oldData['bucket'];
            $data['organization']=$oldData['organization'];
            $data['connection']='Icinga\\Module\\Vislab\\ProvidedHook\\Vislab\\InfluxDb2Connection';
            $name = "influxdb2";

        }elseif ($oldData['apiversion'] == "v1"){
            $data['host']=$oldData['host'];
            $data['port']=$oldData['port'];
            $data['password']=$oldData['password'];
            $data['database']=$oldData['database'];
            $data['connection']='Icinga\\Module\\Vislab\\ProvidedHook\\Vislab\\InfluxDb1Connection';
            $name = "influxdb1";
        }else{
            echo "unsupported version\n";
            exit(3);
        }
        $this->module->getConfig('resources')->setSection($name,$data)->saveIni();
        echo "Resource $name created\n";

        $data = $this->module->getConfig('config')->getSection('settings')->toArray();
        $data['backend'] = $name;
        $this->module->getConfig('config')->setSection('settings',$data)->saveIni();
        echo "Updated backend!\n";

        $dashboardbackend = $this->params->get('dashboardbackend', 'monitoring');
        if($dashboardbackend !== null){
            if($dashboardbackend != "monitoring" && $dashboardbackend != "icingadb"){
                echo "invalid dashboardbackend, use monitoring or icingadb\n";
                exit(3);
            }
            $data = $this->module->getConfig('config')->getSection('settings')->toArray();
            $data['dashboardbackend'] = $dashboardbackend;
            $this->module->getConfig('config')->setSection('settings',$data)->saveIni();
            echo "Updated dashboardbackend!\n";
        }
    }

}
