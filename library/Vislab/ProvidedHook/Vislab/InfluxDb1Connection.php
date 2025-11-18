<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\ProvidedHook\Vislab;


use DateTime;
use DateTimeZone;
use Icinga\Module\Vislab\Hook\ResourceConnectionHook;
use Icinga\Web\Form;
use InfluxDB\Client;
use InfluxDB\Database;

use Icinga\Data\ConfigObject;
use ipl\I18n\Translation;

/**
 * Encapsulate database connections and query creation
 */
class InfluxDb1Connection extends ResourceConnectionHook
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
     * @var Database
     */
    protected $database;

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
        return "vislab-influxdb1";
    }

    /**
     * Create a new connection
     */
    protected function connect()
    {
        $this->client = new Client( $this->config->host,  $this->config->port,  $this->config->user,  $this->config->password);
        $this->database = $this->client->selectDB($this->config->database);
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
            'host',
            array(
                'required'      => true,
                'label'         => $this->translate('Host'),
                'description'   => $this->translate(
                    'The Host of the Influxdb1 instance.'
                )
            )
        );

        $form->addElement(
            'text',
            'port',
            array(
                'required'      => true,
                'label'         => $this->translate('Port'),
                'description'   => $this->translate(
                    'The Port of the Influxdb1 instance.'
                )
            )
        );

        $form->addElement(
            'text',
            'user',
            array(
                'required'      => true,
                'label'         => $this->translate('User'),
                'description'   => $this->translate(
                    'User to log in on the Influxdb1 instance.'
                )
            )
        );

        $form->addElement(
            'password',
            'password',
            array(
                'required'          => true,
                'renderPassword'    => true,
                'label'         => $this->translate('Password'),
                'description'   => $this->translate(
                    'The password to log in on the Influxdb1 instance.'
                ),
                'autocomplete'      => 'new-password'

            )
        );

        $form->addElement(
            'text',
            'database',
            array(
                'required'      => true,
                'label'         => $this->translate('Database'),
                'description'   => $this->translate(
                    'The Database to use'
                )
            )
        );

        return $form;
    }
    public function fetch( $metric, $hostname, $servicename, $check_command, $from, $to=null)
    {
        $dataset=[];
        $unit = "";
        $where = "\"metric\" = '{$metric}' AND \"hostname\" = '$hostname' ";

        if ($servicename !== null) {
            $where .= " AND \"service\" = '$servicename'";
        }
        $where .= " AND time >= $from ";

        if ($to !== null) {
            $where .= " AND time <= $to ";
        }

        $result =  $this->database->getQueryBuilder()
        ->select("value, unit")->from($check_command)->where([$where])->getResultSet()->getPoints();
        if (count($result) > 0) {
            if (isset($result[0]['unit']) && $result[0]['unit'] != "") {
                $unit = $result[0]['unit'];

            }
        }
        foreach ($result as $entry) {
            $date = new DateTime(substr($entry['time'],0,19),(new \DateTimeZone("UTC")));
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $labelwithDay = $date->format('Y-m-d H:i:s');
            $labelwithoutDay = $date->format('H:i:s');

            if($this->isToday($labelwithDay)){
                $label = $labelwithoutDay;
            }else{
                $label=$labelwithDay;
            }
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $dataset[$label] = $entry['value'];
        }

        return array($dataset, $unit);
    }

}
