<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Alexander Wirtz <alex@pc4p.net>                             |
// +----------------------------------------------------------------------+
//
// $Id$

require_once "Services/Weather.php";

$metar = &Services_Weather::service("METAR", array("debug" => 0));

$metar->setMetarDB("sqlite", "", "", "", "/usr/local/lib/php/data/Services_Weather/servicesWeatherDB", "");
//$weatherDotCom->setCache("file", array("cache_dir" => "/tmp/cache/"));
$metar->setUnitsFormat("metric");
$metar->setDateTimeFormat("d.m.Y", "H:i");

$search = $metar->searchLocation("Bonn, Germany");

$location = $metar->getLocation($search);
$weather  = $metar->getWeather($search);
$forecast = $metar->getForecast($search, 3);

var_dump($location);
var_dump($weather);
var_dump($forecast);
?>
