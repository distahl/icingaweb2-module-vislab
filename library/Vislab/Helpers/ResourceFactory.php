<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Vislab\Helpers;

use Icinga\Application\Config;
use Icinga\Application\Hook;
use Icinga\Data\ConfigObject;

use Icinga\Module\Vislab\Hook\ResourceConnectionHook;
use Icinga\Util\ConfigAwareFactory;
use Icinga\Exception\ConfigurationError;

/**
 * Create resources from names or resource configuration
 */
class ResourceFactory implements ConfigAwareFactory
{
    /**
     * Resource configuration
     *
     * @var Config
     */
    private static $resources;

    /**
     * Set resource configurations
     *
     * @param Config $config
     */
    public static function setConfig($config)
    {
        self::$resources = $config;
    }

    /**
     * Get the configuration for a specific resource
     *
     * @param   $resourceName   String      The resource's name
     *
     * @return                  ConfigObject    The configuration of the resource
     *
     * @throws                  ConfigurationError
     */
    public static function getResourceConfig($resourceName)
    {
        self::assertResourcesExist();
        $resourceConfig = self::$resources->getSection($resourceName);
        if ($resourceConfig->isEmpty()) {
            throw new ConfigurationError(
                'Cannot load resource config "%s". Resource does not exist',
                $resourceName
            );
        }
        return $resourceConfig;
    }

    /**
     * Get the configuration of all existing resources, or all resources of the given type
     *
     * @param   string  $type   Filter for resource type
     *
     * @return  Config          The resources configuration
     */
    public static function getResourceConfigs($type = null)
    {
        self::assertResourcesExist();
        if ($type === null) {
            return self::$resources;
        }
        $resources = array();
        foreach (self::$resources as $name => $resource) {
            if ($resource->get('type') === $type) {
                $resources[$name] = $resource;
            }
        }
        return Config::fromArray($resources);
    }

    /**
     * Check if the existing resources are set. If not, load them from resources.ini
     *
     * @throws  ConfigurationError
     */
    private static function assertResourcesExist()
    {
        if (self::$resources === null) {
            self::$resources = Config::module('vislab');
        }
    }

    public static function enumResourceConnections()
    {
        $hooks = Hook::all('Vislab\\ResourceConnection');
        /** @var ResourceConnectionHook $hook */
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getType();

        }

        return $enum;
    }

    /**
     * Create and return a resource based on the given configuration
     *
     * @param   ConfigObject    $config     The configuration of the resource to create
     *
     * @return  ResourceConnectionHook                  The resource
     * @throws  ConfigurationError          In case of an unsupported type or invalid configuration
     */
    public static function createResource(ConfigObject $config)
    {

        $connections = self::enumResourceConnections();
        if(isset($connections[$config->connection])){

            $class = $config->connection;
            if(class_exists($class)){
                /* @var $connection ResourceConnectionHook */
                $connection = new $class($config);

                return $connection;
            }
        } else {
            throw new ConfigurationError(
                'Unsupported resource type "%s"',
                $config->connection
            );
        }


    }


    public static function create($resourceName)
    {
        return self::createResource(self::getResourceConfig($resourceName));
    }
}
