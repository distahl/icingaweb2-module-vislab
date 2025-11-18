<?php

/* Icinga Reporting | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Vislab\Clicommands;

use Icinga\Application\Hook;
use Icinga\Application\Modules\Module;

use Icinga\Module\Reporting\Cli\Command;
use Icinga\Module\Vislab\Hook\ResourceFormHook;


class ResourceCommand extends Command
{
    /* @var Module */
    protected $module;
    public function init()
    {
        parent::init();
        $this->module = Module::get($this->getModuleName());
    }
    public function listtypesAction()
    {
        foreach ($this->enumResourceConnections() as $connection){
            echo $connection."\n";
        }

    }
    public function enumResourceConnections()
    {
        $hooks = Hook::all('Vislab\\ResourceConnection');
        /** @var ResourceFormHook $hook */
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getType();

        }

        return $enum;
    }


    public function exportAction()
    {
        $name = $this->params->getRequired('name');
        $resource = $this->module->getConfig('resources')->getSection($name)->toArray();
        if ($resource === null || $resource == []) {
            echo "Entry not found\n";
            exit(1);
        }
        $resource['name']=$name;

        $saveTo = $this->params->get('saveto',"");

        $saveTo = rtrim($saveTo, "/")."$name.json";
        file_put_contents($saveTo,json_encode($resource, JSON_PRETTY_PRINT));
        echo "Export successful to $saveTo\n";


        exit(0);
    }


    public function importAction()
    {
        $json = $this->params->get('json');
        $data=[];
        if(file_exists($json)){
            $jsonContent = file_get_contents($json);
        }else{
            $jsonContent = $json;
        }
        try{
            $data = json_decode($jsonContent,true);
        }catch (\Throwable $e){
            echo "Invalid Json";
            exit(3);
        }
        if(!isset($data['name'])){
            echo "Invalid Json, no name set";
        }
        $name = $data['name'];
        unset($data['name']);
        $this->module->getConfig('resources')->setSection($name,$data)->saveIni();

        echo "Import successful\n";


        exit(0);
    }
    public function setAction()
    {
        $backend = $this->params->get('backend');
        if($backend !== null){
            $resource = $this->module->getConfig('resources')->getSection($backend)->toArray();
            if ($resource === null || $resource == []) {
                echo "Invalid backend\n";
                exit(3);
            }
            $data = $this->module->getConfig('config')->getSection('settings')->toArray();
            $data['backend'] = $backend;
            $this->module->getConfig('config')->setSection('settings',$data)->saveIni();
            echo "Updated backend!\n";
        }

        $dashboardbackend = $this->params->get('dashboardbackend');
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

        exit(0);
    }

}
