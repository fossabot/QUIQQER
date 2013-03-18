<?php

/**
 * Return a template
 *
 * @param String $template
 * @return String
 */

function ajax_template_get($template)
{
    $Engine   = QUI_Template::getEngine( true );
    $dir      = SYS_DIR .'template/';
    $template = $dir . str_replace( '_', '/', $template ) .'.html';

    if ( !file_exists( $template ) )
    {
        throw new QException(
            \QUI::getLocale()->get(
                'quiqqer/system',
                'exception.template.not.found'
            )
        );
    }

    return $Engine->fetch( $template );
}

QUI::$Ajax->register(
    'ajax_template_get',
    array( 'template' ),
    'Permission::checkAdminUser'
);

?>