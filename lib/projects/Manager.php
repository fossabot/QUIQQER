<?php

/**
 * This file contains the Projects_Manager
 */

/**
 * The Project Manager
 * The main object to get a project
 *
 * @author www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.projects
 */
class Projects_Manager
{
    /**
     * Projects config
     * @var QConfig
     */
    static $Config = null;

    /**
     * laoded projects
     * @var array
     */
    static $projects = array();

    /**
     * standard project
     * @var Projects_Project
     */
    static $Standard = null;

    /**
     * projects.ini
     *
     * @return QConfig
     */
    static function getConfig()
    {
        return QUI::getConfig('etc/projects.ini');
    }

    /**
     * Returns the current project
     *
     * @return Project
     * @throws QException
     */
    static function get()
    {
        $Rewrite = QUI::getRewrite();

        if ( $Rewrite->getParam( 'project' ) )
        {
            return self::getProject(
                $Rewrite->getParam( 'project' ),
                $Rewrite->getParam( 'lang' ),
                $Rewrite->getParam( 'template' )
            );
        }

        $Standard = self::getStandard();

        // Falls andere Sprache gewünscht
        if ( $Rewrite->getParam( 'lang' ) &&
             $Rewrite->getParam( 'lang' ) != $Standard->getAttribute( 'lang' ) )
        {
            return self::getProject(
                $Standard->getAttribute( 'name' ),
                $Rewrite->getParam( 'lang' )
            );
        }

        return $Standard;
    }

    /**
     * Returns a project
     *
     * @param String $project   - Project name
     * @param String $lang		- Project lang, optional (if not set, the standard language used)
     * @param String $template  - used templaed, optional (if not set, the standard templaed used)
     *
     * @return Projects_Project
     */
    static function getProject($project, $lang=false, $template=false)
    {
        if ( $lang == false &&
             isset( self::$projects[ $project ] ) &&
             isset( self::$projects[ $project ][ '_standard' ] ) )
        {
            return self::$projects[ $project ][ '_standard' ];
        }

        if ( isset( self::$projects[ $project ] ) &&
             isset( self::$projects[ $project ][ $lang ] ) )
        {
            return self::$projects[ $project ][ $lang ];
        }

        // Wenn der RAM zu voll wird, Objekte mal leeren
        if ( Utils_System::memUsageCheck() ) {
            self::$projects = array();
        }


        if ( $lang === false )
        {
            self::$projects[ $project ][ '_standard' ] = new Projects_Project( $project );
            return self::$projects[ $project ][ '_standard' ];
        }

        self::$projects[ $project ][ $lang ] = new Projects_Project(
            $project,
            $lang,
            $template
        );

        return self::$projects[ $project ][ $lang ];
    }

    /**
     * Gibt alle Projektnamen zurück
     *
     * @param Bool $asobject - Als Objekte bekommen, default = false
     * @return Array
     */
    static function getProjects($asobject=false)
    {
        $config = self::getConfig()->toArray();
        $list   = array();

        foreach ( $config as $project => $conf )
        {
            try
            {
                $Project = self::getProject(
                    $project,
                    $conf['default_lang'],
                    $conf['template']
                );

                if ( isset( $conf['standard'] ) && $conf['standard'] == 1 ) {
                    self::$Standard = $Project;
                }

                if ( $asobject == true )
                {
                    $list[] = $Project;
                } else
                {
                    $list[] = $project;
                }

            } catch ( QException $e )
            {

            }
        }

        return $list;
    }

    /**
     * Standard Projekt bekommen
     * @return Projects_Project
     */
    static function getStandard()
    {
        if ( !is_null( self::$Standard ) ) {
            return self::$Standard;
        }

        $config = self::getConfig()->toArray();

        foreach ( $config as $project => $conf )
        {
            if ( isset( $conf['standard'] ) && $conf['standard'] == 1)
            {
                self::$Standard = self::getProject(
                    $project,
                    $conf['default_lang'],
                    $conf['template']
                );
            }
        }

        if ( is_null( self::$Standard ) ) {
            throw new QException( 'Es wurde kein Projekt gefunden', 404 );
        }

        return self::$Standard;
    }

    /**
     * Create a new project
     *
     * @param String $name - Project name
     * @param String $lang - Project lang
     * @param String $template - template, optional
     * @throws QException
     *
     * @todo noch einmal anschauen und übersichtlicher schreiben
     */
    static function createProject($name, $lang, $template=false)
    {
        \QUI_Rights_Permission::checkPermission(
            'quiqqer.admin.projects.create'
        );

        if ( strlen( $name ) <= 2 )
        {
            throw new \QException(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.project.longer.two.signs'
                ),
                701
            );
        }

