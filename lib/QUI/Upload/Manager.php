<?php

/**
 * This file contains \QUI\Upload\Manager
 */

namespace QUI\Upload;

use QUI;
use QUI\Utils\System\File as QUIFile;
use QUI\Utils\Security\Orthos;
use QUI\Utils\System\File;

/**
 * Upload Manager
 * Manage Uploads from Users to the media
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.upload
 * @licence For copyright and license information, please view the /README.md
 */
class Manager
{
    /**
     * DataBase table name
     *
     * @var string
     */
    protected $table = 'uploads';

    /**
     * constructor
     */
    public function __construct()
    {
        $this->table = \QUI_DB_PRFX.'uploads';
    }

    /**
     * Return the main upload dir
     */
    public function getDir()
    {
        return VAR_DIR.'uploads/';
    }

    /**
     * Execute the setup and create the table for the upload manager
     */
    public function setup()
    {
        QUI::getDataBase()->table()->addColumn($this->table, array(
            'file'   => 'varchar(255)',
            'user'   => 'int(11)',
            'params' => 'text'
        ));

        QUI::getDataBase()->table()->setIndex(
            $this->table,
            array('file', 'user')
        );
    }

    /**
     * Initialized the upload
     *
     * @return string
     * @throws \QUI\Exception
     */
    public function init()
    {
        // if a onstart function
        if (isset($_REQUEST['onstart']) && !empty($_REQUEST['onstart'])) {
            $this->callFunction($_REQUEST['onstart'], $_REQUEST);
        }

        return $this->upload();
    }

    /**
     * Flush a Message to the JavaScript UploadManager
     *
     * @param array|string $message
     */
    public function flushMessage($message)
    {
        $message = '<script type="text/javascript">
            var UploadManager = false;

            if (typeof window.parent !== "undefined" &&
                typeof window.parent.QUI !== "undefined" &&
                typeof window.parent.QUI.UploadManager !== "undefined")
            {
                UploadManager = window.parent.QUI.UploadManager;
            }

            if (UploadManager) {
                UploadManager.sendMessage('.json_encode($message).');
            }
        </script>';

        echo $message;
        ob_flush();
        flush();
    }

    /**
     * Flush a javascript call to the UploadManager
     *
     * @param string $call - eq: alert(1);
     */
    public function flushAction($call)
    {
        $message = '<script type="text/javascript">
            var UploadManager = false;

            if (typeof window.parent !== "undefined" &&
                typeof window.parent.QUI !== "undefined" &&
                typeof window.parent.QUI.UploadManager !== "undefined")
            {
                UploadManager = window.parent.QUI.UploadManager;
            }

            if (UploadManager) {
                '.$call.'
            }
        </script>';

        echo $message;
        ob_flush();
        flush();
    }

    /**
     * Flush a exception to the UploadManager
     *
     * @param QUI\Exception $Exception
     */
    public function flushException(QUI\Exception $Exception)
    {
        $message = array(
            'Exception' => $Exception->toArray()
        );

        echo '<quiqqer>'.json_encode($message).'</quiqqer>';
        ob_flush();
        flush();
    }

