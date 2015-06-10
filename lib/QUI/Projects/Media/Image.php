<?php

/**
 * This file contains the \QUI\Projects\Media\Image
 */

namespace QUI\Projects\Media;

use QUI;
use QUI\Utils\System\File as QUIFile;
use QUI\Utils\String as QUIString;
use QUI\Utils\Image as QUIImage;
use QUI\Utils\System\File;

/**
 * A media image
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.projects.media
 * @licence For copyright and license information, please view the /README.md
 */
class Image extends Item implements QUI\Interfaces\Projects\Media\File
{
    /**
     * Return the real with of the image
     *
     * @return Integer | false
     */
    public function getWidth()
    {
        if ($this->getAttribute('image_width')) {
            return $this->getAttribute('image_width');
        }

        $data = QUIFile::getInfo($this->getFullPath(),
            array('imagesize' => true));

        if (isset($data['width'])) {
            return $data['width'];
        }

        return false;
    }

    /**
     * Return the real height of the image
     *
     * @return Integer | false
     */
    public function getHeight()
    {
        if ($this->getAttribute('image_height')) {
            return $this->getAttribute('image_height');
        }

        $data = QUIFile::getInfo($this->getFullPath(),
            array('imagesize' => true));

        if (isset($data['height'])) {
            return $data['height'];
        }

        return false;
    }

    /**
     * (non-PHPdoc)
     *
     * @see QUI\Interfaces\Projects\Media\File::createCache()
     */
    public function createCache()
    {
        return $this->createSizeCache();
    }

    /**
     * Return the image path
     *
     * @param string|bool $maxwidth  - (optional)
     * @param string|bool $maxheight - (optional)
     *
     * @return string
     */
    public function getSizeCachePath($maxwidth = false, $maxheight = false)
    {
        $Media = $this->_Media;
        /* @var $Media QUI\Projects\Media */
        $cdir = CMS_DIR.$Media->getCacheDir();
        $file = $this->getAttribute('file');

        if (!$maxwidth && !$maxheight) {
            return $cdir.$file;
        }


        if ($maxwidth > 1200) {
            $maxwidth = 1200;
        }

        if ($maxheight > 1200) {
            $maxheight = 1200;
        }

        $extra = '';
        $params = $this->getResizeSize($maxwidth, $maxheight);

        $width = $params['width'];
        $height = $params['height'];

        if ($this->getAttribute('reflection')) {
            $extra = '_reflection';
        }


        if ($width || $height) {
            $part = explode('.', $file);
            $cachefile = $cdir.$part[0].'__'.$width.'x'.$height.$extra.'.'
                .QUIString::toLower(end($part));

            if (empty($height)) {
                $cachefile = $cdir.$part[0].'__'.$width.$extra.'.'
                    .QUIString::toLower(end($part));
            }

            if ($this->getAttribute('reflection')) {
                $cachefile
                    = $cdir.$part[0].'__'.$width.'x'.$height.$extra.'.png';

                if (empty($height)) {
                    $cachefile = $cdir.$part[0].'__'.$width.$extra.'.png';
                }
            }

        } else {
            $cachefile = $cdir.$file;
        }

        return $cachefile;
    }

    /**
     * Return the image url
     *
     * @param string|bool $maxwidth  - (optional) width
     * @param string|bool $maxheight - (optional) height
     *
     * @return string
     */
    public function getSizeCacheUrl($maxwidth = false, $maxheight = false)
    {
        $cachePath = $this->getSizeCachePath($maxwidth, $maxheight);
        $cacheUrl = str_replace(CMS_DIR, URL_DIR, $cachePath);

        return $cacheUrl;
    }

    /**
     * Creates a cache file and takes into account the maximum sizes
     * return the media url
     *
     * @param Integer|Bool $maxwidth
     * @param Integer|Bool $maxheight
     *
     * @return String - Path to the file
     */
    public function createSizeCacheUrl($maxwidth = false, $maxheight = false)
    {
        $params = $this->getResizeSize($maxwidth, $maxheight);

        $cacheUrl = $this->createSizeCache(
            $params['width'],
            $params['height']
        );

        $cacheUrl = str_replace(CMS_DIR, URL_DIR, $cacheUrl);

        return $cacheUrl;
    }

