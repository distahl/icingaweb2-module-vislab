<?php

namespace Icinga\Module\Vislab\Helpers;


use Icinga\Application\Config;
use Icinga\Module\Vislab\Html\PlainHtml;
use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class GrapherHelper
{
    protected $timerange = "6 hours";
    protected $metric = "";
    protected $visibility = "";
    protected $zoomFrom = "";
    protected $zoomTo = "";

    protected $hostname = null;
    protected $servicename = null;
    protected $perfdata = null;
    protected $check_command = null;
    protected $isIcingadb = false;

    public function __construct($hostname, $check_command, $isIcingadb, $perfdata, $servicename = null)
    {
        $this->hostname = $hostname;
        $this->servicename = $servicename;
        $this->check_command = $check_command;
        $this->isIcingadb = $isIcingadb;
        $this->perfdata = $perfdata;
    }

    public function getHtmlForObject(): ValidHtml
    {

        $helper = new DataHelper($this->hostname, $this->perfdata, $this->check_command, $this->servicename);
        if (count($helper->getMetrics()) == 0) {
            return Html::tag('div');
        }
        $noMenu = Url::fromRequest()->hasParam('noMenu') ? intval(urldecode(Url::fromRequest()->getParam('noMenu')))==1 : false;

        $this->timerange = Url::fromRequest()->hasParam('timerange') ? urldecode(Url::fromRequest()->getParam('timerange')) : $this->timerange;
        $this->metric = Url::fromRequest()->hasParam('metric') ? Url::fromRequest()->getParam('metric') : array_key_first($helper->getMetrics());
        $this->visibility = Url::fromRequest()->hasParam('visibility') ? urldecode(Url::fromRequest()->getParam('visibility')) : "";
        $this->zoomFrom = Url::fromRequest()->hasParam('zoomFrom') ? urldecode(Url::fromRequest()->getParam('zoomFrom')) : "";
        $this->zoomTo= Url::fromRequest()->hasParam('zoomTo') ? urldecode(Url::fromRequest()->getParam('zoomTo')) : "";
        $titel = "";

        if ($this->servicename == null) { //host

            $titel .= $this->hostname;
            $titel .= " - " . $this->check_command;

            if ($this->isIcingadb) {
                $link = 'icingadb/host';
                $parameters = array(
                    'name' => $this->hostname,
                );
            } else {
                $link = 'monitoring/host/show';
                $parameters = array(
                    'host' => $this->hostname,
                );
            }

        } else { // service

            $titel .= $this->hostname;
            $titel .= " - " . $this->servicename;

            if ($this->isIcingadb) {
                $link = 'icingadb/service';
                $parameters = array(
                    'host.name' => $this->hostname,
                    'name' => $this->servicename,
                );
            } else {
                $link = 'monitoring/service/show';
                $parameters = array(
                    'host' => $this->hostname,
                    'service' => $this->servicename,
                );
            }


        }

        // Preserve timerange if set
        $parameters['timerange'] = $this->timerange;
        $parameters['metric'] = $this->metric;
        $id = sha1($titel);

        $visibilities = [];
        //visibility
        if ($this->visibility !== "") {
            $visibilities = explode(",", $this->visibility);
        }

        $index = 0;
        $maxindex = 1; // for the metric itself
        $div = Html::tag('div', ['class'=>'hidden-hrefs']);
        $metricThresholds=[];
        if(isset($helper->getThresholds()[$this->metric])){
            $metricThresholds = $helper->getThresholds()[$this->metric];
            foreach ($metricThresholds as $entry) {
                $maxindex += count($entry);
            }
        }
        $currentUnits="";
        if(isset($helper->getUnits()[$this->metric])){
            $currentUnits = $helper->getUnits()[$this->metric];
        }

        $showThresholds = Config::module('vislab')->get('settings','showthresholds') == "1";
        while ($index < $maxindex) {

            if (!isset($visibilities[$index])) {
                if ($index == 0) {
                    $visibilities[$index] = 1;
                } else {
                    if($showThresholds){
                        $visibilities[$index] = 1;
                    }else{
                        $visibilities[$index] = 0;
                    }

                }
            }
            $currVis = $visibilities[$index];

            if ($visibilities[$index] == '1') {
                $visibilities[$index] = '0';
            } else {
                $visibilities[$index] = '1';
            }
            $url = Url::fromRequest()->setParam('visibility', implode(",", $visibilities));
            $a = Html::tag('a', ['id' => $id . '_href_' . $index, 'href' => $url, 'data-base-target' => "_self"]);
            $div->add($a);
            $visibilities[$index] = $currVis;
            $index++;

        }

        $url = Url::fromRequest();
        $a = Html::tag('a', ['id' => $id . '_href_' . "zoom", 'href' => $url, 'data-base-target' => "_self"]);
        $div->add($a);

        $parameters['visibility'] = implode(",", $visibilities);
        $nojs = Config::module('vislab')->get('settings','nojs',"0") == "1";

        $menu = "";
        if ($noMenu) {

        } else {
            $timeranges = new Timeranges($parameters, $link);
            $withThresholds= $nojs;
            $menu .= $timeranges->getTimerangeMenu($helper->getMetrics(), $metricThresholds, $withThresholds);


        }





        if ($nojs) {


            $base64 = $helper->generateGnuplot($this->metric, $this->timerange, $metricThresholds, $visibilities, $this->isIcingadb);
            $image= Html::tag('img', ['src' => "data:image/svg+xml;base64," . $base64, "alt" => "Chart", "width" => "550", "height" => "370"]);

            $html = '<div class="controls">' . $menu . $div . '</div>' . '<div class="container">' . $image . '</div>';

            return (new PlainHtml())->setContent($html);

        } else {
            $json = $helper->generateChartJs($this->metric, $this->timerange, $currentUnits, $visibilities, $this->isIcingadb);

            $canvas = sprintf('<canvas id="%s" data-json=\'%s\' data-zoom-from=\'%s\'  data-zoom-to=\'%s\' class="display-inline-chartjs chartjs-icingachart chartjs-render-monitor"></canvas>', $id, json_encode($json),$this->zoomFrom,$this->zoomTo);
            $html = '<div class="controls">' . $menu . $div . '</div>' . '<div class="container">' . $canvas . '</div>';
            return (new PlainHtml())->setContent($html);

        }


    }

}