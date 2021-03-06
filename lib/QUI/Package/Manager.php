<?php

/**
 * This file contains \QUI\Package\Manager
 */

namespace QUI\Package;

if (!defined('JSON_UNESCAPED_SLASHES')) {
    define('JSON_UNESCAPED_SLASHES', 64);
}

if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', 128);
}

if (!defined('JSON_UNESCAPED_UNICODE')) {
    define('JSON_UNESCAPED_UNICODE', 256);
}

use QUI;
use QUI\Utils\System\File as QUIFile;

/**
 * Package Manager for the QUIQQER System
 *
 * Sorry, the package manager is little bit complicated
 * when the time is right, i think i must make it clearer
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @licence For copyright and license information, please view the /README.md
 * @event   onOutput [ string $message ]
 *
 * @todo php composer.phar config github-oauth.github.com KEY
 */
class Manager extends QUI\QDOM
{
    const CACHE_NAME_TYPES = 'qui/packages/types';

    /**
     * Package Directory
     *
     * @var string
     */
    protected $dir;

    /**
     * VAR Directory for composer
     * eq: here are the cache and the quiqqer composer.json file
     *
     * @var string
     */
    protected $vardir;

    /**
     * Path to the composer.json file
     *
     * @var string
     */
    protected $composer_json;

    /**
     * Path to the composer.lock file
     *
     * @var string
     */
    protected $composer_lock;

    /**
     * exec command to the composer.phar file
     *
     * @var string
     */
    protected $composer_exec;

    /**
     * Packaglist - installed packages
     *
     * @var array
     */
    protected $list = false;

    /**
     * Can composer execute via bash? shell?
     *
     * @var boolean
     */
    protected $exec = false;

    /**
     * temporary require packages
     *
     * @var array
     */
    protected $require = array();

    /**
     * QUIQQER Version ->getVersion()
     *
     * @var string
     */
    protected $version = null;

    /**
     * List of packages objects
     *
     * @var array
     */
    protected $packages = array();

    /**
     * List of installed packages flags
     *
     * @var array
     */
    protected $installed = array();

    /**
     * internal event manager
     *
     * @var QUI\Events\Manager
     */
    public $Events;

    /**
     * internal event manager
     *
     * @var QUI\Composer\Composer
     */
    public $Composer;

    /**
     * Path to the local repository
     *
     * @var string
     */
    protected $localRepository;

    /**
     * active servers - use as temp for local repo using
     *
     * @var array
     */
    protected $activeServers = array();

    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        // defaults
        $this->setAttributes(array(
            '--prefer-dist' => true
        ));

        $this->dir    = OPT_DIR; // CMS_DIR .'packages/';
        $this->vardir = VAR_DIR.'composer/';

        $this->composer_json = $this->vardir.'composer.json';
        $this->composer_lock = $this->vardir.'composer.lock';

