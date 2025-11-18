<?php

namespace Icinga\Module\Vislab\Helpers;

use Icinga\Application\Config;

use Icinga\Module\Vislab\Helpers\Perfdata\PerfdataSet;


class DataHelper
{
    protected $timerange = "6 hours";
    protected $metric = "";

    private $host;
    private $service = null;
    private $perfdata = null;
    private $check_command = null;


    public function __construct($host, $perfdata, $check_command, $service = null)
    {
        $this->host = $host;
        $this->service = $service;
        $this->perfdata = $perfdata;
        $this->check_command = $check_command;
    }

    public function generateChartJs($metrics, $timerange, $unit, $visibilities, $isIcingaDB = false)
    {
        $host = $this->host;
        $service = $this->service;

        $resourceName = Config::module('vislab')->get('settings', 'backend');
        $resource = Config::module('vislab', 'resources')->getSection($resourceName);
        $dataset = [];
        $connector = ResourceFactory::createResource($resource);
        $metricsArray = explode(",", $metrics);
        if ($service == null) {
            $title = $host . " - " . $this->check_command;

        } else {
            $title = $host . " - " . $service;

        }
        $options = [
            "plugins" =>
                [
                    "title" => [
                        "display" => true,
                        "text" => $title,
                    ],
                    "legend" => [
                        "display" => true,

                    ],
                    "scales" => [

                        "x" => [
                            "type" => 'time',

                        ]

                    ],
                    "zoom" => [

                        "zoom" => [
                            "drag" => [
                                "enabled" => true,

                            ],

                            "mode" => "x"

                        ]

                    ]

                ],

            "responsive" => true,
            "maintainAspectRatio" => false,
            "animation" => false,
            "transitions" => [
                "active" => [
                    "animation" => [
                        "duration" => false
                    ]
                ]
            ],
            "animations" => [
                "x" => false,
                "y" => false
            ],

        ];


        $chartData = [];
        $chartData['type'] = 'line';
        $chartData['data'] = [];
        $chartData['data']['datasets'] = [];
        $chartData['labels'] = [];

        $metric = array_pop($metricsArray);

        $ranges = Timeranges::getTimeragesAsNanoseconds();

        foreach ($ranges as $range => $nanoseconds) {
            if ($range != $timerange) {
                unset($ranges[$range]);
            } else {
                break;
            }
        }
        list($dataset, $orgunit) = $connector->fetch($metric, $host, $service, $this->check_command, $nanoseconds, null);

        foreach ($ranges as $range => $nanoseconds) {
            list($dataset, $orgunit) = $connector->fetch($metric, $host, $service, $this->check_command, $nanoseconds, null);
            if (count($dataset) > 5) {
                break;
            }
        }


        if ($unit != "") {
            $orgunit = $unit;
        }

        $labels = array_keys($dataset);
        $values = array_values($dataset);
        list($values, $newUnit) = $this->normalizeValues($orgunit, $values, null, $isIcingaDB);

        $currentData = [];
        $currentData['data'] = $values;
        $chartData['options'] = $options;

        if($newUnit !== ""){
            $label = $metric . " ($newUnit)";
        }else{
            $label = $metric;
        }
        $currentData['label'] = $label;

        if (count($values) > 300) {
            $currentData['pointRadius'] = 0;
        } else {
            $currentData['pointRadius'] = 3;
            $currentData['pointHoverRadius'] = 4;
        }
        $currentData['borderColor'] = "#0095bf";
        $currentData['fill'] = true;
        $currentData['tension'] = 0.1;
        //$currentData['backgroundColor'] = "rgb(229,229,229,0.5)";
        $chartData['data']['datasets'][] = $currentData;


        $chartData['data']['labels'] = $labels;

        if(isset($this->getThresholds()[$metric] )){
            foreach ($this->getThresholds()[$metric] as $name => $types) {

                foreach ($types as $type => $value) {
                    $thresholdName = $name . " " . $type . " " . $label;

                    $cloned = array_map(fn() => $value, $dataset);


                    $cloned = $this->normalizeValues($orgunit, $cloned, $newUnit, $isIcingaDB)[0];

                    $currentThresholdData = [];
                    $currentThresholdData['data'] = array_values($cloned);
                    $currentThresholdData['label'] = $thresholdName;

                    $currentThresholdData['pointRadius'] = "0";
                    if ($name == "warning") {
                        $currentThresholdData['borderColor'] = "#ffaa44";
                    } else {
                        $currentThresholdData['borderColor'] = "#ff5566";
                    }
                    $currentThresholdData['backgroundColor'] = "rgb(0,0,0,0)";
                    $chartData['data']['datasets'][] = $currentThresholdData;

                }


            }
        }

        foreach ($visibilities as $index => $visible) {
            if ($visible == "0" and isset($chartData['data']['datasets'][$index])) {
                $chartData['data']['datasets'][$index]['hidden'] = "1";
            }
        }
        return $chartData;

    }

