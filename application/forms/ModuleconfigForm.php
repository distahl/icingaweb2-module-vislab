<?php
// Icinga Reporting | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Vislab\Forms;

use Icinga\Application\Config;
use Icinga\Forms\ConfigForm;
use Icinga\Module\Vislab\Helpers\DashboardBackendHelper;

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

        $this->addElement('multiselect', 'settings_dashboardbackend', [
            'label' => $this->translate('Dashboard Backend'),
            'description' => $this->translate('Where to show the vislab dashboard and graphs. Multiple selections allowed.'),
            'required' => true,
            'multiOptions' => ['icingadb' => 'icingadb', 'monitoring' => 'monitoring (ido)']
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

        $this->addElement('text', 'settings_hook_max_width_value', [
            'label' => $this->translate('Hook max width (value)'),
            'description' => $this->translate(
                'Maximum width of the graph container on host/service detail view (hook only, not dashlets). Value is in percent or pixels depending on unit.'
            ),
            'required' => false,
            'value' => 100,
            'validators' => [['Between', false, ['min' => 1, 'max' => 9999]]]
        ]);
        $this->addElement('select', 'settings_hook_max_width_unit', [
            'label' => $this->translate('Hook max width (unit)'),
            'description' => $this->translate('Unit for the hook max width value.'),
            'required' => true,
            'multiOptions' => ['percent' => $this->translate('Percent'), 'pixel' => $this->translate('Pixel')],
            'value' => 'percent'
        ]);




    }
    public function onRequest()
    {
        parent::onRequest();
        foreach ($this->getElements() as $element) {
            if ($element->getType() === 'Zend_Form_Element_Password' && strlen($element->getValue())) {
                $element->setValue(static::$dummyPassword);
            }
        }
        $dashboardEl = $this->getElement('settings_dashboardbackend');
        if ($dashboardEl && $this->config->hasSection('settings')) {
            $raw = $this->config->getSection('settings')->get('dashboardbackend', DashboardBackendHelper::DEFAULT_BACKEND);
            $dashboardEl->setValue(DashboardBackendHelper::parse($raw));
        }
        if ($this->config->hasSection('settings')) {
            $s = $this->config->getSection('settings');
            $v = $this->getElement('settings_hook_max_width_value');
            if ($v !== null) $v->setValue($s->get('hook_max_width_value', 100));
            $u = $this->getElement('settings_hook_max_width_unit');
            if ($u !== null) $u->setValue($s->get('hook_max_width_unit', 'percent'));
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
            if (isset($values['settings_dashboardbackend']) && is_array($values['settings_dashboardbackend'])) {
                $values['settings_dashboardbackend'] = DashboardBackendHelper::serialize($values['settings_dashboardbackend']);
            }
        }

        return $values;
    }


}