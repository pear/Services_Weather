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

require_once "PEAR/Registry.php";

// {{{ constants
// {{{ natural constants and measures
define("SERVICES_WEATHER_RADIUS_EARTH", 6378.15);
// }}}
// }}}

// {{{ class Services_Weather_Common
/**
* PEAR::Services_Weather_Common
*
* Parent class for weather-services. Defines common functions for unit
* conversions, checks for astronomy-class, cache enabling and does other
* miscellaneous things. 
*
* @author       Alexander Wirtz <alex@pc4p.net>
* @package      Services
* @version      1.0
*/
class Services_Weather_Common {

    // {{{ properties
    /**
    * Format of the units provided (standard/metric)
    *
    * @var      string                      $_unitsFormat
    * @access   private
    */
    var $_unitsFormat = "s";

    /**
    * Format of the used dates
    *
    * @var      string                      $_dateFormat
    * @access   private
    */
    var $_dateFormat = "m/d/y";

    /**
    * Format of the used times
    *
    * @var      string                      $_timeFormat
    * @access   private
    */
    var $_timeFormat = "G:i A";

    /**
    * Object containing the units-data
    *
    * @var      object stdClass             $_units
    * @access   private
    */
    var $_units;

    /**
    * Object containing the location-data
    *
    * @var      object stdClass             $_location
    * @access   private
    */
    var $_location;

    /**
    * Object containing the weather-data
    *
    * @var      object stdClass             $_weather
    * @access   private
    */
    var $_weather;

    /**
    * Object containing the forecast-data
    *
    * @var      object stdClass             $_forecast
    * @access   private
    */
    var $_forecast;

    /**
    * Object for registry lookups
    *
    * @var      object  PEAR_Registry       $_registry
    * @access   private
    */
    var $_registry;

    /**
    * Cache, containing the data-objects
    *
    * @var      object Cache                $_cache
    * @access   private
    */
    var $_cache;

    /**
    * Provides check for Cache
    *
    * @var      bool                        $_cacheEnabled
    * @access   private
    */
    var $_cacheEnabled = FALSE;

    /**
    * Provides check for Science_Astronomy
    *
    * @var      bool                        $_astroEnabled
    * @access   private
    */
    var $_astroEnabled = FALSE;
    // }}}

    // {{{ constructor
    /**
    * Constructor
    *
    * @access   private
    */
    function Services_Weather_Common()
    {
        $this->_registry = new PEAR_Registry();
        if($this->_registry->packageExists("Science_Astronomy")) {
            $this->_astroEnabled = TRUE;
        }
    }
    // }}}

