<?php

/**
 * Erste Seite vom Projekt bekommen
 *
 * @return Array
 */
function ajax_login_login($username, $password)
{
    \QUI::getUsers()->login($username, $password);

    return \QUI::getUserBySession()->getAttributes();
}

\QUI::$Ajax->register(
    'ajax_login_login',
    array('username', 'password')
);
