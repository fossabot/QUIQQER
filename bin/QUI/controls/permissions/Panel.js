
/**
 * Permissions Panel
 *
 * @module controls/permissions/Panel
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require utils/permissions/Utils
 * @require utils/Controls
 * @require qui/utils/Object
 * @require Locale
 * @require Ajax
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/Seperator
 * @require qui/controls/sitemap/Map
 * @require qui/controls/sitemap/Item
 * @require qui/controls/windows/Prompt
 * @require qui/controls/windows/Confirm
 * @require css!controls/permissions/Panel.css
 */

define([

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'utils/permissions/Utils',
    'utils/Controls',
    'qui/utils/Object',
    'Locale',
    'Ajax',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Seperator',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'qui/controls/windows/Prompt',
    'qui/controls/windows/Confirm',

    'css!controls/permissions/Panel.css'

], function()
{
    "use strict";

    var QUI                = arguments[ 0 ],
        Panel              = arguments[ 1 ],
        Utils              = arguments[ 2 ],
        ControlUtils       = arguments[ 3 ],
        ObjectUtils        = arguments[ 4 ],
        Locale             = arguments[ 5 ],
        Ajax               = arguments[ 6 ],
        QUIButton          = arguments[ 7 ],
        QUIButtonSeperator = arguments[ 8 ],
        Sitemap            = arguments[ 9 ],
        SitemapItem        = arguments[ 10 ],
        QUIPrompt          = arguments[ 11 ],
        QUIConfirm         = arguments[ 12 ];


    var lg = 'quiqqer/system';


    /**
     * @class controls/permissions/Panel
     *
     * @param {Object} options - QDOM panel params
     * @param {Object} [Bind] - (optional), classes/groups/Group | classes/users/User
     *
     * @memberof! <global>
     */
    return new Class({

        Extends : Panel,
        Type    : 'controls/permissions/Panel',

        Binds : [
            '$onCreate',
            '$onResize',
            '$onRefresh',
            '$onSitemapItemClick',
            '$onFormElementChange',
            'addPermission',
            'delPermission',
            'openSearch',
            'save'
        ],

        initialize : function(options, Bind)
        {
            // defaults
            this.setAttribute( 'title',
                Locale.get( lg, 'permissions.panel.title' )
            );

            this.setAttribute( 'icon', 'icon-gears' );

            // init
            this.parent( options );

            this.$Bind   = Bind || null;
            this.$Map    = null;
            this.$rights = {};

            this.$bindpermissions = {};
            this.$Container       = null;

            this.addEvents({
                onCreate  : this.$onCreate,
                onResize  : this.$onResize,
                onRefresh : this.$onRefresh
            });
        },

        /**
         * Set the object for which the rights are
         *
         * @param {Object} Bind - classes/groups/Group | classes/users/User | classes/projects/project/Site | classes/projects/Project
         */
        setBind : function(Bind)
        {
            this.Loader.show();

            this.$Bind = Bind;

            if ( !this.$Bind )
            {
                this.refresh();
                return;
            }

            var self   = this,
                params = {
                    id : Bind.getId()
                };

            switch ( Bind.getType() )
            {
                case 'classes/projects/Project':
                    params.project = Bind.getName();
                break;

                case 'classes/projects/project/Site':
                    var Project = Bind.getProject();

                    params.project = Project.getName();
                    params.lang    = Project.getLang();
                break;
            }


            Ajax.get('ajax_permissions_get', function(result, Request)
            {
                if ( typeOf( result ) != 'object' ) {
                    result = {};
                }

                self.$bindpermissions = result;
                self.refresh();

            }, {
                params : JSON.encode( params ),
                btype  : Bind.getType()
            });
        },

        /**
         * Get the permission list
         *
         * @param {Function} callback - callback function
         */
        getPermissionList : function(callback)
        {
            if ( !this.$Bind )
            {
                Ajax.get( ['ajax_permissions_list'], callback );
                return;
            }

            var params = {
                    id : this.$Bind.getId()
                };

            switch ( this.$Bind.getType() )
            {
                case 'classes/projects/Project':
                    params.project = this.$Bind.getName();
                break;

                case 'classes/projects/project/Site':
                    var Project = this.$Bind.getProject();

                    params.project = Project.getName();
                    params.lang    = Project.getLang();
                break;
            }


            Ajax.get([
                'ajax_permissions_get',
                'ajax_permissions_list'
            ], function(permissions, allPermissions)
            {
                for ( var key in permissions )
                {
                    if ( !permissions.hasOwnProperty( key ) ) {
                        continue;
                    }

                    if ( typeof allPermissions[ key ] !== 'undefined' ) {
                        permissions[ key ] = allPermissions[ key ];
                    }
                }

                callback( permissions );
            }, {
                params : JSON.encode( params ),
                btype  : this.$Bind.getType()
            });
        },

        /**
         * Opens the search for groups / users
         *
         * @method controls/permissions/Panel#openSearch
         */
        openSearch : function()
        {
            var Sheet = this.createSheet(),
                Body  = this.getBody(),
                self  = this;

            if ( Body.getElement( '.qui-permissions-sitemap' ) ) {
                this.hideSitemap();
            }

            Sheet.addEvent('onOpen', function(Sheet)
            {
                var Container = new Element('div.qui-permissions-select-sheet', {
                    html   : '<h1>'+
                                 Locale.get( lg, 'permissions.panel.select.group.title' ) +
                             '</h1>' +
                             '<div class="buttons"></div>'+
                             '<div class="search"></div>',
                    styles : {
                        margin : '50px auto 0',
                        width  : 612
                    }
                }).inject( Sheet.getBody() );

                var Buttons = Container.getElement( '.buttons' ),
                    btnList = [];


                btnList.push(
                    new QUIButton({
                        text    : Locale.get( lg, 'permissions.panel.btn.select.user' ),
                        icon    : 'icon-user',
                        styles  : {
                            width : 200
                        },
                        events :
                        {
                            onClick : function() {
                                self.$loadUserSearch( Sheet );
                            }
                        }
                    }).inject( Buttons )
                );

                btnList.push(
                    new QUIButton({
                        text   : Locale.get( lg, 'permissions.panel.btn.select.group' ),
                        icon   : 'icon-group',
                        events :
                        {
                            onClick : function() {
                                self.$loadGroupSearch( Sheet );
                            }
                        }
                    }).inject( Buttons )
                );

                btnList.push(
                    new QUIButton({
                        text   : Locale.get( lg, 'permissions.panel.btn.select.site' ),
                        icon   : 'icon-file-alt',
                        events :
                        {
                            onClick : function()
                            {
                                /*
                                Btn.getAttribute( 'Control' ).$loadSiteSearch(
                                    Btn.getAttribute( 'Sheet' )
                                );
                                */
                            }
                        }
                    }).inject( Buttons )
                );

                btnList.push(
                    new QUIButton({
                        text   : Locale.get( lg, 'permissions.panel.btn.select.project' ),
                        icon   : 'icon-home',
                        events :
                        {
                            onClick : function() {
                                self.$loadProjectSearch( Sheet );
                            }
                        }
                    }).inject( Buttons )
                );

                btnList.push(
                    new QUIButton({
                        text   : Locale.get( lg, 'permissions.panel.btn.select.manage' ),
                        icon   : 'icon-gears',
                        events :
                        {
                            onClick : function()
                            {
                                Sheet.hide();

                                self.setBind( null );
                                self.getButtons( 'permissions-sitemap' ).click();
                            }
                        }
                    }).inject( Buttons )
                );

                var i, len, Elm;

                for ( i = 0, len = btnList.length; i < len; i++ )
                {
                    Elm = btnList[ i ].getElm();

                    Elm.removeClass( 'qui-button' );
                    Elm.addClass( 'button' );
                    Elm.addClass( 'btn-rosy' );
                    Elm.setStyles({
                        margin : '0 10px 10px 0',
                        width  : 190
                    });
                }

                self.Loader.hide();
            });

            Sheet.addEvent('onClose', function()
            {
                self.refresh();
                self.showSitemap();
            });

            Sheet.show();
        },

        /**
         * Load the group control, to select a group
         *
         * @method controls/permissions/Panel#$loadGroupSearch
         * @param {Object} Sheet - qui/controls/desktop/panels/Sheet
         */
        $loadGroupSearch : function(Sheet)
        {
            var self   = this,
                Body   = Sheet.getBody(),
                Search = Body.getElement( '.search' );

            Search.set( 'html', '' );

            require(['controls/groups/Input'], function(Input)
            {
                Search.set(
                    'html',

                    '<h2>' +
                        Locale.get( lg, 'permissions.panel.select.group.title' ) +
                    '</h2>'
                );

                var GroupSearch = new Input({
                    max      : 1,
                    multible : false,
                    styles   : {
                        margin : '0 auto',
                        width  : 200
                    },
                    events :
                    {
                        onAdd : function(GroupSearch, groupid)
                        {
                            require(['Groups'], function(Groups)
                            {
                                self.setBind( Groups.get( groupid ) );
                                //this.getButtons( 'permissions-sitemap' ).click();

                                Sheet.hide();
                                GroupSearch.close();
                            });
                        }
                    }
                }).inject( Search );

                GroupSearch.focus();
            });
        },

        /**
         * Load the group control, to select a group
         *
         * @method controls/permissions/Panel#$loadUserSearch
         * @param {Object} Sheet - qui/controls/desktop/panels/Sheet
         */
        $loadUserSearch : function(Sheet)
        {
            var self   = this,
                Body   = Sheet.getBody(),
                Search = Body.getElement( '.search' );

            Search.set( 'html', '' );

            require(['controls/users/Input'], function(Input)
            {
                Search.set(
                    'html',

                    '<h2>' +
                        Locale.get( lg, 'permissions.panel.select.user.title' ) +
                    '</h2>'
                );

                var UserSearch = new Input({
                    max      : 1,
                    multible : false,
                    styles   : {
                        margin : '0 auto',
                        width  : 200
                    },
                    events :
                    {
                        onAdd : function(UserSearch, userid)
                        {
                            require(['Users'], function(Users)
                            {
                                self.setBind( Users.get( userid ) );
                                // this.getButtons( 'permissions-sitemap' ).click();

                                Sheet.hide();
                                UserSearch.close();
                            });

                        }
                    }
                }).inject( Search );

                UserSearch.focus();
            }.bind( this ));
        },

        /**
         * Load the project control, to select a project
         *
         * @method controls/permissions/Panel#$loadProjectSearch
         * @param {Object} Sheet - qui/controls/desktop/panels/Sheet
         */
        $loadProjectSearch : function(Sheet)
        {
            var self   = this,
                Body   = Sheet.getBody(),
                Search = Body.getElement( '.search' );

            Search.set( 'html', '' );

            require(['controls/projects/Input'], function(Input)
            {
                Search.set(
                    'html',

                    '<h2>' +
                        Locale.get( lg, 'permissions.panel.select.project.title' ) +
                    '</h2>'
                );

                var ProjectSearch = new Input({
                    max      : 1,
                    multible : false,
                    styles   : {
                        margin : '0 auto',
                        width  : 200
                    },
                    events :
                    {
                        onAdd : function(UserSearch, project, lang)
                        {
                            require(['Projects'], function(Projects)
                            {
                                self.setBind( Projects.get( project, lang ) );

                                Sheet.hide();
                                ProjectSearch.close();
                            });

                        }
                    }
                }).inject( Search );

                ProjectSearch.focus();
            });
        },

        /**
         * Opens the add permission dialog
         *
         * @method controls/permissions/Panel#addPermission
         */
        addPermission : function()
        {
            var self = this;

            new QUIPrompt({
                title       : Locale.get( lg, 'permissions.panel.window.add.title' ),
                icon        : 'icon-add',
                text        : Locale.get( lg, 'permissions.panel.window.add.text' ),
                information : Locale.get( lg, 'permissions.panel.window.add.information' ),
                autoclose   : false,
                maxWidth    : 600,
                events :
                {
                    onOpen : function(Win)
                    {
                        var Body       = Win.getContent(),
                            PromptBody = Body.getElement( '.qui-windows-prompt' ),
                            Input      = Body.getElement( 'input' );

                        PromptBody.setStyle( 'height', null );

                        Input.setStyles({
                            width   : 200,
                            'float' : 'left'
                        });

                        var Area = new Element('select', {
                            name : 'area',
                            html : '<option value="">Recht für Benutzer und Gruppen</option>'+
                                   '<option value="site">Seiten Zugriffsrecht</option>' +
                                   '<option value="media">Media Zugriffsrecht</option>',
                            styles : {
                                width   : 150,
                                margin  : '10px 5px 10px 10px',
                                'float' : 'left'
                            }
                        }).inject( Input, 'after' );

                        new Element('select', {
                            name : 'type',
                            html : '<option value="bool" selected="selected">bool</option>' +
                                   '<option value="string">string</option>' +
                                   '<option value="int">int</option>' +
                                   '<option value="group">group</option>' +
                                   '<option value="groups">groups</option>' +
                                   '<option value="user">user</option>' +
                                   '<option value="users">users</option>' +
                                   '<option value="array">array</option>',
                            styles : {
                                width   : 80,
                                margin  : '10px 5px',
                                'float' : 'left'
                            }
                        }).inject( Area, 'after' );

                        Body.getElement( '.qui-windows-prompt-information' ).setStyle( 'clear', 'both' );

                        if ( !self.$Map ) {
                            return;
                        }

                        var sels = self.$Map.getSelectedChildren();

                        if ( sels[ 0 ] )
                        {
                            Win.getInput().focus();
                            Win.setValue( sels[ 0 ].getAttribute( 'value' ) +'.' );
                        }
                    },

                    onSubmit : function(value, Win)
                    {
                        Win.Loader.show();

                        Ajax.post('ajax_permissions_add', function(result)
                        {
                            if ( result )
                            {
                                Win.close();
                                self.$createSitemap();
                            }
                        }, {
                            permission     : value,
                            area           : Win.getContent().getElement( '[name="area"]' ).value,
                            permissiontype : Win.getContent().getElement( '[name="type"]' ).value,
                            onError : function(Exception)
                            {
                                QUI.getMessageHandler(function(MessageHandler) {
                                    MessageHandler.addException( Exception );
                                });

                                Win.Loader.hide();
                            }
                        });
                    }
                }

            }).open();
        },

        /**
         * Opens the dialog for delete a permission
         *
         * @method controls/permissions/Panel#delPermission
         * @param {Object|String} right - qui/controls/buttons/Button | String
         */
        delPermission : function(right)
        {
            if ( typeOf( right ) == 'qui/controls/buttons/Button' ) {
                right = right.getAttribute( 'value' );
            }

            if ( !right ) {
                return;
            }

            var self = this;

            new QUIConfirm({
                title : Locale.get( lg, 'permissions.panel.window.delete.title' ),
                text  : Locale.get( lg, 'permissions.panel.window.delete.text', {
                    right : right
                }),
                information : Locale.get( lg, 'permissions.panel.window.delete.information', {
                    right : right
                }),
                icon      : 'icon-false',
                texticon  : 'icon-gears',
                autoclose : false,
                width     : 450,
                height    : 200,
                right     : right,
                events :
                {
                    onSubmit : function(Win)
                    {
                        Win.Loader.show();

                        Ajax.post('ajax_permissions_delete', function()
                        {
                            Win.close();
                            self.$createSitemap();
                        }, {
                            permission : Win.getAttribute( 'right' )
                        });
                    }
                }
            }).open();
        },

        /**
         * Save all permissions
         *
         * @method controls/permissions/Panel#save
         */
        save : function()
        {
            if ( !this.$Bind ) {
                return;
            }

            if ( this.getButtons( 'permissions-save' ) )
            {
                this.getButtons( 'permissions-save' ).setAttribute(
                    'textimage',
                    URL_BIN_DIR +'images/loader.gif'
                );
            }

            var params = {
                id : this.$Bind.getId()
            };

            switch ( this.$Bind.getType() )
            {
                case 'classes/projects/Project':
                    params.project = this.$Bind.getName();
                break;

                case 'classes/projects/project/Site':
                    var Project = this.$Bind.getProject();

                    params.project = Project.getName();
                    params.lang    = Project.getLang();
                break;
            }

            var self = this;

            Ajax.post('ajax_permissions_save', function()
            {
                if ( self.getButtons( 'permissions-save' ) )
                {
                    self.getButtons( 'permissions-save' ).setAttribute(
                        'textimage',
                        'icon-save'
                    );
                }

            }, {
                params      : JSON.encode( params ),
                btype       : this.$Bind.getType(),
                permissions : JSON.encode( this.$bindpermissions )
            });
        },

        /**
         * event: on create
         * create the panel body
         *
         * @method controls/permissions/Panel#$onCreate
         */
        $onCreate : function()
        {
            var self = this;

            this.Loader.show();

            // title - header info
            new Element( 'span.bind-info' ).inject( this.getHeader() );

            // create the main container
            this.$Container = new Element('div', {
                'class' : 'qui-permissions-content box smooth'
            }).inject( this.getBody() );

            this.addButton({
                name   : 'permissions-sitemap',
                image  : 'icon-sitemap',
                alt    : Locale.get( lg, 'permissions.panel.btn.sitemap.alt' ),
                title  : Locale.get( lg, 'permissions.panel.btn.sitemap.title' ),
                events :
                {
                    onClick : function(Btn)
                    {
                        if ( Btn.isActive() )
                        {
                            self.hideSitemap();
                            Btn.setNormal();
                            return;
                        }

                        self.showSitemap();
                        Btn.setActive();
                    }
                }
            });

            this.addButton({
                name   : 'permissions-select',
                image  : 'icon-gears',
                alt    : Locale.get( lg, 'permissions.panel.btn.select.open.alt' ),
                title  : Locale.get( lg, 'permissions.panel.btn.select.open.alt' ),
                events : {
                    onClick : this.openSearch
                }
            });

            this.addButton( new QUIButtonSeperator() );

            this.addButton({
                name      : 'permissions-add',
                textimage : 'icon-plus',
                disabled  : true,
                alt     : Locale.get( lg, 'permissions.panel.btn.add.alt' ),
                title   : Locale.get( lg, 'permissions.panel.btn.add.title' ),
                text    : Locale.get( lg, 'permissions.panel.btn.add.text' ),
                events  : {
                    onClick : this.addPermission
                }
            });

            this.addButton({
                name      : 'permissions-save',
                textimage : 'icon-save',
                disabled  : true,

                alt     : Locale.get( lg, 'permissions.panel.btn.save.alt' ),
                title   : Locale.get( lg, 'permissions.panel.btn.save.title' ),
                text    : Locale.get( lg, 'permissions.panel.btn.save.text' ),
                Control : this,
                events  : {
                    onClick : this.save
                }
            });

            // show the object selection
            if ( !this.$Bind )
            {
                this.openSearch.delay( 200, this );
                return;
            }

            this.setBind( this.$Bind );
            this.getButtons( 'permissions-sitemap' ).click();
        },

        /**
         * event: on resize
         *
         * @method controls/permissions/Panel#$onResize
         */
        $onResize : function()
        {
            var Body = this.getBody(),
                size = Body.getSize();

            if ( this.$Map )
            {
                this.$Container.setStyles({
                    width       : size.x - 360,
                    marginLeft  : 300
                });

            } else
            {
                this.$Container.setStyles({
                    width       : size.x - 40,
                    marginLeft  : 0
                });
            }
        },

        /**
         * event: on panel refresh
         * eq: refresh the buttons
         *
         * @method controls/permissions/Panel#$onRefresh
         */
        $onRefresh : function()
        {
            var BtnSave = this.getButtons( 'permissions-save' ),
                BtnAdd  = this.getButtons( 'permissions-add' );

            if ( !this.$Bind )
            {
                BtnSave.disable();
                BtnAdd.enable();

                this.Loader.hide();
                return;
            }

            BtnSave.enable();
            BtnAdd.disable();

            var Title = this.getHeader(),
                Info  = Title.getElement( '.bind-info' ),
                title = '<span>: </span>';

            // user
            switch ( this.$Bind.getType() )
            {
                case 'classes/users/User':
                    title = title + '<span class="user">'+
                        this.$Bind.getId() +
                        ' - '+
                        this.$Bind.getAttribute( 'username' ) +
                    '</span>';
                break;

                case 'classes/groups/Group':
                    title = title + '<span class="group">'+
                        this.$Bind.getId() +
                        ' - ' +
                        this.$Bind.getAttribute( 'name' ) +
                    '</span>';
                break;

                case 'classes/projects/project/Site':
                    title = title + '<span class="site">'+
                        this.$Bind.getAttribute( 'name' ) +
                        ' - #'+ this.$Bind.getId() +
                    '</span>';
                break;
            }

            Info.set( 'html', title );

            this.showSitemap();
            this.Loader.hide();
        },

        /**
         * Sitemap methods
         */

        /**
         * Show the permission sitemap (list)
         *
         * @method controls/permissions/Panel#showSitemap
         */
        showSitemap : function()
        {
            var Container;

            var Body    = this.getBody(),
                Content = this.$Container;

            Content.set( 'html', '' );

            if ( !Body.getElement( '.qui-permissions-sitemap' ) )
            {
                new Element('div', {
                    'class' : 'qui-permissions-sitemap shadow',
                    styles  : {
                        left     : -350,
                        position : 'absolute'
                    }
                }).inject( Body, 'top' );
            }

            Container = Body.getElement( '.qui-permissions-sitemap' );

            moofx( Container ).animate({
                left : 0
            }, {
                callback : function()
                {
                    this.$createSitemap();
                    this.resize();

                    new Element('div', {
                        'class' : 'qui-permissions-sitemap-handle columnHandle',
                        styles  : {
                            position : 'absolute',
                            top      : 0,
                            right    : 0,
                            height   : '100%',
                            width    : 4,
                            cursor   : 'pointer'
                        },
                        events : {
                            click : this.hideSitemap.bind( this )
                        }
                    }).inject(
                        Body.getElement( '.qui-permissions-sitemap' )
                    );

                    var SitemapBtn = this.getButtons( 'permissions-sitemap' );

                    if ( !SitemapBtn.isActive() ) {
                        SitemapBtn.setActive();
                    }

                    this.Loader.hide();

                }.bind( this )
            });
        },

        /**
         * Hide the permission sitemap (list)
         *
         * @method controls/permissions/Panel#hideSitemap
         */
        hideSitemap : function()
        {
            var Body      = this.getBody(),
                Container = Body.getElement( '.qui-permissions-sitemap' );

            if ( this.$Map )
            {
                this.$Map.destroy();
                this.$Map = null;
            }

            moofx( Container ).animate({
                left : -350
            }, {
                callback : function(Container)
                {
                    var Items = this.$Container;

                    Container.destroy();

                    Items.setStyles({
                        width      : '100%',
                        marginLeft : null
                    });

                    var Btn = this.getButtons( 'permissions-sitemap' );

                    if ( Btn ) {
                        Btn.setNormal();
                    }

                    this.resize();

                }.bind( this, Container )
            });
        },

        /**
         * Creates the permission map
         *
         * @method controls/permissions/Panel#$createSitemap
         */
        $createSitemap : function()
        {
            if ( this.$Map ) {
                this.$Map.destroy();
            }

            var self = this;

            this.$Map = new Sitemap();

            this.$Map.appendChild(
                new SitemapItem({
                    text   : 'Rechte',
                    icon   : 'icon-gears',
                    value  : '',
                    events : {
                        onClick : this.$onSitemapItemClick
                    }
                })
            );

            this.$Map.inject(
                this.getBody().getElement( '.qui-permissions-sitemap' )
            );


            this.getPermissionList(function(result)
            {
                if ( !self.$Map ) {
                    return;
                }

                var right, arr;
                var tmp = {};

                self.$rights = result;

                for ( right in result )
                {
                    if ( !result.hasOwnProperty( right ) ) {
                        continue;
                    }

                    arr = right.split( '.' );
                    arr.pop(); // drop the last element

                    if ( arr.length ) {
                        ObjectUtils.namespace( arr.join( '.' ), tmp );
                    }
                }

                // create the children
                self.$appendSitemapItemTo( self.$Map.firstChild(), '', tmp );

                self.$Map.openAll();
                self.$Map.firstChild().click();
            });

//
//            Ajax.get('ajax_permissions_get', function(result)
//            {
//                var arr, right;
//
//                var tmp = {},
//                    Map = self.$Map;
//
//                self.$rights = result;
//
//                if ( !Map ) {
//                    return;
//                }
//
//                for ( right in result )
//                {
//                    arr = right.split( '.' );
//                    arr.pop(); // drop the last element
//
//                    if ( arr.length ) {
//                        ObjectUtils.namespace( arr.join( '.' ), tmp );
//                    }
//                }
//
//                // create the children
//                self.$appendSitemapItemTo( Map.firstChild(), '', tmp );
//
//                //Map.firstChild().open();
//                Map.openAll();
//                Map.firstChild().click();
//            });
        },

        /**
         * Recursive append item helper for sitemap
         *
         * @method controls/permissions/Panel#$appendSitemapItemTo
         * @param {Object} Parent - qui/controls/sitemap/Item
         * @param {String} name
         * @param {Object} params
         */
        $appendSitemapItemTo : function(Parent, name, params)
        {
            var right, Item, _name;

            for ( right in params )
            {
                if ( !params.hasOwnProperty( right ) ) {
                    continue;
                }

                if ( name.length )
                {
                    _name = name +'.'+ right;
                } else
                {
                    _name = right;
                }

                Item = new SitemapItem({
                    icon  : 'icon-gears',
                    value : _name,
                    text  : Locale.get( 'locale/permissions', _name +'._title' ),
                    events : {
                        onClick : this.$onSitemapItemClick
                    }
                });

                Parent.appendChild( Item );

                this.$appendSitemapItemTo( Item, _name, params[ right ] );
            }
        },

        /**
         * event : on sitemap item click
         *
         * @method controls/permissions/Panel#$onSitemapItemClick
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $onSitemapItemClick : function(Item)
        {
            this.Loader.show();

            var list, right, Elm;

            var i   = 0,
                len = 0,
                val = Item.getAttribute( 'value' ) +'.';

            this.$Container.set( 'html', '' );

            var Table = new Element('table', {
                'class' : 'data-table',
                html    : '<tr><th>'+ Item.getAttribute( 'text' ) +'</th></tr>'
            });


            // maybe to php?
            for ( right in this.$rights )
            {
                if ( !this.$rights.hasOwnProperty( right ) ) {
                    continue;
                }

                if ( val == '.' && right.match( /\./ ) ) {
                    continue;
                }

                if ( val == '.' && !right.match( /\./ ) )
                {
                    this.$createPermissionRow(
                        this.$rights[ right ],
                        i,
                        Table
                    );

                    i++;
                    continue;
                }

                if ( !right.match( val ) ) {
                    continue;
                }

                if ( right.replace( val, '' ).match( /\./ ) ) {
                    continue;
                }

                this.$createPermissionRow(
                    this.$rights[ right ],
                    i,
                    Table
                );

                i++;
            }

            // no rights
            if ( i === 0 )
            {
                new Element('tr', {
                    'class' : 'odd',
                    html    : '<td>'+ Locale.get( lg, 'permissions.panel.message.no.rights' ) + '</td>'
                }).inject( Table );
            }

            Table.inject( this.$Container );

            this.$Container.getElements( 'input' ).addEvent(
                'change',
                this.$onFormElementChange
            );

            var perms = this.$bindpermissions;

            // set form values
            list = this.$Container.getElements( 'input' );

            for ( i = 0, len = list.length; i < len; i++ )
            {
                Elm = list[ i ];

                if ( typeof perms === 'undefined' ) {
                    continue;
                }

                if ( !perms ) {
                    continue;
                }

                if ( typeof perms[ Elm.name ] === 'undefined' ) {
                    continue;
                }

                if ( Elm.type == 'checkbox' )
                {
                    if ( perms[ Elm.name ] == 1 ) {
                        Elm.checked = true;
                    }

                    continue;
                }

                if ( typeOf( perms[ Elm.name ] ) == 'boolean' ) {
                    continue;
                }

                Elm.value = perms[ Elm.name ];
            }

            // parse controls only if an object bind exist
            if ( this.$Bind )
            {
                ControlUtils.parse( Table );

            } else
            {
                // if no bind exist, we would only edit the permissions
                Table.getElements('input,textarea').setStyles({
                    display : 'none'
                });
            }


            this.Loader.hide();
        },

        /**
         * event: if a form element triggered its onchange event
         *
         * @method controls/permissions/Panel#$onFormElementChange
         */
        $onFormElementChange : function(event)
        {
            var Target = event.target;

            if ( Target.type == 'checkbox' )
            {
                this.$bindpermissions[ Target.name ] = Target.checked ? 1 : 0;
                return;
            }

            this.$bindpermissions[ Target.name ] = Target.value;
        },

        /**
         * Create the controls in the rows of the permission tables
         *
         * @method controls/permissions/Panel#$createPermissionRow
         * @param {String} right - right name
         * @param {Number} i - row counter
         * @param {HTMLTableElement} Table - <table> Node Element
         */
        $createPermissionRow : function(right, i, Table)
        {
            var Node, Row;

            Row = new Element('tr', {
                'class' : i % 2 ? 'even' : 'odd',
                html    : '<td></td>'
            });

            Node = Utils.parse( right );

            // first we disable all nodes if the node have a specific area type
            if ( !Node.getElements( 'input[data-area=""]' ) ) {
                Node.addClass( 'disabled' );
            }

            // than, we enable only for the binded area
            if ( this.$Bind )
            {
                switch ( this.$Bind.getType() )
                {
                    case 'classes/projects/project/Site':
                        Node.getElements( 'input[data-area="site"]' )
                            .getParent()
                            .removeClass( 'disabled' );
                    break;

                    case 'classes/projects/Project':
                        Node.getElements( 'input[data-area="project"]' )
                            .getParent()
                            .removeClass( 'disabled' );
                    break;
                }
            }

            // edit modus
            if ( !this.$Bind )
            {
                // only user rights can be deleted
                if ( right.src == 'user' )
                {
                    new QUIButton({
                        icon  : 'icon-remove',
                        title : Locale.get( lg, 'permissions.panel.btn.delete.right.alt', {
                            right : right.name
                        }),
                        alt : Locale.get( lg, 'permissions.panel.btn.delete.right.title', {
                            right : right.name
                        }),
                        value  : right.name,
                        events : {
                            onClick : this.delPermission
                        }
                    }).inject( Node, 'top' );
                }
            }

            Node.inject( Row.getElement( 'td' ) );
            Row.inject( Table );
        }
    });
});
