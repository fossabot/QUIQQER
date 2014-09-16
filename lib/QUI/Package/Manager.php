<?php

/**
 * This file contains \QUI\Package\Manager
 */

namespace QUI\Package;

// Use the Composer classes
if ( !defined('STDIN') ) {
    define( 'STDIN', fopen("php://stdin","r") );
}

if ( !defined( 'JSON_UNESCAPED_SLASHES' ) ) {
    define( 'JSON_UNESCAPED_SLASHES', 64 );
}

if ( !defined( 'JSON_PRETTY_PRINT' ) ) {
    define( 'JSON_PRETTY_PRINT', 128 );
}

if ( !defined( 'JSON_UNESCAPED_UNICODE' ) ) {
    define( 'JSON_UNESCAPED_UNICODE', 256 );
}


use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

use \QUI\Utils\XML as XML;

/**
 * Package Manager for the QUIQQER System
 *
 * @author www.pcsg.de (Henning Leutz)
 * @event onOutput [ String $message ]
 */

class Manager
{
    const CACHE_NAME_TYPES = 'qui/packages/types';

    /**
     * Package Directory
     * @var String
     */
    protected $_dir;

    /**
     * VAR Directory for composer
     * eq: here are the cache and the quiqqer composer.json file
     *
     * @var String
     */
    protected $_vardir;

    /**
     * Packaglist - installed packages
     * @var Array
     */
    protected $_composer_json;

    /**
     * exec command to the composer.phar file
     * @var String
     */
    protected $_composer_exec;

    /**
     * Packaglist - installed packages
     * @var Array
     */
    protected $_list = false;

    /**
     * Can composer execute via bash? shell?
     * @var Bool
     */
    protected $_exec = false;

    /**
     * temporary require packages
     * @var Array
     */
    protected $_require = array();

    /**
     * Composer Application
     * @var Application
     */
    protected $_Application;

    /**
     * internal event manager
     * @var \QUI\Events\Manager
     */
    public $Events;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->_dir    = OPT_DIR; // CMS_DIR .'packages/';
        $this->_vardir = VAR_DIR .'composer/';

        $this->_composer_json = $this->_vardir .'composer.json';

