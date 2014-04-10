<?php

/**
 * This file contains \QUI\System\VhostManager
 */

namespace QUI\System;

/**
 * Virtual Host Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @todo vhosts permissions
 */

class VhostManager
{
    /**
     * Config
     * @var \QUI\Config
     */
    protected $_Config = null;

    /**
     * Return the config
     * @return \QUI\Config
     */
    protected function _getConfig()
    {
        if ( !file_exists( CMS_DIR .'/etc/vhosts.ini' ) ) {
            file_put_contents( CMS_DIR .'/etc/vhosts.ini' , '' );
        }

        $this->_Config = new \QUI\Config( CMS_DIR .'/etc/vhosts.ini' );

        return $this->_Config;
    }

    /**
     * Return the vhost list
     *
     * @return array
     */
    public function getList()
    {
        return $this->_getConfig()->toArray();
    }

    /**
     * Add a vhost
     *
     * @param String $vhost - host name (eq: www.something.com)
     * @throws \QUI\Exception
     */
    public function addVhost($vhost)
    {
        $Config = $this->_getConfig();

        if ( $Config->existValue( $vhost ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'system',
                    'exception.vhost.exist'
                )
            );
        }

        $Config->setSection( $vhost, array() );
        $Config->save();
    }

    /**
     * Add or edit a vhost entry
     *
     * @param String $vhost - host name (eq: www.something.com)
     * @param Array $data - data of the host
     * @throws \QUI\Exception
     */
    public function editVhost($vhost, array $data)
    {
        $Config = $this->_getConfig();

        if ( !$Config->existValue( $vhost ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'system',
                    'exception.vhost.not.found'
                )
            );
        }

        // daten prüfen
        $data = \QUI\Utils\Security\Orthos::clearArray( $data );

        $Config->setSection( $vhost, $data );
        $Config->save();
    }

    /**
     * Remove a vhost entry
     *
     * @param String $vhost
     * @throws \QUI\Exception
     */
    public function removeVhost($vhost)
    {
        $Config = $this->_getConfig();

        if ( !$Config->existValue( $vhost ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'system',
                    'exception.vhost.not.found'
                )
            );
        }

        $Config->del( $vhost );
        $Config->save();
    }

    /**
     * Return the vhost data
     *
     * @param String $vhost
     * @return Array|false
     */
    public function getVhost($vhost)
    {
        return $this->_getConfig()->getSection( $vhost );
    }
}