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

/*
 * Well, this is a more elaborate example how to create a neat little page
 * with fetching METAR/TAF data from noaa.org and putting it into a design.
 * I'm not too proud of my design-skills, most of the stuff here is taken
 * from the metar-block which can be found within the Horde Framework,
 * courtesy of Rick Emery - creative pixelshoving isn't my domain :-P
 * I've used a Firefox for checking the design, so don't be too
 * disappointed, if the page looks shabby with with the IE, not that I care
 * very much anyway ;-)
 * Have fun!
*/

//-------------------------------------------------------------------------
// This is the area, where you can customize the script
//-------------------------------------------------------------------------
$location    = "Kennedy International Airport"; // The city we want to fetch the data for.
//$location    = "Bonn, Germany"; // The city we want to fetch the data for.
                                // Where the search function will look for
                                // the ICAO database (generated with the
                                // buildMetarDB.php script)
$dsn         = "sqlite://localhost//usr/local/lib/php/data/Services_Weather/servicesWeatherDB"; 
$sourceMetar = "file";          // This script will pull the data via http
$sourceTaf   = "file";          // This script will pull the data via http
$sourcePathMetar = "/mnt/E/noaa/metar";
$sourcePathTaf   = "/mnt/E/noaa/taf";
$cacheType   = "";              // Set a type (file, db, mdb, ...) to
                                // enable caching.
$cacheOpt    = array();         // Cache needs various options, depending
                                // on the container-type - please consult
                                // the Cache manual / sourcecode!
$unitsFormat = "metric";        // The format the units are displayed in -
                                // metric, standard or some customization.
$dateFormat  = "j. M Y";        // Set the format the date is displayed in
$timeFormat  = "H:i";           // Set the format the time is displayed in
//-------------------------------------------------------------------------

// Load the Weather class
require_once "Services/Weather.php";

// Object initialization - error checking is important, because of
// handling exceptions such as missing PEAR modules
$metar = &Services_Weather::service("Metar");
if (Services_Weather::isError($metar)) {
    die("Error: ".$metar->getMessage()."\n");
}

// Set parameters for DB access, needed for location searches
$metar->setMetarDB($dsn);
if (Services_Weather::isError($metar)) {
    echo "Error: ".$metar->getMessage()."\n";
}

// Initialize caching
if (strlen($cacheType)) {
    $status = $metar->setCache($cacheType, $cacheOpt);
    if (Services_Weather::isError($status)) {
        echo "Error: ".$status->getMessage()."\n";
    }
}

// Define the units format, bring the retrieved format into
// something more common...
$metar->setUnitsFormat($unitsFormat);
$units = $metar->getUnitsFormat();
$units["temp"]   = "&deg;".strtoupper($units["temp"]);
$units["wind"]   = "&nbsp;".str_replace("kmh", "km/h", $units["wind"]);
$units["vis"]    = "&nbsp;".$units["vis"];
$units["height"] = "&nbsp;".$units["height"];
$units["pres"]   = "&nbsp;".$units["pres"];
$units["rain"]   = "&nbsp;".$units["rain"];

$metar->setMetarSource($sourceMetar, $sourcePathMetar, $sourceTaf, $sourcePathTaf);

// Set date-/time-format
$metar->setDateTimeFormat($dateFormat, $timeFormat);

// Search for defined location and fetch the first item found.
// Bail out if something bad happens...
$search = $metar->searchLocation($location, true);
if (Services_Weather::isError($search)) {
    die("Error: ".$search->getMessage()."\n");
}

// Retrieve data, store in variables, bail out on error
$fetch = array(
    "location" => "getLocation",
    "weather"  => "getWeather",
    "forecast" => "getForecast"
);
foreach ($fetch as $variable => $function) {
    $$variable = $metar->$function($search);
    if (Services_Weather::isError($$variable)) {
        echo "Error: ".$$variable->getMessage()."\n";
        continue;
    }
}

