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
        ]);
        $this->queryApi = $client->createQueryApi();
    }

    public function escapeFluxStringLiteral($string) {
        return addcslashes($string, "\\\"");
    }

    public function fetch( $metric, $hostname, $servicename, $check_command, $from, $to=null)
    {

        $result=[];


        $queryString = sprintf('from(bucket: "%s")
            |> range(start: time(v: %d), stop:now())
            |> filter(fn: (r) => r["_field"] == "value")
            |> filter(fn: (r) => r["_measurement"] == "%s")
            |> filter(fn: (r) => r.metric == "%s")
            |> filter(fn: (r) => r.hostname == "%s")', $this->config->bucket, $from, $this->escapeFluxStringLiteral($check_command), $this->escapeFluxStringLiteral($metric), $this->escapeFluxStringLiteral($hostname));


        $unitString = sprintf('from(bucket: "%s")
            |> range(start: time(v: %d), stop:now())
            |> filter(fn: (r) => r["_field"] == "unit")
            |> filter(fn: (r) => r["_measurement"] == "%s")
            |> filter(fn: (r) => r.metric == "%s")
            |> filter(fn: (r) => r.hostname == "%s")', $this->config->bucket, $from, $this->escapeFluxStringLiteral($check_command), $this->escapeFluxStringLiteral($metric), $this->escapeFluxStringLiteral($hostname));


        if ($servicename != null) {
            $queryString .= sprintf(' |> filter(fn: (r) => r.service == "%s")', $this->escapeFluxStringLiteral($servicename));
            $unitString .= sprintf(' |> filter(fn: (r) => r.service == "%s")', $this->escapeFluxStringLiteral($servicename));
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