    // {{{ setCache()
    /**
    * Enables caching the data, usage strongly recommended
    *
    * @param    string                      $cacheType
    * @param    array                       $cacheOptions
    * @return   PEAR_Error|bool
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_CACHE_INIT_FAILED
    * @access   public
    */
    function setCache($cacheType = "file", $cacheOptions = array())
    {
        if($this->_registry->packageExists("Cache")) {
            require_once "Cache.php";
            @$cache = new Cache($cacheType, $cacheOptions);
            if (!is_object($cache) || !is_subclass_of($cache, "cache_container")) {
                $this->_cache = NULL;
                $this->_cacheEnabled = FALSE;
                return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_CACHE_INIT_FAILED);
            } else {
                $this->_cache = $cache;
                $this->_cacheEnabled = TRUE;
                return TRUE;
            }
        }
    }
    // }}}

    // {{{ setUnitsFormat()
    /**
    * Changes the representation of the units (standard/metric)
    *
    * @param    string                      $unitsFormat
    * @access   public
    */
    function setUnitsFormat($unitsFormat)
    {
        if (strlen($unitsFormat) && in_array(strtolower($unitsFormat{0}), array("s", "m"))) {
            $this->_unitsFormat = strtolower($unitsFormat{0});
        }
    }
    // }}}

    // {{{ setDateTimeFormat()
    /**
    * Changes the representation of time and dates (see http://www.php.net/date)
    *
    * @param    string                      $dateFormat
    * @param    string                      $timeFormat
    * @access   private
    */
    function setDateTimeFormat($dateFormat = "", $timeFormat = "")
    {
        if (strlen($dateFormat)) {
            $this->_dateFormat = $dateFormat;
        }
        if (strlen($timeFormat)) {
            $this->_timeFormat = $timeFormat;
        }
    }
    // }}}

    // {{{ convertTemperature()
    /**
    * Convert temperature between f and c
    *
    * @param    float                       $temperature
    * @param    string                      $from
    * @param    string                      $to
    * @return   float
    * @access   public
    */
    function convertTemperature($temperature, $from, $to)
    {
        $from = strtolower($from{0});
        $to   = strtolower($to{0});

        $result = array(
            "f" => array(
                "f" => $temperature,            "c" => ($temperature - 32) / 1.8
            ),
            "c" => array(
                "f" => 1.8 * $temperature + 32, "c" => $temperature
            )
        );

        return round($result[$from][$to], 2);
    }
    // }}}

    // {{{ convertSpeed()
    /**
    * Convert speed between mph, kmh, kt, mps and fps
    *
    * @param    float                       $speed
    * @param    string                      $from
    * @param    string                      $to
    * @return   float
    * @access   public
    */
    function convertSpeed($speed, $from, $to)
    {
        $from = strtolower($from);
        $to   = strtolower($to);

        $factor = array(
            "mph" => array(
                "mph" => 1,         "kmh" => 1.609344, "kt" => 0.8689762, "mps" => 0.44704,   "fps" => 1.4666667
            ),
            "kmh" => array(
                "mph" => 0.6213712, "kmh" => 1,        "kt" => 0.5399568, "mps" => 0.2777778, "fps" => 0.9113444
            ),
            "kt"  => array(
                "mph" => 1.1507794, "kmh" => 1.852,    "kt" => 1,         "mps" => 0.5144444, "fps" => 1.6878099
            ),
            "mps" => array(
                "mph" => 2.2369363, "kmh" => 3.6,      "kt" => 1.9438445, "mps" => 1,         "fps" => 3.2808399
            ),
            "fps" => array(
                "mph" => 0.6818182, "kmh" => 1.09728,  "kt" => 0.5924838, "mps" => 0.3048,    "fps" => 1
            )
        );

        return round($speed * $factor[$from][$to], 2);
    }
    // }}}

    // {{{ convertPressure()
    /**
    * Convert pressure between in, hpa, mb, mm and atm
    *
    * @param    float                       $pressure
    * @param    string                      $from
    * @param    string                      $to
    * @return   float
    * @access   public
    */
    function convertPressure($pressure, $from, $to)
    {
        $from = strtolower($from);
        $to   = strtolower($to);

        $factor = array(
            "in"   => array(
                "in" => 1,         "hpa" => 33.863887, "mb" => 33.863887, "mm" => 25.4,      "atm" => 0.0334213
            ),
            "hpa"  => array(
                "in" => 0.02953,   "hpa" => 1,         "mb" => 1,         "mm" => 0.7500616, "atm" => 0.0009869
            ),
            "mb"   => array(
                "in" => 0.02953,   "hpa" => 1,         "mb" => 1,         "mm" => 0.7500616, "atm" => 0.0009869
            ),
            "mm"   => array(
                "in" => 0.0393701, "hpa" => 1.3332239, "mb" => 1.3332239, "mm" => 1,         "atm" => 0.0013158
            ),
            "atm"  => array(
                "in" => 29,921258, "hpa" => 1013.2501, "mb" => 1013.2501, "mm" => 759.999952, "atm" => 1
            )
        );

        return round($pressure * $factor[$from][$to], 2);
    }
    // }}}

    // {{{ convertDistance()
    /**
    * Convert distance between km, ft and sm
    *
    * @param    float                       $distance
    * @param    string                      $from
    * @param    string                      $to
    * @return   float
    * @access   public
    */
    function convertDistance($distance, $from, $to)
    {
        $to   = strtolower($to);
        $from = strtolower($from);

        $factor = array(
            "km" => array(
                "km" => 1,         "ft" => 3280.839895, "sm" => 0.6213699
            ),
            "ft" => array(
                "km" => 0.0003048, "ft" => 1,           "sm" => 0.0001894
            ),
            "sm" => array(
                "km" => 1.6093472, "ft" => 5280.0106,   "sm" => 1
            )
        );

        return round($distance * $factor[$from][$to], 2);
    }
    // }}}

    // {{{ calculateWindChill()
    /**
    * Calculate windchill from temperature and windspeed (enhanced formula)
    *
    * @param    float                       $temperature
    * @param    float                       $speed
    * @return   float
    * @access   public
    */
    function calculateWindChill($temperature, $speed)
    {
        return round(35.74 + 0.6215 * $temperature - 35.75 * pow($speed, 0.16) + 0.4275 * $temperature * pow($speed, 0.16));
    }
    // }}}

    // {{{ polar2cartesian()
    /**
    * Convert polar coordinates to cartesian coordinates
    *
    * @param    float                       $latitude
    * @param    float                       $longitude
    * @return   array
    * @access   public
    */
    function polar2cartesian($latitude, $longitude)
    {
        $theta = deg2rad($latitude);
        $phi   = deg2rad($longitude);

        $x = SERVICES_WEATHER_RADIUS_EARTH * cos($phi) * cos($theta);
        $y = SERVICES_WEATHER_RADIUS_EARTH * sin($phi) * cos($theta);
        $z = SERVICES_WEATHER_RADIUS_EARTH             * sin($theta);

        return array($x, $y, $z);
    }
    // }}}
}
// }}}
?>
