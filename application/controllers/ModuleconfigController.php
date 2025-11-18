<?php
// Icinga Reporting | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\Vislab\Controllers;


use Icinga\Application\Config;
use Icinga\Web\Controller;
use Icinga\Module\Vislab\Forms\ModuleconfigForm;

class ModuleconfigController extends Controller
{



    public function createTabs()
    {
        $tabs = $this->getTabs();

        $tabs->add('vislab/config', [
            'label' => $this->translate('Configure Vislab'),
            'url' => 'vislab/config'
        ]);

        return $tabs;

    }

}