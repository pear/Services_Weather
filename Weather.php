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

require_once "PEAR.php";

// {{{ constants
// {{{ cache times
define("SERVICES_WEATHER_EXPIRES_UNITS",      900);
define("SERVICES_WEATHER_EXPIRES_LOCATION",   900);
define("SERVICES_WEATHER_EXPIRES_WEATHER",   1800);
define("SERVICES_WEATHER_EXPIRES_FORECAST",  7200);
define("SERVICES_WEATHER_EXPIRES_LINKS",    43200);
// }}}

// {{{ error codes
define("SERVICES_WEATHER_ERROR_SERVICE_NOT_FOUND",  10);
define("SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION",   11);
define("SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA",  12);
// }}}

// {{{ error codes defined by weather.com
define("SERVICES_WEATHER_ERROR_UNKNOWN_ERROR",            0);
define("SERVICES_WEATHER_ERROR_NO_LOCATION",              1);
define("SERVICES_WEATHER_ERROR_INVALID_LOCATION",         2);
define("SERVICES_WEATHER_ERROR_INVALID_PARTNER_ID",     100);
define("SERVICES_WEATHER_ERROR_INVALID_PRODUCT_CODE",   101);
define("SERVICES_WEATHER_ERROR_INVALID_LICENSE_KEY",    102);
// }}}
// }}}

// {{{ class Services_Weather
/**
* PEAR::Services_Weather
*
* @author       Alexander Wirtz <alex@pc4p.net>
* @package      Services
* @version      1.0
*/
class Services_Weather {

    // {{{ &service()
    /**
    *
    *
    * @param    string                      $service
    * @param    array                       $options
    * @return   PEAR_Error|object
    * @throws   PEAR_Error::SERVICES_WEATHER_ERROR_SERVICE_NOT_FOUND
    * @access   public
    */
    function &service($service, $options = null)
    {
        $service = ucfirst(strtolower($service));
        $classname = "Services_Weather_".$service;

        if (is_array($options) && isset($options["debug"]) && $options["debug"] >= 2) {
            define("SERVICES_WEATHER_DEBUG", TRUE);
            include_once("Services/Weather/".$service.".php");
        } else {
            define("SERVICES_WEATHER_DEBUG", FALSE);
            @include_once("Services/Weather/".$service.".php");
        }

        if (!class_exists($classname)) {
            return Services_Weather::raiseError(SERVICES_WEATHER_ERROR_SERVICE_NOT_FOUND);
        }

        @$obj = &new $classname;

        return $obj;
    }
    // }}}

    // {{{ apiVersion()
    /**
    * For your convenience, when I come up with a new API...
    *
    * @return   int
    * @access   public
    */
   function apiVersion()
    {
        return 1;
    }
    // }}}

    // {{{ _errorMessage()
    /**
    *
    *
    * @param    PEAR_Error|int              $value
    * @return   string
    * @access   private
    */
    function _errorMessage($value)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                SERVICES_WEATHER_ERROR_SERVICE_NOT_FOUND         => "Requested service could not be found.",
                SERVICES_WEATHER_ERROR_UNKNOWN_LOCATION          => "Unknown location provided.",
                SERVICES_WEATHER_ERROR_WRONG_SERVER_DATA         => "Server data wrong or not available.",
                SERVICES_WEATHER_ERROR_UNKNOWN_ERROR             => "An unknown error has occured.",
                SERVICES_WEATHER_ERROR_NO_LOCATION               => "No location provided.",
                SERVICES_WEATHER_ERROR_INVALID_LOCATION          => "Invalid location provided.",
                SERVICES_WEATHER_ERROR_INVALID_PARTNER_ID        => "Invalid partner id.",
                SERVICES_WEATHER_ERROR_INVALID_PRODUCT_CODE      => "Invalid product code.",
                SERVICES_WEATHER_ERROR_INVALID_LICENSE_KEY       => "Invalid license key."
            );
        }

        if (Services_Weather::isError($value)) {
            $value = $value->getCode();
        }

        return isset($errorMessages[$value]) ? $errorMessages[$value] : $errorMessages[SERVICES_WEATHER_ERROR_UNKNOWN_ERROR];
    }
    // }}}

    // {{{ isError()
    /**
    *
    *
    * @param    PEAR_Error|mixed            $value
    * @return   bool
    * @access   public
    */
    function isError($value)
    {
        return (is_object($value) && (get_class($value) == "pear_error" || is_subclass_of($value, "pear_error")));
    }
    // }}}

    // {{{ &raiseError()
    /**
    *
    *
    * @param    int                         $code
    * @return   PEAR_Error
    * @access   private
    */
    function &raiseError($code = SERVICES_WEATHER_ERROR_UNKNOWN_ERROR)
    {
        $message = "Services_Weather: ".Services_Weather::_errorMessage($code);

        return PEAR::raiseError($message, $code, PEAR_ERROR_RETURN, E_USER_NOTICE, "Services_Weather_Error", null, false);
    }
    // }}}
}
// }}}
?>