    /**
     * Creates a cache file and takes into account the maximum sizes
     *
     * @param Integer|Bool $maxwidth
     * @param Integer|Bool $maxheight
     *
     * @return String - Path to the file
     */
    public function createResizeCache($maxwidth = false, $maxheight = false)
    {
        $params = $this->getResizeSize($maxwidth, $maxheight);

        return $this->createSizeCache(
            $params['width'],
            $params['height']
        );
    }

    /**
     * Return the Image specific max resize params
     *
     * @param Bool|Integer $maxwidth  - (optional)
     * @param Bool|Integer $maxheight - (optional)
     *
     * @return array - array('width' => 100, 'height' => 100)
     */
    public function getResizeSize($maxwidth = false, $maxheight = false)
    {
        $width = $this->getAttribute('image_width');
        $height = $this->getAttribute('image_height');

        if (!$width || !$height) {
            $info = QUIFile::getInfo($this->getFullPath(), array(
                'imagesize' => true
            ));

            $width = $info['width'];
            $height = $info['height'];
        }

        $newwidth = $width;
        $newheight = $height;

        if (!$maxwidth) {
            $maxwidth = $width;
        }

        if (!$maxheight) {
            $maxheight = $height;
        }

        // max höhe breite auf 1200
        if ($maxwidth > 1200) {
            $maxwidth = 1200;
        }

        if ($maxheight > 1200) {
            $maxheight = 1200;
        }

        // Breite
        if ($newwidth > $maxwidth) {
            $resize_by_percent = ($maxwidth * 100) / $newwidth;

            $newheight = (int)round(($newheight * $resize_by_percent) / 100);
            $newwidth = $maxwidth;
        }

        // Höhe
        if ($newheight > $maxheight) {
            $resize_by_percent = ($maxheight * 100) / $newheight;

            $newwidth = (int)round(($newwidth * $resize_by_percent) / 100);
            $newheight = $maxheight;
        }

        return array(
            'width'  => $newwidth,
            'height' => $newheight
        );
    }

    /**
     * Create a cache file with the new width and height
     *
     * @param integer|bool $width  - (optional)
     * @param integer|bool $height - (optional)
     *
     * @return string - URL to the cachefile
     */
    public function createSizeCache($width = false, $height = false)
    {
        if (!$this->getAttribute('active')) {
            return false;
        }

        $Media = $this->_Media;
        $original = $this->getFullPath();
        $cachefile = $this->getSizeCachePath($width, $height);

        if (file_exists($cachefile)) {
            return $cachefile;
        }

        // Cachefolder erstellen
        $this->getParent()->createCache();

        // create image
        $Image = $Media->getImageManager()->make($original);

        if ($width || $height) {

            if (!$width) {
                $width = null;
            }

            if (!$height) {
                $height = null;
            }

            $Image->resize($width, $height, function ($Constraint) {
                $Constraint->aspectRatio();
                $Constraint->upsize();
            });
        }

        // effects
        $effects = $this->getEffects();

        if (isset($effects['blur'])
            && is_numeric($effects['blur'])
        ) {
            $blur = (int)$effects['blur'];

            if ($blur > 0 && $blur <= 100) {
                $Image->blur($blur);
            }
        }

        if (isset($effects['brightness'])
            && is_numeric($effects['brightness'])
        ) {
            $brightness = (int)$effects['brightness'];

            if ($brightness !== 0 && $brightness >= -100
                && $brightness <= 100
            ) {
                $Image->brightness($brightness);
            }
        }

        if (isset($effects['contrast'])
            && is_numeric($effects['contrast'])
        ) {
            $contrast = (int)$effects['contrast'];

            if ($contrast !== 0 && $contrast >= -100 && $contrast <= 100) {
                $Image->contrast($contrast);
            }
        }

        if (isset($effects['greyscale'])
            && $effects['greyscale'] == 1
        ) {
            $Image->greyscale();
        }

        // watermark
        $Watermark = $this->getWatermark();

        if ($Watermark) {

            $pos = $this->getWatermarkPosition();

            switch ($pos) {
                case "top-left":
                case "top":
                case "top-right":
                case "left":
                case "center":
                case "right":
                case "bottom-left":
                case "bottom":
                case "bottom-right":
                    $watermarkPosition = $pos;
                    break;

                default:
                    $watermarkPosition = 'bottom-right';
                    break;
            }

            $Image->insert($Watermark->getFullPath(), $watermarkPosition);
        }

        // create folders
        File::mkdir(dirname($cachefile));

        // save cache image
        $Image->save($cachefile);

        return $cachefile;
    }