    /**
     * Upload the file data,
     * read the PUT data and write it to the filesystem or read the $_FILES
     *
     * @return string
     * @throws QUI\Exception
     */
    public function upload()
    {
        QUIFile::mkdir($this->getUserUploadDir());

        $filename = false;
        $filesize = 0;
        $params   = array();
        $onfinish = false;

        if (isset($_REQUEST['filename'])) {
            $filename = $_REQUEST['filename'];
        }

        if (isset($_REQUEST['filesize'])) {
            $filesize = (int)$_REQUEST['filesize'];
        }

        if (isset($_REQUEST['fileparams'])) {
            $params = json_decode($_REQUEST['fileparams'], true);
        }

        if (isset($_REQUEST['onfinish'])) {
            $onfinish = $_REQUEST['onfinish'];
        }

        if (isset($_REQUEST['extract'])) {
            $_REQUEST['extract'] = QUI\Utils\BoolHelper::JSBool($_REQUEST['extract']);
        }

        /**
         * no html5 upload
         */
        if (!$filename) {
            try {
                $this->formUpload($onfinish, $params);
            } catch (QUI\Exception $Exception) {
                $this->flushMessage($Exception->toArray());

                return '';
            }

            $uploadid = 0;

            if (isset($_REQUEST['uploadid'])) {
                $uploadid = $_REQUEST['uploadid'];
            }

            $this->flushAction('UploadManager.isFinish("'.$uploadid.'")');

            return '';
        }

        /**
         * html5 upload
         */
        if (isset($_REQUEST['file'])) {
            $file = json_decode($_REQUEST['file'], true);
        }

        if (isset($file) && isset($file['chunkstart']) && $file['chunkstart'] == 0) {
            $this->delete($filename);
        }

        // add the file to the database
        $this->add($filename, $params);


        $uploaddir = $this->getUserUploadDir();
        $tmp_name  = $uploaddir.$filename;

        /* PUT REQUEST */
        $putdata = file_get_contents('php://input');
        $Handle  = fopen($tmp_name, 'a');

        if ($Handle) {
            fwrite($Handle, $putdata);
        }

        fclose($Handle);

        // upload finish?
        $fileinfo = QUIFile::getInfo($tmp_name, array(
            'filesize' => true
        ));

        $configMaxFileSize = QUI\Projects\Manager::get()->getConfig('media_maxUploadFileSize');

        if ($configMaxFileSize && (int)$fileinfo['filesize'] > $configMaxFileSize) {
            QUIFile::unlink($tmp_name);

            throw new QUI\Exception(array(
                'quiqqer/quiqqer',
                'exception.media.upload.fileSize.is.to.big',
                array(
                    'size' => QUI\Utils\System\File::formatSize($configMaxFileSize)
                )
            ));
        }

        // finish? then upload to folder
        if ((int)$fileinfo['filesize'] == $filesize) {
            // extract if the the extract file is set
            if (isset($_REQUEST['extract']) && $_REQUEST['extract']) {
                $File = $this->extract($tmp_name);
            }

            $Data = $this->getFileData($filename);

            if (!isset($File)) {
                $File = $Data;

                $File->setAttribute(
                    'filepath',
                    $uploaddir.$File->getAttribute('file')
                );
            }

            $File->setAttribute('upload-dir', $uploaddir);
            $File->setAttribute('params', $Data->getAttribute('params'));

            $result = array();

            if (!empty($onfinish)) {
                $result = $this->callFunction($onfinish, array(
                    'File' => $File
                ));
            }

            // delete the file from the database
            $this->delete($filename);

            // delete the real file
            QUIFile::unlink($tmp_name);

            if (isset($result['Exception'])) {
                throw new QUI\Exception(
                    $result['Exception']['message'],
                    $result['Exception']['code']
                );
            }

            if (isset($result['result'])) {
                return $result['result'];
            }

            return true;
        }

        return '';
    }

    /**
     * call a function
     *
     * @param string|callback $function - Function
     * @param array $params - function parameter
     *
     * @return mixed
     * @throws \QUI\Exception
     */
    protected function callFunction($function, $params = array())
    {
        if ($function === false) {
            return false;
        }

        if (is_object($function) && get_class($function) === 'Closure') {
            return $function();
        }

        if (function_exists($function)) {
            return call_user_func_array($function, $params);
        }

        if (strpos($function, 'ajax_') === 0) {
            // if the function is a ajax_function
            $_rf_file = OPT_DIR.'quiqqer/quiqqer/admin/'.str_replace('_', '/', $function).'.php';
            $_rf_file = Orthos::clearPath(realpath($_rf_file));

            if (file_exists($_rf_file)) {
                require_once $_rf_file;
            }

            $_REQUEST = array_merge($_REQUEST, $params, array(
                '_rf' => '["'.$function.'"]'
            ));

            return QUI::getAjax()->callRequestFunction($function, $_REQUEST);
        }

        if (strpos($function, 'package_') === 0) {
            $dir  = OPT_DIR;
            $file = substr(str_replace('_', '/', $function), 8).'.php';

            $_rf_file = $dir.$file;
            $_rf_file = Orthos::clearPath(realpath($_rf_file));

            if (file_exists($_rf_file)) {
                require_once $_rf_file;
            }

            $_REQUEST = array_merge($_REQUEST, $params, array(
                '_rf' => '["'.$function.'"]'
            ));

            return QUI::getAjax()->callRequestFunction($function, $_REQUEST);
        }

        throw new QUI\Exception(
            'Function '.$function.' not found',
            404
        );
    }

