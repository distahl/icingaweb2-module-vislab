<?php
/**
 * Created by PhpStorm.
 * User: carst
 * Date: 19.02.2018
 * Time: 19:05
 */

namespace Icinga\Module\Vislab\Helpers;

use DateInterval;
use DateTimeImmutable;
use Icinga\Application\Icinga;


class Timeranges
{
    private $urlparams;
    private $link;
    private $view;

    static $timeRanges = array(
        'Range' => array(
            '5 minutes' => '5 minutes',
            '15 minutes' => '15 minutes',
            '30 minutes' => '30 minutes',
            '45 minutes' => '45 minutes',
            '1 hour' => '1 hour',
            '3 hours' => '3 hours',
            '6 hours' => '6 hours',
            '8 hours' => '8 hours',
            '12 hours' => '12 hours',
            '24 hours' => '24 hours',
            '2 days' => '2 days',
            '7 days' => '7 days',
            '14 days' => '14 days',
            '30 days' => '30 days',
            '2 months' => '2 months',
            '3 months' => '3 months',
            '6 months' => '6 months',
        ),


    );

    public function __construct(array $array = array(), $link = "")
    {
        $this->urlparams = $array;
        $this->link = $link;

        $this->view = Icinga::app()->getViewRenderer()->view;
    }

    private function getTimerangeLink($rangeName, $timeRange)
    {
        $params = json_decode(json_encode($this->urlparams),true) ;

        $params['timerange'] = $timeRange;

        return $this->view->qlink(
            $rangeName,
            $this->link,
            $params,
            array(
                'class' => 'action-link',
                'data-base-target' => '_self',
                'title' => 'Set timerange for graph(s) to ' . $rangeName
            )
        );
    }
    private function getMetricLink($metric,$name)
    {
        $params = json_decode(json_encode($this->urlparams),true) ;

        $params['metric'] = $metric;

        return $this->view->qlink(
            $name,
            $this->link,
            $params,
            array(
                'class' => 'action-link',
                'data-base-target' => '_self',
                //'target' => '_self',
                'title' => 'Set metric for graph(s) to ' . $metric
            )
        );
    }


    private function getVisibilityLink($name,$index)
    {
        $params = json_decode(json_encode($this->urlparams),true) ;

        $visibilities = explode(",",$params['visibility']);

        if($visibilities[$index]=="1"){
            $visibilities[$index]="0";

            $visibilty = implode(",",$visibilities);
            $params['visibility']=$visibilty;

            return $this->view->qlink(
                "hide ".$name,
                $this->link,
                $params,
                array(
                    'class' => 'action-link',
                    'data-base-target' => '_self',
                    //'target' => '_self',
                    'title' => 'Set Visibility for graph(s) to ' . $name
                )
            );
        }else{
            $visibilities[$index]="1";

            $visibilty = implode(",",$visibilities);
            $params['visibility']=$visibilty;

            return $this->view->qlink(
                "show ".$name,
                $this->link,
                $params,
                array(
                    'class' => 'action-link',
                    'data-base-target' => '_self',
                    //'target' => '_self',
                    'title' => 'Set Visibility for graph(s) to ' . $name
                )
            );
        }

    }

    private function buildTimerangeMenu($metrics,$thresholds,$withThresholds)
    {
        $params = json_decode(json_encode($this->urlparams),true) ;
        $params['noMenu']=1;
        $timerange=  $params['timerange'];
        $clockIcon = $this->view->qlink('', 'dashboard/new-dashlet',
            ['url' => 'vislab/dashboard?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)],
            ['icon' => 'clock', 'title' => 'Add graph to dashboard']);

        $menu = '<table class="grafana-table"><tr>';
        $menu .= '<td>' . $clockIcon . '</td>';
        foreach (self::$timeRanges as $key => $mainValue) {
            $menu .= '<td><ul class="grafana-menu-navigation"><a class="main" href="#">' . $key . '</a>';
            $counter = 1;
            foreach ($mainValue as $subkey => $value) {
                $menu .= '<li class="grafana-menu-n' . $counter . '">' . $this->getTimerangeLink($value,
                        $subkey) . '</li>';
                $counter++;
            }
            $menu .= '</ul></td>';

        }

        $this->urlparams['timerange']=$timerange;

        $menu .= '<td><ul class="grafana-menu-navigation metric"><a class="main" href="#">' . "metric" . '</a>';

        foreach ($metrics as $metric=>$name) {
            $menu .= '<li class="grafana-menu-n' . $counter . ' metric">' . $this->getMetricLink($metric,$name) . '</li>';
            $counter++;
        }
        $menu .= '</ul></td>';

        if($withThresholds){
            $menu .= '<td><ul class="grafana-menu-navigation threshold"><a class="main" href="#">' . "Threshold" . '</a>';

            $visibility=  $this->urlparams['visibility']??"";
            $threhsoldNames=[];

            $threhsoldNames['metric'] = "1";
            foreach ($thresholds as $name => $types) {

                foreach ($types as $type=>$value) {
                    $threhsoldNames[$name . " " . $type . " " . $metric] = "1";

                }
            }

            $index =0;
            foreach ($threhsoldNames as $name=>$vis) {

                $menu .= '<li class="grafana-menu-n' . $counter . ' metric">' . $this->getVisibilityLink($name,$index) . '</li>';
                $index++;


            }
            $menu .= '</ul></td>';
        }


        $menu .= '</tr></table>';
        return $menu;
    }

    public function getTimerangeMenu($metrics = "",$thresholds = [],$withThresholds=false)
    {
        return $this->buildTimerangeMenu($metrics,$thresholds,$withThresholds);
    }

    public static function getTimeranges()
    {
        return call_user_func_array('array_merge', array_values(self::$timeRanges));
    }
    public static function getTimeragesAsNanoseconds()
    {
        $ret = [];
        foreach (self::$timeRanges['Range'] as $key => $value) {
            $dateInterval = DateInterval::createFromDateString($value);
            $reference = new DateTimeImmutable();
            $startTime = $reference->sub($dateInterval);

            $seconds = $startTime->getTimestamp();
            $nanoseconds = $seconds * 1000000000;
            $ret[$value]=$nanoseconds;
        }
        return $ret;

    }
}
