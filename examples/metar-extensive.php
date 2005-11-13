<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * Elaborate example for the METAR/TAF-service
 *
 * Well, this is a more elaborate example how to create a neat little page
 * with fetching METAR/TAF data from NOAA and putting it into a design.
 * I'm not too proud of my design-skills, most of the stuff here is taken
 * from the metar-block which can be found within the Horde Framework,
 * courtesy of Rick Emery - creative pixelshoving isn't my domain :-P
 * I've used a Firefox for checking the design, so don't be too
 * disappointed, if the page looks shabby with the IE, not that I care
 * very much anyway ;-)
 * Have fun!
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

//-------------------------------------------------------------------------
// This is the area, where you can customize the script
//-------------------------------------------------------------------------
$location        = "Bonn, Germany"; // The city we want to fetch the data for.
                                    // Where the search function will look for
                                    // the ICAO database (generated with the
                                    // buildMetarDB.php script)
$dsn             = "sqlite://localhost//usr/local/lib/php/data/Services_Weather/servicesWeatherDB"; 
$sourceMetar     = "http";          // This script will pull the METAR data via http
$sourceTaf       = "http";          //                           TAF
$sourcePathMetar = "";              // Only needed when non-standard access is used
$sourcePathTaf   = "";              //
$cacheType       = "";              // Set a type (file, db, mdb, ...) to
                                    // enable caching.
$cacheOpt        = array();         // Cache needs various options, depending
                                    // on the container-type - please consult
                                    // the Cache manual / sourcecode!
$unitsFormat     = "metric";        // The format the units are displayed in -
                                    // metric, standard or some customization.
$dateFormat      = "j. M Y";        // Set the format the date is displayed in
$timeFormat      = "H:i";           //                    time
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
    <title>Services_Weather::METAR/TAF</title>
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
            <td><span class="bold">Sunrise:</span> <img style="width: 28px; height: 13px; vertical-align: baseline" alt="Sunrise" src="images/sunrise.gif"> <?=$location["sunrise"]?></td>
            <td><span class="bold">Sunset:</span> <img style="width: 30px; height: 15px; vertical-align: baseline" alt="Sunset" src="images/sunset.gif"> <?=$location["sunset"]?></td>
            <td style="width: 190px">&nbsp;</td>
            <td style="width: auto">&nbsp;</td>
        </tr>
        <tr style="height: 15px">
            <td nowrap><span class="bold">Temperature:</span> <?=round($weather["temperature"], 1).$units["temp"]?></td>
            <td nowrap><span class="bold">Dew point:</span> <?=round($weather["dewPoint"], 1).$units["temp"]?></td>
            <td nowrap><span class="bold">Felt temperature:</span> <?=round($weather["feltTemperature"], 1).$units["temp"]?></td>
            <td rowspan="4" valign="top">
                <span class="bold">Trend:</span><br>
