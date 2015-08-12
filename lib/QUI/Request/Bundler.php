<?php

/**
 * This file contains \QUI\Request\Bundler
 */
namespace QUI\Request;

use QUI;
use QUI\Utils\Security\Orthos;

/**
 * Class Bundler
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package QUI\Request
 */
class Bundler
{
    /**
     * Files includes
     *
     * @var array
     */
    protected $_includes = array();

    /**
     * Read the $_REQUEST and create the response
     */
    public function response()
    {
        if (!isset($_REQUEST['quiqqerBundle'])) {
            return '';
        }

        $result = array();
        $requests = $_REQUEST['quiqqerBundle'];

        foreach ($requests as $request) {
            try {
                $result[$request['rid']] = $this->_parseRequest($request);

            } catch (\Exception $Exception) {
                $result[$request['rid']]['Exception'] = array(
                    'message' => $Exception->getMessage(),
                    'code'    => $Exception->getCode()
                );
            }
        }

        return json_encode($result);
    }

    /**
     *
     * @param array $request
     *
     * @return string
     * @throws QUI\Exception
     */
    protected function _parseRequest($request)
    {
        if (!isset($request['request'])) {
            throw new QUI\Exception('Bad Request', 400);
        }

        $function = $request['request'];

        $this->_includesPackage($function, $request);
        $this->_includesProject($function, $request);
        $this->_includes($function);

        QUI\Ajax::checkPermissions($function);


        // Params
        $params = array();

        foreach (QUI\Ajax::$_functions[$function] as $var) {

            if (!isset($_REQUEST[$var])) {
                $params[$var] = '';
                continue;
            }

            $value = $_REQUEST[$var];

            if (is_object($value)) {
                $params[$var] = $value;
                continue;
            }

            $value = urldecode($value);

            if (get_magic_quotes_gpc()) {
                $params[$var] = stripslashes($value);
            } else {
                $params[$var] = $value;
            }
        }

        // execute
        $result = call_user_func_array($function, $params);


        // json errors?
        if (function_exists('json_last_error')) {

            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    break;

                case JSON_ERROR_DEPTH:
                case JSON_ERROR_STATE_MISMATCH:
                case JSON_ERROR_CTRL_CHAR:
                case JSON_ERROR_SYNTAX:
                case JSON_ERROR_UTF8:
                default:
                    QUI\System\Log::write(
                        'JSON Error: '.json_last_error()
                        .' :: '.print_r($result, true),
                        'error'
                    );
                    break;
            }
        }

        // session close -> performance
        QUI::getSession()->getSymfonySession()->save();


        return $result;
    }

    /**
     * Include normal files
     *
     * @param string $function - name of the function
     */
    protected function _includes($function)
    {
        if (isset($this->_includes[$function])) {
            return;
        }

        $dir = dirname(__FILE__).'/';

        $file = $dir.str_replace('_', '/', $function).'.php';
        $file = Orthos::clearPath($file);
        $file = realpath($file);

        if (strpos($file, $dir) !== false && file_exists($file)) {
            require_once $file;
        }

        $this->_includes[$function] = $file;
    }

    /**
     * Include package files
     *
     * @param string $function - name of the function
     * @param array  $request  - Request data
     */
    protected function _includesPackage($function, $request)
    {
        if (isset($request['package'])) {
            return;
        }

        if (isset($this->_includes[$function])) {
            return;
        }

        $package = $request['package'];
        $dir = OPT_DIR;

        $firstpart = 'package_'.str_replace('/', '_', $package);
        $ending = str_replace($firstpart, '', $function);

        $file = $dir.$package.str_replace('_', '/', $ending).'.php';
        $file = Orthos::clearPath($file);
        $file = realpath($file);

        if (strpos($file, $dir) !== false && file_exists($file)) {
            require_once $file;
        }

        $this->_includes[$function] = $file;
    }

    /**
     * Include projects files
     *
     * @param string $function - name of the function
     * @param array  $request  - Request data
     */
    protected function _includesProject($function, $request)
    {
        if (!isset($request['project'])) {
            return;
        }

        if (isset($this->_includes[$function])) {
            return;
        }

        try {
            $Project = QUI::getProjectManager()->decode($request['project']);

        } catch (QUI\Exception $Exception) {
            $Project = QUI::getProjectManager()->getProject(
                $request['project']
            );
        }

        $projectDir = USR_DIR.$Project->getName();
        $firstpart = 'project_'.$Project->getName().'_';

        $file = str_replace($firstpart, '', $function);
        $file = $projectDir.'/lib/'.str_replace('_', '/', $file).'.php';
        $file = Orthos::clearPath($file);
        $file = realpath($file);

        $dir = $projectDir.'/lib/';

        if (strpos($file, $dir) !== false && file_exists($file)) {
            require_once $file;
        }

        $this->_includes[$function] = $file;
    }

}