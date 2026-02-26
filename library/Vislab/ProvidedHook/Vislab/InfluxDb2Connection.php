<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\ProvidedHook\Vislab;




use Icinga\Data\ConfigObject;
use Icinga\Module\Vislab\Hook\ResourceConnectionHook;
use Icinga\Web\Form;
use InfluxDB2\ObjectSerializer;
use InfluxDB2\QueryApi;
use ipl\I18n\Translation;

/**
 * Encapsulate database connections and query creation
 */
class InfluxDb2Connection extends ResourceConnectionHook
{
    use Translation;
    /**
     * Connection config
     *
     * @var ConfigObject
     */
    protected $config;

    protected $client;

    /**
     * Database type
     *
     * @var QueryApi
     */
    protected $queryApi;

    /**
     * Create a new connection object
     *
     * @param ConfigObject $config
     */
    public function __construct(ConfigObject $config = null)
    {
        parent::__construct($config);
    }

    /**
     * Get the connection configuration
     *
     * @return  ConfigObject
     */
    public function getConfig()
    {
        return $this->config;
    }
    public function getType()
    {
        return "vislab-influxdb2";
    }

    /**
     * @see Form::createElements()
     */
    public function createForm(array $formData)
    {
        $form = new Form();
        $form->addElement(
            'text',
            'name',
            array(
                'required'      => true,
                'label'         => $this->translate('Resource Name'),
                'description'   => $this->translate('The unique name of this resource')
            )
        );
        $form->addElement(
            'text',
            'connectionstring',
            array(
                'required'      => true,
                'label'         => $this->translate('Connection String'),
                'description'   => $this->translate(
                    'The Connectionsstring of the influxdb2 instance, e.g. http://127.0.0.1:8426'
                )
            )
        );


        $form->addElement(
            'text',
            'organization',
            array(
                'required'      => true,
                'label'         => $this->translate('Organization'),
                'description'   => $this->translate(
                    'The Organization to use on the Influxdb2 instance.'
                )
            )
        );

        $form->addElement(
            'password',
            'token',
            array(
                'required'          => true,
                'renderPassword'    => true,
                'label'         => $this->translate('Token'),
                'description'   => $this->translate(
                    'The token to use on the Influxdb2 instance.'
                ),
                'autocomplete'      => 'new-password'
            )
        );

        $form->addElement(
            'text',
            'bucket',
            array(
                'required'      => true,
                'label'         => $this->translate('Bucket'),
                'description'   => $this->translate(
                    'The Bucket to use on the Influxdb2 instance.'
                )
            )
        );

        $form->addElement(
            'checkbox',
            'verify_tls',
            array(
                'label'         => $this->translate('Verify TLS Certificate'),
                'description'   => $this->translate(
                    'When using HTTPS, verify the server TLS certificate. Disable for self-signed or internal certificates.'
                ),
                'value'         => '1'
            )
        );

        $mappingOptions = array(
            'servicename'   => $this->translate('Service Name'),
            'hostname'      => $this->translate('Hostname'),
            'check_command' => $this->translate('Check Command'),
        );

        $form->addElement(
            'select',
            'host_mapping_measurement',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Host): Measurement'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "_measurement" on host checks. Must match your InfluxdbWriter host_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'check_command'
            )
        );

        $form->addElement(
            'select',
            'host_mapping_hostname',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Host): Hostname'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "hostname" tag on host checks. Must match your InfluxdbWriter host_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'hostname'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_measurement',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Measurement'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "_measurement" on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'check_command'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_hostname',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Hostname'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "hostname" tag on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'hostname'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_service',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Service'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "service" tag on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'servicename'
            )
        );

        return $form;
    }

    /**
     * Create a new connection
     */

    protected function connect()
    {

        $client = new \InfluxDB2\Client([
            "url" => $this->config->connectionstring,
            "token" => $this->config->token,
            "bucket" => $this->config->bucket,
            "org" => $this->config->organization,
            "verifySSL" => boolval($this->config->get('verify_tls', '1')),
        ]);
        $this->queryApi = $client->createQueryApi();
    }

    public function escapeFluxStringLiteral($string) {
        return addcslashes($string, "\\\"");
    }

    public function fetch( $metric, $hostname, $servicename, $check_command, $from, $to=null)
    {

        $result=[];

        $vars = [
            'servicename'   => $servicename,
            'hostname'      => $hostname,
            'check_command' => $check_command,
        ];

        if ($servicename === null) {
            $mappedMeasurement = $vars[$this->config->get('host_mapping_measurement', 'check_command')];
            $mappedHostname    = $vars[$this->config->get('host_mapping_hostname', 'hostname')];
        } else {
            $mappedMeasurement = $vars[$this->config->get('service_mapping_measurement', 'check_command')];
            $mappedHostname    = $vars[$this->config->get('service_mapping_hostname', 'hostname')];
            $mappedService     = $vars[$this->config->get('service_mapping_service', 'servicename')];
        }

        $queryString = sprintf('from(bucket: "%s")
            |> range(start: time(v: %d), stop:now())
            |> filter(fn: (r) => r["_field"] == "value")
            |> filter(fn: (r) => r["_measurement"] == "%s")
            |> filter(fn: (r) => r.metric == "%s")
            |> filter(fn: (r) => r.hostname == "%s")', $this->config->bucket, $from, $this->escapeFluxStringLiteral($mappedMeasurement), $this->escapeFluxStringLiteral($metric), $this->escapeFluxStringLiteral($mappedHostname));


        $unitString = sprintf('from(bucket: "%s")
            |> range(start: time(v: %d), stop:now())
            |> filter(fn: (r) => r["_field"] == "unit")
            |> filter(fn: (r) => r["_measurement"] == "%s")
            |> filter(fn: (r) => r.metric == "%s")
            |> filter(fn: (r) => r.hostname == "%s")', $this->config->bucket, $from, $this->escapeFluxStringLiteral($mappedMeasurement), $this->escapeFluxStringLiteral($metric), $this->escapeFluxStringLiteral($mappedHostname));


        if ($servicename !== null) {
            $queryString .= sprintf(' |> filter(fn: (r) => r.service == "%s")', $this->escapeFluxStringLiteral($mappedService));
            $unitString .= sprintf(' |> filter(fn: (r) => r.service == "%s")', $this->escapeFluxStringLiteral($mappedService));
        }
        $dataset = [];
        $unit = "";
        $result = $this->queryApi->query($unitString);

        if ( isset($result[0]) && isset($result[0]->records) && count($result[0]->records) > 0) {
            $unit = $result[0]->records[0]->getValue();
        }
        $result = $this->queryApi->query($queryString);





        $withDate=false;
        if ( isset($result[0]) && isset($result[0]->records) && count($result[0]->records) > 0) {
            foreach ($result[0]->records as $entry) {
                $labelwithDay = date("d.m.Y H:i", strtotime(ObjectSerializer::fixDatetimeNanos($entry->getTime())));
                $labelwithoutDay = date("H:i", strtotime(ObjectSerializer::fixDatetimeNanos($entry->getTime())));

                if(!$withDate && $this->isToday($labelwithDay)){
                    $label = $labelwithoutDay;
                }else{
                    $label=$labelwithDay;
                    $withDate=true;
                }

                $value = $entry->getValue();

                $dataset[$label] = $value;
            }
        }


        return array($dataset, $unit);
    }

}