<?php
if (isset($weather["trend"]) && sizeof($weather["trend"])) {
    // Output the trends, loop through the arrays,
    // convert the stuff to nice looking design, jadda, jadda...
    foreach ($weather["trend"] as $trend) {
        foreach ($trend as $key => $val) {
            switch ($key) {
                case "type":
                    switch ($val) {
                        case "NOSIG":
                            $string = "No Significant Weather";
                            break;
                        case "TEMPO":
                            $string = "Temporary Weather";
                            break;
                        case "BECMG":
                            $string = "Weather Becoming";
                            break;
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
                    continue 2;
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
    // Same for the remarks, even less spectacular...
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
            <td colspan="2" style="width: 310px" nowrap><span class="bold">Pressure:</span> <?=round($weather["pressure"], 1).$units["pres"]?></td>
            <td nowrap><span class="bold">Humidity:</span> <?=$weather["humidity"]?>%</td>
        </tr>
        <tr style="height: 15px">
            <td colspan="2" nowrap>
                <span class="bold">Wind:</span> <?=strtolower($weather["windDirection"]) == "calm" ? "Calm" : "From the ".$weather["windDirection"]." (".$weather["windDegrees"]."&deg;) at ".round($weather["wind"], 1).$units["wind"]?> 
                <?=isset($weather["windVariability"]) ? "<br>variable from ".$weather["windVariability"]["from"]."&deg; to ".$weather["windVariability"]["to"]."&deg;" : ""?> 
                <?=isset($weather["windGust"]) ? "<br>with gusts up to ".round($weather["windGust"], 1).$units["wind"] : ""?> 
            </td>
            <td valign="top" nowrap><span class="bold">Visibility:</span> <?=strtolower($weather["visQualifier"])?> <?=round($weather["visibility"], 1).$units["vis"]?></td>
        </tr>
        <tr>
            <td><span class="bold">Current condition:</span><br><?=isset($weather["condition"]) ? ucwords($weather["condition"]) : "No Significant Weather"?></td>
            <td><img style="height: 32px; width: 32px" alt="<?=isset($weather["condition"]) ? ucwords($weather["condition"]) : "No Significant Weather"?>" src="images/32x32/<?=$weather["conditionIcon"]?>.png"></td>
<?php
if (isset($weather["precipitation"]) && sizeof($weather["precipitation"])) {
    // Output a line for each type of precipitation,
    // distinguish between string and numeric values
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
    // Yeah, clouds... same as in the trend handling...
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
            <td colspan="3" align="center" style="border-bottom: 2px solid #abada2"><span class="bold">Forecast (TAF)</span><br>valid from <span class="bold"><?=$forecast["validFrom"]?></span> to <span class="bold"><?=$forecast["validTo"]?></span></td>
        </tr>
        <tr valign="top">
            <td colspan="3">
                <table style="width: 100%">
                <tr>
                    <td align="center" class="bgkhaki" style="height: 15px; border-top: 2px solid #d8d8c0; border-right: 2px solid #8b87a0; border-left: 2px solid #d8d8c0">&nbsp;</td>
                    <td align="center" style="width: 18%"><span class="bold">Meteorological Conditions</span></td>
                    <td align="center" style="width: 18%" class="bggrey"><span class="bold">Wind</span></td>
                    <td align="center" style="width: 18%"><span class="bold">Visibility</span></td>
                    <td align="center" style="width: 18%" class="bggrey"><span class="bold">Clouds</span></td>
                    <td align="center" style="width: 18%"><span class="bold">Condition</span></td>
                </tr>
<?php
$times = array_keys($forecast["time"]);
$pre   = array("wind" => 0, "vis" => 0, "clouds" => 0, "cond" => 0);
// Ok, the forecast is a bit more interesting, as I'm taking a few
// precautions here so that the table isn't filled up to the max.
// o If a value is repeated in the next major timeslot (not when
//   significant weather changes are processed), it's not printed
// o Significant weather gets its own rows, with times printed normal
//   as in opposition to bold print for major timeslots
// o The while($row)-construct below is for handling the significant
//   weather, as I point $row to $row["fmc"] afterwards, where the
//   smaller changes are mentioned
for ($i = 0; $i < sizeof($forecast["time"]); $i++) {
    $row = $forecast["time"][$times[$i]];

    // Create timestamp
    $start = $times[$i];
    if ($i + 1 < sizeof($forecast["time"])) {
        $end = $times[$i + 1];
    } else {
        $end = substr($forecast["validRaw"], -2).":00";
        $end = ($end == "24:00") ? "00:00" : $end;
    }
    $time    = $start." - ".$end;
    $class   = ' class="bold"';
    // This is for outputting "Becoming", "Temporary" and such
    $fmc     = isset($row["fmc"]) ? $row["fmc"] : false; 
    $fmctype = "";
    $fmccnt  = 0;

    while ($row) {
?>
                <tr class="bgkhaki">
                    <td style="height: 1px; empty-cells: show; border-right: 2px solid #8b87a0; border-left: 2px solid #d8d8c0"></td>
                    <td style="height: 1px" colspan="5"></td>
                </tr>
                <tr>
                    <td align="center" class="bgkhaki" style="border-right: 2px solid #8b87a0; border-left: 2px solid #d8d8c0" nowrap><span<?=$class?>><?=$time?></span></td>
                    <td align="center"><?=$fmctype?></td>
<?php
        // This loops through the available data and processes it
        // for output, the different methods were already used above
        // (Only difference is the checking for the pre-values.)
        foreach(array("wind", "vis", "clouds", "cond") as $val) {
            switch ($val) {
                case "wind":
                    if (!isset($row["windDirection"])) {
                        $string = "&nbsp;";
                    } else {
                        $string = strtolower($row["windDirection"]) == "calm" ? "Calm" : "From the ".$row["windDirection"]." (".$row["windDegrees"]."&deg;)<br>at ".round($row["wind"], 1).$units["wind"];
                        if (isset($row["windProb"])) {
                            $string .= " (".$row["windProb"]."%&nbsp;Prob.)";
                        }
                        if ($string === $pre["wind"]) {
                            $string = "&nbsp;";
                        } else {
                            $pre["wind"] = $string;
                        }
                    }
                    $class = ' class="bggrey"';
                    break;
                case "vis":
                    if (!isset($row["visibility"])) {
                        $string = "&nbsp;";
                    } else {
                        $string = strtolower($row["visQualifier"])." ".round($row["visibility"], 1).$units["vis"];
                        if (isset($row["visProb"])) {
                            $string .= " (".$row["visProb"]."%&nbsp;Prob.)";
                        }
                        if ($string === $pre["vis"]) {
                            $string = "&nbsp;";
                        } else {
                            $pre["vis"] = $string;
                        }
                    }
                    $class = '';
                    break;
                case "clouds":
                    if (!isset($row["clouds"])) {
                        $string = "&nbsp;";
                    } else { 
                        $clouds  = "";
                        for ($j = 0; $j < sizeof($row["clouds"]); $j++) {
                            $cloud = ucwords($row["clouds"][$j]["amount"]);
                            if (isset($row["clouds"][$j]["type"])) {
                                $cloud .= " ".$row["clouds"][$j]["type"];
                            }
                            if (isset($row["clouds"][$j]["height"])) {
                                $cloud .= " at ".$row["clouds"][$j]["height"].$units["height"];
                            }
                            if (isset($row["clouds"][$j]["prob"])) {
                                $cloud .= " (".$row["clouds"][$j]["prob"]."%&nbsp;Prob.)";
                            }
                            $clouds .= $cloud."<br>";
                        }
                        if ($clouds === $pre["clouds"]) {
                            $string = "&nbsp;";
                        } else {
                            $string        = $clouds;
                            $pre["clouds"] = $clouds;
                        }
                    }
                    $class = ' class="bggrey"';
                    break;
                case "cond":
                    if (!isset($row["condition"]) || (isset($prerow) && $prerow["condition"] == $row["condition"])) {
                        $string = "&nbsp;";
                    } else {
                        $string = ucwords($row["condition"]);
                    }
                    $class = '';
            }
?>
                    <td valign="top"<?=$class?>><?=$string?></td>
<?php
        }
?>                    
                </tr>
<?php
        // Now check for significant weather changes and move
        // the row accordingly... maybe ugly coding, but this
        // is for showing design, not for fany programming ;-)
        if ($fmc && $fmccnt < sizeof($fmc)) {
            $row     = $fmc[$fmccnt];
            $fmccnt++;
            $fmctype = $row["type"];
            // Check if we have a timestamp, don't output if it's
            // the same as the major-timeslot
            if (!isset($row["from"]) || $row["from"]." - ".$row["to"] == $time) {
                $time = "&nbsp;";
            } else {
                $time    = $row["from"]." - ".$row["to"];
            }
            switch ($row["type"]) {
                case "PROB":
                    $fmctype = "";
                    break;
                case "TEMPO":
                    $fmctype = "Temporary";
                    break;
                case "BECMG":
                    $fmctype = "Becoming";
                    break;
            }
            if (isset($row["probability"])) {
                $fmctype .= " (".$row["probability"]."%&nbsp;Prob.)";
            }
        } else {
            $row = false;
        }
    }
}
?>                
                <tr>
                    <td class="bgkhaki" style="height: 1px; empty-cells: show; border-bottom: 2px solid #d8d8c0; border-left: 2px solid #d8d8c0; border-right: 2px solid #8b87a0"></td>
                    <td style="height: 1px" colspan="5"></td>
                </tr>
                </table>
            </td>
        </tr>
        <tr class="bgkhaki">
    	    <td style="width: 93px; border-top: 2px solid #abada2">&nbsp;</td>
            <td style="border-top: 2px solid #abada2">Updated: (<?=substr($weather["update"], -5)?>&nbsp;/&nbsp;<?=substr($forecast["update"], -5)?>)</td>
            <td style="border-top: 2px solid #abada2" align="right">All times UTC</td>
        </tr>
        </table>
    </td>
</tr>
</table>
<a href="javascript:history.back()">back</a>
</body>
</html>
