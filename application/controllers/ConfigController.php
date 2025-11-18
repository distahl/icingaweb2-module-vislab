<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\Controllers;

;

use Icinga\Module\Icingadb\Forms\RedisConfigForm;
use Icinga\Module\Vislab\Forms\ModuleconfigForm;
use Icinga\Module\Vislab\Forms\ResourceConfigForm;
use Icinga\Web\Form;
use Icinga\Web\Widget\Tab;
use Icinga\Web\Widget\Tabs;
use InvalidArgumentException;
use Icinga\Application\Config;


use Icinga\Forms\ConfirmRemovalForm;

use Icinga\Web\Controller;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use ipl\Html\HtmlString;


/**
 * Application and module configuration
 */
class ConfigController extends Controller
{

    public function backendAction()
    {
        $this->assertPermission('vislab/config');
        $form = (new ModuleconfigForm())
            ->setIniConfig(Config::module('vislab', "config"));

        $form->handleRequest();

        $this->view->form = $form;
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('config/backend'));

    }

    protected function mergeTabs(Tabs $tabs): self
    {
        $currentTabs = $this->getTabs();
        /** @var Tab $tab */
        foreach ($tabs->getTabs() as $tab) {
            $currentTabs->add($tab->getName(), $tab);
        }

        return $this;
    }


    /**
     * Display all available resources and a link to create a new one and to remove existing ones
     */
    public function resourceAction()
    {
        $this->view->resources = Config::module('vislab', 'resources', true)->getConfigObject()
            ->setKeyColumn('name')
            ->select()
            ->order('name');
        $this->view->title = $this->translate('Resources');
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('config/resource'));

    }

    /**
     * Display a form to create a new resource
     */
    public function createresourceAction()
    {
        $this->assertPermission('vislab/config/resource');
        $this->getTabs()->add('vislab/config/resource/new', array(
            'label' => $this->translate('New Resource'),
            'url'   => Url::fromRequest()
        ))->activate('vislab/config/resource/new');

        $form = new ResourceConfigForm();
        $form->addDescription($this->translate('Resources are entities that provide data to Icinga Web 2.'));
        $form->setIniConfig(Config::module('vislab','resources'));
        $form->setRedirectUrl('vislab/config/resource');
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->title = $this->translate('Resources');
        $this->render('resource/create');
    }

    /**
     * Display a form to edit an existing resource
     */
    public function editresourceAction()
    {
        $this->assertPermission('vislab/config/resource');
        $this->getTabs()->add('vislab/config/resource/update', array(
            'label' => $this->translate('Update Resource'),
            'url'   => Url::fromRequest()
        ))->activate('vislab/config/resource/update');
        $form = new ResourceConfigForm();
        $form->setIniConfig(Config::module('vislab','resources'));
        $form->setRedirectUrl('vislab/config/resource');
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->title = $this->translate('Resources');
        $this->render('resource/modify');
    }

    /**
     * Display a confirmation form to remove a resource
     */
    public function removeresourceAction()
    {
        $this->assertPermission('vislab/config/resource');
        $this->getTabs()->add('vislab/config/resource/remove', array(
            'label' => $this->translate('Remove Resource'),
            'url'   => Url::fromRequest()
        ))->activate('vislab/config/resource/remove');
        $form = new ConfirmRemovalForm(array(
            'onSuccess' => function ($form) {
                $configForm = new ResourceConfigForm();
                $configForm->setIniConfig(Config::module('vislab','resources'));
                $resource = $form->getRequest()->getQuery('resource');

                try {
                    $configForm->remove($resource);
                } catch (InvalidArgumentException $e) {
                    Notification::error($e->getMessage());
                    return false;
                }

                if ($configForm->save()) {
                    Notification::success(sprintf(t('Resource "%s" has been successfully removed'), $resource));
                } else {
                    return false;
                }
            }
        ));
        $form->setRedirectUrl('vislab/config/resource');
        $form->handleRequest();

        /**  Check if selected resource is currently used for authentication
        $resource = $this->getRequest()->getQuery('resource');
        $authConfig = Config::app('authentication');
        foreach ($authConfig as $backendName => $config) {
            if ($config->get('resource') === $resource) {
                $form->warning(sprintf(
                    $this->translate(
                        'The resource "%s" is currently utilized for authentication by user backend "%s".'
                        . ' Removing the resource can result in noone being able to log in any longer.'
                    ),
                    $resource,
                    $backendName
                ));
            }
        }

        // Check if selected resource is currently used as user preferences backend
        if (Config::app()->get('global', 'config_resource') === $resource) {
            $form->warning(sprintf(
                $this->translate(
                    'The resource "%s" is currently utilized to store user preferences. Removing the'
                    . ' resource causes all current user preferences not being available any longer.'
                ),
                $resource
            ));
        }
        */
        $this->view->form = $form;
        $this->view->title = $this->translate('Resources');
        $this->render('resource/remove');
    }
}
