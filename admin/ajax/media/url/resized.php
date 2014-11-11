<?php

use \QUI\Projects\Media\Utils as Utils;

/**
 * Return the rewrited url from an image.php url
 *
 * @param String $image_url
 * @return String
 */
function ajax_media_url_resized($fileurl, $maxWidth, $maxHeight)
{
    if ( Utils::isMediaUrl( $fileurl ) === false ) {
        return $fileurl;
    }

    try
    {
        $File = Utils::getImageByUrl( $fileurl );

        if ( !Utils::isImage( $File ) )
        {
            if ( Utils::isFolder( $File )  ) {
                return Utils::getIconByExtension( 'folder' );
            }

            return Utils::getIconByExtension(
                Utils::getExtension( $File->getFullPath() )
            );
        }

        /* @var $File \QUI\Projects\Media\Image */
        return $File->getSizeCacheUrl( $maxWidth, $maxHeight );

    } catch ( \QUI\Exception $Exception )
    {

    }

    return $fileurl;
}

\QUI::$Ajax->register(
    'ajax_media_url_resized',
    array( 'fileurl', 'maxWidth', 'maxHeight' ),
    'Permission::checkAdminUser'
);