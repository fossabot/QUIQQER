/**
 * With the Project-Manager you can create, delete and edit projects
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @requires qui/controls/Control
 * @requires qui/controls/buttons/Button
 * @requires controls/projects/Settings
 * @requires Projects
 * @requires controls/grid/Grid
 *
 * @module controls/projects/Manager
 */

define('controls/projects/Manager', [

    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'controls/projects/project/Settings',
    'Projects',
    'controls/grid/Grid',
    'utils/Template',
    'Locale',

    'css!controls/projects/Manager.css'

], function(QUIPanel, QUIButton, ProjectSettings, Projects, Grid, UtilsTemplate, Locale)
{
    "use strict";

    var lg = 'quiqqer/system';

    /**
     * @class controls/projects/Manager
     *
     * @param {Object} options
     * @memberof! <global>
     */
    return new Class({

        Extends : QUIPanel,
        Type    : 'controls/projects/Manager',

        Binds : [
            'openAddProject',
            'openList',
            '$onCreate',
            '$onResize',
            '$submitCreateProject',
            '$clickBtnProjectSettings'
        ],

        initialize : function(options)
        {
            this.Grid = null;

            this.setAttributes({
                name  : 'projects-manager',
                title : Locale.get( lg, 'projects.project.manager.title' ),
                icon  : 'icon-home'
            });

            this.addEvents({
                onCreate : this.$onCreate,
                onResize : this.$onResize
            });

            this.parent( options );
        },

        /**
         * event: create panel
         *
         * @method controls/projects/Manager#$onCreate
         */
        $onCreate : function()
        {
            this.addCategory({
                name   : 'edit_projects',
                text   : Locale.get( lg, 'projects.project.manager.projects.edit' ),
                icon   : 'icon-list',
                events : {
                    onClick : this.openList
                }
            });

            this.addCategory({
                name   : 'add_project',
                text   : Locale.get( lg, 'projects.project.manager.project.create' ),
                icon   : 'icon-plus',
                events : {
                    onClick : this.openAddProject
                }
            });

            this.getCategoryBar().firstChild().click();
        },

        /**
         * event : resize panel
         *
         * @method controls/projects/Manager#$onResize
         */
        $onResize : function()
        {
            if ( this.Grid )
            {
                var size = this.getBody().getSize();

                this.Grid.setHeight( size.y - 40 );
                this.Grid.setWidth( size.x - 40 );
            }
        },

        /**
         * opens the project list
         *
         * @method controls/projects/Manager#openList
         */
        openList : function()
        {
            this.Loader.show();


            var Control = this,
                Body    = this.getBody();

            Body.set( 'html', '' );

            var Container = new Element( 'div' ).inject( Body ),
                size      = Body.getSize();

            this.Grid = new Grid(Container, {
                columnModel : [{
                    header    : '',
                    dataIndex : 'settingsbtn',
                    dataType  : 'button',
                    width     : 50
                }, {
                    header    : Locale.get( lg, 'project' ),
                    dataIndex : 'project',
                    dataType  : 'string',
                    width     : 150
                }, {
                    header    : Locale.get( lg, 'language' ),
                    dataIndex : 'lang',
                    dataType  : 'string',
                    width     : 150
                }, {
                    header    : Locale.get( lg, 'languages' ),
                    dataIndex : 'langs',
                    dataType  : 'string',
                    width     : 150
                }],

                width  : size.x - 40,
                height : size.y - 40
            });

            Projects.getList(function(result, Request)
            {
                if ( !Object.getLength( result ) )
                {
                    Control.Loader.hide();
                    return;
                }

                var data = [];

                for ( var project in result )
                {
                    data.push({
                        project : project,
                        lang    : result[ project ].default_lang,
                        langs   : result[ project ].langs,

                        settingsbtn : {
                            icon    : 'icon-gear',
                            title   : Locale.get( lg, 'projects.project.manager.open.settings' ),
                            alt     : Locale.get( lg, 'projects.project.manager.open.settings' ),
                            project : project,
                            events  : {
                                onClick : Control.$clickBtnProjectSettings
                            }
                        }
                    });
                }

                Control.Grid.setData({
                    data : data
                });

                Control.Loader.hide();
            });
        },

        /**
         * Opens the project settings
         *
         * @method controls/projects/Manager#openProjectSettings
         * @param {String} project
         */
        openProjectSettings : function(project)
        {
            this.getParent().appendChild(
                new ProjectSettings({
                    project : project
                })
            );
        },

        /**
         * Opens the add project category
         *
         * @method controls/projects/Manager#openAddProject
         */
        openAddProject : function()
        {
            var self = this;

            this.Loader.show();

            UtilsTemplate.get('project/create', function(result)
            {
                var Body = self.getBody();

                Body.set( 'html', result );

                Body.getElement( 'form' ).addEvents({
                    submit : function(event) {
                        event.stop();
                    }
                });

                new QUIButton({
                    text   : Locale.get( lg, 'projects.project.manager.btn.create.project' ),
                    events : {
                        onClick : self.$submitCreateProject
                    }
                }).inject(
                    new Element('p').inject(
                        Body.getElement( 'form' )
                    )
                );


                self.getCategoryBar().getElement( 'add_project' ).setActive();
                self.Loader.hide();
            });
        },

        /**
         * send the project create formular and create the project
         *
         * @method controls/projects/Manager#$submitCreateProject
         */
        $submitCreateProject : function()
        {
            var self = this,
                Form = this.getBody().getElement( 'form' );

            Projects.createNewProject(
                Form.elements.project.value,
                Form.elements.lang.value,
                Form.elements.template.value,
                function(result)
                {
                    if ( !result ) {
                        return;
                    }

                    self.openList();
                    self.openProjectSettings( result );
                }
            );
        },

        /**
         * Opens the project settings
         *
         * @method controls/projects/Manager#$clickBtnProjectSettings
         * @param {qui/controls/buttons/Button} Btn
         */
        $clickBtnProjectSettings : function(Btn)
        {
             this.openProjectSettings(
                 Btn.getAttribute( 'project' )
             );
        }
    });
});