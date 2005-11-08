<?php
/**
 * Calculates sunrise and sunset for a location
 * The sun position algorithm taken from the 'US Naval Observatory's
 * Almanac for Computers', implemented by Ken Bloom <kekabloom[at]ucdavis[dot]edu>
 * for the zmanim project <http://sourceforge.net/projects/zmanim/>
 * and finally converted to C by Moshe Doron <mosdoron[at]netvision[dot]net[dot]il>.
 * 
 * Taken from the PHP5 sources and converted to PHP by the authors below.
 *
 * PHP versions 4 and 5
 *
 * <LICENSE>
 * Copyright (c) 2005, Alexander Wirtz
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
 * @copyright   2005 Alexander Wirtz
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version     CVS: $Id$
 * @link        http://pear.php.net/package/Services_Weather
 * @filesource
 */

if(!defined("SUNFUNCS_RET_TIMESTAMP")) {
    define("SUNFUNCS_RET_TIMESTAMP", 0);
    define("SUNFUNCS_RET_STRING",    1);
    define("SUNFUNCS_RET_DOUBLE",    2);
}
define("SUNFUNCS_DEFAULT_LATITUDE",  31.7667);
define("SUNFUNCS_DEFAULT_LONGITUDE", 35.2333);
define("SUNFUNCS_SUNRISE_ZENITH",    90.83);
define("SUNFUNCS_SUNSET_ZENITH",     90.83);

function php_sunrise_sunset($N, $latitude, $longitude, $zenith, $calc_sunset)
{
    // step 1: First calculate the day of the year
    // int N = theday - date(1, 1, theday.year()) + 1;

    // step 2: convert the longitude to hour value and calculate an approximate time
    $lngHour = $longitude / 15;

    // use 18 for sunset instead of 6
    if ($calc_sunset) {
        // Sunset
        $t = $N + ((18 - $lngHour) / 24);
    } else {
        // Sunrise
        $t = $N + ((6 - $lngHour) / 24);
    } 

    // step 3: calculate the sun's mean anomaly
    $M = (0.9856 * $t) - 3.289;

    // step 4: calculate the sun's true longitude
    $L = $M + (1.916 * sin(deg2rad($M))) + (0.020 * sin(deg2rad(2 * $M))) + 282.634;

    while ($L < 0) {
        $Lx = $L + 360;
        assert($Lx != $L); // askingtheguru: really needed?
        $L = $Lx;
    }
    
    while ($L >= 360) {
        $Lx = $L - 360;
        assert($Lx != $L); // askingtheguru: really needed?
        $L = $Lx;
    }

    // step 5a: calculate the sun's right ascension
    $RA = rad2deg(atan(0.91764 * tan(deg2rad($L))));

    while ($RA < 0) {
        $RAx = $RA + 360;
        assert($RAx != $RA); // askingtheguru: really needed?
        $RA = $RAx;
    }

    while ($RA >= 360) {
        $RAx = $RA - 360;
        assert($RAx != $RA); // askingtheguru: really needed?
        $RA = $RAx;
    } 

    // step 5b: right ascension value needs to be in the same quadrant as L
    $Lquadrant  = floor($L / 90) * 90;
    $RAquadrant = floor($RA / 90) * 90;

    $RA = $RA + ($Lquadrant - $RAquadrant);

    // step 5c: right ascension value needs to be converted into hours
    $RA /= 15;

    // step 6: calculate the sun's declination
    $sinDec = 0.39782 * sin(deg2rad($L));
    $cosDec = cos(asin($sinDec));

    // step 7a: calculate the sun's local hour angle
    $cosH = (cos(deg2rad($zenith)) - ($sinDec * sin(deg2rad($latitude)))) / ($cosDec * cos(deg2rad($latitude)));

    // XXX: What's the use of this block.. ?
    // if (!calc_sunset && cosH > 1 || calc_sunset && cosH < -1) {
    //     throw doesnthappen();
    // }

    // step 7b: finish calculating H and convert into hours 
    if ($calc_sunset) {
        // Sunset
        $H = rad2deg(acos($cosH));
    } else {
        // Sunrise
        $H = 360 - rad2deg(acos($cosH));
    }
    $H = $H / 15;

    // step 8: calculate local mean time
    $T = $H + $RA - (0.06571 * $t) - 6.622;

    // Sunset step 9: convert to UTC
    $UT = $T - $lngHour;

    while ($UT < 0) {
        $UTx = $UT + 24;
        assert($UTx != $UT); // askingtheguru: really needed?
        $UT = $UTx;
    }

    while ($UT >= 24) {
        $UTx = $UT - 24;
        assert($UTx != $UT); // askingtheguru: really needed?
        $UT = $UTx;
    }

    return $UT;
}

function php_do_sunrise_sunset($date, $retformat, $latitude, $longitude, $zenith, $gmt_offset, $calc_sunset)
{
    if (is_int($date)) {
        $time = $date;
    } elseif (is_string($date)) {
        // todo: more user friendly format
    } else {
        // date must be timestamp for now
        trigger_error("date must be timestamp for now", E_USER_WARNING);
        return false;
    }
    
    $N = date("z", $time) + 1;
    
    if ($retformat === "") {
        $retformat  = SUNFUNCS_RET_STRING;
    }
    if ($latitude === "") {
        $latitude   = SUNFUNCS_DEFAULT_LATITUDE;
    }
    if ($longitude === "") {
        $longitude  = SUNFUNCS_DEFAULT_LONGITUDE;
    }
    if ($zenith === "") {
        if($calc_sunset) {
            $zenith = SUNFUNCS_SUNSET_ZENITH;
        } else {
            $zenith = SUNFUNCS_SUNRISE_ZENITH;
        }
            
    }
    if ($gmt_offset === "") {
        $gmt_offset = date("Z", $time) / 3600;
    }

    $ret = php_sunrise_sunset($N, $latitude, $longitude, $zenith, $calc_sunset) + $gmt_offset;

    switch ($retformat) {
        case SUNFUNCS_RET_TIMESTAMP:
            return floor($time - ($time % (24 * 3600))) + floor(60 * $ret);
        case SUNFUNCS_RET_STRING:
            $N = floor($ret);
            return sprintf("%02d:%02d", $N, floor(60 * ($ret - $N)));
        case SUNFUNCS_RET_DOUBLE:
            return $ret;
        default:
            trigger_error("invalid format", E_USER_WARNING);
            return false;
    } 
}

if (!function_exists("date_sunrise")) {
	function date_sunrise($date, $retformat = "", $latitude = "", $longitude = "", $zenith = "", $gmt_offset = "")
	{
        return php_do_sunrise_sunset($date, $retformat, $latitude, $longitude, $zenith, $gmt_offset, 0);
	}
}

if (!function_exists("date_sunset")) {
    function date_sunset($date, $retformat = "", $latitude = "", $longitude = "", $zenith = "", $gmt_offset = "")
    {
        return php_do_sunrise_sunset($date, $retformat, $latitude, $longitude, $zenith, $gmt_offset, 1);
    }
}
?>
