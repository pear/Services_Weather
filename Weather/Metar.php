<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2004 The PHP Group                                |
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

/**
* @package      Services_Weather
* @filesource
*/

/**
*/
require_once "Services/Weather/Common.php";

require_once "DB.php";

// {{{ class Services_Weather_Metar
/**
* PEAR::Services_Weather_Metar
*
* This class acts as an interface to the METAR/TAF service of
* weather.noaa.gov. It searches for locations given in ICAO notation and
* retrieves the current weather data.
*
* Of course the parsing of the METAR-data has its limitations, as it
* follows the Federal Meteorological Handbook No.1 with modifications to
* accomodate for non-US reports, so if the report deviates from these
* standards, you won't get it parsed correctly.
* Anything that is not parsed, is saved in the "noparse" array-entry,
* returned by getWeather(), so you can do your own parsing afterwards. This
* limitation is specifically given for remarks, as the class is not
* processing everything mentioned there, but you will get the most common
* fields like precipitation and temperature-changes. Again, everything not
* parsed, goes into "noparse".
*
* If you think, some important field is missing or not correctly parsed,
* please file a feature-request/bugreport at http://pear.php.net/ and be
* sure to provide the METAR (or TAF) report with a _detailed_ explanation!
*
* For working examples, please take a look at
*     docs/Services_Weather/examples/metar-basic.php
*     docs/Services_Weather/examples/metar-extensive.php
*
* @author       Alexander Wirtz <alex@pc4p.net>
* @link         http://weather.noaa.gov/weather/metar.shtml
* @link         http://weather.noaa.gov/weather/taf.shtml
* @example      examples/metar-basic.php        metar-basic.php
* @example      examples/metar-extensive.php    metar-extensive.php
* @package      Services_Weather
* @license      http://www.php.net/license/2_02.txt
* @version      1.3
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
    
    /**
    * The source METAR uses
    *
    * @var      string                      $_sourceMetar
    * @access   private
    */
    var $_sourceMetar;

    /**
    * The source TAF uses
    *
    * @var      string                      $_sourceTaf
    * @access   private
    */
    var $_sourceTaf;

    /**
    * This path is used to find the METAR data
    *
    * @var      string                      $_sourcePathMetar
    * @access   private
    */
    var $_sourcePathMetar;

    /**
    * This path is used to find the TAF data
    *
    * @var      string                      $_sourcePathTaf
    * @access   private
    */
    var $_sourcePathTaf;
    // }}}

    // {{{ constructor
    /**
    * Constructor
    *
    * @param    array                       $options
    * @param    mixed                       $error
    * @throws   PEAR_Error
    * @see      Science_Weather::Science_Weather
    * @access   private
    */
    function Services_Weather_Metar($options, &$error)
    {
        $perror = null;
        $this->Services_Weather_Common($options, $perror);
        if (Services_Weather::isError($perror)) {
            $error = $perror;
            return;
        }
        
        // Set options accordingly        
        if (isset($options["dsn"])) {
            if (isset($options["dbOptions"])) {
                $status = $this->setMetarDB($options["dsn"], $options["dbOptions"]);
            } else {
                $status = $this->setMetarDB($options["dsn"]);
            }
        }
        if (Services_Weather::isError($status)) {
            $error = $status;
            return;
        }
        
        // Setting the data sources for METAR and TAF - have to watch out for older API usage
        if (($source = isset($options["source"])) || isset($options["sourceMetar"])) {
            $sourceMetar = $source ? $options["source"] : $options["sourceMetar"]; 
            if (($sourcePath = isset($options["sourcePath"])) || isset($options["sourcePathMetar"])) {
                $sourcePathMetar = $sourcePath ? $options["sourcePath"] : $options["sourcePathMetar"];
            } else {
                $sourcePathMetar = "";
            }
        } else {
            $sourceMetar = "http";
            $sourcePathMetar = "";
        }
        if (isset($options["sourceTaf"])) {
            $sourceTaf = $options["sourceTaf"];
            if (isset($option["sourcePathTaf"])) {
                $sourcePathTaf = $options["sourcePathTaf"];
            } else {
                $soucePathTaf = "";
            }
        } else {
            $sourceTaf = "http";
            $sourcePathTaf = "";
        }
        $this->setMetarSource($sourceMetar, $sourcePathMetar, $sourceTaf, $sourcePathTaf);
    }
    // }}}

    // {{{ setMetarDB()
    /**
    * Sets the parameters needed for connecting to the DB, where the
    * location-search is fetching its data from. You need to build a DB
    * with the external tool buildMetarDB first, it fetches the locations
    * and airports from a NOAA-website.
    *
    * @param    string                      $dsn
    * @param    array                       $dbOptions
    * @return   DB_Error|bool
    * @throws   DB_Error
    * @see      DB::parseDSN
    * @access   public
    */
    function setMetarDB($dsn, $dbOptions = array())
    {
        $dsninfo = DB::parseDSN($dsn);
        if (is_array($dsninfo) && !isset($dsninfo["mode"])) {
            $dsninfo["mode"]= 0644;
        }
        
        // Initialize connection to DB and store in object if successful
        $db =  DB::connect($dsninfo, $dbOptions);
        if (DB::isError($db)) {
            return $db;
        }
        $this->_db = $db;

        return true;
    }
    // }}}

    // {{{ setMetarSource()
    /**
    * Sets the source, where the class tries to locate the METAR/TAF data
    *
    * Source can be http, ftp or file.
    * Alternate sourcepaths can be provided.
    *
    * @param    string                      $sourceMetar
    * @param    string                      $sourcePathMetar
    * @param    string                      $sourceTaf
    * @param    string                      $sourcePathTaf
    * @access   public
    */
    function setMetarSource($sourceMetar, $sourcePathMetar = "", $sourceTaf = "", $sourcePathTaf = "")
    {
        if (in_array($sourceMetar, array("http", "ftp", "file"))) {
            $this->_sourceMetar = $sourceMetar;
        }
        if (strlen($sourcePathMetar)) {
            $this->_sourcePathMetar = $sourcePathMetar;
        } else {
            switch ($sourceMetar) {
                case "http":
                    $this->_sourcePathMetar = "http://weather.noaa.gov/pub/data/observations/metar/stations/";
                    break;
                case "ftp":
                    $this->_sourcePathMetar = "ftp://weather.noaa.gov/data/observations/metar/stations/";
                    break;
                case "file":
                    $this->_sourcePathMetar = "./";
                    break;
            }
        }
        if (in_array($sourceTaf, array("http", "ftp", "file"))) {
            $this->_sourceTaf = $sourceTaf;
        }
        if (strlen($sourcePathTaf)) {
            $this->_sourcePathTaf = $sourcePathTaf;
        } else {
            switch ($sourceTaf) {
                case "http":
                    $this->_sourcePathTaf = "http://weather.noaa.gov/pub/data/forecasts/taf/stations/";
                    break;
                case "ftp":
                    $this->_sourcePathTaf = "ftp://weather.noaa.gov/data/forecasts/taf/stations/";
                    break;
                case "file":
                    $this->_sourcePathTaf = "./";
                    break;
            }
        }
    }
    // }}}

    // {{{ _checkLocationID()
    /**
    * Checks the id for valid values and thus prevents silly requests to
    * METAR server
    *
    * @param    string                      $id
    * @return   PEAR_Error|bool
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_NO_LOCATION
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_INVALID_LOCATION
    * @access   private
    */
    function _checkLocationID($id)
    {
        if (is_array($id) || is_object($id) || !strlen($id)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_NO_LOCATION, __FILE__, __LINE__);
        } elseif (!ctype_alpha($id) || (strlen($id) > 4)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION, __FILE__, __LINE__);
        }

        return true;
    }
    // }}}

    // {{{ _parseWeatherData()
    /**
    * Parses the data returned by the provided source and caches it
    *    
    * METAR KPIT 091955Z COR 22015G25KT 3/4SM R28L/2600FT TSRA OVC010CB
    * 18/16 A2992 RMK SLP045 T01820159
    *
    * @param    string                      $source
    * @return   PEAR_Error|array
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @access   private
    */
    function _parseWeatherData($source)
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
                "nsc"         => "no significant cloud",
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

                "vc"          => "vicinity",     "re"          => "recent",
                "nsw"         => "no significant weather",

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
                "rvrno"  => "Runway Visual Range Detector offline",
                "pwino"  => "Present Weather Identifier offline",
                "pno"    => "Tipping Bucket Rain Gauge offline",
                "fzrano" => "Freezing Rain Sensor offline",
                "tsno"   => "Lightning Detection System offline",
                "visno"  => "2nd Visibility Sensor offline",
                "chino"  => "2nd Ceiling Height Indicator offline"
            );
        }
 
        $metarCode = array(
            "report"      => "METAR|SPECI",
            "station"     => "\w{4}",
            "update"      => "(\d{2})?(\d{4})Z",
            "type"        => "AUTO|COR",
            "wind"        => "(\d{3}|VAR|VRB)(\d{2,3})(G(\d{2}))?(\w{2,3})",
            "windVar"     => "(\d{3})V(\d{3})",
            "visibility"  => "(\d{4})|((M|P)?((\d{1,2}|((\d) )?(\d)\/(\d))(SM|KM)))|(CAVOK)",
            "runway"      => "R(\d{2})(\w)?\/(P|M)?(\d{4})(FT)?(V(P|M)?(\d{4})(FT)?)?(\w)?",
            "condition"   => "(-|\+|VC|RE|NSW)?(MI|BC|PR|TS|BL|SH|DR|FZ)?((DZ)|(RA)|(SN)|(SG)|(IC)|(PL)|(GR)|(GS)|(UP))*(BR|FG|FU|VA|DU|SA|HZ|PY)?(PO|SQ|FC|SS|DS)?",
            "clouds"      => "(SKC|CLR|NSC|((FEW|SCT|BKN|OVC|VV)(\d{3})(TCU|CB)?))",
            "temperature" => "(M)?(\d{2})\/((M)?(\d{2})|XX|\/\/)?",
            "pressure"    => "(A)(\d{4})|(Q)(\d{4})",
            "trend"       => "NOSIG|TEMPO|BECMG",
            "remark"      => "RMK"
        );
        
        $remarks = array(
            "nospeci"     => "NOSPECI",
            "autostation" => "AO(1|2)",
            "presschg"    => "PRES(R|F)R",
            "seapressure" => "SLP(\d{3}|NO)",
            "precip"      => "(P|6|7)(\d{4}|\/{4})",
            "snowdepth"   => "4\/(\d{3})",
            "snowequiv"   => "933(\d{3})",
            "cloudtypes"  => "8\/(\d|\/)(\d|\/)(\d|\/)",
            "sunduration" => "98(\d{3})",
            "1htempdew"   => "T(0|1)(\d{3})((0|1)(\d{3}))?",
            "6hmaxtemp"   => "1(0|1)(\d{3})",
            "6hmintemp"   => "2(0|1)(\d{3})",
            "24htemp"     => "4(0|1)(\d{3})(0|1)(\d{3})",
            "3hpresstend" => "5([0-8])(\d{3})",
            "sensors"     => "RVRNO|PWINO|PNO|FZRANO|TSNO|VISNO|CHINO",
            "maintain"    => "[\$]"
        );        

        $data = @file($source);

        // Check for correct data, 2 lines in size
        if (!$data || !is_array($data) || sizeof($data) < 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA, __FILE__, __LINE__);
        } else {
            if (SERVICES_WEATHER_DEBUG) {
                for ($i = 0; $i < sizeof($data); $i++) {
                    echo $data[$i];
                }
            }
            // Ok, we have correct data, start with parsing the first line for the last update
            $weatherData = array();
            $weatherData["station"]   = "";
            $weatherData["update"]    = strtotime(trim($data[0])." GMT");
            $weatherData["updateRaw"] = trim($data[0]);
            // and prepare the rest for stepping through
            array_shift($data);
            $metar = explode(" ", preg_replace("/\s{2,}/", " ", implode(" ", $data)));

            // Add a few local variables for data processing
            $trendCount = 0;             // If we have trends, we need this
            $pointer    =& $weatherData; // Pointer to the array we add the data to 
            for ($i = 0; $i < sizeof($metar); $i++) {
                // Check for whitespace and step loop, if nothing's there
                $metar[$i] = trim($metar[$i]);
                if (!strlen($metar[$i])) {
                    continue;
                }

                if (SERVICES_WEATHER_DEBUG) {
                    $tab = str_repeat("\t", 2 - floor((strlen($metar[$i]) + 2) / 8));
                    echo "\"".$metar[$i]."\"".$tab."-> ";
                }

                $found = false;
                foreach ($metarCode as $key => $regexp) {
                    // Check if current code matches current metar snippet
                    if (($found = preg_match("/^".$regexp."$/i", $metar[$i], $result)) == true) {
                        switch ($key) {
                            case "station":
                                $pointer["station"] = $result[0];
                                unset($metarCode["station"]);
                                break;
                            case "wind":
                                // Parse wind data, first the speed, convert from kt to chosen unit
                                $pointer["wind"] = $this->convertSpeed($result[2], strtolower($result[5]), "mph");
                                if ($result[1] == "VAR" || $result[1] == "VRB") {
                                    // Variable winds
                                    $pointer["windDegrees"]   = "Variable";
                                    $pointer["windDirection"] = "Variable";
                                } else {
                                    // Save wind degree and calc direction
                                    $pointer["windDegrees"]   = intval($result[1]);
                                    $pointer["windDirection"] = $compass[round($result[1] / 22.5) % 16];
                                }
                                if (is_numeric($result[4])) {
                                    // Wind with gusts...
                                    $pointer["windGust"] = $this->convertSpeed($result[4], strtolower($result[5]), "mph");
                                }
                                break;
                            case "windVar":
                                // Once more wind, now variability around the current wind-direction
                                $pointer["windVariability"] = array("from" => intval($result[1]), "to" => intval($result[2]));
                                break;
                            case "visibility":
                                $pointer["visQualifier"] = "AT";
                                if (is_numeric($result[1]) && ($result[1] == 9999)) {
                                    // Upper limit of visibility range
                                    $visibility = $this->convertDistance(10, "km", "sm");
                                    $pointer["visQualifier"] = "BEYOND";
                                } elseif (is_numeric($result[1])) {
                                    // 4-digit visibility in m
                                    $visibility = $this->convertDistance(($result[1]/1000), "km", "sm");
                                } elseif (!isset($result[11]) || $result[11] != "CAVOK") {
                                    if ($result[3] == "M") {
                                        $pointer["visQualifier"] = "BELOW";
                                    } elseif ($result[3] == "P") {
                                        $pointer["visQualifier"] = "BEYOND";
                                    }
                                    if (is_numeric($result[5])) {
                                        // visibility as one/two-digit number
                                        $visibility = $this->convertDistance($result[5], $result[10], "sm");
                                    } else {
                                        // the y/z part, add if we had a x part (see visibility1)
                                        if (is_numeric($result[7])) {
                                            $visibility = $this->convertDistance($result[7] + $result[8] / $result[9], $result[10], "sm");
                                        } else {
                                            $visibility = $this->convertDistance($result[8] / $result[9], $result[10], "sm");
                                        }
                                    }
                                } else {
                                    $pointer["visQualifier"] = "BEYOND";
                                    $visibility = $this->convertDistance(10, "km", "sm");
                                    $pointer["clouds"] = array(array("amount" => "Clear below", "height" => 5000));
                                    $pointer["condition"] = "no significant weather";
                                }
                                $pointer["visibility"] = $visibility;
                                break;
                            case "condition":
                                // First some basic setups
                                if (!isset($pointer["condition"])) {
                                    $pointer["condition"] = "";
                                } elseif (strlen($pointer["condition"]) > 0) {
                                    $pointer["condition"] .= ",";
                                }

                                if (in_array(strtolower($result[0]), $conditions)) {
                                    // First try matching the complete string
                                    $pointer["condition"] .= " ".$conditions[strtolower($result[0])];
                                } else {
                                    // No luck, match part by part
                                    array_shift($result);
                                    $result = array_unique($result);
                                    foreach ($result as $condition) {
                                        if (strlen($condition) > 0) {
                                            $pointer["condition"] .= " ".$conditions[strtolower($condition)];
                                        }
                                    }
                                }
                                $pointer["condition"] = trim($pointer["condition"]);
                                break;
                            case "clouds":
                                if (!isset($pointer["clouds"])) {
                                    $pointer["clouds"] = array();
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
                                    // SKC or CLR or NSC
                                    $cloud = array("amount" => $clouds[strtolower($result[0])]);
                                }
                                $pointer["clouds"][] = $cloud;
                                break;
                            case "temperature":
                                // normal temperature in first part
                                // negative value
                                if ($result[1] == "M") {
                                    $result[2] *= -1;
                                }
                                $pointer["temperature"] = $this->convertTemperature($result[2], "c", "f");
                                if (sizeof($result) > 4) {
                                    // same for dewpoint
                                    if ($result[4] == "M") {
                                        $result[5] *= -1;
                                    }
                                    $pointer["dewPoint"] = $this->convertTemperature($result[5], "c", "f");
                                    $pointer["humidity"] = $this->calculateHumidity($result[2], $result[5]);
                                }
                                if (isset($pointer["wind"])) {
                                    // Now calculate windchill from temperature and windspeed
                                    $pointer["feltTemperature"] = $this->calculateWindChill($pointer["temperature"], $pointer["wind"]);
                                }
                                break;
                            case "pressure":
                                if ($result[1] == "A") {
                                    // Pressure provided in inches
                                    $pointer["pressure"] = $result[2] / 100;
                                } elseif ($result[3] == "Q") {
                                    // ... in hectopascal
                                    $pointer["pressure"] = $this->convertPressure($result[4], "hpa", "in");
                                }
                                break;
                            case "trend":
                                // We may have a trend here... extract type and set pointer on
                                // created new array
                                if (!isset($weatherData["trend"])) {
                                    $weatherData["trend"] = array();
                                    $weatherData["trend"][$trendCount] = array();
                                }
                                $pointer =& $weatherData["trend"][$trendCount];
                                $trendCount++;
                                $pointer["type"] = $result[0];
                                while (isset($metar[$i + 1]) && preg_match("/^(FM|TL|AT)(\d{2})(\d{2})$/i", $metar[$i + 1], $lresult)) {
                                    if ($lresult[1] == "FM") {
                                        $pointer["from"] = $lresult[2].":".$lresult[3];                                
                                    } elseif ($lresult[1] == "TL") {
                                        $pointer["to"] = $lresult[2].":".$lresult[3];
                                    } else {
                                        $pointer["at"] = $lresult[2].":".$lresult[3];
                                    }
                                    // As we have just extracted the time for this trend
                                    // from our METAR, increase field-counter
                                    $i++;
                                }
                                break;
                            case "remark":
                                // Remark part begins
                                $metarCode = $remarks;
                                $weatherData["remark"] = array();
                                break;
                            case "autostation":
                                // Which autostation do we have here?
                                if ($result[1] == 0) {
                                    $weatherData["remark"]["autostation"] = "Automatic weatherstation w/o precipitation discriminator";
                                } else {
                                    $weatherData["remark"]["autostation"] = "Automatic weatherstation w/ precipitation discriminator";
                                }
                                unset($metarCode["autostation"]);
                                break;
                            case "presschg":
                                // Decoding for rapid pressure changes
                                if (strtolower($result[1]) == "r") {
                                    $weatherData["remark"]["presschg"] = "Pressure rising rapidly";
                                } else {
                                    $weatherData["remark"]["presschg"] = "Pressure falling rapidly";
                                }
                                unset($metarCode["presschg"]);
                                break;
                            case "seapressure":
                                // Pressure at sea level (delivered in hpa)
                                // Decoding is a bit obscure as 982 gets 998.2
                                // whereas 113 becomes 1113 -> no real rule here
                                if (strtolower($result[1]) != "no") {
                                    if ($result[1] > 500) {
                                        $press = 900 + round($result[1] / 100, 1);
                                    } else {
                                        $press = 1000 + $result[1];
                                    }
                                    $weatherData["remark"]["seapressure"] = $this->convertPressure($press, "hpa", "in");
                                }
                                unset($metarCode["seapressure"]);
                                break;
                            case "precip":
                                // Precipitation in inches
                                static $hours;
                                if (!isset($weatherData["precipitation"])) {
                                    $weatherData["precipitation"] = array();
                                    $hours = array("P" => "1", "6" => "3/6", "7" => "24");
                                }
                                if (!is_numeric($result[2])) {
                                    $precip = "indeterminable";
                                } elseif ($result[2] == "0000") {
                                    $precip = "traceable";
                                }else {
                                    $precip = $result[2] / 100;
                                }
                                $weatherData["precipitation"][] = array(
                                    "amount" => $precip,
                                    "hours"  => $hours[$result[1]]
                                );
                                break;
                            case "snowdepth":
                                // Snow depth in inches
                                $weatherData["remark"]["snowdepth"] = $result[1];
                                unset($metarCode["snowdepth"]);
                                break;
                            case "snowequiv":
                                // Same for equivalent in Water... (inches)
                                $weatherData["remark"]["snowequiv"] = $result[1] / 10;
                                unset($metarCode["snowequiv"]);
                                break;
                            case "cloudtypes":
                                // Cloud types, haven't found a way for decent decoding (yet)
                                unset($metarCode["cloudtypes"]);
                                break;
                            case "sunduration":
                                // Duration of sunshine (in minutes)
                                $weatherData["remark"]["sunduration"] = "Total minutes of sunshine: ".$result[1];
                                unset($metarCode["sunduration"]);
                                break;
                            case "1htempdew":
                                // Temperatures in the last hour in C
                                if ($result[1] == "1") {
                                    $result[2] *= -1;
                                }
                                $weatherData["remark"]["1htemp"] = $this->convertTemperature($result[2] / 10, "c", "f");
                                
                                if (sizeof($result) > 3) {
                                    // same for dewpoint
                                    if ($result[4] == "1") {
                                        $result[5] *= -1;
                                    }
                                    $weatherData["remark"]["1hdew"] = $this->convertTemperature($result[5] / 10, "c", "f");
                                }
                                unset($metarCode["1htempdew"]);
                                break;
                            case "6hmaxtemp":
                                // Max temperature in the last 6 hours in C
                                if ($result[1] == "1") {
                                    $result[2] *= -1;
                                }
                                $weatherData["remark"]["6hmaxtemp"] = $this->convertTemperature($result[2] / 10, "c", "f");
                                unset($metarCode["6hmaxtemp"]);
                                break;
                            case "6hmintemp":
                                // Min temperature in the last 6 hours in C
                                if ($result[1] == "1") {
                                    $result[2] *= -1;
                                }
                                $weatherData["remark"]["6hmintemp"] = $this->convertTemperature($result[2] / 10, "c", "f");
                                unset($metarCode["6hmintemp"]);
                                break;
                            case "24htemp":
                                // Max/Min temperatures in the last 24 hours in C
                                if ($result[1] == "1") {
                                    $result[2] *= -1;
                                }
                                $weatherData["remark"]["24hmaxtemp"] = $this->convertTemperature($result[2] / 10, "c", "f");

                                if ($result[3] == "1") {
                                    $result[4] *= -1;
                                }
                                $weatherData["remark"]["24hmintemp"] = $this->convertTemperature($result[4] / 10, "c", "f");
                                unset($metarCode["24htemp"]);
                                break;
                            case "3hpresstend":
                                // We don't save the pressure during the day, so no decoding
                                // possible, sorry
                                unset($metarCode["3hpresstend"]);
                                break;
                            case "nospeci":
                                // No change during the last hour
                                $weatherData["remark"]["nospeci"] = "No changes in weather conditions";
                                unset($metarCode["nospeci"]);
                                break;
                            case "sensors":
                                // We may have multiple broken sensors, so do not unset
                                if (!isset($weatherData["remark"]["sensors"])) {
                                    $weatherData["remark"]["sensors"] = array();
                                }
                                $weatherData["remark"]["sensors"][strtolower($result[0])] = $sensors[strtolower($result[0])];
                                break;
                            case "maintain":
                                $weatherData["remark"]["maintain"] = "Maintainance needed";
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
        if (isset($weatherData["noparse"])) {
            $weatherData["noparse"] = implode(" ",  $weatherData["noparse"]);
        }

        return $weatherData;
    }
    // }}}

    // {{{ _parseForecastData()
    /**
    * Parses the data returned by the provided source and caches it
    *    
    * TAF KLGA 271734Z 271818 11007KT P6SM -RA SCT020 BKN200
    *   FM2300 14007KT P6SM SCT030 BKN150
    *   FM0400 VRB03KT P6SM SCT035 OVC080 PROB30 0509 P6SM -RA BKN035
    *   FM0900 VRB03KT 6SM -RA BR SCT015 OVC035
    *       TEMPO 1215 5SM -RA BR SCT009 BKN015
    *       BECMG 1517 16007KT P6SM NSW SCT015 BKN070
    *
    * @param    string                      $source
    * @return   PEAR_Error|array
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @access   private
    */
    function _parseForecastData($source)
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
                "nsc"         => "no significant cloud",
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

                "vc"          => "vicinity",     "re"          => "recent",
                "nsw"         => "no significant weather",

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

        $tafCode = array(
            "report"      => "TAF|AMD",
            "station"     => "\w{4}",
            "update"      => "(\d{2})?(\d{4})Z",
            "valid"       => "(\d{2})(\d{2})(\d{2})",
            "wind"        => "(\d{3}|VAR|VRB)(\d{2,3})(G(\d{2}))?(\w{2,3})",
            "visibility"  => "(\d{4})|((M|P)?((\d{1,2}|((\d) )?(\d)\/(\d))(SM|KM)))|(CAVOK)",
            "condition"   => "(-|\+|VC|RE|NSW)?(MI|BC|PR|TS|BL|SH|DR|FZ)?((DZ)|(RA)|(SN)|(SG)|(IC)|(PL)|(GR)|(GS)|(UP))*(BR|FG|FU|VA|DU|SA|HZ|PY)?(PO|SQ|FC|SS|DS)?",
            "clouds"      => "(SKC|CLR|NSC|((FEW|SCT|BKN|OVC|VV)(\d{3})(TCU|CB)?))",
            "windshear"   => "WS(\d{3})\/(\d{3})(\d{2,3})(\w{2,3})",
            "tempmax"     => "TX(\d{2})\/(\d{2})(\w)",
            "tempmin"     => "TN(\d{2})\/(\d{2})(\w)",
            "tempmaxmin"  => "TX(\d{2})\/(\d{2})(\w)TN(\d{2})\/(\d{2})(\w)",
            "from"        => "FM(\d{2})(\d{2})",
            "fmc"         => "(PROB|BECMG|TEMPO)(\d{2})?"
        );

        $data = @file($source);

        // Check for correct data
        if (!$data || !is_array($data) || sizeof($data) < 2) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA, __FILE__, __LINE__);
        } else {
            if (SERVICES_WEATHER_DEBUG) {
                for ($i = 0; $i < sizeof($data); $i++) {
                    echo $data[$i];
                }
            }
            // Ok, we have correct data, start with parsing the first line for the last update
            $forecastData = array();
            $forecastData["station"]   = "";
            $forecastData["update"]    = strtotime(trim($data[0])." GMT");
            $forecastData["updateRaw"] = trim($data[0]);
            // and prepare the rest for stepping through
            array_shift($data);
            $taf = explode(" ", preg_replace("/\s{2,}/", " ", implode(" ", $data)));

            // Add a few local variables for data processing
            $fromTime =  "";            // The timeperiod the data gets added to
            $fmcCount =  0;             // If we have FMCs (Forecast Meteorological Conditions), we need this
            $pointer  =& $forecastData; // Pointer to the array we add the data to 
            for ($i = 0; $i < sizeof($taf); $i++) {
                // Check for whitespace and step loop, if nothing's there
                $taf[$i] = trim($taf[$i]);
                if (!strlen($taf[$i])) {
                    continue;
                }

                if (SERVICES_WEATHER_DEBUG) {
                    $tab = str_repeat("\t", 2 - floor((strlen($taf[$i]) + 2) / 8));
                    echo "\"".$taf[$i]."\"".$tab."-> ";
                }

                $found = false;
                foreach ($tafCode as $key => $regexp) {
                    // Check if current code matches current taf snippet
                    if (($found = preg_match("/^".$regexp."$/i", $taf[$i], $result)) == true) {
                        $insert = array();
                        switch ($key) {
                            case "station":
                                $pointer["station"] = $result[0];
                                unset($tafCode["station"]);
                                break;
                            case "valid":
                                $pointer["validRaw"] = $result[0];
                                // Generates the timeperiod the report is valid for
                                list($year, $month, $day) = explode("-", date("Y-m-d", $forecastData["update"]));
                                // Date is in next month
                                if ($result[1] < $day) {
                                    $month++;
                                }
                                $pointer["validFrom"] = gmmktime($result[2], 0, 0, $month, $result[1], $year);
                                // Valid time ends next day
                                if ($result[2] >= $result[3]) {
                                    $result[1]++;
                                }
                                $pointer["validTo"]   = gmmktime($result[3], 0, 0, $month, $result[1], $year);
                                unset($tafCode["valid"]);
                                // Now the groups will start, so initialize the time groups
                                $pointer["time"] = array();
                                $fromTime = $result[2].":00";
                                $pointer["time"][$fromTime] = array();
                                // Set pointer to the first timeperiod
                                $pointer =& $pointer["time"][$fromTime];
                                break;
                            case "wind":
                                // Parse wind data, first the speed, convert from kt to chosen unit
                                $pointer["wind"] = $this->convertSpeed($result[2], strtolower($result[5]), "mph");
                                if ($result[1] == "VAR" || $result[1] == "VRB") {
                                    // Variable winds
                                    $pointer["windDegrees"]   = "Variable";
                                    $pointer["windDirection"] = "Variable";
                                } else {
                                    // Save wind degree and calc direction
                                    $pointer["windDegrees"]   = $result[1];
                                    $pointer["windDirection"] = $compass[round($result[1] / 22.5) % 16];
                                }
                                if (is_numeric($result[4])) {
                                    // Wind with gusts...
                                    $pointer["windGust"] = $this->convertSpeed($result[4], strtolower($result[5]), "mph");
                                }
                                if (isset($probability)) {
                                    $pointer["windProb"] = $probability;
                                    unset($probability);
                                }
                                break;
                            case "visibility":
                                $pointer["visQualifier"] = "AT";
                                if (is_numeric($result[1]) && ($result[1] == 9999)) {
                                    // Upper limit of visibility range
                                    $visibility = $this->convertDistance(10, "km", "sm");
                                    $pointer["visQualifier"] = "BEYOND";
                                } elseif (is_numeric($result[1])) {
                                    // 4-digit visibility in m
                                    $visibility = $this->convertDistance(($result[1]/1000), "km", "sm");
                                } elseif (!isset($result[11]) || $result[11] != "CAVOK") {
                                    if ($result[3] == "M") {
                                        $pointer["visQualifier"] = "BELOW";
                                    } elseif ($result[3] == "P") {
                                        $pointer["visQualifier"] = "BEYOND";
                                    }
                                    if (is_numeric($result[5])) {
                                        // visibility as one/two-digit number
                                        $visibility = $this->convertDistance($result[5], $result[10], "sm");
                                    } else {
                                        // the y/z part, add if we had a x part (see visibility1)
                                        if (is_numeric($result[7])) {
                                            $visibility = $this->convertDistance($result[7] + $result[8] / $result[9], $result[10], "sm");
                                        } else {
                                            $visibility = $this->convertDistance($result[8] / $result[9], $result[10], "sm");
                                        }
                                    }
                                } else {
                                    $pointer["visQualifier"] = "BEYOND";
                                    $visibility = $this->convertDistance(10, "km", "sm");
                                    $pointer["clouds"] = array(array("amount" => "Clear below", "height" => 5000));
                                    $pointer["condition"] = "no significant weather";
                                }
                                if (isset($probability)) {
                                    $pointer["visProb"] = $probability;
                                    unset($probability);
                                }
                                $pointer["visibility"] = $visibility;
                                break;
                            case "condition":
                                // First some basic setups
                                if (!isset($pointer["condition"])) {
                                    $pointer["condition"] = "";
                                } elseif (strlen($pointer["condition"]) > 0) {
                                    $pointer["condition"] .= ",";
                                }

                                if (in_array(strtolower($result[0]), $conditions)) {
                                    // First try matching the complete string
                                    $pointer["condition"] .= " ".$conditions[strtolower($result[0])];
                                } else {
                                    // No luck, match part by part
                                    array_shift($result);
                                    $result = array_unique($result);
                                    foreach ($result as $condition) {
                                        if (strlen($condition) > 0) {
                                            $pointer["condition"] .= " ".$conditions[strtolower($condition)];
                                        }
                                    }
                                }
                                $pointer["condition"] = trim($pointer["condition"]);
                                if (isset($probability)) {
                                    $pointer["condition"] .= " (".$probability."% prob.)";
                                    unset($probability);
                                }
                                break;
                            case "clouds":
                                if (!isset($pointer["clouds"])) {
                                    $pointer["clouds"] = array();
                                }

                                if (sizeof($result) == 5) {
                                    // Only amount and height
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4] * 100));
                                }
                                elseif (sizeof($result) == 6) {
                                    // Amount, height and type
                                    $cloud = array("amount" => $clouds[strtolower($result[3])], "height" => ($result[4] * 100), "type" => $clouds[strtolower($result[5])]);
                                }
                                else {
                                    // SKC or CLR or NSC
                                    $cloud = array("amount" => $clouds[strtolower($result[0])]);
                                }
                                if(isset($probability)) {
                                    $cloud["prob"] = $probability;
                                    unset($probability);
                                }
                                $pointer["clouds"][] = $cloud;
                                break;
                            case "windshear":
                                // Parse windshear, if available
                                $pointer["windshear"]          = $this->convertSpeed($result[3], strtolower($result[4]), "mph");
                                $pointer["windshearHeight"]    = $result[1] * 100;
                                $pointer["windshearDegrees"]   = $result[2];
                                $pointer["windshearDirection"] = $compass[round($result[2] / 22.5) % 16];
                                break;
                            case "tempmax":
                                $forecastData["temperatureHigh"] = $this->convertTemperature($result[1], "c", "f");
                                break;
                            case "tempmin":
                                // Parse max/min temperature
                                $forecastData["temperatureLow"]  = $this->convertTemperature($result[1], "c", "f");
                                break;
                            case "tempmaxmin":
                                $forecastData["temperatureHigh"] = $this->convertTemperature($result[1], "c", "f");
                                $forecastData["temperatureLow"]  = $this->convertTemperature($result[4], "c", "f");
                                break;
                            case "from":
                                // Next timeperiod is coming up, prepare array and
                                // set pointer accordingly
                                $fromTime = $result[1].":".$result[2];
                                $forecastData["time"][$fromTime] = array();
                                $fmcCount = 0;
                                $pointer =& $forecastData["time"][$fromTime];
                                break;
                            case "fmc";
                                // Test, if this is a probability for the next FMC                                
                                if (preg_match("/^BECMG|TEMPO$/i", $taf[$i + 1], $lresult)) {
                                    // Set type to BECMG or TEMPO
                                    $type = $lresult[0];
                                    // Set probability
                                    $probability = $result[2];
                                    // Now extract time for this group
                                    preg_match("/^(\d{2})(\d{2})$/i", $taf[$i + 2], $lresult);
                                    $from = $lresult[1].":00";
                                    $to   = $lresult[2].":00";
                                    $to   = ($to == "24:00") ? "00:00" : $to;
                                    // As we now have type, probability and time for this FMC
                                    // from our TAF, increase field-counter
                                    $i += 2;
                                } elseif (preg_match("/^(\d{2})(\d{2})$/i", $taf[$i + 1], $lresult)) {
                                    // Normal group, set type and use extracted time
                                    $type = $result[1];
                                    // Check for PROBdd
                                    if (isset($result[2])) {
                                        $probability = $result[2];
                                    }
                                    $from = $lresult[1].":00";
                                    $to   = $lresult[2].":00";
                                    $to   = ($to == "24:00") ? "00:00" : $to;
                                    // Same as above, we have a time for this FMC from our TAF, 
                                    // increase field-counter
                                    $i += 1;
                                } else {
                                    // This is either a PROBdd or a malformed TAF
                                    if (isset($result[2])) {
                                        $probability = $result[2];
                                    }
                                }

                                // Handle the FMC, generate neccessary array if it's the first...
                                if (isset($type)) {
                                    if (!isset($forecastData["time"][$fromTime]["fmc"])) {
                                        $forecastData["time"][$fromTime]["fmc"] = array();
                                    }
                                    $forecastData["time"][$fromTime]["fmc"][$fmcCount] = array();
                                    // ...and set pointer.
                                    $pointer =& $forecastData["time"][$fromTime]["fmc"][$fmcCount];
                                    $fmcCount++;
                                    // Insert data
                                    $pointer["type"] = $type;
                                    $pointer["from"] = $from;
                                    $pointer["to"]   = $to;
                                    unset($type, $from, $to);
                                    if (isset($probability)) {
                                        $pointer["probability"] = $probability;
                                        unset($probability);
                                    }
                                }
                                break;
                            default:
                                // Do nothing
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
                    if (!isset($forecastData["noparse"])) {
                        $forecastData["noparse"] = array();
                    }
                    $forecastData["noparse"][] = $taf[$i];
                }
            }
        }
        if (isset($forecastData["noparse"])) {
            $forecastData["noparse"] = implode(" ",  $forecastData["noparse"]);
        }

        return $forecastData;
    }
    // }}}

    // {{{ _convertReturn()
    /**
    * Converts the data in the return array to the desired units and/or
    * output format. 
    *
    * @param    array                       $target
    * @param    string                      $units
    * @param    string                      $location
    * @access   private
    */
    function _convertReturn(&$target, $units, $location)
    {
        if (is_array($target)) {
            foreach ($target as $key => $val) {
                if (is_array($val)) {
                    // Another array detected, so recurse into it to convert the units
                    $this->_convertReturn($target[$key], $units, $location);
                } else {
                    switch ($key) {
                        case "station":
                            $newVal = $location["name"];
                            break;
                        case "update":
                        case "validFrom":
                        case "validTo":
                            $newVal = gmdate(trim($this->_dateFormat." ".$this->_timeFormat), $val);
                            break;
                        case "wind":
                        case "windGust":
                        case "windshear":
                            $newVal = $this->convertSpeed($val, "mph", $units["wind"]);
                            break;
                        case "visibility":
                            $newVal = $this->convertDistance($val, "sm", $units["vis"]);
                            break;
                        case "height":
                        case "windshearHeight":
                            $newVal = $this->convertDistance($val, "ft", $units["height"]);
                            break;
                        case "temperature":
                        case "temperatureHigh":
                        case "temperatureLow":
                        case "dewPoint":
                        case "feltTemperature":
                            $newVal = $this->convertTemperature($val, "f", $units["temp"]);
                            break;
                        case "pressure":
                            $newVal = $this->convertPressure($val, "in", $units["pres"]);
                            break;
                        case "amount":
                        case "snowdepth":
                        case "snowequiv":
                            if (is_numeric($val)) {
                                $newVal = $this->convertPressure($val, "in", $units["rain"]);
                            } else {
                                $newVal = $val;
                            }
                            break;
                        case "seapressure":
                            $newVal = $this->convertPressure($val, "in", $units["pres"]);
                            break;
                        case "1htemp":
                        case "1hdew":
                        case "6hmaxtemp":
                        case "6hmintemp":
                        case "24hmaxtemp":
                        case "24hmintemp":
                            $newVal = $this->convertTemperature($val, "f", $units["temp"]);
                            break;
                        default:
                            continue 2;
                    }
                    $target[$key] = $newVal;
                }
            }
        }
    }
    // }}}

    // {{{ searchLocation()
    /**
    * Searches IDs for given location, returns array of possible locations
    * or single ID
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
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED, __FILE__, __LINE__);
        }
        
        if (is_string($location)) {
            // Try to part search string in name, state and country part
            // and build where clause from it for the select
            $location = explode(",", $location);
            if (sizeof($location) >= 1) {
                $where  = "LOWER(name) LIKE '%".strtolower(trim($location[0]))."%'";
            }
            if (sizeof($location) == 2) {
                $where .= " AND LOWER(country) LIKE '%".strtolower(trim($location[1]))."%'";
            } elseif (sizeof($location) == 3) {
                $where .= " AND LOWER(state) LIKE '%".strtolower(trim($location[1]))."%'";
                $where .= " AND LOWER(country) LIKE '%".strtolower(trim($location[2]))."%'";
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
            } elseif (strtolower(get_class($result)) != "db_result" || $result->numRows() == 0) {
                return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION, __FILE__, __LINE__);
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
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION, __FILE__, __LINE__);
        }

        return $icao;
    }
    // }}}

    // {{{ searchLocationByCountry()
    /**
    * Returns IDs with location-name for a given country or all available
    * countries, if no value was given 
    *
    * @param    string                      $country
    * @return   PEAR_Error|array
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA
    * @access   public
    */
    function searchLocationByCountry($country = "")
    {
        if (!isset($this->_db) || !DB::isConnection($this->_db)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED, __FILE__, __LINE__);
        }

        // Return the available countries as no country was given
        if (!strlen($country)) {
            $select = "SELECT DISTINCT(country) ".
                      "FROM metarAirports ".
                      "ORDER BY country ASC";
            $countries = $this->_db->getCol($select);

            // As $countries is either an error or the true result,
            // we can just return it
            return $countries;
        }

        // Now for the real search
        $select = "SELECT icao, name, state, country ".
                  "FROM metarAirports ".
                  "WHERE LOWER(country) LIKE '%".strtolower(trim($country))."%' ".
                  "ORDER BY name ASC";
        $result = $this->_db->query($select);
        // Check result for validity
        if (DB::isError($result)) {
            return $result;
        } elseif (strtolower(get_class($result)) != "db_result" || $result->numRows() == 0) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION, __FILE__, __LINE__);
        }

        // Construct the result
        $locations = array();
        while (($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) != null) {
            $locicao = $row["icao"];
            // First the name of the location
            if (!strlen($row["state"])) {
                $locname = $row["name"].", ".$row["country"];
            } else {
                $locname = $row["name"].", ".$row["state"].", ".$row["country"];
            }
            $locations[$locicao] = $locname;
        }

        return $locations;
    }
    // }}}

    // {{{ searchAirport()
    /**
    * Searches the nearest airport(s) for given coordinates, returns array
    * of IDs or single ID
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
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_DB_NOT_CONNECTED, __FILE__, __LINE__);
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_INVALID_LOCATION, __FILE__, __LINE__);
        }           
        
        // Get all airports
        $select = "SELECT icao, x, y, z FROM metarAirports";
        $result = $this->_db->query($select);
        if (DB::isError($result)) {
            return $result;
        } elseif (strtolower(get_class($result)) != "db_result" || $result->numRows() == 0) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION, __FILE__, __LINE__);
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
                if ($min_dist != null && $dist > $min_dist) {
                    break;
                }
            }

            if ($d >= sizeof($air)) {
                // Ok, current airport is one of the nearer locations
                // add to result-array
                $search["dist"][] = $dist;
                $search["icao"][] = $icao;
                // Sort array for distance
                array_multisort($search["dist"], SORT_NUMERIC, SORT_ASC, $search["icao"], SORT_STRING, SORT_ASC);
                // If array is larger then desired results, chop off last one
                if (sizeof($search["dist"]) > $numResults) {
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
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION, __FILE__, __LINE__);
        }
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
            } elseif (strtolower(get_class($result)) != "db_result" || $result->numRows() == 0) {
                return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION, __FILE__, __LINE__);
            }
            // Result is ok, put things into object
            $this->_location = $result->fetchRow(DB_FETCHMODE_ASSOC);

            if ($this->_cacheEnabled) {
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
        } elseif (strlen($this->_location["country"])) {
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

        // Get other data
        $units    = $this->getUnitsFormat($unitsFormat);
        $location = $this->getLocation($id);

        if ($this->_cacheEnabled && ($weather = $this->_cache->get("METAR-".$id, "weather"))) {
            // Wee... it was cached, let's have it...
            $weatherReturn  = $weather;
            $this->_weather = $weatherReturn;
            $weatherReturn["cache"] = "HIT";
        } else {
            // Set the source
            if ($this->_sourceMetar == "file") {
                $source = realpath($this->_sourcePathMetar."/".$id.".TXT");
            } else {
                $source = $this->_sourcePathMetar."/".$id.".TXT";
            }

            // Download and parse weather
            $weatherReturn  = $this->_parseWeatherData($source);

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

        $this->_convertReturn($weatherReturn, $units, $location);

        return $weatherReturn;
    }
    // }}}
    
    // {{{ getForecast()
    /**
    * METAR provides no forecast per se, we use the TAF reports to generate
    * a forecast for the announced timeperiod
    *
    * @param    string                      $id
    * @param    int                         $days           Ignored, not applicable
    * @param    string                      $unitsFormat
    * @return   PEAR_Error|array
    * @throws   PEAR_Error
    * @access   public
    */
    function getForecast($id = "", $days = null, $unitsFormat = "")
    {
        $id     = strtoupper($id);
        $status = $this->_checkLocationID($id);

        if (Services_Weather::isError($status)) {
            return $status;
        }

        // Get other data
        $units    = $this->getUnitsFormat($unitsFormat);
        $location = $this->getLocation($id);

        if ($this->_cacheEnabled && ($forecast = $this->_cache->get("METAR-".$id, "forecast"))) {
            // Wee... it was cached, let's have it...
            $forecastReturn  = $forecast;
            $this->_forecast = $forecastReturn;
            $forecastReturn["cache"] = "HIT";
        } else {
            // Set the source
            if ($this->_sourceTaf == "file") {
                $source = realpath($this->_sourcePathTaf."/".$id.".TXT");
            } else {
                $source = $this->_sourcePathTaf."/".$id.".TXT";
            }

            // Download and parse weather
            $forecastReturn  = $this->_parseForecastData($source);

            if (Services_Weather::isError($forecastReturn)) {
                return $forecastReturn;
            }
            if ($this->_cacheEnabled) {
                // Cache weather
                $expire = constant("SERVICES_WEATHER_EXPIRES_FORECAST");
                $this->_cache->extSave("METAR-".$id, $forecastReturn, $unitsFormat, $expire, "forecast");
            }
            $this->_forecast = $forecastReturn;
            $forecastReturn["cache"] = "MISS";
        }

        $this->_convertReturn($forecastReturn, $units, $location);

        return $forecastReturn;
    }
    // }}}
}
// }}}
?>
