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
 * with fetching data from weather.com and putting it into a design.
 * I'm not too proud of my design-skills, most of the stuff here is taken
 * from the weather-block which can be found within the Horde Framework,
 * courtesy of Rick Emery - creative pixelshoving isn't my domain :-P
 * I've used a Firefox for checking the design, so don't be too
 * disappointed, if the page looks shabby with the IE, not that I care
 * very much anyway ;-)
 * If you want to know where you can obtain the icons, please register at
 * http://www.weather.com/services/xmloap.html and you'll receive an eMail
 * containing the partner id, the license key and a link pointing to the
 * SDK. Put the 32x32 and the logos folders into the images/ directory.
 * The design should adhere to the conventions defined by the weather.com
 * SDK-guide, though I won't give any legal certainty for this (of course).
 * Have fun!
*/

//-------------------------------------------------------------------------
// This is the area, where you can customize the script
//-------------------------------------------------------------------------
$location     = "Bonn, Germany"; // The city we want to fetch the data for.
$forecastDays = 4;               // This regulates the amount of displayed
                                 // dates in the forecast. (1 <= x <= 10)
$partnerID    = "<PartnerID>";   // As provided by weather.com in the
$licenseKey   = "<LicenseKey>";  // registration eMail.
$cacheType    = "";              // Set a type (file, db, mdb, ...) to
                                 // enable caching.
$cacheOpt     = array();         // Cache needs various options, depending
                                 // on the container-type - please consult
                                 // the Cache manual / sourcecode!
$unitsFormat  = "metric";        // The format the units are displayed in -
                                 // metric, standard or some customization.
$dateFormat   = "Y-m-d";         // Set the format the date is displayed in
                                 // Changing it will break a few things in
                                 // this script, but usually you can define
                                 // this to your likings.
$timeFormat   = "H:i";           // Set the format the time is displayed in
//-------------------------------------------------------------------------

// Load the Weather class
require_once "Services/Weather.php";

// Object initialization - error checking is important, because of
// handling exceptions such as missing PEAR modules
$weatherDotCom = &Services_Weather::service("WeatherDotCom", array("httpTimeout" => 30));
if (Services_Weather::isError($weatherDotCom)) {
    die("Error: ".$weatherDotCom->getMessage()."\n");
}

// Set weather.com partner data
$weatherDotCom->setAccountData($partnerID, $licenseKey);

// Initialize caching
if (strlen($cacheType)) {
    $status = $weatherDotCom->setCache($cacheType, $cacheOpt);
    if (Services_Weather::isError($status)) {
        echo "Error: ".$status->getMessage()."\n";
    }
}

// Define the units format, bring the retrieved format into
// something more common...
$weatherDotCom->setUnitsFormat($unitsFormat);
$units = $weatherDotCom->getUnitsFormat();
$units["temp"]   = "&deg;".strtoupper($units["temp"]);
$units["wind"]   = "&nbsp;".str_replace("kmh", "km/h", $units["wind"]);
$units["vis"]    = "&nbsp;".$units["vis"];
$units["height"] = "&nbsp;".$units["height"];
$units["pres"]   = "&nbsp;".$units["pres"];
$units["rain"]   = "&nbsp;".$units["rain"];

// Set date-/time-format
$weatherDotCom->setDateTimeFormat($dateFormat, $timeFormat);

// Search for defined location and fetch the first item found.
// Bail out if something bad happens...
$search = $weatherDotCom->searchLocation($location, true);
if (Services_Weather::isError($search)) {
    die("Error: ".$search->getMessage()."\n");
}

// Retrieve data, store in variables, bail out on error
$fetch = array(
    "links"    => "getLinks",
    "location" => "getLocation",
    "weather"  => "getWeather",
    "forecast" => "getForecast"
);
foreach ($fetch as $variable => $function) {
    $$variable = $weatherDotCom->$function($search, $forecastDays);
    if (Services_Weather::isError($$variable)) {
        echo "Error: ".$$variable->getMessage()."\n";
        continue;
    }
}

// We need this for some time-checks and displays later
$wupd = strtotime($weather["update"])  + date("Z");
$fupd = strtotime($forecast["update"]) + date("Z");
$fup  = strtotime($forecast["update"]) + $location["timezone"] * 3600;

