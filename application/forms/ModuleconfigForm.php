<?php
// Icinga Reporting | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Vislab\Forms;

use Icinga\Application\Config;
use Icinga\Forms\ConfigForm;

class ModuleconfigForm extends ConfigForm
{
    protected static $dummyPassword = '_web_form_m0r34m4z1n6n1ck';

    public function init()
    {

        $this->setName('vislab_settings');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $backends = [];
        $query  = Config::module('vislab', 'resources', true)->getConfigObject()
            ->setKeyColumn('name')
            ->select()
            ->order('name');
        foreach($query as $name => $value){
            $backends[$name]=$name;
        }
        $this->addElement('select', 'settings_backend', [
            'label' => $this->translate('Backend'),
            'required' => true,
            'multiOptions' => $backends
        ]);

        $this->addElement('select', 'settings_dashboardbackend', [
            'label' => $this->translate('Dasboard Backend'),
            'required' => true,
            'multiOptions' => ['icingadb'=>'icingadb', 'monitoring'=>'monitoring (ido)']
        ]);




        $this->addElement('checkbox','settings_showthresholds',
            [
                'label' => $this->translate('Show Thresholds in Graph'),
                'required' => false,
                'description' => $this->translate(
                    'Whether we should enable or disable The lines for the thresholds'
                ),
                'value' => 0
            ]
        );

        $this->addElement('checkbox','settings_nojs',
            [
                'label' => $this->translate('No JavaScript'),
                'required' => false,
                'description' => $this->translate(
                    'Whether to use the javascript less gnuplot implementation'
                ),
                'value' => 0
            ]
        );




    }
    public function onRequest()
    {
        parent::onRequest();
        foreach ($this->getElements() as $element) {
            if ($element->getType() === 'Zend_Form_Element_Password' && strlen($element->getValue())) {
                $element->setValue(static::$dummyPassword);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValues($suppressArrayNotation = false)
    {
        $values = parent::getValues($suppressArrayNotation);
        $resource = "settings";
        if ($resource !== null && $this->config->hasSection($resource)) {

            $resourceConfig = $this->config->getSection($resource)->toArray();

            foreach ($this->getElements() as $element) {
                if ($element->getType() === 'Zend_Form_Element_Password') {
                    $name = $element->getName();
                    $name2 = str_replace($resource . "_", "", $name);

                    if (isset($values[$name]) && $values[$name] === static::$dummyPassword) {
                        if (isset($resourceConfig[$name2])) {
                            $values[$name] = $resourceConfig[$name2];
                        } else {
                            unset($values[$name]);
                        }

                    }
                }
            }
        }

        return $values;
    }


}