    public function generateGnuplot($metrics, $timerange, $unit, $visibilities, $isIcingaDB = false)
    {
        $host = $this->host;
        $service = $this->service;

        $resourceName = Config::module('vislab')->get('settings', 'backend');
        $resource = Config::module('vislab', 'resources')->getSection($resourceName);
        $dataset = [];
        $connector = ResourceFactory::createResource($resource);
        $metricsArray = explode(",", $metrics);
        if ($service == null) {
            $title = $host . " - " . $this->check_command;

        } else {
            $title = $host . " - " . $service;

        }
        $title = $this->gnuplotEscape($title);

        $metric = array_pop($metricsArray);

        $ranges = Timeranges::getTimeragesAsNanoseconds();

        foreach ($ranges as $range => $nanoseconds) {
            if ($range != $timerange) {
                unset($ranges[$range]);
            } else {
                break;
            }
        }
        foreach ($ranges as $range => $nanoseconds) {
            list($dataset, $orgunit) = $connector->fetch($metric, $host, $service, $this->check_command, $nanoseconds, null);
            if (count($dataset) > 10) {
                break;
            }
        }


        if ($unit != "") {
            $orgunit = $unit;
        }

        list($dataset, $newUnit) = $this->normalizeValues($orgunit, $dataset, null, $isIcingaDB);


        $gpData = "";

        $labelCount = count($dataset);
        $maxLabels = 20;
        $skipEvery = 1;

        if ($labelCount > $maxLabels) {
            $skipEvery = ceil($labelCount / $maxLabels);
        }

        $i = 0;


        $data = [];
        foreach ($dataset as $label => $value) {
            $data[$label] = [$value];
        }

        $lineColor = "#0095bf";


        $thresholdPlots = "plot";

        $plotIndex = 3;

        if($newUnit !== ""){
            $label = $metric . " ($newUnit)";
        }else{
            $label = $metric;
        }
        $index = 0;
        if (isset($visibilities[$index]) && $visibilities[$index] == "1") {
            $thresholdPlots .= " \$Mydata using 2:xtic(1) title '{$this->gnuplotEscape($label)}' with lines lt rgb \"$lineColor\", ";

        }
        $index++;
        foreach ($this->getThresholds()[$metric] as $name => $types) {

            foreach ($types as $type => $value) {

                if (isset($visibilities[$index]) && $visibilities[$index] == "0") {
                    continue;
                }

                if ($name == "warning") {
                    $lineColor = "#ffaa44";
                } else {
                    $lineColor = "#ff5566";
                }

                $thresholdName = $this->gnuplotEscape($name . " " . $type . " " . $label);
                $thresholdPlots .= " \$Mydata using $plotIndex:xtic(1) title '$thresholdName' with lines lt rgb \"$lineColor\", ";
                $plotIndex++;
                $cloned = array_map(fn() => $value, $dataset);

                $cloned = $this->normalizeValues($orgunit, $cloned, $newUnit, $isIcingaDB)[0];

                foreach ($cloned as $label => $thresholdValue) {
                    array_push($data[$label], $thresholdValue);

                }
                $index++;

            }


        }
        $thresholdPlots = rtrim($thresholdPlots, ",");

        foreach ($data as $label => $values) {

            $showLabel = ($i % $skipEvery === 0) ? "\"$label\"" : "\" \"";

            $gpData .= "$showLabel " . implode(" ", $values) . "\n";
            $i++;

        }


        file_put_contents('/tmp/plot.gp', <<<GPLOT
set terminal svg size 550,370 enhanced background rgb 'white'
set output '/tmp/chart.svg'
set title '$title'
set style data lines
set xtics rotate by -45
set datafile missing "?"
set xtics nomirror
\$Mydata << EOD
$gpData
EOD
$thresholdPlots

GPLOT
        );
        exec('gnuplot /tmp/plot.gp');
        $svgContent = file_get_contents('/tmp/chart.svg');

        $base64 = base64_encode($svgContent);
        return $base64;

    }

