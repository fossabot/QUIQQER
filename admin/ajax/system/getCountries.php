<?php

/**
 * Get the available versions of quiqqer
 *
 * @return String
 */
QUI::$Ajax->registerFunction(
    'ajax_system_getCountries',
    function () {
        return QUI\Countries\Manager::getAllCountryCodes();
    },
    false,
    'Permission::checkUser'
);
