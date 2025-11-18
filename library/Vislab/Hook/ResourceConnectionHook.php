<?php
/* Icinga Web 2 | (c) 2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\Hook;


use DateTime;
use Icinga\Data\ConfigObject;
use Icinga\Module\Vislab\Helpers\ResourceFactory;

/**
 * Encapsulate database connections and query creation
 */
abstract class ResourceConnectionHook
{
    /**
     * Connection config
     *
     * @var ConfigObject
     */
    protected $config;

    /**
     * Create a new connection object
     *
     * @param ConfigObject $config
     */
    public function __construct(ConfigObject $config = null)
    {
        $this->config = $config;
        if($config !== null){
            $this->connect();

        }
    }
    public abstract function createForm(array $formData);
    /**
     * Get the connection configuration
     *
     * @return  ConfigObject
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Getter for database type
     *
     * @return string
     */
    public function getDbType()
    {
        return "influxdb1";
    }


    /**
     * Create a new connection
     */
    abstract protected function connect();
    abstract public function getType();

    abstract public function fetch( $metric, $hostname, $servicename, $check_command, $from, $to=null);


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
}
