<?php

/**
 * This file contains QUI\Package\LocalServer
 */

namespace QUI\Package;

use QUI;
use QUI\Utils\System\File;

/**
 * Class LocalServer
 *
 * @package QUI\Package
 */
class LocalServer extends QUI\Utils\Singleton
{
    /**
     * activate the locale repository,
     * if the repository is not in the server list, the repository would be added
     */
    public function activate()
    {
        $serverDir = $this->getDir();
        $Packages  = QUI::getPackageManager();

        $Packages->addServer($serverDir, array(
            "type" => "artifact"
        ));

        $Packages->setServerStatus($serverDir, 1);
    }

    /**
     * deactivate the locale repository,
     */
    public function deactivate()
    {
        $serverDir = $this->getDir();
        $Packages  = QUI::getPackageManager();
        $Packages->removeServer($serverDir);
    }

    /**
     * @return string
     */
    public function getDir()
    {
        $updatePath = QUI::conf('update', 'updatePath');

        if (!empty($updatePath) && is_dir($updatePath)) {
            return rtrim($updatePath, '/').'/';
        }

        $localeUpdateFolder = VAR_DIR.'update/packages/';

        if (!is_dir($localeUpdateFolder)) {
            File::mkdir($localeUpdateFolder);
        }

        return $localeUpdateFolder;
    }

    /**
     * Move a file to the locale server
     *
     * @param $file
     * @throws QUI\Exception
     */
    public function uploadPackage($file)
    {
        $serverDir = $this->getDir();
        $info      = File::getInfo($file);
        $filename  = $info['filename'].'.'.$info['extension'];

        if ($info['mime_type'] !== 'application/zip') {
            throw new QUI\Exception('File is not a Package Archive');
        }

        if (!file_exists($file)) {
            throw new QUI\Exception('Package Archive File not found');
        }

        if (file_exists($serverDir.$filename)) {
            unlink($serverDir.$filename);
        }

        File::move($file, $serverDir.$filename);
    }

    /**
     * Return the package list in the locale server
     *
     * @return array
     */
    public function getPackageList()
    {
        $dir = $this->getDir();

        if (!is_dir($dir)) {
            return array();
        }

        $files  = File::readDir($dir);
        $result = array();

        chdir($dir);

        foreach ($files as $package) {
            try {
                $composerJson = file_get_contents(
                    "zip://{$package}#composer.json"
                );
            } catch (\Exception $Exception) {
                // maybe gitlab package?
                try {
                    $packageName  = pathinfo($package);
                    $composerJson = file_get_contents(
                        "zip://{$package}#{$packageName['filename']}/composer.json"
                    );
                } catch (\Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getMessage());
                    continue;
                }
            }

            if (empty($composerJson)) {
                continue;
            }

            $composerJson = json_decode($composerJson, true);

            if (!isset($composerJson['name'])) {
                continue;
            }

            if (is_dir(OPT_DIR.$composerJson['name'])) {
                continue;
            }

            $result[] = $composerJson;
        }

        return $result;
    }
}