    protected function gnuplotEscape($string)
    {
        return str_replace("_", "\_", $string);
    }

    public function getThresholds()
    {
        $p = PerfdataSet::fromString($this->perfdata)->asArray();
        $thresholds = [];
        foreach ($p as $item) {
            $critical = array();
            $warning = array();
            $thresholds[$item->getLabel()] = [];

            if ($item->getCriticalThreshold()->getMax() !== null) {
                $critical["upper"] = $item->getCriticalThreshold()->getMax();
            }

            if ($item->getCriticalThreshold()->getMin() !== null && $item->getCriticalThreshold()->getMin() != 0) {
                $critical["lower"] = $item->getCriticalThreshold()->getMin();
            }

            if ($item->getWarningThreshold()->getMax() !== null) {
                $warning["upper"] = $item->getWarningThreshold()->getMax();
            }
            if ($item->getWarningThreshold()->getMin() !== null && $item->getWarningThreshold()->getMin() != 0) {
                $warning["lower"] = $item->getWarningThreshold()->getMin();
            }
            $thresholds[$item->getLabel()]['warning'] = $warning;
            $thresholds[$item->getLabel()]['critical'] = $critical;

        }

        return $thresholds;
    }



    public function getMetrics()
    {
        $p = PerfdataSet::fromString($this->perfdata)->asArray();
        $metrics = [];
        foreach ($p as $item) {
            $name = $item->getLabel();
            if (preg_match('/^([^:]+)::[^:]+::([^:]+)$/', $name, $matches)) {
                $newText = $matches[1] . "::" . $matches[2];
                $name = $newText;
            }
            $metrics[$item->getLabel()] = $name;
        }

        return $metrics;
    }

    public function getUnits()
    {
        $p = PerfdataSet::fromString($this->perfdata)->asArray();
        $units = [];
        foreach ($p as $item) {
            if ($item->getUnit() == null) {
                $units[$item->getLabel()] = "";
                continue;
            }

            if (strpos($item->getUnit(), 's') !== false) {
                $units[$item->getLabel()] = "seconds";
            } elseif (strpos($item->getUnit(), "b") !== false) {
                $units[$item->getLabel()] = "bytes";
            } else {
                $units[$item->getLabel()] = "";
            }


        }

        return $units;
    }

    public function normalizeValues($unit, $values, $newUnit = null, $isIcingaDB = true)
    {
        if (count($values) == 0) {
            return array($values, $unit);
        }
        if ($newUnit == $unit) {
            return array($values, $unit);
        }

        if ($isIcingaDB) {
            $units_byte = [2 => "KB", 3 => "MB", 4 => "GB", 5 => "TB", 6 => "PB"];
            if ($unit == "bytes") {
                foreach ($units_byte as $unit) {
                    if ($newUnit != $unit) {
                        $values = array_map(function ($val) {
                            return $val / 1000;
                        }, $values);

                    } else {
                        $values = array_map(function ($val) {
                            return $val / 1000;
                        }, $values);
                        break;
                    }
                    if (min(array_values($values)) < 1000 && $newUnit == null) {
                        break;
                    }
                }
            }
        } else {
            $units_byte = [2 => "kibibyte", 3 => "mebibyte", 4 => "gibibyte", 5 => "tebibyte", 6 => "pebibyte"];
            if ($unit == "bytes") {
                foreach ($units_byte as $unit) {
                    if ($newUnit != $unit) {
                        $values = array_map(function ($val) {
                            return $val / 1024;
                        }, $values);

                    } else {
                        $values = array_map(function ($val) {
                            return $val / 1024;
                        }, $values);
                        break;
                    }
                    if (min(array_values($values)) < 1024 && $newUnit == null) {
                        break;
                    }
                }
            }
        }

        $units_time = [2 => "minutes", 3 => "hours"];
        if ($unit == "seconds") {

            foreach ($units_time as $currentUnit) {
                if (min(array_values($values)) < 60 && $newUnit == null) {
                    return array($values, $unit);
                }
                if ($newUnit != $currentUnit) {
                    $values = array_map(function ($val) {
                        return $val / 60;
                    }, $values);
                } else {
                    $values = array_map(function ($val) {
                        return $val / 60;
                    }, $values);
                    return array($values, $unit);
                }
                $unit = $currentUnit;


            }
        }
        return array($values, $unit);
    }


}
