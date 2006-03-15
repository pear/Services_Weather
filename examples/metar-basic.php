<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * Basic example for the METAR/TAF-service
 *
 * PHP versions 4 and 5
 *
 * <LICENSE>
 * Copyright (c) 2005-2006, Alexander Wirtz
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * o Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 * o Neither the name of the software nor the names of its contributors
 *   may be used to endorse or promote products derived from this software
 *   without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 * </LICENSE>
 * 
 * @category    Web Services
 * @package     Services_Weather
 * @author      Alexander Wirtz <alex@pc4p.net>
 * @copyright   2005-2006 Alexander Wirtz
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     CVS: $Id$
 * @link        http://pear.php.net/package/Services_Weather
 * @filesource
 */

require_once "Services/Weather.php";

// Object initialization - error checking is important, because of
// handling exceptions such as missing PEAR modules
$metar = &Services_Weather::service("METAR", array("debug" => 0));
if (Services_Weather::isError($metar)) {
    die("Error: ".$metar->getMessage()."\n");
}

// Set parameters for DB access, needed for location searches
$metar->setMetarDB("sqlite://localhost//usr/local/lib/php/data/Services_Weather/servicesWeatherDB");
if (Services_Weather::isError($metar)) {
    echo "Error: ".$metar->getMessage()."\n";
}

/* Erase comments to enable caching
$status = $metar->setCache("file", array("cache_dir" => "/tmp/cache/"));
if (Services_Weather::isError($status)) {
    echo "Error: ".$status->getMessage()."\n";
}
*/

$metar->setUnitsFormat("custom", array(
    "wind"   => "kt",
    "vis"    => "km",
    "height" => "ft",
    "temp"   => "c",
    "pres"   => "hpa",
    "rain"   => "in"));
$metar->setDateTimeFormat("d.m.Y", "H:i");

// First get code for location
$search = $metar->searchLocation("Bonn, Germany");
if (Services_Weather::isError($search)) {
    die("Error: ".$search->getMessage()."\n");
}

// Now iterate through available functions for retrieving data
foreach (array("getLocation", "getWeather", "getForecast") as $function) {
    $data = $metar->$function($search);
    if (Services_Weather::isError($data)) {
        echo "Error: ".$data->getMessage()."\n";
        continue;
    }

    var_dump($data);
}
?>
