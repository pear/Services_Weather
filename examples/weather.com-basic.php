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

$weatherDotCom = &Services_Weather::service("WeatherDotCom", array("debug" => 2));

$weatherDotCom->setAccountData("<PartnerID>", "<LicenseKey>");
//$weatherDotCom->setCache("file", array("cache_dir" => "/tmp/cache/"));
$weatherDotCom->setUnitsFormat("metric");
$weatherDotCom->setDateTimeFormat("d.m.Y", "H:i");

$search = $weatherDotCom->searchLocation("Bonn, Germany");

$location = $weatherDotCom->getLocation($search);
$weather  = $weatherDotCom->getWeather($search);
$forecast = $weatherDotCom->getForecast($search, 3);

var_dump($location);
var_dump($weather);
var_dump($forecast);
?>