        $this->Events = new \QUI\Events\Manager();
    }

    /**
     * Return the Composer Application
     * @return \Composer\Console\Application
     */
    protected function _getApplication()
    {
        if ( $this->_Application ) {
            return $this->_Application;
        }

        // Create the application and run it with the commands
        $this->_Application = new Application();
        $this->_Application->setAutoExit( false );

        \QUI\Utils\System\File::mkdir( $this->_vardir );

        putenv( "COMPOSER_HOME=". $this->_vardir );

        return $this->_Application;
    }

    /**
     * Return the version from the composer json
     *
     * @return String
     */
    public function getVersion()
    {
        if ( !file_exists( $this->_composer_json ) ) {
            return '';
        }

        $data = file_get_contents( $this->_composer_json );
        $data = json_decode( $data, true );

        return $data['version'];
    }

    /**
     * Checks if the composer.json exists
     * if not, the system will try to create the composer.json (with all installed packages)
     */
    protected function _checkComposer()
    {
        if ( file_exists( $this->_composer_json ) ) {
            return;
        }

        $this->_createComposerJSON();
    }

    /**
     * Create the composer.json file for the system
     */
    protected function _createComposerJSON()
    {
        $template = file_get_contents(
            dirname( __FILE__ ) .'/composer.tpl'
        );

        // make the repository list
        $servers      = $this->getServerList();
        $repositories = array();

        foreach ( $servers as $server => $params )
        {
            if ( $server == 'packagist' ) {
                continue;
            }

            if ( !isset($params['active']) || $params['active'] != 1 ) {
                continue;
            }

            $repositories[] = array(
                'type' => $params['type'],
                'url'  => $server
            );
        }

        if ( isset( $servers['packagist'] ) &&
             $servers['packagist']['active'] == 0 )
        {
            $repositories[] = array(
                'packagist' => false
            );
        }


        $template = str_replace( '{$PACKAGE_DIR}', OPT_DIR, $template );
        $template = str_replace( '{$VAR_COMPOSER_DIR}', $this->_vardir, $template );
        $template = str_replace( '{$LIB_DIR}', LIB_DIR, $template );

        $template = str_replace(
            '{$repositories}',
            json_encode( $repositories, \JSON_PRETTY_PRINT ),
            $template
        );

        // standard require
        $list = $this->_getList();

        $quiqqerVersion = '1.*';

        if ( \QUI::conf( 'globals', 'quiqqer_version' ) ) {
            $quiqqerVersion = \QUI::conf( 'globals', 'quiqqer_version' );
        }

        // must have
        $require = array();
        $require["php"]                     = ">=5.3.2";
        $require["quiqqer/quiqqer"]         = $quiqqerVersion;
        $require["tedivm/stash"]            = "0.11.*";
        $require["symfony/http-foundation"] = "*";
        $require["composer/composer"]       = "1.0.*@dev";
        $require["robloach/component-installer"] = "*";

        foreach ( $list as $package ) {
            $require[ $package['name'] ] = $package['version'];
        }

        ksort( $require );

        $template = str_replace(
            '{$REQUIRE}',
            json_encode( $require, \JSON_PRETTY_PRINT ),
            $template
        );

        $template = json_encode( json_decode( $template, true ), \JSON_PRETTY_PRINT );

        if ( file_exists( $this->_composer_json ) ) {
            unlink( $this->_composer_json );
        }

        file_put_contents( $this->_composer_json, $template );
    }

    /**
     * Package Methods
     */

    /**
     * Return the composer array
     *
     * @return Array
     */
    protected function _getComposerJSON()
    {
        $this->_checkComposer();

        $json   = file_get_contents( $this->_composer_json );
        $result = json_decode( $json, true );

        return $result;
    }

    /**
     * internal get list method
     * return all installed packages and create the internal package list cache
     *
     * @return Array
     */
    protected function _getList()
    {
        if ( $this->_list ) {
            return $this->_list;
        }

        try
        {
            $this->_list = \QUI\Cache\Manager::get( self::CACHE_NAME_TYPES );

            return $this->_list;

        } catch ( \QUI\Exception $Exception )
        {

        }

        $installed_file = $this->_dir .'composer/installed.json';

        if ( !file_exists( $installed_file ) ) {
            return array();
        }

        $data = file_get_contents( $installed_file );
        $list = json_decode( $data, true );

        $result = array();

        if ( is_array( $list ) )
        {
            foreach ( $list  as $entry )
            {
                if ( !isset( $entry['type'] ) )
                {
                    $result[] = $entry;
                    continue;
                }

                if ( $entry['type'] != 'quiqqer-library' )
                {
                    $result[] = $entry;
                    continue;
                }

                $path = OPT_DIR . $entry['name'] .'/';

                if ( file_exists( $path .'settings.xml' ) ) {
                    $entry['_settings'] = 1;
                }

                if ( file_exists( $path .'permissions.xml' ) ) {
                    $entry['_permissions'] = 1;
                }

                if ( file_exists( $path .'database.xml' ) ) {
                    $entry['_database'] = 1;
                }

                $result[] = $entry;
            }

            $this->_list = $result;
        }

        \QUI\Cache\Manager::set( self::CACHE_NAME_TYPES , $this->_list );

        return $this->_list;
    }

    /**
     * Refreshed the installed package list
     * If some packages are uploaded, sometimes the package versions and data are not correct
     *
     * this method correct it
     */
    protected function _refreshInstalledList()
    {
        $installed_file = $this->_dir .'composer/installed.json';

        if ( !file_exists( $installed_file ) ) {
            return;
        }


        $data = file_get_contents( $installed_file );
        $list = json_decode( $data, true );

        foreach ( $list as $key => $entry )
        {
            $cf = $this->_dir . $entry['name'] .'/composer.json';

            if ( !file_exists( $cf ) ) {
                continue;
            }

            $data = json_decode( file_get_contents( $cf ), true );

            if ( !is_array( $data ) ) {
                continue;
            }

            if ( !isset( $data['version'] ) ) {
                continue;
            }

            /*
            $list[ $key ]['version'] = $data['version'];

            // is that right?
            $list[ $key ]["version_normalized"] = str_replace(
                array('x', '*'),
                9999999,
                $data['version']
            );
            */
        }

        $this->_list = array();

        if ( is_array( $list ) ) {
            $this->_list = $list;
        }
    }

    /**
     * Return the installed packages
     *
     * @param {Array} $params - [optional] search / limit params
     * @return Array
     */
    public function getInstalled($params=array())
    {
        $list   = $this->_getList();
        $result = $list;

        if ( isset( $params['type'] ) )
        {
            $result = array();

            foreach ( $list as $package )
            {
                if ( !isset( $package['type'] ) ) {
                    continue;
                }

                if ( !empty( $params['type'] ) &&
                     $params['type'] != $package['type'] )
                {
                    continue;
                }

                $result[] = $package;
            }
        }

        if ( isset( $params['limit'] ) && isset( $params['page'] ) )
        {
            $limit = (int)$params['limit'];
            $page  = (int)$params['page'];

            return \QUI\Utils\Grid::getResult( $result, $page, $limit );
        }

        return $result;
    }

    /**
     * Install Package
     *
     * @param String $package - name of the package
     * @param String $version - [optional] version of the package default = dev-master
     */
    public function install($package, $version=false)
    {
        $this->_checkComposer();

        if ( !$version ) {
            $version = 'dev-master';
        }

        $result = $this->_execComposer('require', array(
            'packages' => array(
                $package .':'. $version
            )
        ));

        \QUI\System\Log::writeRecursive( $result );
    }

    /**
     * Add a Package to the composer json
     *
     * @param String|Array $package - name of the package
     * @param String $version - [optional] version of the package default = dev-master
     */
    public function setPackage($package, $version=false)
    {
        if ( !$version ) {
            $version = 'dev-master';
        }

        $json    = $this->_getComposerJSON();
        $quiqqer = false;

        if ( is_array( $package ) )
        {
            foreach ( $package as $pkg )
            {
                $json['require'][ $pkg ] = $version;

                if ( $pkg == 'quiqer/quiqqer' ) {
                    $quiqqer = true;
                }
            }

        } else
        {
            $json['require'][ $package ] = $version;

            if ( $package == 'quiqer/quiqqer' ) {
                $quiqqer = true;
            }
        }


        // minimum-stability
        if ( $quiqqer && $version == 'dev-dev' )
        {
            $json['minimum-stability'] = 'dev';

        } else if ( $quiqqer )
        {
            $json['minimum-stability'] = 'stable';
        }


        $json = json_encode( $json, \JSON_PRETTY_PRINT );

        if ( file_exists( $this->_composer_json ) ) {
            unlink( $this->_composer_json );
        }

        file_put_contents( $this->_composer_json, $json );
    }

    /**
     * Return the params of an installed package
     *
     * @param String $package
     * @return Array
     */
    public function getPackage($package)
    {
        $cache = 'packages/cache/info/'. $package;

        try
        {
            return \QUI\Cache\Manager::get( $cache );

        } catch ( \QUI\Exception $Exception )
        {
            \QUI\System\Log::addDebug( $Exception->getMessage() );
        }

        $list   = $this->_getList();
        $result = array();

        foreach ( $list as $pkg )
        {
            if ( !isset( $pkg['name'] ) ) {
                continue;
            }

            if ( $pkg['name'] == $package )
            {
                $pkg['dependencies'] = $this->getDependencies( $package );
                $result = $pkg;
                break;
            }
        }

        $showData = $this->show( $package );

        if ( isset( $showData['versions'] ) ) {
            $result['versions'] = $showData['versions'];
        }

        if ( isset( $showData['require'] ) ) {
            $result['require'] = $showData['require'];
        }

        \QUI\Cache\Manager::set( $cache, $result, 3600 );

        return $result;
    }

    /**
     * Return the dependencies of a package
     *
     * @param String $package - package name
     */
    public function getDependencies($package)
    {
        $list   = $this->_getList();
        $result = array();

        foreach ( $list as $pkg )
        {
            if ( !isset( $pkg['require'] ) ||
                 empty( $pkg['require'] ) )
            {
                continue;
            }

            if ( isset( $pkg['require'][ $package ] ) ) {
                $result[] = $pkg['name'];
            }
        }

        return $result;
    }

    /**
     * Return package details
     *
     * @param String $package
     * @return Array
     */
    public function show($package)
    {
        $cache = 'packages/cache/show/'. $package;

        try
        {
            return \QUI\Cache\Manager::get( $cache );

        } catch ( \QUI\Exception $Exception )
        {
            \QUI\System\Log::addDebug( $Exception->getMessage() );
        }

        $this->_checkComposer();

        $result = array();

        $show = $this->_execComposer('show', array(
            'package' => $package
        ), true);

        foreach ( $show as $k => $line )
        {
            if ( strpos( $line , '<info>') === false ) {
                continue;
            }

            if ( strpos( $line , ':') === false ) {
                continue;
            }

            $line   = explode( ':', $line );
            $key    = trim( strip_tags( $line[ 0 ] ) );
            $value  = trim( strip_tags( $line[ 1 ] ) );

            if ( $key == 'versions' ) {
                $value = array_map( 'trim',  explode( ',', $value ) );
            }

            if ( $key == 'descrip.' ) {
                $key = 'description';
            }

            if ( $line == 'requires' )
            {
                $_temp = $show;
                $result[ 'require' ] = array_slice( $_temp, $k + 1 );

                continue;
            }

            $result[ $key ] = $value;
        }

        \QUI\Cache\Manager::set( $cache, $result, 3600 );

        return $result;
    }

    /**
     * Search a string in the repository
     *
     * @param String $str - search string
     * @return Array
     */
    public function searchPackage($str)
    {
        $result = array();
        $str    = \QUI\Utils\Security\Orthos::clearShell( $str );
        $list   = $this->_getList();

        $list = $this->_execComposer('search', array(
            'tokens' => array( $str )
        ));

        foreach ( $list as $entry )
        {
            $expl = explode( ' ', $entry, 2 );

            if ( isset( $expl[0] ) && isset( $expl[1] ) ) {
                $result[ $expl[0] ] = $expl[1];
            }
        }

        return $result;
    }

    /**
     * Execute a setup for a package
     *
     * @param String $package
     */
    public function setup($package)
    {
        $dir = OPT_DIR . $package .'/';

        if ( !is_dir( $dir ) ) {
            return;
        }

        \QUI\Update::importDatabase( $dir .'database.xml' );
        \QUI\Update::importTemplateEngines( $dir .'engines.xml' );
        \QUI\Update::importEditors( $dir .'wysiwyg.xml' );
        \QUI\Update::importMenu( $dir .'menu.xml' );
        \QUI\Update::importPermissions( $dir .'permissions.xml', $package );
        \QUI\Update::importEvents( $dir .'events.xml' );
        \QUI\Update::importMenu( $dir .'menu.xml' );

        // settings
        if ( !file_exists( $dir .'settings.xml' ) ) {
            return;
        }

        $defaults = XML::getConfigParamsFromXml( $dir .'settings.xml' );
        $Config   = XML::getConfigFromXml( $dir .'settings.xml' );

        if ( $Config ) {
            $Config->save();
        }
    }

    /**
     * Update Server Methods
     */

    /**
     * Refresh the server list in the var dir
     */
    public function refreshServerList()
    {
        $this->checkUpdates();

        $json = $this->_getComposerJSON();


        // make the repository list
        $servers      = $this->getServerList();
        $repositories = array();

        foreach ( $servers as $server => $params )
        {
            if ( $server == 'packagist' ) {
                continue;
            }

            if ( !isset($params['active']) || $params['active'] != 1 ) {
                continue;
            }

            $repositories[] = array(
                'type' => $params['type'],
                'url'  => $server
            );
        }

        if ( isset( $servers['packagist'] ) &&
             $servers['packagist']['active'] == 0 )
        {
            $repositories[] = array(
                'packagist' => false
            );
        }


        $json['repositories'] = $repositories;
        $json = json_encode( $json, \JSON_PRETTY_PRINT );

        if ( file_exists( $this->_composer_json ) ) {
            unlink( $this->_composer_json );
        }

        file_put_contents( $this->_composer_json, $json );
    }

    /**
     * Return the server list
     *
     * @return Array
     */
    public function getServerList()
    {
        try
        {
            return \QUI::getConfig( 'etc/source.list.ini.php' )->toArray();

        } catch ( \QUI\Exception $Exception )
        {

        }

        return array();
    }

    /**
     * Activate or Deactivate a server
     *
     * @param String $server - Server, IP, Host
     * @param Bool $status - 1 = active, 0 = disabled
     */
    public function setServerStatus($server, $status)
    {
        $Config  = \QUI::getConfig( 'etc/source.list.ini.php' );
        $status = (bool)$status ? 1 : 0;

        $Config->setValue( $server, 'active', $status );
        $Config->save();

        $this->refreshServerList();
    }

    /**
     * Add a server to the update-server list
     *
     * @param String $server - Server, IP, Host
     * @param Array $params - Server Parameter
     */
    public function addServer($server, $params=array())
    {
        if ( empty( $server ) ) {
            return;
        }

        if ( !is_array( $params ) ) {
            return;
        }


        $Config = \QUI::getConfig( 'etc/source.list.ini.php' );
        $Config->setValue( $server, 'active', 0 );

        if ( isset( $params['type'] ) ) {
            $Config->setValue( $server, 'type', $params['type'] );
        }

        $Config->save();

        $this->refreshServerList();
    }

    /**
     * Remove a Server completly from the update-server list
     *
     * @param String|Array $server
     */
    public function removeServer($server)
    {
        $Config = \QUI::getConfig( 'etc/source.list.ini.php' );

        if ( is_array( $server ) )
        {
            foreach ( $server as $entry ) {
                $Config->del( $entry );
            }
        } else
        {
            $Config->del( $server );
        }

        $Config->save();

        $this->refreshServerList();
    }

