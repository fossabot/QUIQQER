<?php

/**
 * Verfügbare Templates bekommen
 *
 * @return Array
 */
function ajax_template_getlist()
{
    return \QUI::getPackageManager()->getInstalled(array(
        'type' => "quiqqer-template"
    ));
}

\QUI::$Ajax->register(
    'ajax_template_getlist',
    false,
    'Permission::checkAdminUser'
);

