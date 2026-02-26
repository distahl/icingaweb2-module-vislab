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
        $queryWithLabels = sprintf('{service="%s",db="%s",hostname="%s",metric="%s",__name__="%s_value"}'
            ,$servicename,$this->config->database,$hostname,$metric,$check_command);

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
