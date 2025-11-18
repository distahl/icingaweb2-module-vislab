<?php
/* Icinga Web 2 | (c) 2014 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\Forms;

use Icinga\Application\Config;
use Icinga\Application\Hook;

use Icinga\Module\Vislab\Hook\ResourceConnectionHook;
use Icinga\Module\Vislab\Hook\ResourceFormHook;
use InvalidArgumentException;
use Icinga\Exception\ConfigurationError;
use Icinga\Data\ConfigObject;
use Icinga\Data\Inspection;
use Icinga\Forms\ConfigForm;

use Icinga\Web\Form;
use Icinga\Web\Notification;
use Zend_Form_Element;

class ResourceConfigForm extends ConfigForm
{
    /**
     * Bogus password when inspecting password elements
     *
     * @var string
     */
    protected static $dummyPassword = '_web_form_5847ed1b5b8ca';

    /**
     * If the global config must be updated because a resource has been changed, this is the updated global config
     *
     * @var Config|null
     */
    protected $updatedAppConfig = null;

    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_resource');
        $this->setSubmitLabel($this->translate('Save Changes'));
        $this->setValidatePartial(true);
    }

    public function enumResourceConnections()
    {
        $hooks = Hook::all('Vislab\\ResourceConnection');
        $enum['']="please choose...";
        /** @var ResourceFormHook $hook */
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getType();

        }

        return $enum;
    }

    /**
     * Return a form object for the given resource type
     *
     * @param   string      $type      The resource type for which to return a form
     *
     * @return  Form
     */
    public function getResourceForm($type)
    {
        $resources = $this->enumResourceConnections();

        if(isset($resources[$type])){
            $class = $type;
            if(class_exists($class)){
                /* @var $hook ResourceConnectionHook */
                $hook = new $class();
                /* @var $form Form */
                $form = $hook->createForm([]);
                return $form;
            }
        } else {
            throw new InvalidArgumentException(sprintf($this->translate('Invalid resource type "%s" provided'), $type));
        }
    }

    /**
     * Add a particular resource
     *
     * The backend to add is identified by the array-key `name'.
     *
     * @param   array   $values             The values to extend the configuration with
     *
     * @return  $this
     *
     * @throws  InvalidArgumentException    In case the resource does already exist
     */
    public function add(array $values)
    {
        $name = isset($values['name']) ? $values['name'] : '';
        if (! $name) {
            throw new InvalidArgumentException($this->translate('Resource name missing'));
        } elseif ($this->config->hasSection($name)) {
            throw new InvalidArgumentException($this->translate('Resource already exists'));
        }

        unset($values['name']);
        $this->config->setSection($name, $values);
        return $this;
    }

    /**
     * Edit a particular resource
     *
     * @param   string      $name           The name of the resource to edit
     * @param   array       $values         The values to edit the configuration with
     *
     * @return  ConfigObject                The edited configuration
     *
     * @throws  InvalidArgumentException    In case the resource does not exist
     */
    public function edit($name, array $values)
    {
        if (! $name) {
            throw new InvalidArgumentException($this->translate('Old resource name missing'));
        } elseif (! ($newName = isset($values['name']) ? $values['name'] : '')) {
            throw new InvalidArgumentException($this->translate('New resource name missing'));
        } elseif (! $this->config->hasSection($name)) {
            throw new InvalidArgumentException($this->translate('Unknown resource provided'));
        }

        $resourceConfig = $this->config->getSection($name);
        $this->config->removeSection($name);
        unset($values['name']);
        $this->config->setSection($newName, $resourceConfig->merge($values));

        //TODO CHECK Config::app
        if ($newName !== $name) {
            $appConfig = Config::app();
            $section = $appConfig->getSection('global');
            if ($section->config_resource === $name) {
                $section->config_resource = $newName;
                $this->updatedAppConfig = $appConfig->setSection('global', $section);
            }
        }

        return $resourceConfig;
    }

    /**
     * Remove a particular resource
     *
     * @param   string      $name           The name of the resource to remove
     *
     * @return  ConfigObject                The removed resource configuration
     *
     * @throws  InvalidArgumentException    In case the resource does not exist
     */
    public function remove($name)
    {
        if (! $name) {
            throw new InvalidArgumentException($this->translate('Resource name missing'));
        } elseif (! $this->config->hasSection($name)) {
            throw new InvalidArgumentException($this->translate('Unknown resource provided'));
        }

        $resourceConfig = $this->config->getSection($name);
        $resourceForm = $this->getResourceForm($resourceConfig->connection);
        if (method_exists($resourceForm, 'beforeRemove')) {
            $resourceForm::beforeRemove($resourceConfig);
        }

        $this->config->removeSection($name);
        return $resourceConfig;
    }

    /**
     * Add or edit a resource and save the configuration
     *
     * Performs a connectivity validation using the submitted values. A checkbox is
     * added to the form to skip the check if it fails and redirection is aborted.
     *
     * @see Form::onSuccess()
     */
    public function onSuccess()
    {
        $resourceForm = $this->getResourceForm($this->getElement('connection')->getValue());

        if (($el = $this->getElement('force_creation')) === null || false === $el->isChecked()) {
            $inspection = static::inspectResource($this);
            if ($inspection !== null && $inspection->hasError()) {
                $this->error($inspection->getError());
                $this->addElement($this->getForceCreationCheckbox());
                return false;
            }
        }

        $resource = $this->request->getQuery('resource');
        try {
            if ($resource === null) { // create new resource
                if (method_exists($resourceForm, 'beforeAdd')) {
                    if (! $resourceForm::beforeAdd($this)) {
                        return false;
                    }
                }
                $this->add(static::transformEmptyValuesToNull($this->getValues()));
                $message = $this->translate('Resource "%s" has been successfully created');
            } else { // edit existing resource
                $this->edit($resource, static::transformEmptyValuesToNull($this->getValues()));
                $message = $this->translate('Resource "%s" has been successfully changed');
            }
        } catch (InvalidArgumentException $e) {
            Notification::error($e->getMessage());
            return false;
        }

        if ($this->save()) {
            Notification::success(sprintf($message, $this->getElement('name')->getValue()));
        } else {
            return false;
        }
    }

    /**
     * Populate the form in case a resource is being edited
     *
     * @see Form::onRequest()
     *
     * @throws  ConfigurationError      In case the backend name is missing in the request or is invalid
     */
    public function onRequest()
    {
        $resource = $this->request->getQuery('resource');
        if ($resource !== null) {
            if ($resource === '') {
                throw new ConfigurationError($this->translate('Resource name missing'));
            } elseif (! $this->config->hasSection($resource)) {
                throw new ConfigurationError($this->translate('Unknown resource provided'));
            }
            $configValues = $this->config->getSection($resource)->toArray();
            $configValues['name'] = $resource;
            $this->populate($configValues);
            foreach ($this->getElements() as $element) {
                if ($element->getType() === 'Zend_Form_Element_Password' && $element->getValue() !== null && strlen($element->getValue())) {
                    $element->setValue(static::$dummyPassword);
                }
            }
        }
    }

    /**
     * Return a checkbox to be displayed at the beginning of the form
     * which allows the user to skip the connection validation
     *
     * @return  Zend_Form_Element
     */
    protected function getForceCreationCheckbox()
    {
        return $this->createElement(
            'checkbox',
            'force_creation',
            array(
                'order'         => 0,
                'ignore'        => true,
                'label'         => $this->translate('Force Changes'),
                'description'   => $this->translate('Check this box to enforce changes without connectivity validation')
            )
        );
    }


    /**
     * @see Form::createElemeents()
     */
    public function createElements(array $formData)
    {

        $resourceDefaultConnection = isset($formData['connection']) ? $formData['connection'] : array_keys($this->enumResourceConnections())[0];

        $resourceConnections = $this->enumResourceConnections();

        $this->addElement(
            'select',
            'connection',
            array(
                'required'          => true,
                'autosubmit'        => true,
                'label'             => $this->translate('Resource Connection'),
                'description'       => $this->translate('The Connection implementation of the resource'),
                'multiOptions'      => $resourceConnections,
                'value'             => $resourceDefaultConnection
            )
        );

        if (isset($formData['force_creation']) && $formData['force_creation']) {
            // In case another error occured and the checkbox was displayed before
            $this->addElement($this->getForceCreationCheckbox());
        }
        if(isset($formData['connection'])){
            $this->addElements($this->getResourceForm($resourceDefaultConnection)->getElements());

        }

    }

    /**
     * Create a resource by using the given form's values and return its inspection results
     *
     * @param   Form    $form
     *
     * @return  ?Inspection
     */
    public static function inspectResource(Form $form)
    {

    }

    /**
     * Run the configured resource's inspection checks and show the result, if necessary
     *
     * This will only run any validation if the user pushed the 'resource_validation' button.
     *
     * @param   array   $formData
     *
     * @return  bool
     */
    public function isValidPartial(array $formData)
    {
        if ($this->getElement('resource_validation')->isChecked() && parent::isValid($formData)) {
            $inspection = static::inspectResource($this);
            if ($inspection !== null) {
                $join = function ($e) use (&$join) {
                    return is_array($e) ? join("\n", array_map($join, $e)) : $e;
                };
                $this->addElement(
                    'note',
                    'inspection_output',
                    array(
                        'order'         => 0,
                        'value'         => '<strong>' . $this->translate('Validation Log') . "</strong>\n\n"
                            . join("\n", array_map($join, $inspection->toArray())),
                        'decorators'    => array(
                            'ViewHelper',
                            array('HtmlTag', array('tag' => 'pre', 'class' => 'log-output')),
                        )
                    )
                );

                if ($inspection->hasError()) {
                    $this->warning(sprintf(
                        $this->translate('Failed to successfully validate the configuration: %s'),
                        $inspection->getError()
                    ));
                    return false;
                }
            }

            $this->info($this->translate('The configuration has been successfully validated.'));
        }

        return true;
    }

    /**
     * Add a submit button to this form and one to manually validate the configuration
     *
     * Calls parent::addSubmitButton() to add the submit button.
     *
     * @return  $this
     */
    public function addSubmitButton()
    {
        parent::addSubmitButton()
            ->getElement('btn_submit')
            ->setDecorators(array('ViewHelper'));

        $this->addElement(
            'submit',
            'resource_validation',
            array(
                'ignore'                => true,
                'label'                 => $this->translate('Validate Configuration'),
                'data-progress-label'   => $this->translate('Validation In Progress'),
                'decorators'            => array('ViewHelper')
            )
        );

        $this->setAttrib('data-progress-element', 'resource-progress');
        $this->addElement(
            'note',
            'resource-progress',
            array(
                'decorators'    => array(
                    'ViewHelper',
                    array('Spinner', array('id' => 'resource-progress'))
                )
            )
        );

        $this->addDisplayGroup(
            array('btn_submit', 'resource_validation', 'resource-progress'),
            'submit_validation',
            array(
                'decorators' => array(
                    'FormElements',
                    array('HtmlTag', array('tag' => 'div', 'class' => 'control-group form-controls'))
                )
            )
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues($suppressArrayNotation = false)
    {
        $values = parent::getValues($suppressArrayNotation);
        $resource = $this->request->getQuery('resource');
        if ($resource !== null && $this->config->hasSection($resource)) {
            $resourceConfig = $this->config->getSection($resource)->toArray();
            foreach ($this->getElements() as $element) {
                if ($element->getType() === 'Zend_Form_Element_Password') {
                    $name = $element->getName();
                    if (isset($values[$name]) && $values[$name] === static::$dummyPassword) {
                        if (isset($resourceConfig[$name])) {
                            $values[$name] = $resourceConfig[$name];
                        } else {
                            unset($values[$name]);
                        }
                    }
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritDoc}
     */
    protected function writeConfig(Config $config)
    {
        parent::writeConfig($config);
        if ($this->updatedAppConfig !== null) {
            $this->updatedAppConfig->saveIni();
        }
    }
}
