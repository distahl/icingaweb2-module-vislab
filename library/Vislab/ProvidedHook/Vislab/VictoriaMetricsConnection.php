<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\ProvidedHook\Vislab;


use DateTime;
use DateTimeZone;
use Exception;
use Icinga\Module\Vislab\Helpers\ResourceFactory;


use Icinga\Data\ConfigObject;
use Icinga\Module\Vislab\Hook\ResourceConnectionHook;
use Icinga\Web\Form;
use ipl\I18n\Translation;

/**
 * Encapsulate database connections and query creation
 */
class VictoriaMetricsConnection extends ResourceConnectionHook
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
     * Create a new connection object
     *
     * @param ConfigObject $config
     */
    public function __construct(ConfigObject $config = null)
    {
        parent::__construct($config);
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
                    'The Connectionsstring of the victoriametrics instance, e.g. http://127.0.0.1:8426'
                )
            )
        );



        $form->addElement(
            'text',
            'user',
            array(
                'required'      => false,
                'label'         => $this->translate('User'),
                'description'   => $this->translate(
                    'User to log in on the victoriametrics instance.'
                )
            )
        );

        $form->addElement(
            'password',
            'password',
            array(
                'required'      => false,
                'renderPassword' => true,
                'label'         => $this->translate('Password'),
                'description'   => $this->translate(
                    'The password to log in on the victoriametrics instance. If no username is given, the password is used as token.'
                ),
                'autocomplete'      => 'new-password'
            )
        );

        $form->addElement(
            'text',
            'database',
            array(
                'required'          => true,
                'label'         => $this->translate('database'),
                'description'   => $this->translate(
                    'The database that icinga2 used to write the metric'
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
            'host_mapping_hostname',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Host): Hostname'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "hostname" label on host checks. Must match your InfluxdbWriter host_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'hostname'
            )
        );

        $form->addElement(
            'select',
            'host_mapping_name',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Host): Measurement'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "__name__" (measurement) label on host checks. Must match your InfluxdbWriter host_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'check_command'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_service',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Service'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "service" label on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'servicename'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_hostname',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Hostname'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "hostname" label on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'hostname'
            )
        );

        $form->addElement(
            'select',
            'service_mapping_name',
            array(
                'required'      => false,
                'label'         => $this->translate('Mapping (Service): Measurement'),
                'description'   => $this->translate(
                    'Which Icinga2 variable to use for the "__name__" (measurement) label on service checks. Must match your InfluxdbWriter service_template.'
                ),
                'multiOptions'  => $mappingOptions,
                'value'         => 'check_command'
            )
        );

        return $form;
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
        return "vislab-victoriametrics";
    }


    /**
     * Create a new connection
     */
    protected function connect()
    {

    }

    public function fetch( $metric, $hostname, $servicename, $check_command, $from, $to=null)
    {

        $unit = "";
        $result =[];

        $vars = [
            'servicename'   => $servicename,
            'hostname'      => $hostname,
            'check_command' => $check_command,
        ];

        if ($servicename === null) {
            $mappedHostname = $vars[$this->config->get('host_mapping_hostname', 'hostname')];
            $mappedName     = $vars[$this->config->get('host_mapping_name', 'check_command')];
            $queryWithLabels = sprintf('{db="%s",hostname="%s",metric="%s",__name__="%s_value"}',
                $this->config->database, $mappedHostname, $metric, $mappedName);
        } else {
            $mappedService  = $vars[$this->config->get('service_mapping_service', 'servicename')];
            $mappedHostname = $vars[$this->config->get('service_mapping_hostname', 'hostname')];
            $mappedName     = $vars[$this->config->get('service_mapping_name', 'check_command')];
            $queryWithLabels = sprintf('{service="%s",db="%s",hostname="%s",metric="%s",__name__="%s_value"}',
                $mappedService, $this->config->database, $mappedHostname, $metric, $mappedName);
        }

        if($to == null){
            $to = time();

        }
        $results = $this->queryRaw($queryWithLabels,($from/1000000000),$to);

        $dataset = [];


        $withDate=false;
        foreach ($results as $result){
            if(isset($result['values']) && count($result['values']) > 0){
                foreach ($result['values'] as $key =>$entry) {
                    $timestamp_sec = intval($result['timestamps'][$key])/1000;

                    $date = new DateTime("@$timestamp_sec");
                    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));

                    $labelwithDay = $date->format('Y-m-d H:i');
                    $labelwithoutDay = $date->format('H:i');
                    if(!$withDate && $this->isToday($labelwithDay)){
                        $label = $labelwithoutDay;
                    }else{
                        $label=$labelwithDay;
                        $withDate=true;
                    }
                    $dataset[$label] = $entry;
                }
            }
        }



        return array($dataset, '');
    }
    public static function fromResourceName($name)
    {
        return new static(ResourceFactory::getResourceConfig($name));
    }

    public function inspect()
    {
        return true;
    }

    function isToday($dateTime) {
        return (new DateTime($dateTime))->format('Y-m-d') === (new DateTime())->format('Y-m-d');
    }




    public function queryRaw($promqlQuery, $start, $end) {
        $url = rtrim($this->config->connectionstring,"/") . '/api/v1/export';

        // Prepare the query parameters
        $params = [
            'match[]' => $promqlQuery,
            'start' => $start,
            'end' => $end,
        ];

        // Make the HTTP request
        return $this->makeRequest($url, $params);
    }

    // Helper function to make HTTP requests
    private function makeRequest($url, $params) {
        $ch = curl_init();
        // Set the URL with query parameters
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_TIMEOUT,1);

        $user = $this->config->get('user');
        $password = $this->config->get('password');
        if ($user !== null && $user !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        } elseif ($password !== null && $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $password]);
        }

        if (boolval($this->config->get('verify_tls', '1')) === false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        // Execute the request
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Request Error: ' . curl_error($ch));
        }

        curl_close($ch);
        $return =[];
        // Decode the JSON response
        $datas = explode("\n", $response);
        foreach($datas as $data){
            $timeseries = json_decode($data, true);
            if (isset($timeseries['error'])) {
                throw new Exception('API Error: ' . $timeseries['error']);
            }
            $return[] = $timeseries;
        }

        // Check for errors in the response


        return $return;
    }

}
