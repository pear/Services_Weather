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
* Of course the parsing of the METAR-data has its limitations, as it follows the
* Federal Meteorological Handbook No.1 with modifications to accomodate for non-US reports,
* so if the report deviates from these standards, you won't get it parsed correctly.
* Anything that is not parsed, is saved in the "noparse" array-entry, returned by
* getWeather(), so you can do your own parsing afterwards. This limitation is specifically
* given for remarks, as the class is not processing everything mentioned there, but you will
* get the most common fields like precipitation and temperature-changes. Again, everything
* not parsed, goes into "noparse".
*
* If you think, some important field is missing or not correctly parsed, please file a feature-
* request/bugreport at http://pear.php.net/ and be sure to provide the METAR report with a
* _detailed_ explanation!
*
* For a working example, please take a look at
*     docs/Services_Weather/examples/metar-basic.php
*
* @author       Alexander Wirtz <alex@pc4p.net>
* @link         http://weather.noaa.gov/weather/metar.shtml
* @example      docs/Services_Weather/examples/metar-basic.php
* @package      Services_Weather
* @license      http://www.php.net/license/2_02.txt
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
    * @param    string                      $dsn
    * @param    array                       $options
    * @return   DB_Error|bool
    * @throws   DB_Error
    * @see      DB::parseDSN
    * @access   public
    */
    function setMetarDB($dsn, $options = array())
    {
        $dsninfo = DB::parseDSN($dsn);
        if (is_array($dsninfo) && !isset($dsninfo["mode"])) {
            $dsninfo["mode"]= 0644;
        }
        
        // Initialize connection to DB and store in object if successful
        $db =  DB::connect($dsninfo, $options);
        if (DB::isError($db)) {
            return $db;
        }
        $this->_db = $db;

        return true;
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
        return true;
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
    * @param    array                       $units
    * @param    int                         $days
    * @return   PEAR_Error|array
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @access   private
    */
    function _parseWeatherData($id = "", $url, $units, $days = 0)
    {
        static $compass;
        static $clouds;
        static $conditions;
        static $sensors;
        if (!isset($compass)) {
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
                "vv"          => "vertical visibility",
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
            $sensors = array(
                "rvrno"     => "Runway Visual Range Detector offline",
                "pwino"     => "Present Weather Identifier offline",
                "pno"       => "Tipping Bucket Rain Gauge offline",
                "fzrano"    => "Freezing Rain Sensor offline",
                "tsno"      => "Lightning Detection System offline",
                "visno_loc" => "2nd Visibility Sensor offline",
                "chino_loc" => "2nd Ceiling Height Indicator offline"
            );
        }
 
        $metarCode = array(
            "report"      => "METAR|SPECI",
            "station"     => "\w{4}",
            "update"      => "(\d{2})?(\d{4})Z",
            "type"        => "AUTO|COR",
            "wind"        => "(\d{3}|VAR|VRB)(\d{2,3})(G(\d{2}))?(\w{2,3})",
            "windVar"     => "(\d{3})V(\d{3})",
            "visibility1" => "^\d$",
            "visibility2" => "(\d{4})|((\d{1,2}|(\d)\/(\d))(SM|KM))|(CAVOK)",
            "runway"      => "R(\d{2})(\w)?\/(P|M)?(\d{4})(FT)?(V(P|M)?(\d{4})(FT)?)?(\w)?",
            "condition"   => "(-|\+|VC)?(MI|BC|PR|TS|BL|SH|DR|FZ)?(DZ|RA|SN|SG|IC|PL|GR|GS|UP)?(BR|FG|FU|VA|DU|SA|HZ|PY)?(PO|SQ|FC|SS|DS)?",
            "clouds"      => "(SKC|CLR|((FEW|SCT|BKN|OVC|VV)(\d{3})(TCU|CB)?))",
            "temperature" => "(M)?(\d{2})\/((M)?(\d{2})|XX|\/\/)?",
            "pressure"    => "(A)(\d{4})|(Q)(\d{4})",
            "nosig"       => "NOSIG",
            "remark"      => "RMK"
        );
        
        $remarks = array(
            "nospeci"     => "NOSPECI",
            "autostation" => "AO(1|2)",
            "presschg"    => "PRESS(R|F)R",
            "seapressure" => "SLP(\d{3}|NO)",
            "1hprecip"    => "P(\d{4})",
            "6hprecip"    => "6(\d{4}|\/{4})",
            "24hprecip"   => "7(\d{4}|\/{4})",
            "snowdepth"   => "4\/(\d{3})",
            "snowequiv"   => "933(\d{3})",
            "cloudtypes"  => "8\/(\d|\/)(\d|\/)(\d|\/)",
            "sunduration" => "98(\d{3})",
            "1htempdew"   => "T(0|1)(\d{3})((0|1)(\d{3}))?",
            "6hmaxtemp"   => "1(0|1)(\d{3})",
            "6hmintemp"   => "2(0|1)(\d{3})",
            "24htemp"     => "4(0|1)(\d{3})(0|1)(\d{3})",
            "3hpresstend" => "5([0-8])(\d{3})",
            "sensors"     => "RVRNO|PWINO|PNO|FZRANO|TSNO|VISNO_LOC|CHINO_LOC",
            "maintain"    => "[\$]"
        );        

        $data = @file($url);

        // Check for correct data, 2 lines in size
        if (!$data || !is_array($data) || sizeof($data) < 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA);
        } elseif (sizeof($data) > 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION);
        } else {
            // Ok, we have correct data, start with parsing the first line for the last update
            $weatherData = array();
            $weatherData["station"] = $id;
            $weatherData["update"]  = date($this->_dateFormat." ".$this->_timeFormat, strtotime(trim($data[0])) + date("Z"));
            // and prepare the second line for stepping through
            $metar = explode(" ", trim($data[1]));

            for ($i = 0; $i < sizeof($metar); $i++) {
                // Check for whitespace and step loop, if nothing's there
                $metar[$i] = trim($metar[$i]);
                if (!strlen($metar[$i])) {
                    continue;
                }

                if(SERVICES_WEATHER_DEBUG) {
                    $tab = str_repeat("\t", 2 - floor(strlen($metar[$i]) / 8));
                    echo $metar[$i].$tab."-> ";
                }

                $found = false;
                foreach ($metarCode as $key => $regexp) {
                    // Check if current code matches current metar snippet
                    if ($found = preg_match("/^".$regexp."$/i", $metar[$i], $result)) {
                        switch ($key) {
                            case "wind":
                                // Parse wind data, first the speed, convert from kt to chosen unit
                                $weatherData["wind"] = $this->convertSpeed($result[2], strtolower($result[5]), str_replace("/", "", $units["wind"]));
                                if ($result[1] == "VAR" || $result[1] == "VRB") {
                                    // Variable winds
                                    $weatherData["windDegrees"]   = "Variable";
                                    $weatherData["windDirection"] = "Variable";
                                } else {
                                    // Save wind degree and calc direction
                                    $weatherData["windDegrees"]   = $result[1];
                                    $weatherData["windDirection"] = $compass[round($result[1] / 22.5) % 16];
                                }
                                if (is_numeric($result[4])) {
                                    // Wind with gusts...
                                    $weatherData["windGust"] = $this->convertSpeed($result[4], strtolower($result[5]), str_replace("/", "", $units["wind"]));
                                }
                                // We got that, unset
                                unset($metarCode["wind"]);
                                break;
                            case "windVar":
                                // Once more wind, now variability around the current wind-direction
                                $weatherData["windVariability"] = array("from" => $result[1], "to" => $result[2]);
                                unset($metarCode["windVar"]);
                                break;
                            case "visibility1":
                                // Visibility will come as x y/z, first the single digit part
                                $weatherData["visibility"] = $result[0];
                                unset($metarCode["visibility1"]);
                                break;
                            case "visibility2":
                                if (is_numeric($result[1]) && ($result[1] == 9999)) {
                                    // Upper limit of visibility range
                                    $visibility = "greater than ".$this->convertDistance(10, "km", $units["vis"]).$units["vis"];
                                } elseif (is_numeric($result[1])) {
                                    // 4-digit visibility in m
                                    $visibility = $this->convertDistance(($result[1]/1000), "km", $units["vis"]);
                                } elseif ($result[7] != "CAVOK") {
                                    if (is_numeric($result[3])) {
                                        // visibility as one/two-digit number
                                        $visibility = $this->convertDistance($result[3], $result[6], $units["vis"]);
                                    } else {
                                        // the y/z part, add if we had a x part (see visibility1)
                                        $visibility = $this->convertDistance($result[4] / $result[5], $result[6], $units["vis"]);
                                        if (isset($weatherData["visibility"])) {
                                            $visibility += $weatherData["visibility"];
                                        }
                                    }
                                } else {
                                    $visibility               = "greater than ".$this->convertDistance(10, "km", $units["vis"]).$units["vis"];
                                    $weatherData["clouds"]    = array("amount" => "none", "height" => "below 5000ft");
                                    $weatherData["condition"] = "no  significant weather";
                                }
                                $weatherData["visibility"] = $visibility;
                                unset($metarCode["visibility2"]);
                                break;
                            case "condition":
                                // First some basic setups
                                if (!isset($weatherData["condition"])) {
                                    $weatherData["condition"] = "";
                                } elseif (strlen($weatherData["condition"]) > 0) {
                                    $weatherData["condition"] .= ",";
                                }

                                if (in_array(strtolower($result[0]), $conditions)) {
                                    // First try matching the complete string
                                    $weatherData["condition"] .= " ".$conditions[strtolower($result[0])];
                                } else {
                                    // No luck, match part by part
                                    for ($c = 1; $c < sizeof($result); $c++) {
                                        if (strlen($result[$c]) > 0) {
                                            $weatherData["condition"] .= " ".$conditions[strtolower($result[$c])];
                                        }
                                    }
                                }
                                $weatherData["condition"] = trim($weatherData["condition"]);
                                break;
                            case "clouds":
                                if (!isset($weatherData["clouds"])) {
                                    $weatherData["clouds"] = array();
                                }

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
                                break;
                            case "temperature":
                                // normal temperature in first part
                                $temperature = $this->convertTemperature($result[2], "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[1] == "M") {
                                    $temperature *= -1;
                                }
                                $weatherData["temperature"] = $temperature;
                                if (sizeof($result) > 4) {
                                    // same for dewpoint
                                    $dewPoint = $this->convertTemperature($result[5], "c", strtolower($units["temp"]));
                                    if ($result[4] == "M") {
                                        $dewPoint *= -1;
                                    }
                                    $weatherData["dewPoint"] = $dewPoint;
                                    $weatherData["humidity"] = $this->calculateHumidity($temperature, $dewPoint);
                                }
                                if (isset($weatherData["wind"])) {
                                    // Now calculate windchill from temperature and windspeed
                                    $feltTemperature = $this->calculateWindChill($this->convertTemperature($weatherData["temperature"], strtolower($units["temp"]), "f"), $this->convertSpeed($weatherData["wind"], str_replace("/", "", $units["wind"]), "mph"));
                                    $weatherData["feltTemperature"] = $this->convertTemperature($feltTemperature, "f", strtolower($units["temp"]));
                                }
                                unset($metarCode["temperature"]);
                                break;
                            case "pressure":
                                if ($result[1] == "A") {
                                    // Pressure provided in inches
                                    $weatherData["pressure"] = $this->convertPressure(($result[2] / 100), "in", $units["pres"]);
                                } elseif ($result[3] == "Q") {
                                    // ... in hectopascal
                                    $weatherData["pressure"] = $this->convertPressure($result[4], "hpa", $units["pres"]);
                                }
                                unset($metarCode["pressure"]);
                                break;
                            case "nosig":
                            case "nospeci":
                                // No change during the last hour
                                if (!isset($weatherData["remark"])) {
                                    $weatherData["remark"] = array();
                                }
                                $weatherData["remark"][] = "No changes in weather conditions";
                                unset($metarCode[$key]);
                                break;
                            case "remark":
                                // Remark part begins
                                $metarCode = $remarks;
                                if (!isset($weatherData["remark"])) {
                                    $weatherData["remark"] = array();
                                }
                                break;
                            case "autostation":
                                // Which autostation do we have here?
                                if ($result[1] == 0) {
                                    $weatherData["remark"][] = "Automatic weatherstation w/o precipitation discriminator";
                                } else {
                                    $weatherData["remark"][] = "Automatic weatherstation w/ precipitation discriminator";
                                }
                                unset($metarCode["autostation"]);
                                break;
                            case "presschg":
                                // Decoding for rapid pressure changes
                                if (strtolower($result[1]) == "r") {
                                    $weatherData["remark"][] = "Pressure rising rapidly";
                                } else {
                                    $weatherData["remark"][] = "Pressure falling rapidly";
                                }
                                unset($metarCode["presschg"]);
                                break;
                            case "seapressure":
                                // Pressure at sea level (delivered in inches)
                                // Decoding is a bit obscure as 982 gets 998.2
                                // whereas 113 becomes 1113 -> no real rule here
                                if (strtolower($result[1]) != "no") {
                                    if ($result[1] > 500) {
                                        $press = 900 + round($result[1] / 100, 1);
                                    } else {
                                        $press = 1000 + $result[1];
                                    }
                                    $weatherData["remark"][] = "Sea-level pressure: ".$this->convertPressure($press, "hpa", $units["pres"]).$units["pres"];
                                }
                                unset($metarCode["seapressure"]);
                                break;
                            case "1hprecip":
                                // Precipitation for the last hour in inches
                                if ($result[1] == 0) {
                                    $precip = "less than ".$this->convertPressure(1/100, "in", $units["rain"]).$units["rain"];
                                } else {
                                    $precip = $this->convertPressure($result[1]/100, "in", $units["rain"]).$units["rain"];
                                }
                                $weatherData["remark"][] = "Precipitation last hour: ".$precip;
                                unset($metarCode["1hprecip"]);
                                break;
                            case "6hprecip":
                                // Same for last 3 resp. 6 hours... no way to determine
                                // which report this is, so keeping the text general
                                if (!is_numeric($result[1])) {
                                    $precip = "indetermindable";
                                } elseif ($result[1] == 0) {
                                    $precip = "traceable";
                                }else {
                                    $precip = $this->convertPressure($result[1] / 100, "in", $units["rain"]).$units["rain"];
                                }
                                $weatherData["remark"][] = "Precipitation last 3/6 hours: ".$precip;
                                unset($metarCode["6hprecip"]);
                                break;
                            case "24hprecip":
                                // And the same for the last 24 hours
                                if (!is_numeric($result[1])) {
                                    $precip = "indetermindable";
                                } elseif ($result[1] == 0) {
                                    $precip = "traceable";
                                }else {
                                    $precip = $this->convertPressure($result[1] / 100, "in", $units["rain"]).$units["rain"];
                                }
                                $weatherData["remark"][] = "Precipitation last 24 hours: ".$precip;
                                unset($metarCode["24hprecip"]);
                                break;
                            case "snowdepth":
                                // Snow depth in inches
                                $snow = $this->convertPressure($result[1], "in", $units["rain"]).$units["rain"];
                                $weatherData["remark"][] = "Snow depth on ground: ".$snow;
                                unset($metarCode["snowdepth"]);
                                break;
                            case "snowequiv":
                                // Same for equivalent in Water... (inches)
                                $equiv = $this->convertPressure($result[1] / 10, "in", $units["rain"]).$units["rain"];
                                $weatherData["remark"][] = "Water equivalent of snow on ground: ".$equiv;
                                unset($metarCode["snowequiv"]);
                                break;
                            case "cloudtypes":
                                // Cloud types, haven't found a way for decent decoding (yet)
                                unset($metarCode["cloudtypes"]);
                                break;
                            case "sunduration":
                                // Duration of sunshine (in minutes)
                                $weatherData["remark"][] = "Total minutes of sunshine: ".$result[1];
                                unset($metarCode["sunduration"]);
                                break;
                            case "1htempdew":
                                // Temperatures in the last hour in C
                                $temp = $this->convertTemperature($result[2] / 10, "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[1] == "1") {
                                    $temp *= -1;
                                }
                                $temptext = "Temperature";
                                $temp     = $temp.$units["temp"];
                                if (sizeof($result) > 3) {
                                    // same for dewpoint
                                    $dew = $this->convertTemperature($result[5] / 10, "c", strtolower($units["temp"]));
                                    if ($result[4] == "1") {
                                        $dew *= -1;
                                    }
                                    $temptext = $temptext."/Dewpoint";
                                    $temp     = $temp."/".$dew.$units["temp"];
                                }
                                $weatherData["remark"][] = $temptext." during the last hour: ".$temp;
                                unset($metarCode["1htempdew"]);
                                break;
                            case "6hmaxtemp":
                                // Max temperature in the last 6 hours in C
                                $max = $this->convertTemperature($result[2] / 10, "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[1] == "1") {
                                    $max *= -1;
                                }
                                $weatherData["remark"][] = "Maximum temperature in the last 6 hours: ".$max.$units["temp"];
                                unset($metarCode["6hmaxtemp"]);
                                break;
                            case "6hmintemp":
                                // Min temperature in the last 6 hours in C
                                $min = $this->convertTemperature($result[2] / 10, "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[1] == "1") {
                                    $min *= -1;
                                }
                                $weatherData["remark"][] = "Minimum temperature in the last 6 hours: ".$min.$units["temp"];
                                unset($metarCode["6hmintemp"]);
                                break;
                            case "24htemp":
                                // Max/Min temperatures in the last 24 hours in C
                                $max = $this->convertTemperature($result[2] / 10, "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[1] == "1") {
                                    $max *= -1;
                                }
                                $min = $this->convertTemperature($result[4] / 10, "c", strtolower($units["temp"]));
                                // negative value
                                if ($result[3] == "1") {
                                    $min *= -1;
                                }
                                $weatherData["remark"][] = "Temperatures in the last 24 hours: max ".$max.$units["temp"]."/min ".$min.$units["temp"];
                                unset($metarCode["24htemp"]);
                                break;
                            case "3hpresstend":
                                // We don't save the pressure during the day, so no decoding
                                // possible, sorry
                                unset($metarCode["3hpresstend"]);
                                break;
                            case "sensors":
                                // We may have multiple broken sensors, so do not unset
                                $weatherData["remark"][] = $sensors[strtolower($result[0])];
                                break;
                            case "maintain":
                                $weatherData["remark"][] = "Maintainance needed";
                                unset($metarCode["maintain"]);
                                break;
                            default:
                                // Do nothing, just prevent further matching
                                unset($metarCode[$key]);
                                break;
                        }
                        if (SERVICES_WEATHER_DEBUG) {
                            echo $key."\n";
                        }
                        break;
                    }
                }
                if (!$found) {
                    if (SERVICES_WEATHER_DEBUG) {
                        echo "n/a\n";
                    }
                    if (!isset($weatherData["noparse"])) {
                        $weatherData["noparse"] = array();
                    }
                    $weatherData["noparse"][] = $metar[$i];
                }
            }
        }
        if (isset($weatherData["remark"])) {
            $weatherData["remark"]  = implode(", ", $weatherData["remark"]);
        }
        if (isset($weatherData["noparse"])) {
            $weatherData["noparse"] = implode(" ",  $weatherData["noparse"]);
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
    function searchLocation($location, $useFirst = false)
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
            while (($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) != null) {
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
        $select = "SELECT icao, x, y, z FROM metarAirports";
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
        while (($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) != null) {
            $icao = $row["icao"];
            $air  = array($row["x"], $row["y"], $row["z"]);

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
            "vis"   => "sm",
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

        if ($this->_cacheEnabled && ($location = $this->_cache->get("METAR-".$id, "location"))) {
            // Grab stuff from cache
            $this->_location = $location;
            $locationReturn["cache"] = "HIT";
        } elseif (isset($this->_db) && DB::isConnection($this->_db)) {
            // Get data from DB
            $select = "SELECT icao, name, state, country, latitude, longitude, elevation ".
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
                $this->_cache->extSave("METAR-".$id, $this->_location, "", $expire, "location");
            }

            $locationReturn["cache"] = "MISS";
        } else {
            $this->_location = array(
                "name"      => $id,
                "state"     => "",
                "country"   => "",
                "latitude"  => "",
                "longitude" => "",
                "elevation" => ""
            );
        }
        // Stuff name-string together
        if (strlen($this->_location["state"]) && strlen($this->_location["country"])) {
            $locname = $this->_location["name"].", ".$this->_location["state"].", ".$this->_location["country"];
        } elseif(strlen($this->_location["country"])) {
            $locname = $this->_location["name"].", ".$this->_location["country"];
        } else {
            $locname = $this->_location["name"];
        }
        $locationReturn["name"]      = $locname;
        $locationReturn["latitude"]  = $this->_location["latitude"];
        $locationReturn["longitude"] = $this->_location["longitude"];
        $locationReturn["elevation"] = $this->_location["elevation"];

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

        if ($this->_cacheEnabled && ($weather = $this->_cache->get("METAR-".$id, "weather"))) {
            // Wee... it was cached, let's have it...
            $weatherReturn  = $weather;
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "HIT";
        } else {
            // Download and parse weather
            $weatherReturn  = $this->_parseWeatherData("", $weatherURL, $units, 0);

            if (Services_Weather::isError($weatherReturn)) {
                return $weatherReturn;
            }
            if ($this->_cacheEnabled) {
                // Cache weather
                $expire = constant("SERVICES_WEATHER_EXPIRES_WEATHER");
                $this->_cache->extSave("METAR-".$id, $weatherReturn, $unitsFormat, $expire, "weather");
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
    * METAR has no forecast per se, so this function is just for
    * compatibility purposes.
    *
    * @param    string                      $int
    * @param    int                         $days
    * @param    string                      $unitsFormat
    * @return   bool
    * @access   public
    * @deprecated
    */
    function getForecast($id = null, $days = null, $unitsFormat = null)
    {
        return false;
    }
    // }}}
}
// }}}
?>