/**
 * Update methods
 */

    /**
     * Check for updates
     * @throws \\QUI\Exception
     */
    public function checkUpdates()
    {
        $this->_checkComposer();

        $packages = array();

        $result = $this->_execComposer( 'update', array(
            '--dry-run' => true
        ));


        \QUI\System\Log::addDebug( print_r($result, true) );

        foreach ( $result as $line )
        {
            if ( strpos( $line, '-' ) === false ||
                 strpos( $line, '/' ) === false ||
                 strpos( $line, '(' ) === false )
            {
                continue;
            }

            if ( strpos($line, 'Installing') !== false )
            {
                preg_match( '#Installing ([^ ]*) #i', $line, $package );

            } else
            {
                preg_match( '#Updating ([^ ]*) #i', $line, $package );
            }

            preg_match_all( '#\(([^\)]*)\)#', $line, $versions );

            if ( isset( $package[1] ) ) {
                $package = $package[1];
            }

            $from = '';
            $to   = '';

            if ( isset( $versions[ 1 ] ) )
            {
                if ( isset( $versions[ 1 ][ 0 ] ) )
                {
                    $from = $versions[ 1 ][ 0 ];
                    $to   = $versions[ 1 ][ 0 ]; // if "to" isn't set
                }

                if ( isset( $versions[ 1 ][ 1 ] ) ) {
                    $to = $versions[ 1 ][ 1 ];
                }
            }

            $packages[] = array(
                'package' => $package,
                'from'    => $from,
                'to'      => $to
            );
        }

        return $packages;
    }

    /**
     * Update a package or the entire system
     *
     * @param String|false $package - optional, package name, if false, it updates the complete system
     *
     * @throws \QUI\Exception
     *
     * @todo if exception uncommited changes -> own error message
     * @todo if exception uncommited changes -> interactive mode
     */
    public function update($package=false)
    {
        if ( $package )
        {
            $output = $this->_execComposer('update', array(
                'packages' => array($package)
            ));

        } else
        {
            $output = $this->_execComposer('update');
        }

        // exception?
        foreach ( $output as $key => $msg )
        {
            // if not installed
            if ( strpos( $msg, $package ) !== false &&
                 strpos( $msg, 'not installed' ) !== false )
            {
                $this->install( $package );
            }

            if ( strpos( $msg, 'Exception' ) )
            {
                throw new \QUI\Exception(
                    $output[ $key + 1 ]
                );
            }
        }

        \QUI\System\Log::addInfo( implode("\n", $output) );
    }

    /**
     * Update a package or the entire system from a package archive
     *
     * @param String $packagepath - path to the ZIP archive
     * @throws \\QUI\Exception
     */
    public function updatePackage($packagepath)
    {
        if ( !file_exists( $packagepath ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.packages.update.archive.not.found'
                )
            );
        }

        // extract the archive
        $folder = \QUI::getTemp()->createFolder();

        \QUI\Archiver\Zip::unzip( $packagepath, $folder );

        // read composer file
        $composer     = $folder .'composer.json';
        $repositories = VAR_DIR .'repository/bin/';

        if ( !file_exists( $composer ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.no.quiqqer.update.archive'
                )
            );
        }

        $package     = json_decode( file_get_contents( $composer ), true );
        $package_dir = $repositories . $package['name'] .'/';
        $update_file = $package_dir . $package['version'] .'.zip';

        if ( file_exists( $update_file ) ) {
            unlink( $update_file );
        }

        \QUI\Utils\System\File::mkdir( $package_dir );
        \QUI\Utils\System\File::move( $packagepath, $update_file );


        // create packages.json
        $server_json     = $repositories .'packages.json';
        $server_packages = '';

        if ( file_exists( $server_json ) ) {
            $server_packages = json_decode( $server_json, true );
        }

        if ( !is_array( $server_packages ) ||
             !isset( $server_packages[ 'packages' ] ) )
        {
            $server_packages = array(
                'packages' => array()
            );
        }

        $version = $package['version'];

        $server_packages[ 'packages' ] = array(
            $package['name'] => array(
                $version => array(
                    "name" => $package[ 'name' ],
                    "version" => $version,
                    "dist" => array(
                        "url"  => HOST .'/'. str_replace( VAR_DIR, URL_VAR_DIR, $update_file ),
                        "type" => "zip"
                    ),

                    "require"     => $package['require'],
                    "type"        => $package['type'],
                    "description" => $package['description']
                )
            )
        );

        file_put_contents( $server_json, json_encode( $server_packages ) );

        // create composer json file for working dir
        $template = file_get_contents(
            dirname( __FILE__ ) .'/composer.tpl'
        );

        // make the repository list
        $list = array(
            'packagist' => false,
            array(
                "type" => "composer",
                "url"  => HOST .'/'. str_replace( VAR_DIR, URL_VAR_DIR, $repositories )
            )
        );

        $template = str_replace(
            '{$repositories}',
            json_encode( $list ),
            $template
        );

        $template = str_replace(
            '{$PACKAGE_DIR}',
            OPT_DIR,
            $template
        );

        $template = str_replace(
            '{$VAR_COMPOSER_DIR}',
            $this->_vardir,
            $template
        );

        $template = str_replace(
            '{$LIB_DIR}',
            LIB_DIR,
            $template
        );


        if ( file_exists( $repositories .'composer.json' ) ) {
            unlink( $repositories .'composer.json' );
        }

        file_put_contents( $repositories .'composer.json', $template );

        // make an update from the repository archive source
        $result = $this->_execComposer('', array(
            '--working-dir' => $repositories
        ));

        if ( !count( $result ) )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.packages.exec.not.found.composer'
                )
            );
        }

        $result = $this->_execComposer('update', array(
            '--dry-run' => true
        ));

        $last = end( $result );

        if ( $last == 'Nothing to install or update' )
        {
            throw new \QUI\Exception(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.packages.update.version.not.found'
                )
            );
        }

        $result = $this->_execComposer('update');
    }

    /**
     * Execute a composer command
     *
     * @param String $command
     * @param Array $params
     */
    protected function _execComposer($command, $params=array(), $showInfo=false)
    {
        // composer output some warnings that composer/cache is not empty
        try
        {
            \QUI::getTemp()->moveToTemp( $this->_vardir .'cache' );

        } catch ( \QUI\Exception $Exception )
        {
            \QUI\System\Log::addInfo( $Exception->getMessage() );
        }

        if ( !isset( $params['--working-dir'] ) ) {
            $params['--working-dir'] = $this->_vardir;
        }

        $params = array_merge(array(
            'command' => $command
        ), $params);

        $Input  = new ArrayInput( $params );
        $Output = new \QUI\Package\Output();

        // output events
        $PackageManager = $this;

        $Output->Events->addEvent('onOutput', function($message) use ($PackageManager) {
            $PackageManager->Events->fireEvent( 'output', array( $message ) );
        });

        \QUI\System\Log::addDebug( print_r($params, true) );

        // run application
        $this->_getApplication()->run( $Input, $Output );
        \QUI\Cache\Manager::clear( self::CACHE_NAME_TYPES );

        $messages = $Output->getMessages();
        $result   = array();

        foreach ( $messages as $entry )
        {
            if ( empty( $entry ) ) {
                continue;
            }

            if ( strpos( $entry, '<error>' ) !== false )
            {
                preg_match( "#<error>(.*?)</error>#si", $entry, $match );

                \QUI::getMessagesHandler()->addError( $match[ 0 ] );
                continue;
            }

            if ( $showInfo === false && strpos( $entry, '<info>' ) !== false ) {
                continue;
            }


            $result[] = $entry;
        }

        \QUI\System\Log::addDebug( print_r($result, true) );

        return $result;


//         $exec_var = str_replace( CMS_DIR, '', $this->_vardir );

//         $this->_composer_exec  = 'cd '. CMS_DIR .';';
//         $this->_composer_exec .= ' php '. $exec_var .'composer.phar';
    }


    /**
     * XML helper
     */

    /**
     * Return all packages which includes a site.xml
     *
     * @return array
     */
    public function getPackageSiteXmlList()
    {
        try
        {
            return \QUI\Cache\Manager::get( 'qui/packages/list/haveSiteXml' );

        } catch ( \QUI\Exception $Exception )
        {

        }

        $packages = $this->getInstalled();
        $result   = array();

        foreach ( $packages as $package )
        {
            if ( !is_dir( OPT_DIR . $package[ 'name' ] ) ) {
                continue;
            }

            $file = OPT_DIR . $package[ 'name' ] .'/site.xml';

            if ( !file_exists( $file ) ) {
                continue;
            }

            $result[] = $package[ 'name' ];
        }

        \QUI\Cache\Manager::set( 'qui/packages/list/haveSiteXml', $result );

        return $result;
    }

    /**
     * Return all packages which includes a site.xml
     *
     * @return array
     */
    public function getPackageDatabaseXmlList()
    {
        try
        {
            return \QUI\Cache\Manager::get( 'qui/packages/list/haveDatabaseXml' );

        } catch ( \QUI\Exception $Exception )
        {

        }

        $packages = $this->getInstalled();
        $result   = array();

        foreach ( $packages as $package )
        {
            $file = OPT_DIR . $package[ 'name' ] .'/database.xml';

            if ( !file_exists( $file ) ) {
                continue;
            }

            $result[] = $package[ 'name' ];
        }

        \QUI\Cache\Manager::set( 'qui/packages/list/haveDatabaseXml', $result );

        return $result;
    }
}
