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

require_once "Services/Weather/Common.php";

// {{{ class Services_Weather_Metar
/**
* PEAR::Services_Weather_Metar
*
* This class acts as an interface to the metar service of weather.noaa.gov. It searches for
* locations given in ICAO notation and retrieves the current weather data.
*
* For a working example, please take a look at
*     docs/Weather/examples/metar-basic.php
*
* @author       Alexander Wirtz <alex@pc4p.net>
* @link         http://weather.noaa.gov/weather/metar.shtml
* @package      Services
* @version      1.0
*/
class Services_Weather_Metar extends Services_Weather_Common
{
    // {{{ constructor
    /**
    * Constructor
    *
    * @access   private
    */
    function Services_Weather_Metar()
    {
        $this->Services_Weather_Common();
    }
    // }}}

    // {{{ _checkLocationID()
    /**
    * Checks the id for valid values and thus prevents silly requests to METAR server
    *
    * @param    string                      $id
    * @return   PEAR_Error|bool
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_NO_LOCATION
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_INVALID_LOCATION
    * @access   private
    */
    function _checkLocationID($id)
    {
        if(!strlen($id)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_NO_LOCATION);
        } elseif(!ctype_alpha($id) || (strlen($id) > 4)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION);
        }
        return TRUE;
    }
    // }}}

    // {{{ _parseWeatherData()
    /**
    * Parses the data returned by the provided URL and caches it
    *    
    * METAR KPIT 091955Z COR 22015G25KT 3/4SM R28L/2600FT TSRA OVC010CB 18/16 A2992 RMK SLP045 T01820159
    *
    * @param    string                      $id
    * @param    string                      $url
    * @param    int                         $days
    * @return   PHP_Error|array
    * @throws   PHP_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @throws   PHP_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @access   private
    */
    function _parseWeatherData($id = null, $url, $days = null)
    {
        static $metarCode;
        static $compass;
        static $cloud;
        static $condition;
        if (!isset($metarCode)) {
            $metarCode = array(
                "station"     => "\w{4}",
                "update"      => "(\d{2})(\d{4})Z",
                "type"        => "AUTO|COR",
                "wind"        => "(\d{3}|VAR)(\d{2,3})(G(\d{2}))?(\w{2,3})",
                "windVar"     => "(\d{3})V(\d{3})",
                "visibility1" => "^\d$",
                "visibility2" => "(\d{4})|((\d{1,2}|(\d)\/(\d))(SM|KM))|CAVOK",
                "runway"      => "R(\d{2})(\w)?\/(P|M)?(\d{4})(FT)?(V(P|M)?(\d{4})(FT)?)?(\w)?",
                "condition"   => "(-|\+|VC)?(MI|BC|PR|TS|BL|SH|DR|FZ)?(DZ|RA|SN|SG|IC|PE|GR|GS|UP|BR|FG|FU|VA|SA|HZ|PY|DU|SQ|SS|DS|PO|FC)",
                "clouds"      => "(SKC|CLR|((FEW|SCT|BKN|OVC|VV)(\d{3})(TCU|CB)?))",
                "temperature" => "(M)?(\d{2})\/((M)?(\d{2})|XX|\/\/)?",
                "pressure"    => "(A)(\d{4})|(Q)(\d{4})"
            );
            $compass = array(
                "N", "NNE", "NE", "ENE",
                "E", "ESE", "SE", "SSE",
                "S", "SSW", "SW", "WSW",
                "W", "WNW", "NW", "NNW"
            );
            $clouds    = array(
                "skc"         => "sky clear",
                "few"         => "few",
                "sct"         => "scattered",
                "bkn"         => "broken",
                "ovc"         => "overcast",
                "vv"          => "vertival visibility",
                "tcu"         => "Towering Cumulus",
                "cb"          => "Cumulonimbus",
                "clt"         => "clear below 12,000 ft"
            );
            $condition = array(
                "+"           => "heavy",        "-"           => "light",

                "vc"          => "vicinity",

                "mi"          => "shallow",      "bc"          => "patches",
                "pr"          => "partial",      "ts"          => "thunderstorm",
                "bl"          => "blowing",      "sh"          => "showers",
                "dr"          => "low drifting", "fz"          => "freezing",

                "dz"          => "drizzle",      "ra"          => "rain",
                "sn"          => "snow",         "sg"          => "snow grains",
                "ic"          => "ice crystals", "pe"          => "ice pellets",
                "gr"          => "hail",         "gs"          => "small hail/snow pellets",
                "up"          => "unknown precipitation",

                "br"          => "mist",         "fg"          => "fog",
                "fu"          => "smoke",        "va"          => "volcanic ash",
                "sa"          => "sand",         "hz"          => "haze",
                "py"          => "spray",        "du"          => "widespread dust",

                "sq"          => "squall",       "ss"          => "sandstorm",
                "ds"          => "duststorm",    "po"          => "well developed dust/sand whirls",
                "fc"          => "funnel cloud",

                "+fc"         => "tornado/waterspout"
            );
        }

        $data = file( $url );

        if (!$data || !is_array($data) || sizeof($data) < 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA);
        } elseif(sizeof($data) > 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
        } else {
            $weatherData = array();
            $weatherData["update"] = date( $this->_timeFormat, strtotime(trim($data[0])) + date("Z") );
            $metar = explode(" ", $data[1]);

            var_dump($metar);
            foreach($metarCode as $key => $regexp) {
                if(preg_match("/".$regexp."/i", current($metar), $result)) {
                    switch ($key) {
                        case "station":
                            $weatherData["station"] = $result[0];
                            break;
                        case "wind":
                            $weatherData["wind"] = $this->convertSpeed($result[2], strtolower($result[5]), str_replace("/", "", $this->_units["wind"]));
                            if ($result[1] == "VAR") {
                                $weatherData["windDegrees"] = "Variable";
                                $weatherData["windDirection"] = "Variable";
                            } else {
                                $weatherData["windDegrees"] = $result[1];
	                			$weatherData["windDirection"] = $compass[round($result[1] / 22.5) % 16];
                            }
                            if (is_numeric($result[4])) {
                                $weatherData["windGust"] = $this->convertSpeed($result[4], strtolower($result[5]), str_replace("/", "", $this->_units["wind"]));
                            }
                            break;
                        case "windVar":
                            $weatherData["windVariability"] = array("from" => $result[1], "to" => $result[2]);
                            break;
                        case "visibility1":
                            $weatherData["visibility"] = $result[0];
                            break;
                        case "visibility2":
                            if (is_numeric($result[1])) {
                                $visibility = $this->convertDistance(($result[1]/1000), "km", $this->_units["vis"]);
                            } else {
                                if(is_numeric($result[3])) {
                                    $visibility = $this->convertDistance($result[3], $result[6], $this->_units["vis"]);
                                } else {
                                    $visibility = $this->convertDistance($result[4] / $result[5], $result[6], $this->_units["vis"]);
                                    if(isset($weatherData["visibility"])) {
                                        $visibility += $weatherData["visibility"];
                                    }
                                }
                            }
                            $weatherData["visibility"] = $visibility;
                            break;
                        case "clouds":
                            prev($metar);
                            $weatherData["clouds"] = array();
                            while(preg_match("/".$regexp."/i", next($metar), $result)) {
                                if (sizeof($result) == 5) {
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4]*100));
                                }
                                elseif (sizeof($result) == 6) {
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4]*100), "type" => $clouds[strtolower($result[5])]);
                                }
                                else {
                                    $cloud = array("amount" => $clouds[strtolower($result[0])]);
                                }
                                $weatherData["clouds"][] = $cloud;
                            }
                            prev($metar);
                            break;
                        case "temperature":
                            $temperature = $this->convertTemperature($result[2], "c", strtolower($this->_units["temp"]));
                            if ($result[1] == "M") {
                                $temperature *= -1;
                            }
                            $weatherData["temperature"] = $temperature;
                            if (sizeof($result) > 4) {
                                $dewPoint = $this->convertTemperature($result[5], "c", strtolower($this->_units["temp"]));
                                if ($result[4] == "M") {
                                    $dewPoint *= -1;
                                }
                                $weatherData["dewPoint"] = $dewPoint;
                            }
                            if (isset($weatherData["wind"])) {
                                $feltTemperature = $this->calculateWindChill($this->convertTemperature($weatherData["temperature"], strtolower($this->_units["temp"]), "f"), $this->convertSpeed($weatherData["speed"], str_replace("/", "", $this->_units["wind"]), "mph"));
                                $weatherData["feltTemperature"] = $this->convertTemperature($feltTemperature, "f", strtolower($this->_units["temp"]));
                            }
                            break;
                        case "pressure":
                            if ($result[1] == "A") {
                                $weatherData["pressure"] = $this->convertPressure(($result[2] / 100), "in", $this->_units["pres"]);
                            } else {
                                $weatherData["pressure"] = $this->convertPressure($result[2], "hpa", $this->_units["pres"]);
                            }
                            break;
                        default:
                            break;
                    }
                    echo $key."\n";
                    var_dump( $result );
                    if(!next($metar)) {
                        break;
                    }
                }
            }
        }
        return $weatherData;
    }
    // }}}

    // {{{ searchLocation()
    /**
    * Not yet implemented...
    */
    function searchLocation()
    {
        return -1;
    }
    // }}}

    /**
    * Returns the units for the current query
    *
    * @param    string                      $id
    * @param    string                      $unitsFormat
    * @return   array
    * @access   public
    */
    function getUnits($id = null, $unitsFormat = "")
    {
        if (strlen($unitsFormat) > 0) {
            $unitsFormat = strtolower($unitsFormat{0});
        } else {
            $unitsFormat = $this->_unitsFormat;
        }
        $s = array(
            "cache" => "MISS",
            "temp"  => "F",
            "vis"   => "mi",
            "wind"  => "mph",
            "pres"  => "in",
            "rain"  => "in"
        );
        $m = array(
            "cache" => "MISS",
            "temp"  => "C",
            "vis"   => "km",
            "wind"  => "km/h",
            "pres"  => "mb",
            "rain"  => "mm"
        );
        $this->_units = ${$unitsFormat};

        return ${$unitsFormat};
    }

    // {{{ getLocation()
    /**
    * Not yet implemented...
    */
    function getLocation()
    {
        return -1;
    }
    // }}}

    // {{{ getWeather()
    /**
    * Returns the weather-data for the supplied location
    *
    * @param    string                      $id
    * @param    string                      $unitsFormat
    * @return   PHP_Error|array
    * @throws   PHP_Error
    * @access   public
    */
    function getWeather($id = "", $unitsFormat = "")
    {
        $id = strtoupper($id);
        $status = $this->_checkLocationID($id);

        if (Services_Weather::isError($status)) {
            return $status;
        }
        if (strlen($unitsFormat) > 0) {
            $unitsFormat = strtolower($unitsFormat{0});
        } else {
            $unitsFormat = $this->_unitsFormat;
        }
        $this->getUnits(null, $unitsFormat);

        $weatherURL = "http://weather.noaa.gov/pub/data/observations/metar/stations/".$id.".TXT";

        if ($this->_cacheEnabled && ($weather = $this->_cache->get($id, "weather"))) {
            $weatherReturn  = $weather;
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "HIT";
        } else {
            $weatherReturn  = $this->_parseWeatherData(null, $weatherURL, $unitsFormat, null);

            if (Services_Weather::isError($weatherReturn)) {
                return $weatherReturn;
            }
            if($this->_cacheEnabled) {
               $expire = constant("SERVICES_WEATHER_EXPIRES_".strtoupper($varname));
               $this->_cache->extSave($id, $weatherReturn, $unitsFormat, $expire, "weather");
            }
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "MISS";
        }
        return $weatherReturn;
    }
    // }}}
    
    // {{{ getForecast()
    /**
    * Not yet implemented...
    */
    function getForecast()
    {
        return -1;
    }
    // }}}
}
// }}}
?>
