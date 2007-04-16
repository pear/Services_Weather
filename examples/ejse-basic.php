<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * Basic example for the EJSE-service
 *
 * PHP versions 4 and 5
 *
 * <LICENSE>
 * Copyright (c) 2005-2007, Alexander Wirtz
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
 * @copyright   2005-2007 Alexander Wirtz
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     CVS: $Id$
 * @link        http://pear.php.net/package/Services_Weather
 * @filesource
 */
 
require_once "Services/Weather.php";

// Object initialization - error checking is important, because of
// handling exceptions such as missing PEAR modules or not being online
$ejse = &Services_Weather::service("Ejse", array("debug" => 2));
if (Services_Weather::isError($ejse)) {
    die("Error: ".$ejse->getMessage()."\n");
}

// Set ejse.com account data
$ejse->setAccountData("<Username>", "<Password>");

/* Erase comments to enable caching
$status = $ejse->setCache("file", array("cache_dir" => "/tmp/cache/"));
if (Services_Weather::isError($status)) {
    echo "Error: ".$status->getMessage()."\n";
}
*/

$ejse->setUnitsFormat("metric");
$ejse->setDateTimeFormat("d.m.Y", "H:i");

$location = "81611"; // Aspen, CO
//$location = "02115"; // Boston, MA
//$location = "96799"; // Pago Pago, AS
//$location = "09009"; // Armed Forces Europe -> Error

// Now iterate through available functions for retrieving data
foreach (array("getLocation", "getWeather", "getForecast") as $function) {
    $data = $ejse->$function($location);
    if (Services_Weather::isError($data)) {
        echo "Error: ".$data->getMessage()."\n";
        continue;
    }

    var_dump($data);
}
?>
