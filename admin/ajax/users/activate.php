<?php

/**
 * Benutzer aktivieren
 *
 * @param {JSON Integer|JSON Array} $uid
 * @return Array
 */
function ajax_users_activate($uid)
{
    $uid = json_decode( $uid, true );

    if ( !is_array($uid) ) {
        $uid = array( $uid );
    }

    $Users  = \QUI::getUsers();
    $result = array();

    foreach ( $uid as $_uid )
    {
        try
        {
            $User = $Users->get( $_uid );
            $User->activate();

            $result[ $_uid ] = $User->isActive() ? 1 : 0;

        } catch ( \QUI\Exception $Exception )
        {
            \QUI::getMessagesHandler()->addException( $Exception );
            continue;
        }
    }

    return $result;
}
QUI::$Ajax->register('ajax_users_activate', array('uid'), 'Permission::checkSU')

?>