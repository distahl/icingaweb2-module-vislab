<?php

namespace Icinga\Module\Vislab\Helpers;

use Icinga\Application\Config;

/**
 * Helper for reading and writing the dashboardbackend setting (multi-select, backwards compatible).
 */
class DashboardBackendHelper
{
    // Order important, first matching and enabled backend
    // will get the default route in run.php
    public const VALID_BACKENDS = ['icingadb', 'monitoring'];

    public const DEFAULT_BACKEND = 'icingadb';

    /**
     * Parse config value to array of allowed backend names.
     * Accepts: single string, comma-separated string, or array. Invalid entries are dropped.
     *
     * @param mixed $value Raw value from config (string or array)
     * @return array<string> Non-empty list of 'icingadb' and/or 'monitoring'
     */
    public static function parse($value): array
    {
        if ($value === null || $value === '') {
            return [self::DEFAULT_BACKEND];
        }
        if (is_array($value)) {
            $list = $value;
        } else {
            $list = array_map('trim', explode(',', (string) $value));
        }
        $allowed = array_filter($list, function ($v) {
            return $v !== '' && in_array($v, self::VALID_BACKENDS, true);
        });
        $result = array_values(array_unique($allowed));
        return $result === [] ? [self::DEFAULT_BACKEND] : $result;
    }

    /**
     * Serialize array of backends to INI string (comma-separated).
     *
     * @param array<string> $backends
     * @return string
     */
    public static function serialize(array $backends): string
    {
        $valid = array_filter($backends, function ($v) {
            return in_array($v, self::VALID_BACKENDS, true);
        });
        return implode(',', array_values(array_unique($valid)));
    }

    /**
     * Read dashboardbackend from module config and return as array.
     *
     * @return array<string>
     */
    public static function getFromConfig(): array
    {
        $raw = Config::module('vislab')->get('settings', 'dashboardbackend', self::DEFAULT_BACKEND);
        return self::parse($raw);
    }

    /**
     * Check whether the given backend is enabled in config.
     *
     * @param string $backend 'icingadb' or 'monitoring'
     * @return bool
     */
    public static function isEnabled(string $backend): bool
    {
        return in_array($backend, self::getFromConfig(), true);
    }
}