        if ( strlen( $lang ) != 2 )
        {
            throw new \QException(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.project.lang.not.two.signs'
                ),
                701
            );
        }

        $not_allowed_signs = array(
            '-', '.' ,',', ':',  ';', '#',
            '`', '!', '§', '$', '%', '&',
            '/', '?', '<', '>', '=', '\'', '"'
        );

        if ( preg_match( "@[-.,:;#`!§$%&/?<>\=\'\" ]@", $name ) )
        {
            throw new QException(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.project.not.allowed.signs',
                    array(
                        'signs' => implode( ' ', $not_allowed_signs )
                    )
                ),
                702
            );
        }

        $projects = self::getProjects();

        if ( isset( $projects[ $name ] ) )
        {
            throw new QException(
                \QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.project.not.allowed.signs'
                )
            );
        }

        $name = \Utils_Security_Orthos::clear( $name );

        $DataBase = \QUI::getDataBase();
        $Table    = $DataBase->Table();


        /**
         * Sites and sites relation
         */
        $table_site      = QUI_DB_PRFX . $name .'_'. $lang .'_sites';
        $table_site_rel  = QUI_DB_PRFX . $name .'_'. $lang .'_sites_relations';

        $Table->appendFields($table_site, array(
            "id"          => "bigint(20) NOT NULL",
            "name"        => "varchar(200) NOT NULL",
            "title"       => "tinytext",
            "short"       => "text",
            "content"     => "longtext",
            "type"        => "varchar(32) default NULL",
            "active"      => "tinyint(1) NOT NULL",
            "deleted"     => "tinyint(1) NOT NULL",
            "c_date"      => "timestamp NULL default NULL",
            "e_date"      => "timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP",
            "c_user"      => "int(11) default NULL",
            "e_user"      => "int(11) default NULL",
            "nav_hide"    => "tinyint(1) NOT NULL",
            "order_type"  => "varchar(100) default NULL",
            "order_field" => "bigint(20) default NULL",
            "extra"       => "text default NULL",
        ));

        $Table->appendFields($table_site_rel, array(
            "parent" => "bigint(20) NOT NULL",
            "child"  => "bigint(20) NOT NULL"
        ));

        // first site
        $DataBase->insert($table_site, array(
            "id"    => 1,
            "name"  => 'Start',
            "title" => 'start',
            "short" => 'Shorttext',
            "content" => "<p>Welcome to my project</p>",
            "type"    => 'standard',
            "active"  => 1,
            "deleted" => 0,
            "c_date"  => date( 'Y-m-d H:i:s' ),
            "c_user"  => \QUI::getUserBySession()->getId(),
            "e_user"  => \QUI::getUserBySession()->getId(),
            "nav_hide"    => '',
            "order_type"  => "",
            "order_field" => ""
        ));


        /**
         * Media and media relation
         */
        $table_media     = QUI_DB_PRFX . $name .'_'. $lang .'_media';
        $table_media_rel = QUI_DB_PRFX . $name .'_'. $lang .'_media_relations';

        $Table->appendFields($table_media, array(
            "id"      => "bigint(20) NOT NULL",
            "name"    => "varchar(200) NOT NULL",
            "title"   => "tinytext",
            "short"   => "text",
            "type"    => "varchar(32) default NULL",
            "active"  => "tinyint(1) NOT NULL",
            "deleted" => "tinyint(1) NOT NULL",
            "c_date"  => "timestamp NULL default NULL",
            "e_date"  => "timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP",
            "c_user"  => "int(11) default NULL",
            "e_user"  => "int(11) default NULL",
            "file"    => "text",
            "alt"     => "text",
            "mime_type"    => "text",
            "image_height" => "int(6) default NULL",
            "image_width"  => "int(6) default NULL"
        ));

        $Table->appendFields($table_media_rel, array(
            "parent" => "bigint(20) NOT NULL",
            "child"  => "bigint(20) NOT NULL"
        ));

        // first folder
        $DataBase->insert($table_media, array(
            "id"      => 1,
            "name"    => 'Start',
            "title"   => 'start',
            "short"   => 'Shorttext',
            "type"    => 'folder',
            "file"    => '',
            "active"  => 1,
            "deleted" => 0,
            "c_date"  => date( 'Y-m-d H:i:s' ),
            "c_user"  => \QUI::getUserBySession()->getId(),
            "e_user"  => \QUI::getUserBySession()->getId()
        ));


        /**
         * Create the file system folders
         */
        Utils_System_File::mkdir( CMS_DIR .'media/sites/'. $name .'/' );
        Utils_System_File::mkdir( USR_DIR . $name .'/' );


        /**
         * Write the config
         */
        if ( !file_exists( CMS_DIR .'etc/projects.ini' ) ) {
            file_put_contents( CMS_DIR .'etc/projects.ini', '' );
        }

        $Config = self::getConfig();

        $Config->setSection($name, array(
            'default_lang' => $lang,
            'langs'        => $lang,
            'admin_mail'   => 'support@pcsg.de',
            'template'     => $name,
            'image_text'   => '0',
            'keywords'     => '',
            'description'  => '',
            'robots'       => 'index',
            'author'       => '',
            'publisher'    => '',
            'copyright'    => '',
            'standard'     => '0'
        ));

        $Config->save();

        // Projekt setup
        $Project = self::getProject( $name );
        $Project->setup();

        // Projekt Cache löschen
        System_Cache_Manager::clear( 'QUI::config' );
    }

    /**
     * Search a project
     *
     * @param Array $params - Search params
     *     'search' => 'search string',
     *     'limit'  => 5,
     *     'page'   => 1
     *
     * @return Array
     */
    static function search($params)
    {
        if ( !isset( $params[ 'search' ] ) ) {
            return array();
        }

        $search = $params[ 'search' ];

        $result = array();
        $list   = self::getConfig()->toArray();

        foreach ( $list as $project => $entry )
        {
            if ( !empty( $search ) && strpos( $project, $search ) === false ) {
                continue;
            }

            $langs = explode( ',', $entry[ 'langs' ] );

            foreach ( $langs as $lang )
            {
                $result[] = array(
                    'project' => $project,
                    'lang'    => $lang
                );
            }
        }

        return $result;
    }
}

?>