        $this->Composer = null;
        $this->Events   = new QUI\Events\Manager();
        $this->setAttributes($attributes);
    }

    /**
     * Return the internal composer object
     *
     * @return null|QUI\Composer\Composer
     */
    public function getComposer()
    {
        if (is_null($this->Composer)) {
            $this->Composer = new QUI\Composer\Composer($this->vardir);

            if (php_sapi_name() != 'cli') {
                $this->Composer->setMode(QUI\Composer\Composer::MODE_WEB);
            } else {
                $this->Composer->setMode(QUI\Composer\Composer::MODE_CLI);
            }
        }

        return $this->Composer;
    }

    /**
     * Return the available quiqqer package types
     *
     * @return array
     */
    public static function getPackageTypes()
    {
        return array(
            'quiqqer-library', // deprecated
            'quiqqer-plugin',
            'quiqqer-module',
            'quiqqer-template',
            'quiqqer-application'
        );
    }

    /**
     * Return the last update date
     *
     * @return integer
     */
    public function getLastUpdateDate()
    {
        return (int)$this->getUpdateConf()->get('quiqqer', 'lastUpdate');
    }

    /**
     * Return the last update date
     *
     * @return integer
     */
    public function getLastUpdateCheckDate()
    {
        return (int)$this->getUpdateConf()->get('quiqqer', 'lastUpdateCheck');
    }

    /**
     * Set the last update date to now
     *
     * @throws QUI\Exception
     */
    public function setLastUpdateDate()
    {
        $Last = $this->getUpdateConf();
        $Last->set('quiqqer', 'lastUpdate', time());
        $Last->save();
    }

    /**
     * Set the last update date to now
     *
     * @throws QUI\Exception
     */
    public function setLastUpdateCheckDate()
    {
        $Last = $this->getUpdateConf();
        $Last->set('quiqqer', 'lastUpdateCheck', time());
        $Last->save();
    }

    /**
     * Return the version from the composer json
     *
     * @return string
     */
    public function getVersion()
    {
        if ($this->version) {
            return $this->version;
        }

        if (!file_exists($this->composer_json)) {
            return '';
        }

        $data = file_get_contents($this->composer_lock);
        $data = json_decode($data, true);

        $package = array_filter($data['packages'], function ($package) {
            return $package['name'] === 'quiqqer/quiqqer';
        });

        $package       = current($package);
        $this->version = $package['version'];

        return $this->version;
    }

    /**
     * Return the lock data from the package
     *
     * @param Package $Package
     *
     * @return array
     */
    public function getPackageLock(Package $Package)
    {
        $data = file_get_contents($this->composer_lock);
        $data = json_decode($data, true);

        $packageName = $Package->getName();

        $package = array_filter($data['packages'], function ($package) use ($packageName) {
            return $package['name'] === $packageName;
        });

        if (empty($package)) {
            return array();
        }

        $package = current($package);

        return $package;
    }

    /**
     * Checks if the composer.json exists
     * if not, the system will try to create the composer.json (with all installed packages)
     */
    protected function checkComposer()
    {
        if (file_exists($this->composer_json)) {
            return;
        }

        $this->createComposerJSON();
    }

    /**
     * Create the composer.json file for the system
     */
    protected function createComposerJSON()
    {
        if (file_exists($this->composer_json)) {
            $composerJson = json_decode(
                file_get_contents($this->composer_json)
            );
        } else {
            $template = file_get_contents(
                dirname(__FILE__).'/composer.tpl'
            );

            $composerJson = json_decode($template);
        }

        // config
        $composerJson->config = array(
            "vendor-dir"        => OPT_DIR,
            "cache-dir"         => $this->vardir,
            "component-dir"     => OPT_DIR.'bin',
            "quiqqer-dir"       => CMS_DIR,
            "minimum-stability" => 'dev',
            "secure-http"       => false
        );

        $composerJson->extra = array(
            "asset-installer-paths"  => array(
                "npm-asset-library"   => OPT_DIR.'bin',
                "bower-asset-library" => OPT_DIR.'bin'
            ),
            "asset-registry-options" => array(
                "npm"              => false,
                "bower"            => false,
                "npm-searchable"   => false,
                "bower-searchable" => false
            )
        );

        // composer events scripts
        $composerEvents = [
            // command events
            'pre-update-cmd'         => [
                'QUI\\Package\\Composer\\CommandEvents::preUpdate'
            ],
            'post-update-cmd'        => [
                'QUI\\Package\\Composer\\CommandEvents::postUpdate'
            ],
            // package events
            'pre-package-install'    => [
                'QUI\\Package\\Composer\\PackageEvents::prePackageInstall'
            ],
            'post-package-install'   => [
                'QUI\\Package\\Composer\\PackageEvents::postPackageInstall'
            ],
            'pre-package-update'     => [
                'QUI\\Package\\Composer\\PackageEvents::prePackageUpdate'
            ],
            'post-package-update'    => [
                'QUI\\Package\\Composer\\PackageEvents::postPackageUpdate'
            ],
            'pre-package-uninstall'  => [
                'QUI\\Package\\Composer\\PackageEvents::prePackageUninstall'
            ],
            'post-package-uninstall' => [
                'QUI\\Package\\Composer\\PackageEvents::postPackageUninstall'
            ]
        ];

        if (empty($composerJson->scripts)) {
            $composerJson->scripts = (object)[];
        }

        foreach ($composerEvents as $composerEvent => $events) {
            if (empty($composerJson->scripts->{$composerEvent})) {
                $composerJson->scripts->{$composerEvent} = [];
            }

            if (!is_array($composerJson->scripts->{$composerEvent})) {
                $composerJson->scripts->{$composerEvent} = [];
            }

            $composerJson->scripts->{$composerEvent} = array_unique(array_merge(
                $events,
                $composerJson->scripts->{$composerEvent}
            ));
        }

        // make the repository list
        $servers      = $this->getServerList();
        $repositories = [];
        $npmServer    = [];

        foreach ($servers as $server => $params) {
            if ($server == 'packagist') {
                continue;
            }

            if ($server == 'bower') {
                continue;
            }

            if ($server == 'npm') {
                continue;
            }

            if (!isset($params['active']) || $params['active'] != 1) {
                continue;
            }

            if ($params['type'] === 'npm') {
                $npmHostName             = parse_url($server, \PHP_URL_HOST);
                $npmServer[$npmHostName] = $server;
                continue;
            }

            $repositories[] = array(
                'type' => $params['type'],
                'url'  => $server
            );
        }

        if (isset($servers['packagist']) && $servers['packagist']['active'] == 0) {
            $repositories[] = array(
                'packagist' => false
            );
        }

        // license information
        $licenseConfigFile = CMS_DIR.'etc/license.ini.php';

        if (file_exists($licenseConfigFile)) {
            $LicenseConfig    = new QUI\Config($licenseConfigFile);
            $data             = $LicenseConfig->getSection('license');
            $licenseServerUrl = QUI::conf('license', 'url');

            if (!empty($data['id'])
                && !empty($data['licenseHash'])
                && !empty($licenseServerUrl)
            ) {
                $hash = bin2hex(QUI\Security\Encryption::decrypt(hex2bin($data['licenseHash'])));

                $repositories[] = array(
                    'type'    => 'composer',
                    'url'     => $licenseServerUrl,
                    'options' => array(
                        'http' => array(
                            'header' => array(
                                'licenseid: '.$data['id'],
                                'licensehash: '.$hash,
                                'clientdata: '.bin2hex(json_encode($this->getLicenseClientData()))
                            )
                        )
                    )
                );
            }
        }

        if (isset($servers['npm']) && $servers['npm']['active'] == 1) {
            $composerJson->extra["asset-registry-options"]["npm"]            = true;
            $composerJson->extra["asset-registry-options"]["npm-searchable"] = true;
        }

        if (isset($servers['bower']) && $servers['bower']['active'] == 1) {
            $composerJson->extra["asset-registry-options"]["bower"]            = true;
            $composerJson->extra["asset-registry-options"]["bower-searchable"] = true;
        }

        $composerJson->repositories = $repositories;

        // add npm server
        if (!empty($npmServer)) {
            $composerJson->extra['asset-custom-npm-registries'] = $npmServer;
        }

        // standard require
        if (empty($composerJson->require)) {
            $list = $this->getList();

            // must have
            $require                    = array();
            $require["php"]             = ">=5.5";
            $require["quiqqer/quiqqer"] = "dev-master";

            foreach ($list as $package) {
                $require[$package['name']] = $package['version'];
            }

            ksort($require);

            $composerJson->require = $require;
        }

        // save
        file_put_contents($this->composer_json, json_encode(
            $composerJson,
            \JSON_PRETTY_PRINT
        ));
    }

    /**
     * Creates a backup from the composer.json file
     */
    public function createComposerBackup()
    {
        if (!file_exists($this->composer_json)) {
            throw new QUI\Exception(
                'Composer File not found'
            );
        }

        $backupDir = VAR_DIR.'backup/composer/';

        QUIFile::mkdir($backupDir);

        $date = date('Y-m-d__H-i-s');

        $composerJson = $backupDir.'composer_'.$date.'.json';
        $composerLock = $backupDir.'composer_'.$date.'.lock';

        if (file_exists($composerJson) || file_exists($composerLock)) {
            $count = 1;

            while (true) {
                $composerJson = "{$backupDir}composer_{$date}_({$count}).json";
                $composerLock = "{$backupDir}composer_{$date}_({$count}).lock";

                if (file_exists($composerJson)) {
                    $count++;
                    continue;
                }

                if (file_exists($composerJson)) {
                    $count++;
                    continue;
                }

                break;
            }
        }

        QUIFile::copy($this->composer_json, $composerJson);
        QUIFile::copy($this->composer_lock, $composerLock);
    }

    /**
     * Clear the complete composer cache
     */
    public function clearComposerCache()
    {
        QUI::getTemp()->moveToTemp($this->vardir.'repo/');
        QUI::getTemp()->moveToTemp($this->vardir.'files/');

        $this->getComposer()->clearCache();
    }

    /**
     * Package Methods
     */

    /**
     * Return the composer array
     *
     * @return array
     */
    protected function getComposerJSON()
    {
        $this->checkComposer();

        $json   = file_get_contents($this->composer_json);
        $result = json_decode($json, true);

        return $result;
    }

    /**
     * internal get list method
     * return all installed packages and create the internal package list cache
     *
     * @return array
     */
    protected function getList()
    {
        if ($this->list) {
            return $this->list;
        }

        try {
            $this->list = QUI\Cache\Manager::get(self::CACHE_NAME_TYPES);

            return $this->list;
        } catch (QUI\Exception $Exception) {
        }

        $installed_file = $this->dir.'composer/installed.json';

        if (!file_exists($installed_file)) {
            return array();
        }

        $data = file_get_contents($installed_file);
        $list = json_decode($data, true);

        $result = array();

        if (is_array($list)) {
            foreach ($list as $entry) {
                if (!isset($entry['type'])) {
                    $result[] = $entry;
                    continue;
                }

                if ($entry['type'] != 'quiqqer-module') {
                    $result[] = $entry;
                    continue;
                }

                $path = OPT_DIR.$entry['name'].'/';

                if (file_exists($path.'settings.xml')) {
                    $entry['_settings'] = 1;
                }

                if (file_exists($path.'permissions.xml')) {
                    $entry['_permissions'] = 1;
                }

                if (file_exists($path.'database.xml')) {
                    $entry['_database'] = 1;
                }

                $result[] = $entry;
            }

            $this->list = $result;
        }

        QUI\Cache\Manager::set(self::CACHE_NAME_TYPES, $this->list);

        return $this->list;
    }

    /**
     * Refreshed the installed package list
     * If some packages are uploaded, sometimes the package versions and data are not correct
     *
     * this method correct it
     */
    protected function refreshInstalledList()
    {
        $installed_file = $this->dir.'composer/installed.json';

        if (!file_exists($installed_file)) {
            return;
        }

        $data = file_get_contents($installed_file);
        $list = json_decode($data, true);

        foreach ($list as $key => $entry) {
            $cf = $this->dir.$entry['name'].'/composer.json';

            if (!file_exists($cf)) {
                continue;
            }

            $data = json_decode(file_get_contents($cf), true);

            if (!is_array($data)) {
                continue;
            }

            if (!isset($data['version'])) {
                continue;
            }
        }

        $this->list = array();

        if (is_array($list)) {
            $this->list = $list;
        }
    }

    /**
     * Return the installed packages
     *
     * @param array $params - [optional] search / limit params
     *
     * @return array
     */
    public function getInstalled($params = array())
    {
        $list   = $this->getList();
        $result = $list;

        if (isset($params['type'])) {
            $result = array();

            foreach ($list as $package) {
                if (!isset($package['type'])) {
                    continue;
                }

                if (!empty($params['type'])
                    && $params['type'] != $package['type']
                ) {
                    continue;
                }

                $result[] = $package;
            }
        }

        if (isset($params['limit']) && isset($params['page'])) {
            $limit = (int)$params['limit'];
            $page  = (int)$params['page'];

            $result = QUI\Utils\Grid::getResult($result, $page, $limit);
        }

        foreach ($result as $key => $package) {
            try {
                $Package = $this->getInstalledPackage($package['name']);

                $result[$key]['title']       = $Package->getTitle();
                $result[$key]['description'] = $Package->getDescription();
                $result[$key]['image']       = $Package->getImage();
            } catch (QUI\Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return a package object
     *
     * @param string $package - name of the package
     *
     * @return QUI\Package\Package
     * @throws QUI\Exception
     */
    public function getInstalledPackage($package)
    {
        if (!isset($this->packages[$package])) {
            $this->packages[$package] = new QUI\Package\Package($package);
        }

        return $this->packages[$package];
    }

    /**
     * Install Package
     *
     * @param string|array $packages - name of the package, or list of paackages
     * @param string|boolean $version - (optional) version of the package default = dev-master
     */
    public function install($packages, $version = false)
    {
        QUI\System\Log::addDebug(
            'Install package '.print_r($packages, true).' -> install'
        );

        $this->composerRequireOrInstall($packages, $version);

        $this->setup($packages);
    }

    /**
     * Returns whether the package is installed or not
     *
     * Please use this method to check the installation status and not ->getInstalledPackage()
     * This method use an internal caching
     *
     * @param string $packageName
     *
     * @return bool
     */
    public function isInstalled($packageName)
    {
        if (isset($this->installed[$packageName])) {
            return $this->installed[$packageName];
        }

        try {
            $this->getInstalledPackage($packageName);

            $this->installed[$packageName] = true;
        } catch (QUI\Exception $Exception) {
            $this->installed[$packageName] = false;
        }

        return $this->installed[$packageName];
    }

    /**
     * Install only a local package
     *
     * @param string|array $packages - name of the package
     * @param boolean $version - (optional) version of the package
     */
    public function installLocalPackage($packages, $version = false)
    {
        QUI\System\Log::addDebug(
            'Install package '.print_r($packages, true).' -> installLocalPackage'
        );

        $this->useOnlyLocalRepository();
        $this->getComposer()->requirePackage($packages, $version);
        $this->resetRepositories();

        $this->setup($packages);
    }

    /**
     * Return the params of an installed package
     * If you want the Package Object, you should use getInstalledPackage
     *
     * @param string $package
     *
     * @return array
     */
    public function getPackage($package)
    {
        $cache = 'packages/cache/info/'.$package;

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }

        $list   = $this->getList();
        $result = array();

        foreach ($list as $pkg) {
            if (!isset($pkg['name'])) {
                continue;
            }

            if ($pkg['name'] == $package) {
                $pkg['dependencies'] = $this->getDependencies($package);
                $result              = $pkg;
                break;
            }
        }

        $showData = $this->show($package);

        if (isset($showData['versions'])) {
            $result['versions'] = $showData['versions'];
        }

        if (isset($showData['require'])) {
            $result['require'] = $showData['require'];
        }

        QUI\Cache\Manager::set($cache, $result, 3600);

        return $result;
    }

    /**
     * Return the dependencies of a package
     *
     * @param string $package - package name
     *
     * @return array - list of dependencies
     */
    public function getDependencies($package)
    {
        $list   = $this->getList();
        $result = array();

        foreach ($list as $pkg) {
            if (!isset($pkg['require']) || empty($pkg['require'])) {
                continue;
            }

            if (isset($pkg['require'][$package])) {
                $result[] = $pkg['name'];
            }
        }

        return $result;
    }

    /**
     * Return package details, via composer
     * If you want a local package, please use getInstalledPackage() and use the Package instead
     *
     * @param string $package - Name of the package eq: quiqqer/quiqqer
     *
     * @return array
     */
    public function show($package)
    {
        $cache = 'packages/cache/show/'.$package;

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }

        $this->checkComposer();

        $result = array();
        $show   = $this->getComposer()->show($package);

        foreach ($show as $k => $line) {
            if (strpos($line, '<info>') === false) {
                continue;
            }

            if (strpos($line, ':') === false) {
                continue;
            }

            $line  = explode(':', $line);
            $key   = trim(strip_tags($line[0]));
            $value = trim(strip_tags($line[1]));

            if ($key == 'versions') {
                $value = array_map('trim', explode(',', $value));
            }

            if ($key == 'descrip.') {
                $key = 'description';
            }

            if ($line == 'requires') {
                $_temp             = $show;
                $result['require'] = array_slice($_temp, $k + 1);

                continue;
            }

            $result[$key] = $value;
        }

        QUI\Cache\Manager::set($cache, $result, 3600);

        return $result;
    }

    /**
     * Search a string in the repositories
     *
     * @param string $search - search string
     *
     * @return array
     */
    public function searchPackages($search)
    {
        return $this->getComposer()->search(
            QUI\Utils\Security\Orthos::clearShell($search)
        );
    }

    /**
     * Search a string in the repositories
     * Returns only not installed packages
     *
     * @param string $search - search string
     *
     * @return array
     */
    public function searchNewPackages($search)
    {
        $result   = array();
        $packages = $this->searchPackages($search);

        $installed = array_map(function ($entry) {
            return $entry['name'];
        }, $this->getList());

        $installed = array_flip($installed);

        foreach ($packages as $package => $description) {
            if (!isset($installed[$package])) {
                $result[$package] = $description;
            }
        }

        return $result;
    }

    /**
     * Execute a setup for a package
     *
     * @param string|array $packages
     * @param array $setupOptions - optional, setup package options
     */
    public function setup($packages, $setupOptions = array())
    {
        QUIFile::mkdir(CMS_DIR.'etc/plugins/');

        if (!is_array($packages)) {
            $packages = array($packages);
        }

        foreach ($packages as $package) {
            try {
                $Package = $this->getInstalledPackage($package);
                $Package->setup($setupOptions);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception, QUI\System\Log::LEVEL_WARNING);
            }
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
        $this->createComposerJSON();
    }

    /**
     * Return the server list
     *
     * @return array
     */
    public function getServerList()
    {
        try {
            $servers = QUI::getConfig('etc/source.list.ini.php')->toArray();

            if (!isset($servers['npm'])) {
                $servers['npm']['active'] = false;
            }

            if (!isset($servers['bower'])) {
                $servers['bower']['active'] = false;
            }

            // default types
            $servers['packagist']['type'] = 'composer';
            $servers['bower']['type']     = 'bower';
            $servers['npm']['type']       = 'npm';

            return $servers;
        } catch (QUI\Exception $Exception) {
        }

        return array();
    }

    /**
     * Activate or Deactivate a server
     *
     * @param string $server - Server, IP, Host
     * @param boolean $status - 1 = active, 0 = disabled
     * @param boolean $backup - Optional (default=true, create a backup, false = create no backup
     */
    public function setServerStatus(
        $server,
        $status,
        $backup = true
    ) {
        $Config = QUI::getConfig('etc/source.list.ini.php');
        $status = (bool)$status ? 1 : 0;

        $Config->setValue($server, 'active', $status);
        $Config->save();

        if ($backup) {
            $this->createComposerBackup();
        }

        $this->createComposerJSON();
    }

    /**
     * Add a server to the update-server list
     *
     * @param string $server - Server, IP, Host
     * @param array $params - Server Parameter
     */
    public function addServer($server, $params = array())
    {
        if (empty($server)) {
            return;
        }

        if (!is_array($params)) {
            return;
        }

        $this->createComposerBackup();

        $Config = QUI::getConfig('etc/source.list.ini.php');
        $Config->setValue($server, 'active', 0);

        if (isset($params['type'])) {
            switch ($params['type']) {
                case "composer":
                case "vcs":
                case "pear":
                case "package":
                case "artifact":
                case "npm":
                case "bower":
                    $Config->setValue($server, 'type', $params['type']);
                    $Config->setValue($server, 'active', 1);
                    break;
            }
        }

        $Config->save();

        $this->refreshServerList();
    }

    /**
     * Edit server from the update-server list
     *
     * @param string $server - Server, IP, Host
     * @param array $params - Server Parameter
     */
    public function editServer($server, $params = array())
    {
        if (empty($server)) {
            return;
        }

        if (!is_array($params)) {
            return;
        }

        $Config = QUI::getConfig('etc/source.list.ini.php');

        // rename server
        if (isset($params['server'])
            && $server != $params['server']
        ) {
            $this->addServer($params['server'], $Config->getSection($server));
            $this->removeServer($server);
            $server = $params['server'];
        }

        if (isset($params['type'])) {
            switch ($params['type']) {
                case "composer":
                case "vcs":
                case "pear":
                case "package":
                case "artifact":
                case "npm":
                case "bower":
                    $Config->setValue($server, 'type', $params['type']);
                    break;
            }
        }

        $Config->save();
        $this->refreshServerList();
    }

    /**
     * Remove a Server completly from the update-server list
     *
     * @param string|array $server
     */
    public function removeServer($server)
    {
        $Config = QUI::getConfig('etc/source.list.ini.php');

        if (is_array($server)) {
            foreach ($server as $entry) {
                $Config->del($entry);
            }
        } else {
            $Config->del($server);
        }

        $Config->save();

        $this->createComposerBackup();
        $this->refreshServerList();
    }

    /**
     * Update methods
     */

    /**
     * Check for updates
     *
     * @throws \QUI\Exception
     */
    public function checkUpdates()
    {
        $this->checkComposer();

        return $this->getComposer()->updatesAvailable(false);
    }

    /**
     * Check for updates
     *
     * @param bool $force - if force is true -> database / cache output from the last check wouldn't be checked
     *
     * @return array
     *
     * @throws \QUI\Exception
     */
    public function getOutdated($force = false)
    {
        if (!is_bool($force)) {
            $force = false;
        }

        $this->checkComposer();
        $this->setLastUpdateCheckDate();

        if ($force === false) {
            // get last database check
            $result = QUI::getDataBase()->fetch(array(
                'from'  => QUI::getDBTableName('updateChecks'),
                'where' => array(
                    'result' => array(
                        'type'  => 'NOT',
                        'value' => ''
                    ),
                    'date'   => array(
                        'type'  => '>=',
                        'value' => $this->getLastUpdateDate()
                    )
                )
            ));

            if (!empty($result)) {
                $result = json_decode($result[0]['result'], true);

                if (!empty($result)) {
                    usort($result, function ($a, $b) {
                        return strcmp($a["package"], $b["package"]);
                    });

                    return $result;
                }
            }
        }

        try {
            $output = $this->getOutdatedPackages();

            usort($output, function ($a, $b) {
                return strcmp($a["package"], $b["package"]);
            });

            QUI::getDataBase()->insert(QUI::getDBTableName('updateChecks'), array(
                'date'   => time(),
                'result' => json_encode($output)
            ));
        } catch (QUI\Composer\Exception $Exception) {
            QUI::getDataBase()->insert(QUI::getDBTableName('updateChecks'), array(
                'date'  => time(),
                'error' => json_encode($Exception->toArray())
            ));

            throw $Exception;
        }

        return $output;
    }

    /**
     * Update a package or the entire system
     *
     * @param string|boolean $package - optional, package name, if false, it updates the complete system
     * @param bool $mute -mute option for the composer output
     *
     * @throws QUI\Exception
     *
     * @todo if exception uncommited changes -> own error message
     * @todo if exception uncommited changes -> interactive mode
     */
    public function update($package = false, $mute = true)
    {
        $Composer = $this->getComposer();

        // WEB MODE Check
        // Wenn VCS Server eingestellt sind sollte mindestens 256M vorhanden sein.
        // Ohne VCS mindestens 128M
        $existsVCS = function ($Update) {
            /* @var $Update self */
            $servers = $Update->getServerList();

            foreach ($servers as $server) {
                if ($server['type'] === 'vcs') {
                    return true;
                }
            }

            return false;
        };

        $needledRAM = $existsVCS($this) ? '256M' : '128M';
        $limit      = QUI\Utils\System::getMemoryLimit();

        if (php_sapi_name() != 'cli'
            && $limit != -1
            && $existsVCS
            && QUIFile::getBytes($needledRAM) > $limit) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'quiqqer/quiqqer',
                    'message.online.update.RAM.not.enough',
                    array(
                        'command' => 'php quiqqer.php update'
                    )
                )
            );
        }

        $this->createComposerBackup();

        if ($mute === true) {
            $Composer->mute();
        }

        if (is_string($package) && empty($package)) {
            $package = false;
        }

        if (!is_string($package) && !is_bool($package)) {
            $package = false;
        }

        $this->composerUpdateOrInstall($package);

        // composer optimize
        $Composer->dumpAutoload(array(
            '--optimize' => true
        ));

        if ($package) {
            $Package = self::getInstalledPackage($package);
            $Package->setup();
        } else {
            QUI\Setup::all();
        }

        // set last update
        $Last = $this->getUpdateConf();
        $Last->set('quiqqer', 'lastUpdate', time());
        $Last->save();
    }

    /**
     * Returns the update config object
     *
     * @return QUI\Config
     */
    protected function getUpdateConf()
    {
        // set last update
        if (!file_exists(CMS_DIR.'etc/last_update.ini.php')) {
            file_put_contents(CMS_DIR.'etc/last_update.ini.php', '');
        }

        return new QUI\Config(CMS_DIR.'etc/last_update.ini.php');
    }

    /**
     * Update a package or the entire system from a package archive
     *
     * @param string|boolean $package - Name of the package
     *
     * @throws QUI\Exception
     */
    public function updateWithLocalRepository($package = false)
    {
        $this->createComposerBackup();
        $this->useOnlyLocalRepository();

        try {
            $this->update($package);
            $this->resetRepositories();
        } catch (QUI\Exception $Exception) {
            $this->resetRepositories();
            LocalServer::getInstance()->activate();

            throw $Exception;
        }
    }

    /**
     * use only the local repository
     */
    protected function useOnlyLocalRepository()
    {
        // deactivate active servers
        $activeServers = array();
        $serverList    = $this->getServerList();

        foreach ($serverList as $server => $data) {
            if ($data['active'] == 1) {
                $activeServers[] = $server;
            }
        }

        foreach ($activeServers as $server) {
            $this->setServerStatus($server, 0, false);
        }

        // activate local repos
        LocalServer::getInstance()->activate();

        $this->createComposerJSON();
        $this->activeServers = $activeServers;
    }

    /**
     * reset the repositories after only local repo using
     */
    protected function resetRepositories()
    {
        // activate active servers
        foreach ($this->activeServers as $server) {
            $this->setServerStatus($server, 1, false);
        }

        $this->createComposerJSON();
    }

    /**
     * XML helper
     */

    /**
     * Return all packages which includes a site.xml
     *
     * @return array
     * @todo move to an API XML Handler
     */
    public function getPackageSiteXmlList()
    {
        try {
            return QUI\Cache\Manager::get('qui/packages/list/haveSiteXml');
        } catch (QUI\Exception $Exception) {
        }

        $packages = $this->getInstalled();
        $result   = array();

        foreach ($packages as $package) {
            if (!is_dir(OPT_DIR.$package['name'])) {
                continue;
            }

            $file = OPT_DIR.$package['name'].'/site.xml';

            if (!file_exists($file)) {
                continue;
            }

            $result[] = $package['name'];
        }

        QUI\Cache\Manager::set('qui/packages/list/haveSiteXml', $result);

        return $result;
    }

    /**
     * Return all packages which includes a site.xml
     *
     * @return array
     * @todo move to an API XML Handler
     */
    public function getPackageDatabaseXmlList()
    {
        try {
            return QUI\Cache\Manager::get('qui/packages/list/haveDatabaseXml');
        } catch (QUI\Exception $Exception) {
        }

        $packages = $this->getInstalled();
        $result   = array();

        foreach ($packages as $package) {
            $file = OPT_DIR.$package['name'].'/database.xml';

            if (!file_exists($file)) {
                continue;
            }

            $result[] = $package['name'];
        }

        QUI\Cache\Manager::set('qui/packages/list/haveDatabaseXml', $result);

        return $result;
    }

    /**
     * Get specific XML file of all packages that provide it
     *
     * @param string $name - e.g. "database.xml" / "package.xml" etc.
     *
     * @return array - absolute file paths
     */
    public function getPackageXMLFiles($name)
    {
        // @todo cache

        $packages = $this->getInstalled();
        $result   = array();

        foreach ($packages as $package) {
            $file = OPT_DIR.$package['name'].'/'.$name;

            if (!file_exists($file)) {
                continue;
            }

            $result[] = $file;
        }

        return $result;
    }

    /**
     * Get extra client data for composer license server header
     *
     * @return array
     */
    protected function getLicenseClientData()
    {
        return array(
            'phpVersion'     => phpversion(),
            'quiqqerHost'    => QUI::conf('globals', 'host'),
            'quiqqerCmsDir'  => QUI::conf('globals', 'cms_dir'),
            'quiqqerVersion' => QUI::version()
        );
    }

    /**
     * This will try to retieve the lock file from the lockserver, if the lockserver is enabled.
     * If a Lockfile has been generated by the lockserver composer will use it and execute an install.
     * If the lockserver is disabled or not available composer will issue an usual update command.
     *
     * @param bool|string - (otional) The packagename which should get updated.
     *
     * @return string
     */
    protected function composerUpdateOrInstall($package)
    {
        // Disable lockserver if a vcs repository is used
        $repositories = $this->getServerList();
        foreach ($repositories as $repo) {
            if ($repo['type'] === 'vcs') {
                return $this->getComposer()->update();
            }
        }

        if ($this->getComposer()->getMode() != QUI\Composer\Composer::MODE_WEB) {
            return $this->getComposer()->update();
        }

        $lockServerEnabled = QUI::conf("globals", "lockserver_enabled");
        if (!$lockServerEnabled) {
            return $this->getComposer()->update();
        }

        $Lockclient = new QUI\Lockclient\Lockclient();
        try {
            $lockContent = $Lockclient->update($this->composer_json, $package);
        } catch (\Exception $Exception) {
            return $this->getComposer()->update();
        }

        file_put_contents($this->composer_lock, $lockContent);

        return $this->getComposer()->install();
    }

    /**
     * This will check if the Lockserver is enabled and available.
     * The package will be required or added to the lockfile and installed.
     *
     * @param $packages
     * @param $version
     *
     * @return string
     */
    protected function composerRequireOrInstall($packages, $version)
    {
        // Disable lockserver if a vcs repository is used
        $repositories = $this->getServerList();
        foreach ($repositories as $repo) {
            if ($repo['type'] === 'vcs') {
                return $this->getComposer()->requirePackage($packages, $version);
            }
        }

        if ($this->Composer->getMode() != QUI\Composer\Composer::MODE_WEB) {
            return $this->getComposer()->requirePackage($packages, $version);
        }

        $lockServerEnabled = QUI::conf("globals", "lockserver_enabled");
        if (!$lockServerEnabled) {
            return $this->getComposer()->requirePackage($packages, $version);
        }

        $Lockclient = new QUI\Lockclient\Lockclient();
        try {
            $lockContent = $Lockclient->requirePackage($this->composer_json, $packages, $version);
        } catch (\Exception $Exception) {
            return $this->getComposer()->requirePackage($packages, $version);
        }

        file_put_contents($this->composer_lock, $lockContent);

        return $this->getComposer()->install();
    }

    /**
     * Gets a list of outdated packages.
     * Returns an array in the format:
     * array(
     *   'package' => "vendor/package,
     *   'version' => "dev-master def567",
     *   'oldVersion' => "dev-master abc1234"
     *  );
     *
     * @return array
     * @throws QUI\Composer\Exception
     * @throws \Exception
     */
    protected function getOutdatedPackages()
    {
        $repositories = $this->getServerList();
        foreach ($repositories as $repo) {
            if ($repo['type'] === 'vcs') {
                return $this->getComposer()->getOutdatedPackages();
            }
        }

        if ($this->getComposer()->getMode() != QUI\Composer\Composer::MODE_WEB) {
            return $this->getComposer()->getOutdatedPackages();
        }

        $lockServerEnabled = QUI::conf("globals", "lockserver_enabled");
        if (!$lockServerEnabled) {
            return $this->getComposer()->getOutdatedPackages();
        }

        // use the lockserver to get the outdated packages
        $result      = array();
        $constraints = array();

        $outdatedPackages = $this->getComposer()->outdated();

        foreach ($outdatedPackages as $outdatedPackage) {
            $packageName = $outdatedPackage['package'];
            $requiredBy  = $this->getComposer()->why($packageName);

            foreach ($requiredBy as $requiredByPackage) {
                $constraints[$packageName][] = $requiredByPackage['constraint'];
            }
        }

        $onlyStable = true;
        if (file_exists(VAR_DIR."composer/composer.json")) {
            $composerJsonContent = file_get_contents(VAR_DIR."composer/composer.json");
            $composerJsonData    = json_decode($composerJsonContent, true);
            if (isset($composerJsonData['minimum-stability']) && $composerJsonData['minimum-stability'] != "stable") {
                $onlyStable = false;
            }
        }

        $Lockclient     = new QUI\Lockclient\Lockclient();
        $latestVersions = $Lockclient->getLatestVersionInContraints($constraints, $onlyStable);

        foreach ($outdatedPackages as $outdatedPackage) {
            $packageName    = $outdatedPackage['package'];
            $currentVersion = $outdatedPackage['version'];

            if (!isset($latestVersions[$packageName])) {
                continue;
            }
            $newVersion = $latestVersions[$packageName];

            if (ltrim($currentVersion, 'vV') == ltrim($newVersion, 'vV')) {
                continue;
            }

            $result[] = array(
                'package'    => $packageName,
                'version'    => $newVersion,
                'oldVersion' => $currentVersion
            );
        }

        return $result;
    }
}