    /**
     * Internal form upload method
     * If the upload is not over HTML5
     *
     * @param string|callback $onfinish - Function
     * @param $params - extra params for the \QUI\QDOM File Object
     *
     * @throws \QUI\Exception
     */
    protected function formUpload($onfinish, $params)
    {
        if (empty($_FILES) || !isset($_FILES['files'])) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.no.data'),
                400
            );
        }

        $list = $_FILES['files'];

        if (!is_array($list['error'])) {
            $this->checkUpload($list['error']);

            $uploaddir = $this->getUserUploadDir();
            $filename  = $list['name'];
            $file      = $uploaddir.$filename;

            if (!move_uploaded_file($list["tmp_name"], $file)) {
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.move', array(
                        'file' => $file
                    ))
                );
            }

            // extract if the the extract file is set
            if (isset($_REQUEST['extract']) && $_REQUEST['extract']) {
                $File = $this->extract($file);
            }

            if (!isset($File)) {
                $File = new QUI\QDOM();
                $File->setAttribute('name', $filename);
                $File->setAttribute('filepath', $file);
            }

            $File->setAttribute('params', $params);
            $File->setAttribute('upload-dir', $uploaddir);

            $this->callFunction($onfinish, array(
                'File' => $File
            ));

            // delete the real file
            QUIFile::unlink($file);

            return;
        }

        foreach ($list['error'] as $key => $error) {
            $this->checkUpload($error);

            $uploaddir = $this->getUserUploadDir();
            $filename  = $list['name'][$key];
            $file      = $uploaddir.$filename;

            if (!move_uploaded_file($list["tmp_name"], $file)) {
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.move', array(
                    'file' => $filename
                ));
            }

            // extract if the the extract file is set
            if (isset($_REQUEST['extract']) && $_REQUEST['extract']) {
                $File = $this->extract($file);
            }

            if (!isset($File)) {
                $File = new QUI\QDOM();
                $File->setAttribute('name', $filename);
                $File->setAttribute('filepath', $file);
            }

            $File->setAttribute('params', $params);
            $File->setAttribute('upload-dir', $uploaddir);

            $this->callFunction($onfinish, array(
                'File' => $File
            ));

            // delete the real file
            QUIFile::unlink($file);
        }
    }

    /**
     * Check if some errors occurred on the upload entry
     *
     * @param integer $error
     *
     * @return bool
     * @throws \QUI\Exception
     */
    protected function checkUpload($error)
    {
        switch ($error) {
            // There is no error, the file upload was successful
            case UPLOAD_ERR_OK:
                return true;
                break;

            case UPLOAD_ERR_INI_SIZE:
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.max.filesize')
                );

            case UPLOAD_ERR_FORM_SIZE:
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.max.form.filesize')
                );

            case UPLOAD_ERR_PARTIAL:
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.partially.uploaded')
                );

            case UPLOAD_ERR_NO_FILE:
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.no.data')
                );

            case UPLOAD_ERR_NO_TMP_DIR:
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.missing.temp')
                );
        }

        return true;
    }

    /**
     * Extract the Archiv
     *
     * @param string $filename
     *
     * @throws \QUI\Exception
     * @return \QUI\QDOM
     *
     * @todo more archive types
     */
    protected function extract($filename)
    {
        $fileinfo = QUIFile::getInfo($filename);

        if ($fileinfo['mime_type'] != 'application/zip') {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.upload.unsupported.archive')
            );
        }

        $to = $this->getUserUploadDir().$fileinfo['filename'];

        QUIFile::unlink($to);
        QUIFile::mkdir($to);

        QUI\Archiver\Zip::unzip($filename, $to);

        $File = new QUI\QDOM();
        $File->setAttribute('name', $fileinfo['filename']);
        $File->setAttribute('filepath', $to);

        return $File;
    }

    /**
     * Return the Path to the User upload directory
     *
     * @param \QUI\Users\User|boolean $User - optional, standard is the session user
     *
     * @return string
     */
    protected function getUserUploadDir($User = false)
    {
        if (!QUI::getUsers()->isUser($User)) {
            $User = QUI::getUserBySession();
        }

        $id = $User->getId();

        // for nobody we use the session id
        if (!$id) {
            $id = QUI::getSession()->getId();
        }

        return $this->getDir().$id.'/';
    }

    /**
     * Cancel the upload
     *
     * @param string $filename - the filename of the file
     */
    public function cancel($filename)
    {
        $this->delete($filename);
    }

    /**
     * Add a file to the Upload Manager
     *
     * @param string $filename - filename
     * @param array $params - optional
     */
    protected function add($filename, $params)
    {
        $file = $this->getUserUploadDir().$filename;

        if (file_exists($file)) {
            return;
        }

        QUI::getDataBase()->insert($this->table, array(
            'file'   => $filename,
            'user'   => QUI::getUserBySession()->getId(),
            'params' => json_encode($params)
        ));
    }

    /**
     * Delete the file entry and the uploaded temp file
     *
     * @param string $filename
     *
     * @throws \QUI\Exception
     */
    protected function delete($filename)
    {
        QUI::getDataBase()->exec(array(
            'delete' => true,
            'from'   => $this->table,
            'where'  => array(
                'user' => QUI::getUserBySession()->getId(),
                'file' => $filename
            )
        ));

        QUIFile::unlink(
            $this->getUserUploadDir().$filename
        );
    }

    /**
     * Return a \QUI\QDOM Object of the file entry
     *
     * @param string $filename
     *
     * @return \QUI\QDOM
     * @throws \QUI\Exception
     */
    protected function getFileData($filename)
    {
        $db_result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table,
            'where' => array(
                'user' => QUI::getUserBySession()->getId(),
                'file' => $filename
            )
        ));

        if (!isset($db_result[0])) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.file.not.found'),
                404
            );
        }

        $File = new QUI\QDOM();
        $File->setAttributes($db_result[0]);

        if ($File->getAttribute('params')) {
            $File->setAttribute(
                'params',
                json_decode($File->getAttribute('params'), true)
            );
        }

        return $File;
    }

    /**
     * Get unfinish uploads from a specific user
     * so you can resume the upload
     *
     * @param \QUI\Users\User|boolean $User - optional, if false = the session user
     *
     * @return array
     */
    public function getUnfinishedUploadsFromUser($User = false)
    {
        if (!QUI::getUsers()->isUser($User)) {
            $User = QUI::getUserBySession();
        }

        // read user upload dir
        $dir = $this->getDir().$User->getId().'/';

        if (!file_exists($dir) || !is_dir($dir)) {
            return array();
        }

        $files  = QUIFile::readDir($dir);
        $result = array();

        foreach ($files as $file) {
            try {
                $File       = $this->getFileData($file);
                $attributes = $File->getAttributes();

                if (isset($attributes['params'])
                    && is_string($attributes['params'])
                ) {
                    $params    = json_decode($attributes['params'], true);
                    $file_info = QUIFile::getInfo($dir.$file);

                    $params['file']['uploaded'] = $file_info['filesize'];

                    $attributes['params'] = $params;
                }

                $result[] = $attributes;
            } catch (QUI\Exception $Exception) {
                if ($Exception->getCode() === 404) {
                    QUIFile::unlink($dir.$file);
                }
            }
        }

        return $result;
    }
}
