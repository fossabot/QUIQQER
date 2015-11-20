<?php

/**
 * This file contains QUI\Users\Auth
 */
namespace QUI\Users;

use QUI;

/**
 * Class Auth
 * Standard QUIQQER authentification
 *
 * @package QUI\Users
 */
class Auth implements QUI\Interfaces\Users\Auth
{
    /**
     * User object
     * @var false|QUI\Users\User
     */
    protected $_User;

    /**
     * Name of the user
     * @var string
     */
    protected $_username;

    /**
     * @param string $username
     */
    public function __construct($username = '')
    {
        $this->_username = $username;
    }

    /**
     * Authenticate the user
     *
     * @param string $password
     * @return boolean
     */
    public function auth($password = '')
    {
        $userData = QUI::getDataBase()->fetch(array(
            'select' => array('password'),
            'from'   => QUI::getUsers()->Table(),
            'where'  => array(
                'id' => $this->getUserId()
            ),
            'limit'  => 1
        ));

        if (empty($userData)
            || !isset($userData[0]['password'])
            || empty($userData[0]['password'])
        ) {
            return false;
        }

        // retrieve salt from saved password
        $savedPassword = $userData[0]['password'];
        $salt          = mb_substr($savedPassword, 0, SALT_LENGTH);

        // generate password with given password and salt
        $password = Manager::genHash($password, $salt);

        return $savedPassword === $password;
    }

    /**
     * Return the quiqqer user id
     *
     * @return integer|boolean
     */
    public function getUserId()
    {
        if ($this->_User) {
            return $this->_User->getId();
        }

        $username = $this->_username;
        $User     = false;

        /**
         * Standard Authentifizierung
         */
        if (QUI::conf('globals', 'emaillogin')
            && strpos($username, '@') !== false
        ) {
            try {
                $User = QUI::getUsers()->getUserByMail($username);
            } catch (QUI\Exception $Exception) {

            }
        }

        if ($User === false) {
            try {
                $User = QUI::getUsers()->getUserByName($username);
            } catch (QUI\Exception $Exception) {
                return false;
            }
        }

        $this->_User = $User;

        return $User->getId();
    }
}