/**
 * WYWIWYG Editor Setting Panel
 *
 * @author www.namerobot.com (Henning Leutz)
 */

define('controls/editors/Settings', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'qui/controls/windows/Prompt',
    'controls/grid/Grid',
    'Ajax'

], function(QUI, QUIPanel, QUIConfirm, QUIPrompt, Grid, Ajax)
{
    "use strict";

    return new Class({

        Extends : QUIPanel,
        Type    : 'controls/editors/Settings',

        Binds : [
            '$onCreate',
            '$onResize',
            '$onRefresh',

            '$gridClick',
            '$gridDblClick',
            '$gridBlur',

            'openAddToolbarWindow',
            'openDeleteToolbarWindow'
        ],

        options : {
            title : 'WYSIWYG-Editor Einstellungen',
            icon  : 'icon-font'
        },

        initialize : function(options)
        {
            this.parent( options );

            this.$Grid = null;

            this.addEvents({
                onCreate  : this.$onCreate,
                onResize  : this.$onResize,
                onRefresh : this.$onRefresh
            });
        },

        /**
         * event : on create
         */
        $onCreate : function()
        {
            var self = this;

            this.addButton({
                name      : 'toolbarAdd',
                text      : 'Toolbar hinzufügen',
                textimage : 'icon-plus',
                events    : {
                    onClick : this.openAddToolbarWindow
                }
            });

            this.addButton({
                type : 'seperator'
            });

            this.addButton({
                name      : 'toolbarEdit',
                text      : 'Markierte Toolbar editieren',
                textimage : 'icon-edit',
                disabled  : true,
                events    : {
                    onClick : this.editToolbar
                }
            });

            this.addButton({
                name      : 'toolbarDelete',
                text      : 'Markierte Toolbar löschen',
                textimage : 'icon-trash',
                disabled  : true,
                events    : {
                    onClick : function()
                    {
                        var sel = self.getGrid().getSelectedData();

                        self.openDeleteToolbarWindow( sel[ 0 ].toolbar );
                    }
                }
            });

            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid( Container, {
                columnModel : [{
                    header    : 'Toolbar-Name',
                    dataIndex : 'toolbar',
                    dataType  : 'string',
                    width     : 200
                }],
                onrefresh : function() {
                    self.loadToolbars();
                }
            });

            this.$Grid.addEvents({
                onClick    : this.$gridClick,
                onDblClick : this.$gridDblClick,
                onBlur     : this.$gridBlur
            });

            this.refresh();
        },

        /**
         * event : on refresh
         */
        $onRefresh : function()
        {
            this.getGrid().refresh();
        },

        /**
         * event : on resize
         */
        $onResize : function()
        {
            if ( !this.$Grid ) {
                return;
            }

            var Body = this.getContent();

            if ( !Body ) {
                return;
            }


            var size = Body.getSize();

            this.$Grid.setHeight( size.y - 40 );
            this.$Grid.setWidth( size.x - 40 );
        },

        /**
         * get the toolbars
         *
         * @param {Function} callback - callback( list )
         */
        getToolbars : function(callback)
        {
            Ajax.get('ajax_editor_get_toolbars', function(list)
            {
                callback( list );
            });
        },

        /**
         * Delete a toolbar
         *
         * @param {String} toolbar - name of the toolbar
         * @param {Function} callback
         */
        deleteToolbar : function(toolbar, callback)
        {
            Ajax.get('ajax_editor_toolbar_delete', function(list)
            {
                callback( list );
            }, {
                toolbar : toolbar
            });
        },

        /**
         * Add a toolbar
         *
         * @param {String} toolbar - name of the toolbar (myNewToolbar)
         * @param {Function} callback
         */
        addToolbar : function(toolbar, callback)
        {
            Ajax.get('ajax_editor_toolbar_add', function(list)
            {
                callback();
            }, {
                toolbar : toolbar
            });
        },

        /**
         * Open the edit sheet for a toolbar
         *
         * @param {String} toolbar
         */
        editToolbar : function(toolbar)
        {
            this.createSheet({
                title  : 'Toolbar '+ toolbar +' editieren',
                icon   : 'icon-edit',
                events :
                {
                    onOpen : function(Sheet)
                    {
                        // Sheet.Loader.show();
                        var Content = Sheet.getContent();

                        require([
                            'controls/editors/ToolbarConfigurator'
                        ], function(Configurator)
                        {
                            var C = new Configurator({
                                toolbar : toolbar
                            });

                            Sheet.addButton({
                                text : 'Änderungen speichern',
                                textimage : 'icon-save',
                                events :
                                {
                                    onClick : function() {
                                        C.save();
                                    }
                                }
                            });

                            C.inject( Content );
                        });
                    }
                }
            }).show();
        },

        /**
         * load the toolbars into the grid
         */
        loadToolbars : function()
        {
            var self = this;

            this.Loader.show();

            this.getToolbars(function(list)
            {
                if ( !list )
                {
                    self.Loader.hide();
                    return;
                }

                var i, len;
                var data = [];

                for ( i = 0, len = list.length; i < len; i++ )
                {
                    data.push({
                        toolbar : list[ i ]
                    });
                }

                self.getGrid().setData({
                    data : data
                });

                self.Loader.hide();
            });
        },


        /**
         * Windows
         */

        /**
         * Opens the dialog for delete a toolbar
         *
         * @param {String} toolbar - Name of the toolbar
         */
        openDeleteToolbarWindow : function(toolbar)
        {
            var self = this;

            new QUIConfirm({
                title  : 'Toolbar wirklich löschen?',
                icon   : 'icon-trash',
                text   : 'Möchten Sie die Toolbar '+ toolbar +' wirklich löschen?',
                events :
                {
                    onSubmit : function(Win)
                    {
                        self.deleteToolbar(toolbar, function()
                        {
                            Win.close();
                            self.refresh();
                        });
                    }
                }

            }).open();
        },

        /**
         * Opens the dialog for add a toolbar
         *
         * @param {String} toolbar - Name of the toolbar
         */
        openAddToolbarWindow : function()
        {
            var self = this;

            new QUIPrompt({
                title : 'Toolbar hinzufügen',
                icon  : 'icon-plus',
                information : 'Geben Sie bitte einen neuen Toolbarnamen ein',
                events :
                {
                    onSubmit : function(toolbar, Win)
                    {
                        self.addToolbar(toolbar, function()
                        {
                            self.refresh();
                            self.editToolbar( toolbar.replace('\.xml', '') +'.xml' );
                        });
                    }
                }

            }).open();
        },

        /**
         * Grid methods
         */

        /**
         * Return the grid
         *
         * @return {controls/grid/Grid}
         */
        getGrid : function()
        {
            return this.$Grid;
        },

        /**
         * onclick on the grid
         */
        $gridClick : function(data)
        {
            var len    = data.target.selected.length,
                Edit   = this.getButtons( 'toolbarEdit' ),
                Delete = this.getButtons( 'toolbarDelete' );

            if ( len === 0 )
            {
                Edit.disable();
                Delete.disable();

                return;
            }

            Edit.enable();
            Delete.enable();

            data.evt.stop();
        },

        /**
         * dblclick on the grid
         */
        $gridDblClick : function(data)
        {
            this.editToolbar(
                data.target.getDataByRow( data.row ).toolbar
            );
        },

        /**
         * onblur on the grid
         */
        $gridBlur : function(data)
        {
            return;

            if ( !this.getGrid() ) {
                return;
            }

            this.getGrid().unselectAll();
            this.getGrid().removeSections();

            this.getButtons( 'toolbarEdit' ).disable(),
            this.getButtons( 'toolbarDelete' ).disable();
        }

    });

});