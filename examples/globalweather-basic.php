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

$globalweather = &Services_Weather::service("GlobalWeather", array("debug" => 2));
//$globalweather->setCache("file", array("cache_dir" => "/tmp/cache/"));
$globalweather->setUnitsFormat("metric");
$globalweather->setDateTimeFormat("d.m.Y", "H:i");

$search = $globalweather->searchLocation("Koeln / Bonn");

$location = $globalweather->getLocation($search);
$weather  = $globalweather->getWeather($search);
$forecast = $globalweather->getForecast($search, 3);

var_dump($location);
var_dump($weather);
var_dump($forecast);
?>