// Check, if we're in the afternoon and if the forecast was updated yet...
// This triggers if the day-forecast for the current day will get shown.
$afternoon = ($location["time"] > "13:59" || date("Ymd", $fup) < date("Ymd")) ? true : false;

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
    var_dump($links, $location, $weather, $forecast);
    echo "</pre>\n";
} 
?>
<span class="bluebold" style="font-size: 13pt">Weather Forecast</span> created with <a style="font-size: 13pt" href="http://pear.php.net/">PEARs</a> <a style="font-size: 13pt" href="http://pear.php.net/package/Services_Weather/">Services_Weather</a><br>
<table style="width: 100%">
<tr>
    <td>
        <table style="border-top: 2px solid #524b98; border-bottom: 2px solid #e0e3ce; border-left: 2px solid #b8b6c1; border-right: 2px solid #8b87a0; width: 100%">
        <tr class="bgkhaki">
            <td style="width: 310px; border-bottom: 2px solid #abada2" colspan="2"><span class="bold"><?=$location["name"]?></span></td>
            <td style="width: 190px; border-bottom: 2px solid #abada2"><span class="bold">Local time:</span> <?=$location["time"]?> (GMT<?=(($location["timezone"] < 0) ? "" : "+").$location["timezone"]?>)</td>
            <td style="border-bottom: 2px solid #abada2">&nbsp;</td>
        </tr>
        <tr>
            <td><span class="bold">Sunrise:</span> <img style="width: 28px; height: 13px; vertical-align: baseline" alt="Sunrise" src="images/sunrise.gif"> <?=$location["sunrise"]?></td>
            <td colspan="2"><span class="bold">Sunset:</span> <img style="width: 30px; height: 15px; vertical-align: baseline" alt="Sunset" src="images/sunset.gif"> <?=$location["sunset"]?></td>
            <td rowspan="5" valign="middle" align="center">
                <table style="border-top: 2px solid #524b98; border-bottom: 2px solid #e0e3ce; border-left: 2px solid #b8b6c1; border-right: 2px solid #8b87a0">
                <tr class="bgkhaki">
                    <td align="center" style="border-bottom: 2px solid #abada2"><span class="bold">Featured on <span class="bolditalic">weather.com<span class="reg">&reg;</span></span></span></td>
                </tr>
<?php
// Loop through the mandatory links, nothing spectacular
for ($i = 0; $i < sizeof($links["promo"]); $i++) {
?>
                <tr class="bggrey">
                    <td><a href="<?=$links["promo"][$i]["link"]?>"><?=$links["promo"][$i]["title"]?></a></td>
                </tr>
<?php
}
?>
                </table>
            </td>
        </tr>
        <tr>
            <td><span class="bold">Temperature:</span> <?=round($weather["temperature"], 1).$units["temp"]?></td>
            <td><span class="bold">Dew point:</span> <?=round($weather["dewPoint"], 1).$units["temp"]?></td>
            <td><span class="bold">Felt temperature:</span> <?=round($weather["feltTemperature"], 1).$units["temp"]?></td>
        </tr>
        <tr>
            <td colspan="2"><span class="bold">Pressure:</span> <?=round($weather["pressure"], 1).$units["pres"]?> and <?=$weather["pressureTrend"]?></td>
            <td><span class="bold">Humidity:</span> <?=$weather["humidity"]?>%</td>
        </tr>
        <tr>
            <td colspan="2"><span class="bold">Wind:</span> <?=strtolower($weather["windDirection"]) == "calm" ? "Calm" : "From the ".$weather["windDirection"]." (".$weather["windDegrees"]."&deg;) at ".round($weather["wind"], 1).$units["wind"]?></td>
            <td><span class="bold">Visibility:</span> <?=round($weather["visibility"], 1).$units["vis"]?></td>
        </tr>
        <tr>
            <td><span class="bold">Current condition:</span><br><?=$weather["condition"]?></td>
            <td><img style="height: 32px; width: 32px" alt="<?=$weather["condition"]?>" src="images/32x32/<?=$weather["conditionIcon"]?>.png"></td>
            <td valign="top"><span class="bold">UV-Index:</span> <?=$weather["uvIndex"]?> (<?=$weather["uvText"]?>)</td>
        </tr>
        </table>
    </td>
