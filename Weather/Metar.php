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

require_once "DB.php";

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
* @package      Services_Weather
* @version      1.0
*/
class Services_Weather_Metar extends Services_Weather_Common
{
    // {{{ properties
    /**
    * Information to access the location DB
    *
    * @var      object  DB                  $_db
    * @access   private
    */
    var $_db;
    // }}}

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

    // {{{ setMetarDB()
    /**
    * Sets the parameters needed for connecting to the DB, where the location-
    * search is fetching its data from. You need to build a DB with the external
    * tool buildMetarDB first, it fetches the locations and airports from a
    * NOAA-website.
    *
    * @param    string                      $dbType
    * @param    string                      $dbUser
    * @param    string                      $dbPass
    * @param    string                      $dbHost
    * @param    string                      $dbName
    * @param    array                       $dbOptions
    * @return   DB_Error|bool
    * @throws   DB_Error
    * @see      DB
    * @access   public
    */
    function setMetarDB($dbType, $dbUser, $dbPass, $dbHost, $dbName, $dbOptions)
    {
        $dsn     = $dbType."://".$dbUser.":".$dbPass."@".$dbHost."/".$dbName;
        $dsninfo = array(
            "phptype"  => $dbType,
            "username" => $dbUser,
            "password" => $dbPass,
            "hostspec" => $dbHost,
            "database" => $dbName,
            "mode"     => 0644
        );
        
        // Initialize connection to DB and store in object if successful
        $db =  DB::connect($dsninfo, $dbOptions);
        if (DB::isError($db)) {
            return $db;
        }
        $this->_db = $db;

        return TRUE;
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
        if (!strlen($id)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_NO_LOCATION);
        } elseif (!ctype_alpha($id) || (strlen($id) > 4)) {
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
    * @return   PEAR_Error|array
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
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
                "wind"        => "(\d{3}|VAR|VRB)(\d{2,3})(G(\d{2}))?(\w{2,3})",
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
                "clr"         => "clear below 12,000 ft"
            );
            $conditions = array(
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

        $data = @file($url);

        // Check for correct data, 2 lines in size
        if (!$data || !is_array($data) || sizeof($data) < 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA);
        } elseif (sizeof($data) > 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
        } else {
            // Ok, we have correct data, start with parsing the first line for the last update
            $weatherData = array();
            $weatherData["update"] = date( $this->_timeFormat, strtotime(trim($data[0])) + date("Z") );
            // and prepare the second line for stepping through
            $metar = explode(" ", $data[1]);

            foreach ($metarCode as $key => $regexp) {
                // We use the code as anchor
                if(SERVICES_WEATHER_DEBUG) {
                    echo $key."->".current($metar)."\n";
                }
                // Check if current code matches current metar snippet
                if (preg_match("/".$regexp."/i", current($metar), $result)) {
                    switch ($key) {
                        case "station":
                            // Simple the station... no big deal
                            $weatherData["station"] = $result[0];
                            break;
                        case "wind":
                            // Parse wind data, first the speed, convert from kt to chosen unit
                            $weatherData["wind"] = $this->convertSpeed($result[2], strtolower($result[5]), str_replace("/", "", $this->_units["wind"]));
                            if ($result[1] == "VAR" || $result[1] == "VRB") {
                                // Variable winds
                                $weatherData["windDegrees"] = "Variable";
                                $weatherData["windDirection"] = "Variable";
                            } else {
                                // Save wind degree and calc direction
                                $weatherData["windDegrees"] = $result[1];
	                			$weatherData["windDirection"] = $compass[round($result[1] / 22.5) % 16];
                            }
                            if (is_numeric($result[4])) {
                                // Wind with gusts...
                                $weatherData["windGust"] = $this->convertSpeed($result[4], strtolower($result[5]), str_replace("/", "", $this->_units["wind"]));
                            }
                            break;
                        case "windVar":
                            // Once more wind, now variability around the current wind-direction
                            $weatherData["windVariability"] = array("from" => $result[1], "to" => $result[2]);
                            break;
                        case "visibility1":
                            // Visibility will come as x y/z, first the single digit part
                            $weatherData["visibility"] = $result[0];
                            break;
                        case "visibility2":
                            if (is_numeric($result[1])) {
                                // 4-digit visibility in m
                                $visibility = $this->convertDistance(($result[1]/1000), "km", $this->_units["vis"]);
                            } else {
                                if (is_numeric($result[3])) {
                                    // visibility as one/two-digit number
                                    $visibility = $this->convertDistance($result[3], $result[6], $this->_units["vis"]);
                                } else {
                                    // the y/z part, add if we had a x part (see visibility1)
                                    $visibility = $this->convertDistance($result[4] / $result[5], $result[6], $this->_units["vis"]);
                                    if (isset($weatherData["visibility"])) {
                                        $visibility += $weatherData["visibility"];
                                    }
                                }
                            }
                            $weatherData["visibility"] = $visibility;
                            break;
                        case "condition":
                            // We may have several condition strings, so extra parse-loop
                            prev($metar);
                            $weatherData["condition"] = "";
                            while (preg_match("/".$regexp."/i", next($metar), $result)) {
                                if (strlen($weatherData["condition"]) > 0) {
                                    $weatherData["condition"] .= ",";
                                }
                                if (in_array(strtolower($result[0]), $conditions)) {
                                    // First try matching the complete string
                                    $weatherData["condition"] .= " ".$conditions[strtolower($result[0])];
                                } else {
                                    // No luck, match part by part
                                    for ($i = 1; $i < sizeof($result); $i++) {
                                        if (strlen($result[$i]) > 0) {
                                            $weatherData["condition"] .= " ".$conditions[strtolower($result[$i])];
                                        }
                                    }
                                }
                            }
                            prev($metar);
                            $weatherData["condition"] = trim($weatherData["condition"]);
                            break;
                        case "clouds":
                            // Same approach as the condition-part, multiple clouds possible
                            prev($metar);
                            $weatherData["clouds"] = array();
                            while (preg_match("/".$regexp."/i", next($metar), $result)) {
                                if (sizeof($result) == 5) {
                                    // Only amount and height
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4]*100));
                                }
                                elseif (sizeof($result) == 6) {
                                    // Amount, height and type
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4]*100), "type" => $clouds[strtolower($result[5])]);
                                }
                                else {
                                    // SKC or CLR
                                    $cloud = array("amount" => $clouds[strtolower($result[0])]);
                                }
                                $weatherData["clouds"][] = $cloud;
                            }
                            prev($metar);
                            break;
                        case "temperature":
                            // normal temperature in first part
                            $temperature = $this->convertTemperature($result[2], "c", strtolower($this->_units["temp"]));
                            // negative value
                            if ($result[1] == "M") {
                                $temperature *= -1;
                            }
                            $weatherData["temperature"] = $temperature;
                            if (sizeof($result) > 4) {
                                // same for dewpoint
                                $dewPoint = $this->convertTemperature($result[5], "c", strtolower($this->_units["temp"]));
                                if ($result[4] == "M") {
                                    $dewPoint *= -1;
                                }
                                $weatherData["dewPoint"] = $dewPoint;
                            }
                            if (isset($weatherData["wind"])) {
                                // Now calculate windchill from temperature and windspeed
                                $feltTemperature = $this->calculateWindChill($this->convertTemperature($weatherData["temperature"], strtolower($this->_units["temp"]), "f"), $this->convertSpeed($weatherData["wind"], str_replace("/", "", $this->_units["wind"]), "mph"));
                                $weatherData["feltTemperature"] = $this->convertTemperature($feltTemperature, "f", strtolower($this->_units["temp"]));
                            }
                            break;
                        case "pressure":
                            if ($result[1] == "A") {
                                // Pressure provided in inches
                                $weatherData["pressure"] = $this->convertPressure(($result[2] / 100), "in", $this->_units["pres"]);
                            } elseif ($result[3] == "Q") {
                                // ... in hectopascal
                                $weatherData["pressure"] = $this->convertPressure($result[4], "hpa", $this->_units["pres"]);
                            }
                            break;
                        default:
                            break;
                    }
                    if (!next($metar)) {
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
    * Searches IDs for given location, returns array of possible locations or single ID
    *
    * @param    string|array                $location
    * @param    bool                        $useFirst       If set, first ID of result-array is returned
    * @return   PEAR_Error|array|string
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_INVALID_LOCATION
    * @access   public
    */
    function searchLocation($location, $useFirst = FALSE)
    {
        if (!isset($this->_db) || !DB::isConnection($this->_db)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED);
        }
        
        if (is_string($location)) {
            // Try to part search string in name, state and country part
            // and build where clause from it for the select
            $location = explode(",", $location);
            if (sizeof($location) >= 1) {
                $where  = "lower(name) like '%".strtolower(trim($location[0]))."%'";
            }
            if (sizeof($location) == 2) {
                $where .= " AND lower(country) like '%".strtolower(trim($location[1]))."%'";
            } elseif (sizeof($location) == 3) {
                $where .= " AND lower(state) like '%".strtolower(trim($location[1]))."%'";
                $where .= " AND lower(country) like '%".strtolower(trim($location[2]))."%'";
            }
                
            // Create select, locations with ICAO first
            $select = "SELECT icao, name, state, country, latitude, longitude ".
                      "FROM metarLocations ".
                      "WHERE ".$where." ".
                      "ORDER BY icao DESC";
            $result = $this->_db->query($select);
            // Check result for validity
            if (DB::isError($result)) {
                return $result;
            } elseif (get_class($result) != "db_result" || $result->numRows() == 0) {
                return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
            }
            
            // Result is valid, start preparing the return
            $icao = array();
            while (($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) != NULL) {
                $locicao = $row["icao"];
                // First the name of the location
                if (!strlen($row["state"])) {
                    $locname = $row["name"].", ".$row["country"];
                } else {
                    $locname = $row["name"].", ".$row["state"].", ".$row["country"];
                }
                if ($locicao != "----") {
                    // We have a location with ICAO
                    $icao[$locicao] = $locname;
                } else {
                    // No ICAO, try finding the nearest airport
                    $locicao = $this->searchAirport($row["latitude"], $row["longitude"]);
                    if (!isset($icao[$locicao])) {
                        $icao[$locicao] = $locname;
                    }
                }
            }
            // Only one result? Return as string
            if (sizeof($icao) == 1) {
                $icao = key($icao);
            }
        } elseif (is_array($location)) {
            // Location was provided as coordinates, search nearest airport
            $icao = $this->searchAirport($location[0], $location[1]);
        } else {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION);
        }
        return $icao;
    }
    // }}}

    // {{{ searchAirport()
    /**
    * Searches the nearest airport(s) for given coordinates, returns array of IDs or single ID
    *
    * @param    float                       $latitude
    * @param    float                       $longitude
    * @param    int                         $numResults
    * @return   PEAR_Error|array|string
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_INVALID_LOCATION
    * @access   public
    */
    function searchAirport($latitude, $longitude, $numResults = 1)
    {
        if (!isset($this->_db) || !DB::isConnection($this->_db)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED);
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION);
        }           
        
        // Get all airports
        $select = "SELECT icao, latitude, longitude FROM metarAirports";
        $result = $this->_db->query($select);
        if (DB::isError($result)) {
            return $result;
        } elseif (get_class($result) != "db_result" || $result->numRows() == 0) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
        }

        // Result is valid, start search
        // Initialize values
        $min_dist = null;
        $query    = $this->polar2cartesian($latitude, $longitude);
        $search   = array("dist" => array(), "icao" => array());
        while (($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) != NULL) {
            $icao = $row["icao"];
            $air  = $this->polar2cartesian($row["latitude"], $row["longitude"]);

            $dist = 0;
            $d = 0;
            // Calculate distance of query and current airport
            // break off, if distance is larger than current $min_dist
            for($d; $d < sizeof($air); $d++) {
                $t = $air[$d] - $query[$d];
                $dist += pow($t, 2);
                if($min_dist != null && $dist > $min_dist) {
                    break;
                }
            }

            if($d >= sizeof($air)) {
                // Ok, current airport is one of the nearer locations
                // add to result-array
                $search["dist"][] = $dist;
                $search["icao"][] = $icao;
                // Sort array for distance
                array_multisort($search["dist"], SORT_NUMERIC, SORT_ASC, $search["icao"], SORT_STRING, SORT_ASC);
                // If array is larger then desired results, chop off last one
                if(sizeof($search["dist"]) > $numResults) {
                    array_pop($search["dist"]);
                    array_pop($search["icao"]);
                }
                $min_dist = max($search["dist"]);
            }
        }
        if ($numResults == 1) {
            // Only one result wanted, return as string
            return $search["icao"][0];
        } elseif ($numResults > 1) {
            // Return found locations
            return $search["icao"];
        } else {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
        }
    }
    // }}}

    // {{{ getUnits()
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
        // This is cheap'o stuff, no caching, no polling
        if (strlen($unitsFormat) && in_array(strtolower($unitsFormat{0}), array("s", "m"))) {
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
    // }}}

    // {{{ getLocation()
    /**
    * Returns the data for the location belonging to the ID
    *
    * @param    string                      $id
    * @return   PEAR_Error|array
    * @throws   PEAR_Error
    * @access   public
    */
    function getLocation($id = "")
    {
        $status = $this->_checkLocationID($id);

        if (Services_Weather::isError($status)) {
            return $status;
        }

        $locationReturn = array();

        if ($this->_cacheEnabled && ($location = $this->_cache->get($id, "location"))) {
            // Grab stuff from cache
            $this->_location = $location;
            $locationReturn["cache"] = "HIT";
        } else {
            // Get data from DB
            $select = "SELECT icao, name, state, country, latitude, longitude ".
                      "FROM metarAirports WHERE icao='".$id."'";
            $result = $this->_db->query($select);

            if (DB::isError($result)) {
                return $result;
            } elseif (get_class($result) != "db_result" || $result->numRows() == 0) {
                return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
            }
            // Result is ok, put things into object
            $this->_location = $result->fetchRow(DB_FETCHMODE_ASSOC);

            if($this->_cacheEnabled) {
                // ...and cache it
                $expire = constant("SERVICES_WEATHER_EXPIRES_LOCATION");
                $this->_cache->extSave($id, $this->_location, "", $expire, "location");
            }

            $locationReturn["cache"] = "MISS";
        }
        // Stuff name-string together
        if (!strlen($this->_location["state"])) {
            $locname = $this->_location["name"].", ".$this->_location["country"];
        } else {
            $locname = $this->_location["name"].", ".$this->_location["state"].", ".$this->_location["country"];
        }
        $locationReturn["name"]      = $locname;
        $locationReturn["latitude"]  = $this->_location["latitude"];
        $locationReturn["longitude"] = $this->_location["longitude"];

        return $locationReturn;
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
        $id     = strtoupper($id);
        $status = $this->_checkLocationID($id);

        if (Services_Weather::isError($status)) {
            return $status;
        }
        if (strlen($unitsFormat) && in_array(strtolower($unitsFormat{0}), array("s", "m"))) {
            $unitsFormat = strtolower($unitsFormat{0});
        } else {
            $unitsFormat = $this->_unitsFormat;
        }
        // Get other data
        $units    = $this->getUnits(null, $unitsFormat);
        $location = $this->getLocation($id);

        $weatherURL = "http://weather.noaa.gov/pub/data/observations/metar/stations/".$id.".TXT";

        if ($this->_cacheEnabled && ($weather = $this->_cache->get($id, "weather"))) {
            // Wee... it was cached, let's have it...
            $weatherReturn  = $weather;
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "HIT";
        } else {
            // Download and parse weather
            $weatherReturn  = $this->_parseWeatherData(null, $weatherURL, $unitsFormat, null);

            if (Services_Weather::isError($weatherReturn)) {
                return $weatherReturn;
            }
            if ($this->_cacheEnabled) {
                // Cache weather
                $expire = constant("SERVICES_WEATHER_EXPIRES_WEATHER");
                $this->_cache->extSave($id, $weatherReturn, $unitsFormat, $expire, "weather");
            }
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "MISS";
        }
        $weatherReturn["station"] = $location["name"];
        
        return $weatherReturn;
    }
    // }}}
    
    // {{{ getForecast()
    /**
    * Not yet implemented...
    */
    function getForecast($id = "", $days = 2, $unitsFormat = "")
    {
        return -1;
    }
    // }}}
}
// }}}
?>
