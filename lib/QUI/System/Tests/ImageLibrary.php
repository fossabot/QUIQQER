<?php

/**
 * This class contains \QUI\System\Tests\ImageLibrary
 */

namespace QUI\System\Tests;

use QUI;

/**
 * JSON Test
 *
 * @package quiqqer/quiqqer
 * @author www.pcsg.de (Henning Leutz)
 */
class ImageLibrary extends QUI\System\Test
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setAttributes(array(
            'title'       => 'Image Libraries',
            'description' => ''
        ));

        $this->_isRequired = self::TEST_IS_REQUIRED;
    }

    /**
     * Check, if json_encode and json_decode available
     *
     * @return self::STATUS_OK|self::STATUS_ERROR
     */
    public function execute()
    {
        $libraries = array();

        // ImageMagick PHP
        if ( class_exists('Imagick') ) {
            $libraries[] = 'PHP Image Magick';
        }

        // ImageMagick System
        exec( escapeshellcmd('convert'), $im_console );

        // ImageMagick Konsole
        if ( isset( $im_console ) && is_array( $im_console ) && count( $im_console ) ) {
            $libraries[] = 'System Image Magick';
        }

        // GD Lib
        if ( function_exists( 'imagecopyresampled' ) ) {
            $libraries[] = 'GD Lib';
        }

        if ( empty( $libraries ) ) {
            return self::STATUS_ERROR;
        }

        $this->setAttribute(
            'title',
            'Image Libraries ('. implode( ', ', $libraries ) .')'
        );

        return self::STATUS_OK;
    }
}