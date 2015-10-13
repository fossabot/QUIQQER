<?php

/**
 * This file contains \QUI\System\Log
 */

namespace QUI\System;

use QUI;

/**
 * Writes Logs into the logdir
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @licence For copyright and license information, please view the /README.md
 * @package QUI\System
 * @need quiqqr/log
 */
class Log
{
    const LEVEL_DEBUG = 100;
    const LEVEL_INFO = 200;
    const LEVEL_NOTICE = 250;
    const LEVEL_WARNING = 300;
    const LEVEL_ERROR = 400;
    const LEVEL_CRITICAL = 500;
    const LEVEL_ALERT = 550;
    const LEVEL_EMERGENCY = 600;

    /**
     * Return the log name by a log level
     *
     * @param Integer $LogLevel - Log Level
     *
     * @return String
     */
    static function levelToLogName($LogLevel)
    {
        switch ($LogLevel) {
            case self::LEVEL_DEBUG     :
                return 'debug';
            case self::LEVEL_INFO      :
                return 'info';
            case self::LEVEL_NOTICE    :
                return 'notice';
            case self::LEVEL_WARNING   :
                return 'warning';
            case self::LEVEL_ERROR     :
                return 'error';
            case self::LEVEL_CRITICAL  :
                return 'critical';
            case self::LEVEL_ALERT     :
                return 'alert';
            case self::LEVEL_EMERGENCY :
                return 'emergency';
        }

        return 'error';
    }

    /**
     * Writes a string to a log file
     *
     * @param String $message - String to write
     * @param Integer $loglevel - loglevel ( \QUI\System\Log::LEVEL_ERROR ... )
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database,
     *
     * @example \QUI\System\Log::write( 'My Error', \QUI\System\Log::LEVEL_ERROR );
     */
    static function write(
        $message,
        $loglevel = self::LEVEL_INFO,
        $filename = false
    ) {
        $Logger = QUI\Log\Logger::getLogger();

        if (isset($_SERVER['REQUEST_URI'])
            && !empty($_SERVER['REQUEST_URI'])
        ) {
            $message = HOST . $_SERVER['REQUEST_URI'] . ' : ' . $message;
        }

        $context = array(
            'filename' => $filename
        );

        switch (self::levelToLogName($loglevel)) {
            case 'debug':
                $Logger->addDebug($message, $context);
                break;

            case 'info':
                $Logger->addInfo($message, $context);
                break;

            case 'notice':
                $Logger->addNotice($message, $context);
                break;

            case 'warning':
                $Logger->addWarning($message, $context);
                break;

            case 'critical':
                $Logger->addCritical($message, $context);
                break;

            case 'alert':
                $Logger->addAlert($message, $context);
                break;

            case 'emergency':
                $Logger->addEmergency($message, $context);
                break;

            case 'error':
            default:
                $Logger->addError($message, $context);
        }

//
//        // trigger only events if the session already exist, because we can get in loops otherwise
//        if (QUI::$Session) {
//            QUI::getEvents()->fireEvent('logWrite', array(
//                'message'  => $message,
//                'loglevel' => $loglevel
//            ));
//        }
//
//        $message = '[' . date(\DATE_ATOM) . '] ' . $message . "\n";
//
//
//        $dir  = VAR_DIR . 'log/';
//        $file = $dir . $filename . date('-Y-m-d') . '.log';
//
//        // Log Verzeichnis erstellen
//        QUI\Utils\System\File::mkdir($dir);
//
//        error_log($message . "\n", 3, $file);
    }

    /**
     * Writes with print_r the object into a log file
     *
     * @param Object|String|Integer|Array $object
     * @param Integer $loglevel - loglevel ( \QUI\System\Log::LEVEL_ERROR ... )
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database,
     */
    static function writeRecursive(
        $object,
        $loglevel = self::LEVEL_INFO,
        $filename = false
    ) {
        self::write(print_r($object, true), $loglevel, $filename);
    }

    /**
     * Writes an Exception to a log file
     *
     * @param \Exception $Exception
     * @param Integer $loglevel - loglevel ( \QUI\System\Log::LEVEL_ERROR ... )
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database,
     */
    static function writeException(
        $Exception,
        $loglevel = self::LEVEL_ERROR,
        $filename = false
    ) {
        $message = $Exception->getCode() . " :: \n\n";
        $message .= $Exception->getMessage();
        $message .= $Exception->getTraceAsString();

        self::write($message, $loglevel, $filename);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * @param String $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addDebug($message, $filename = false)
    {
        self::write($message, self::LEVEL_DEBUG, $filename);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addInfo($message, $filename = false)
    {
        self::write($message, self::LEVEL_INFO, $filename);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addNotice($message, $filename = false)
    {
        self::write($message, self::LEVEL_NOTICE, $filename);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addWarning($message, $filename = false)
    {
        self::write($message, self::LEVEL_WARNING, $filename);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addError($message, $filename = false)
    {
        self::write($message, self::LEVEL_ERROR, $filename);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addCritical($message, $filename = false)
    {
        self::write($message, self::LEVEL_CRITICAL, $filename);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addAlert($message, $filename = false)
    {
        self::write($message, self::LEVEL_ALERT, $filename);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * @param string $message The log message
     * @param String|Boolean $filename - [optional] name of the log eq: messages, database (default = error)
     */
    static function addEmergency($message, $filename = false)
    {
        self::write($message, self::LEVEL_EMERGENCY, $filename);
    }
}
