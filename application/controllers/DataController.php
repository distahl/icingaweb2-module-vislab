<?php

namespace Icinga\Module\Vislab\Controllers;


use Icinga\Module\Vislab\Helpers\DataHelper;
use ipl\Web\Compat\CompatController;

class DataController extends CompatController
{

    public function init()
    {

        parent::init();
    }
    public function fetchAction(){
        if($this->getRequest()->isPost()){
            $metric = $this->getRequest()->getPost('metric');
            $host = $this->getRequest()->getPost('host');
            $service = $this->getRequest()->getPost('service');
            $perfdata = $this->getRequest()->getPost('perfdata');
            $checkcommand = $this->getRequest()->getPost('checkcommand');
            $visibilities = $this->getRequest()->getPost('visibilities',[]);
            $range = $this->getRequest()->getPost('range');
        }else{
            $metric = $this->params->getRequired('metric');
            $host = $this->params->getRequired('host');
            $service = $this->params->get('service');
            $perfdata = $this->params->getRequired('perfdata');
            $checkcommand = $this->params->getRequired('checkcommand');
            $visibilities = $this->params->get('visibilities',[]);
            $range = $this->params->getRequired('range');
        }

        $helper = new DataHelper($host, $perfdata, $checkcommand, $service);

        $json = $helper->generateChartJs($metric, $range, $helper->getUnits()[$metric], $visibilities, true);

        echo json_encode($json);

        //https://icinga.nisc.at/icingaweb2/vislab/data?host=Brother%20MFC-L2710DN&service=alma9-access&range=6%20hours&metric=access.log%24_filecount&visibility=1%2C0&checkcommand=check_filematch&perfdata=access.log_count=0;;0;0 access.log$_totalmatchcount=0;;;0 access.log$_filecount_with_matches=0;;;0 access.log$_filecount=1;;;0
        exit(0);

    }

}