</tr>
<tr>
    <td>
        <table style="border-top: 2px solid #524b98; border-bottom: 2px solid #e0e3ce; border-left: 2px solid #b8b6c1; border-right: 2px solid #8b87a0">
        <tr class="bgkhaki">
            <td align="center" style="border-bottom: 2px solid #abada2" colspan="<?=(1 + $forecastDays)?>"><span class="bold"><?=$forecastDays?>-day forecast</span></td>
        </tr>
        <tr valign="top">
            <td style="width: 10%">
                <table class="bgkhaki" style="width: 100%; border-top: 2px solid #d8d8c0; border-bottom: 2px solid #d8d8c0; border-left: 2px solid #d8d8c0; border-right: 2px solid #8b87a0">
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
<?php
for ($day = 0; $day < $forecastDays; $day++) {
    // Set name of day
    if ($day == 0) {
        $dayname = "Today";
    } elseif ($day == 1) {
        $dayname = "Tomorrow";
    } else {
        $dayname = date("l", $fup + $day * 86400);
    }
    // Afternoon is only important for today
    $afternoon = ($day == 0) ? $afternoon : false;
?>
            <td style="width: <?=(90 / $forecastDays)?>%">
                <table style="width: 100%"<?=($day % 2) ? ' class="bggrey"' : ""?>>
                    <tr>
                        <td align="center" colspan="2" style="height: 15px"><span class="bold"><?=$dayname?></span></td>
                    </tr>
                    <tr>
                        <td align="center" colspan="2" style="height: 45px"><?=$afternoon ? "" : '<span class="redbold">'.round($forecast["days"][$day]["temperatureHigh"], 0).$units["temp"].'</span> / '?><span class="bluebold"><?=round($forecast["days"][$day]["temperatureLow"], 0).$units["temp"]?></span></td>
                    </tr>
                    <tr>
                        <td align="center" style="width: 50%; height: 15px"><?=$afternoon ? "&nbsp;" : '<span class="bold">Day</span>'?></td>
                        <td align="center" style="width: 50%; height: 15px"><span class="bold">Night</span></td>
                    <tr>
                    <tr>
                        <td align="center" style="height: 75px" validn="top">
                            <?=$afternoon ? "&nbsp;" : '<img style="height: 32px; width: 32px" align="top" alt="'.$forecast["days"][$day]["day"]["condition"].'" src="images/32x32/'.$forecast["days"][$day]["day"]["conditionIcon"].'.png">'?><br>
                            <?=$afternoon ? "&nbsp;" : $forecast["days"][$day]["day"]["condition"]?> 
                        </td>
                        <td align="center" style="height: 75px" validn="top">
                            <img style="height: 32px; width: 32px" align="top" alt="<?=$forecast["days"][$day]["night"]["condition"]?>" src="images/32x32/<?=$forecast["days"][$day]["night"]["conditionIcon"]?>.png"><br>
                            <?=$forecast["days"][$day]["night"]["condition"]?> 
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="height: 45px"><?=$afternoon ? "&nbsp" : $forecast["days"][$day]["day"]["precipitation"]."%"?></td>
                        <td align="center" style="height: 45px"><?=$forecast["days"][$day]["night"]["precipitation"]?>%</td>
                    </tr>
                    <tr>
                        <td align="center" style="height: 45px"><?=$afternoon ? "&nbsp;" : round($forecast["days"][$day]["day"]["wind"], 0).$units["wind"]." from&nbsp;".$forecast["days"][0]["day"]["windDirection"]?></td>
                        <td align="center" style="height: 45px"><?=round($forecast["days"][$day]["night"]["wind"], 0).$units["wind"]?> from&nbsp;<?=$forecast["days"][0]["day"]["windDirection"]?></td>
                    </tr>
                    <tr>
                        <td align="center" style="height: 15px"><?=$afternoon ? "" : $forecast["days"][$day]["day"]["humidity"]."%"?></td>
                        <td align="center" style="height: 15px"><?=$forecast["days"][$day]["night"]["humidity"]?>%</td>
                    </tr>
                </table>
            </td>
<?php
}
?>
        </tr>
        <tr class="bgkhaki">
    	    <td style="border-top: 2px solid #abada2">&nbsp;</td>
            <td style="border-top: 2px solid #abada2">Updated: (<?=date($timeFormat, $wupd)?>&nbsp;/&nbsp;<?=date($timeFormat, $fupd)?>)</td>
            <td align="right" style="border-top: 2px solid #abada2" colspan="<?=($forecastDays - 1)?>">Weather data provided by <a href="http://www.weather.com/?prod=xoap&par=<?=$partnerID?>">weather.com<span class="reg">&reg;</span></a><a href="http://www.weather.com/?prod=xoap&par=<?=$partnerID?>"><img style="height: 32px; width: 43px" alt="weather.com(R) logo" src="images/logos/TWClogo_32px.png"></a></td>
        </tr>
        </table>
    </td>
</tr>
</table>
<a href="javascript:history.back()">back</a>
</body>
</html>