// Now we output all the data, please don't expect extensive comments here, this is basic
// HTML/CSS stuff. Also this isn't a very fancy design, it's just to show you, what
// the script is able to do (and more ;-))...
?>
<html>
<head>
    <title>Services_Weather::Weatherdotcom</title>
    <style type="text/css">
        .normal     { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; font-weight: normal; font-style: normal }
        .italic     { font-weight: normal; font-style: italic }
        .bold       { font-weight: bold; font-style: normal }
        .bolditalic { font-weight: bold; font-style: italic }
        .redbold    { font-weight: bold; font-style: normal; color: #ff0000 }
        .bluebold   { font-weight: bold; font-style: normal; color: #0000ff }
        .bggrey     { background-color: #e9e9e9 }
        .bgkhaki    { background-color: #d8d8c0 }
        .reg        { font-size: 7pt; vertical-align: super }
        img         { vertical-align: middle; border-style: none; border-width: 0px }
        a           { font-weight: bold; font-style: italic; color: #993300; text-decoration: none }
        a:visited   { font-weight: bold; font-style: italic; color: #993300; text-decoration: none }
        a:hover     { font-weight: bold; font-style: italic; color: #cc3300; text-decoration: underline }
        table       { border: 0px none black; border-spacing: 0px }
        td          { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; font-weight: normal; font-style: normal }
    </style>
</head>
<body class="normal">
<?php
// Debug outputs the raw data fetched by the foreach-loop above, just for checking...
if (isset($_GET["debug"])) {
    echo "<pre>\n";
    var_dump($location, $weather, $forecast);
    echo "</pre>\n";
} 
?>
<span class="bluebold" style="font-size: 13pt">Weather Forecast</span> created with <a style="font-size: 13pt" href="http://pear.php.net/">PEARs</a> <a style="font-size: 13pt" href="http://pear.php.net/package/Services_Weather/">Services_Weather</a><br>
<table style="width: 100%">
<tr>
    <td>
        <table border="0" style="border-top: 2px solid #524b98; border-bottom: 2px solid #e0e3ce; border-left: 2px solid #b8b6c1; border-right: 2px solid #8b87a0; width: 100%">
        <tr class="bgkhaki">
            <td colspan="4" style="border-bottom: 2px solid #abada2"><span class="bold"><?=$location["name"]?> (<?=$search?>)</span></td>
        </tr>
        <tr>
            <td colspan="2" nowrap><span class="bold">Last updated:</span> <?=$weather["update"]?><br>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        <tr style="height: 15px">
            <td nowrap><span class="bold">Temperature:</span> <?=round($weather["temperature"], 1).$units["temp"]?></td>
            <td nowrap><span class="bold">Dew point:</span> <?=round($weather["dewPoint"], 1).$units["temp"]?></td>
            <td nowrap><span class="bold">Felt temperature:</span> <?=round($weather["feltTemperature"], 1).$units["temp"]?></td>
            <td rowspan="4" valign="top">
                <span class="bold">Trend:</span><br>
<?php
if (isset($weather["trend"]) && sizeof($weather["trend"])) {
    foreach ($weather["trend"] as $trend) {
        foreach ($trend as $key => $val) {
            switch ($key) {
                case "type":
                    if ($val == "NOSIG") {
                        $string = "No significant weather";
                    } elseif ($val == "TEMPO") {
                        $string = "Temporary weather";
                    } elseif ($val == "BECMG") {
                        $string = "Weather becoming";
                    }
                    $value  = "";
                    foreach (array("from", "to", "at") as $time) {
                        if (isset($trend[$time])) {
                            $value .= " ".$time." ".$trend[$time];
                        }
                    }
                    ($value != "") ? $value  = trim($value).":":"";
                    $string = '<span class="italic">'.$string.'</span>';
                    $value  = '<span class="italic">'.$value.'</span>';
                    break;
                case "wind":
                    $string = "Wind:";
                    $value  = (strtolower($trend["windDirection"]) == "calm") ? "Calm" : "From the ".$trend["windDirection"]." (".$trend["windDegrees"]."&deg;) at ".round($trend["wind"], 1).$units["wind"];
                    if (isset($trend["windVariability"])) {
                        $value .= ", variable from ".$trend["windVariability"]["from"]."&deg; to ".$trend["windVariability"]["to"]."&deg;";
                    }
                    if (isset($trend["windGust"])) {
                        $value .= ", with gusts up to ".round($trend["windGust"], 1).$units["wind"];
                    }
                    break;
                case "visibility":
                    $string = "Visibility:";
                    $value  = strtolower($trend["visQualifier"])." ".round($trend["visibility"], 1).$units["vis"];
                    break;
                case "clouds":
                    $string = "Clouds:";
                    $value  = "";
                    for ($i = 0; $i < sizeof($val); $i++) {
                        $cloud = ucwords($val[$i]["amount"]);
                        if (isset($val[$i]["type"])) {
                            $cloud .= " ".$val[$i]["type"];
                        }
                        if (isset($val[$i]["height"])) {
                            $cloud .= " at ".$val[$i]["height"].$units["height"];
                        }
                        $value .= $cloud." ";
                    }
                    break;
                case "condition":
                    $string = "Condition:";
                    $value  = ucwords($val);
                    break;
                case "pressure":
                    $string = "Pressure:";
                    $value  = round($val, 1).$units["pres"];
                    break;
                case "from":
                case "to":
                case "at":
                case "windDirection":
                case "windDegrees":
                case "windVariability":
                case "windGust":
                case "visQualifier":
                    continue(2);
                    break;
                default:
                    $string = ""; $value = "";
                    var_dump($key, $val);
                    break;
            }
?>
                <?=$string?> <?=$value?><br>
<?php
        }

    }
} else {
?>
                none<br>
<?php
}
?>
                <span class="bold">Remarks:</span><br>
<?php
if (isset($weather["remark"]) && sizeof($weather["remark"])) {
    foreach($weather["remark"] as $key => $val) {
        switch ($key) {
            case "autostation":
            case "presschg":
            case "nospeci":
            case "sunduration":
            case "maintain":
                $string = "";
                $value  = $val;
                break;
            case "seapressure":
                $string = "Pressure at sealevel:";
                $value  = round($val, 1).$units["pres"];
                break;
            case "1htemp":
                $string = "Temperature for last hour:";
                $value  = round($val, 1).$units["temp"];
                break;
            case "1hdew":
                $string = "Dew Point for last hour:";
                $value  = round($val, 1).$units["temp"];
                break;
            case "6hmaxtemp":
            case "6hmintemp":
                if (!isset($weather["remark"]["6hmaxtemp"]) && !isset($weather["remark"]["6hmintemp"])) {
                    continue(2);
                }
                $string = "Max/Min Temp for last 6 hours:";
                $value  = (isset($weather["remark"]["6hmaxtemp"])) ? round($weather["remark"]["6hmaxtemp"], 1).$units["temp"] : "-";
                $value .= "/";
                $value .= (isset($weather["remark"]["6hmintemp"])) ? round($weather["remark"]["6hmintemp"], 1).$units["temp"] : "-";
                unset($weather["remark"]["6hmaxtemp"]); unset($weather["remark"]["6hmintemp"]);
                break;
            case "24hmaxtemp":
            case "24hmintemp":
                if (!isset($weather["remark"]["24hmaxtemp"]) && !isset($weather["remark"]["24hmintemp"])) {
                    continue(2);
                }
                $string = "Max/Min Temp for last 24 hours:";
                $value  = (isset($weather["remark"]["24hmaxtemp"])) ? round($weather["remark"]["24hmaxtemp"], 1).$units["temp"] : "-";
                $value .= "/";
                $value .= (isset($weather["remark"]["24hmintemp"])) ? round($weather["remark"]["24hmintemp"], 1).$units["temp"] : "-";
                unset($weather["remark"]["24hmaxtemp"]); unset($weather["remark"]["24hmintemp"]);
                break;
            case "snowdepth":
                $string = "Snow depth:";
                $value  = $val.$units["rain"];
                break;
            case "snowequiv":
                $string = "Water equivalent of snow:";
                $value  = $val.$units["rain"];
                break;
            case "sensors":
                $string = "";
                $value  = implode("<br>", $val);
                break;
            default:
                $string = ""; $value = "";
                var_dump($key, $val);
                break;
        }
?>
                <?=$string?> <?=$value?><br>
<?php
    }
} else {
?>
                none<br>
<?php
}
?>
            </td>
        </tr>
        <tr style="height: 15px">
            <td colspan="2" nowrap><span class="bold">Pressure:</span> <?=round($weather["pressure"], 1).$units["pres"]?></td>
            <td nowrap><span class="bold">Humidity:</span> <?=$weather["humidity"]?>%</td>
        </tr>
        <tr style="height: 15px">
            <td colspan="2" nowrap>
                <span class="bold">Wind:</span> <?=strtolower($weather["windDirection"]) == "calm" ? "Calm" : "From the ".$weather["windDirection"]." (".$weather["windDegrees"]."&deg;) at ".round($weather["wind"], 1).$units["wind"]?>
<?php
if (isset($weather["windVariability"])) {
?>
                <br>variable from <?=$weather["windVariability"]["from"]?>&deg; to <?=$weather["windVariability"]["to"]?>&deg;
<?php
}
if (isset($weather["windGust"])) {
?>
                <br>with gusts up to <?=round($weather["windGust"], 1).$units["wind"]?>
<?php
}
?>
            </td>
            <td valign="top" nowrap><span class="bold">Visibility:</span> <?=strtolower($weather["visQualifier"])?> <?=round($weather["visibility"], 1).$units["vis"]?></td>
        </tr>
        <tr>
            <td colspan="2" valign="top">
                <span class="bold">Current condition:</span><br>
                <?=isset($weather["condition"]) ? ucwords($weather["condition"]) : "No Significant Weather"?>
<?php
if (isset($weather["precipitation"]) && sizeof($weather["precipitation"])) {
?>
                <br><span class="bold">Precipitation:</span><br>
<?php
    for ($i = 0; $i < sizeof($weather["precipitation"]); $i++) {
        $precip = "last ".$weather["precipitation"][$i]["hours"]."h: ".$weather["precipitation"][$i]["amount"];
        $precip .= (ctype_alpha($weather["precipitation"][$i]["amount"])) ? "" : $units["rain"];
?>
                <?=$precip?><br>
<?php
    }
}
?>
            </td>
            <td valign="top">
                <span class="bold">Clouds:</span><br>
<?php
if (isset($weather["clouds"]) && sizeof($weather["clouds"])) {
    for ($i = 0; $i < sizeof($weather["clouds"]); $i++) {
        $cloud = ucwords($weather["clouds"][$i]["amount"]);
        if (isset($weather["clouds"][$i]["type"])) {
            $cloud .= " ".$weather["clouds"][$i]["type"];
        }
        if (isset($weather["clouds"][$i]["height"])) {
            $cloud .= " at ".$weather["clouds"][$i]["height"].$units["height"];
        }
?>
                <?=$cloud?><br>
<?php
    }
} else {
?>
                Clear Below <?=$metar->convertDistance(12000, "ft", $units["height"]).$units["height"]?>
<?php
}
?>
            </td>
        </tr>
        </table>
    </td>
</tr>
<tr>
    <td>
        <table style="border-top: 2px solid #524b98; border-bottom: 2px solid #e0e3ce; border-left: 2px solid #b8b6c1; border-right: 2px solid #8b87a0; width: 100%">
        <tr class="bgkhaki">
            <td align="center" style="border-bottom: 2px solid #abada2"><span class="bold">Forecast (TAF)</span></td>
        </tr>
        <tr valign="top">
            <td>
                <table class="bgkhaki" style="border-top: 2px solid #d8d8c0; border-bottom: 2px solid #d8d8c0; border-left: 2px solid #d8d8c0; border-right: 2px solid #8b87a0; width: 100%">
                <tr>
                    <td align="center" style="height: 15px">&nbsp;</td>
                <tr>
                    <td align="center" style="height: 45px"><span class="bold">Temperature</span> <span class="redbold">High</span> / <span class="bluebold">Low</span></td>
                </tr>
                <tr>
                    <td align="center" style="height: 15px">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center" style="height: 75px"><span class="bold">Condition</span></td>
                </tr>
                <tr>
                    <td align="center" style="height: 45px"><span class="bold">Precipitation probability</span></td>
                </tr>            
                <tr>
                    <td align="center" style="height: 45px"><span class="bold">Wind</span></td>
                </tr>
                <tr>
                    <td align="center" style="height: 15px"><span class="bold">Humidity</span></td>
                </tr>
                </table>
            </td>
        </tr>
        <tr class="bgkhaki">
    	    <td style="border-top: 2px solid #abada2">&nbsp;</td>
        </tr>
        </table>
    </td>
</tr>
</table>
<a href="javascript:history.back()">back</a>
</body>
</html>