    /**
     * (non-PHPdoc)
     *
     * @see QUI\Interfaces\Projects\Media\File::deleteCache()
     */
    public function deleteCache()
    {
        $Media = $this->_Media;
        $Project = $Media->getProject();

        $cdir = CMS_DIR.$Media->getCacheDir();
        $file = $this->getAttribute('file');

        $cachefile = $cdir.$file;
        $cacheData = pathinfo($cachefile);

        $fileData = QUIFile::getInfo($this->getFullPath());
        $files = QUIFile::readDir($cacheData['dirname'], true);
        $filename = $fileData['filename'];

        foreach ($files as $file) {
            $len = strlen($filename);

            if (substr($file, 0, $len + 2) == $filename.'__') {
                QUIFile::unlink($cacheData['dirname'].'/'.$file);
            }
        }

        QUIFile::unlink($cachefile);

        // delete admin cache
        $cache_folder
            = VAR_DIR.'media_cache/'.$Project->getAttribute('name').'/';

        if (!is_dir($cache_folder)) {
            return;
        }

        $list = QUI\Utils\System\File::readDir($cache_folder);
        $id = $this->getId();
        $cache = $id.'_';

        foreach ($list as $file) {
            if (strpos($file, $cache) !== false) {
                QUIFile::unlink($cache_folder.$file);
            }
        }
    }

    /**
     * Resize the image
     *
     * @param String  $new_image - Path to the new image
     * @param Integer $new_width
     * @param Integer $new_height
     *
     * @return String - Path to the new Image
     */
    public function resize($new_image, $new_width = 0, $new_height = 0)
    {
        $dir = CMS_DIR.$this->_Media->getPath();
        $original = $dir.$this->getAttribute('file');

        try {
            return QUIImage::resize(
                $original,
                $new_image,
                $new_width,
                $new_height
            );

        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $original;
    }

    /**
     * Return the Watermark image file
     *
     * @return Image|Boolean
     * @throws QUI\Exception
     */
    public function getWatermark()
    {
        // own watermark?
        $imageEffects = $this->getEffects();

        if (!$imageEffects
            || !isset($imageEffects['watermark'])
            || $imageEffects['watermark'] === ''
        ) {
            return false;
        }

        if ($imageEffects['watermark'] == 'default') {

            try {

                $Project = $this->getProject();

                return Utils::getImageByUrl($Project->getConfig('media_watermark'));

            } catch (QUI\Exception $Exception) {

            }

            return false;
        }


        try {
            return Utils::getImageByUrl($imageEffects['watermark']);

        } catch (QUI\Exception $Exception) {

        }


        return false;
    }

    /**
     * Return the Watermark image file
     *
     * @return Image|Boolean
     * @throws QUI\Exception
     */
    public function getWatermarkPosition()
    {
        $imageEffects = $this->getEffects();

        if ($imageEffects && isset($imageEffects['watermark_position'])) {
            return $imageEffects['watermark_position'];
        }

        // global watermark position?
        $Project = $this->getProject();

        if ($Project->getConfig('media_watermark_position')) {
            return $Project->getConfig('media_watermark_position');
        }

        return false;
    }

    /**
     * Hash methods
     */

    /**
     * Generate the MD5 file hash and set it to the Database and to the Object
     */
    public function generateMD5()
    {
        if (!file_exists($this->getFullPath())) {
            throw new QUI\Exception(
                QUI::getLocale()
                   ->get('quiqqer/system', 'exception.file.not.found', array(
                       'file' => $this->getAttribute('file')
                   )),
                404
            );
        }

        $md5 = md5_file($this->getFullPath());

        $this->setAttribute('md5hash', $md5);

        QUI::getDataBase()->update(
            $this->_Media->getTable(),
            array('md5hash' => $md5),
            array('id' => $this->getId())
        );
    }

    /**
     * Generate the SHA1 file hash and set it to the Database and to the Object
     */
    public function generateSHA1()
    {
        if (!file_exists($this->getFullPath())) {
            throw new QUI\Exception(
                QUI::getLocale()
                   ->get('quiqqer/system', 'exception.file.not.found', array(
                       'file' => $this->getAttribute('file')
                   )),
                404
            );
        }

        $sha1 = sha1_file($this->getFullPath());

        $this->setAttribute('sha1hash', $sha1);

        QUI::getDataBase()->update(
            $this->_Media->getTable(),
            array('sha1hash' => $sha1),
            array('id' => $this->getId())
        );
    }
}
