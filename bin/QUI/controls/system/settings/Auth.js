/**
 * quiqqer authentication configuration
 *
 * @module controls/system/settings/Auth
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require utils/Template
 * @require Ajax
 * @require Locale
 * @require css!controls/system/settings/Auth.css
 */
define('controls/system/settings/Auth', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'utils/Template',
    'Ajax',
    'Locale',

    'css!controls/system/settings/Auth.css'

], function (QUI, QUIControl, QUIButton, Template, Ajax, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'controls/system/settings/Auth',

        Binds: [
            '$onImport',
            '$updateMemcachedData'
        ],

        initialize: function (Panel) {
            this.$Panel = Panel;

            this.$TypeSelect     = null;
            this.$MemcachedTable = null;
            this.$MemcachedTbody = null;
            this.$MemcachedTest  = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event : on import
         */
        $onImport: function () {
            var self    = this,
                Panel   = this.$Panel,
                Content = Panel.getContent();

            // events for session server
            this.$TypeSelect = Content.getElement('[name="session.type"]');

            this.$TypeSelect.addEvents({
                change: function () {

                    self.resetDisplay();

                    switch (this.value) {
                        case 'memcached':
                            self.showMemcached();
                            break;
                    }

                }
            });

            this.$TypeSelect.fireEvent('change');
        },

        /**
         * Reset the auth displays
         * destroy .auth-settings-container container and clear the specific objects
         */
        resetDisplay: function () {
            this.$Panel.getContent().getElements('.auth-settings-container').destroy();
            this.$MemcachedTable = null;
            this.$MemcachedTbody = null;
        },

        /**
         * Show the memcached server list
         */
        showMemcached: function () {
            this.$Panel.Loader.show();

            var self      = this,
                Content   = this.$Panel.getContent(),
                Container = new Element('div', {
                    'class': 'auth-settings-container'
                }).inject(Content);


            Template.get('settings/auth/memcached').then(function (html) {

                Container.set('html', html);

                var Table  = Container.getElement('.auth-memcached-table'),
                    TBody  = Table.getElement('tbody'),
                    config = self.$Panel.$config;


                if (!("session" in config)) {
                    self.$Panel.Loader.hide();
                    return;
                }

                if (!("memcached_data" in config.session)) {
                    self.$Panel.Loader.hide();
                    return;
                }

                self.$MemcachedTable = Table;
                self.$MemcachedTbody = TBody;

                var i, len, Row, server;
                var data = config.session.memcached_data.split(';');

                for (i = 0, len = data.length; i < len; i++) {

                    server = data[i].split(':');
                    Row    = self.$addMemcachedServer(server[0], server[1]);

                    Row.addClass(i % 2 ? 'even' : 'odd');
                }

                new QUIButton({
                    text     : QUILocale.get('quiqqer/system', 'quiqqer.settings.auth.memcached.addServer'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function () {
                            self.$addMemcachedServer();
                            self.$refreshMemcachedList();
                            self.$MemcachedTbody.getElement('tr:last-child input').focus();
                        }
                    },
                    styles   : {
                        'float': 'right'
                    }
                }).inject(self.$MemcachedTable.getElement('tfoot td'));

                self.$MemcachedTest = new QUIButton({
                    text     : QUILocale.get('quiqqer/system', 'quiqqer.settings.auth.memcached.testServer'),
                    textimage: 'fa fa-bolt',
                    events   : {
                        onClick: function () {
                            self.$testMemcachedServers();
                        }
                    }
                }).inject(self.$MemcachedTable.getElement('tfoot td'));

                self.$Panel.Loader.hide();
            });
        },

        /**
         * Add a memcached server
         *
         * @param {String} [server] - Memcached Server
         * @param {String|Number} [port] - Memcached Port
         *
         * @return {HTMLTableRowElement|Boolean}
         */
        $addMemcachedServer: function (server, port) {
            if (this.$TypeSelect.value != 'memcached') {
                return false;
            }

            if (!this.$MemcachedTbody) {
                return false;
            }

            var self = this;
            var Row  = new Element('tr', {
                html: '<td><input type="text" name="server" /></td>' +
                      '<td><input type="text" name="port" /></td>' +
                      '<td></td>'
            });

            var Server = Row.getElement('[name="server"]');
            var Port   = Row.getElement('[name="port"]');

            Server.value = server || '';
            Port.value   = port || '';

            Server.addEvent('change', this.$updateMemcachedData);
            Port.addEvent('change', this.$updateMemcachedData);

            new QUIButton({
                icon  : 'fa fa-remove',
                events: {
                    onClick: function () {
                        Row.destroy();
                        self.$refreshMemcachedList();
                        self.$updateMemcachedData();
                    }
                }
            }).inject(Row.getElement('td:last-child'));

            Row.inject(this.$MemcachedTbody);

            return Row;
        },

        /**
         * refresh memcache server list display, set the row odd even classes
         */
        $refreshMemcachedList: function () {
            if (!this.$MemcachedTbody) {
                return;
            }

            var rows = this.$MemcachedTbody.getElements('tr');

            for (var i = 0, len = rows.length; i < len; i++) {
                rows[i].removeClass('even');
                rows[i].removeClass('odd');
                rows[i].addClass(i % 2 ? 'even' : 'odd');
            }
        },

        /**
         * Test the memcached settings
         */
        $testMemcachedServers: function () {
            this.$MemcachedTest.setAttribute(
                'textimage',
                'fa fa-spinner fa-spin'
            );

            var self = this,
                data = [],
                rows = this.$MemcachedTbody.getElements('tr');

            for (var i = 0, len = rows.length; i < len; i++) {
                data.push({
                    server: rows[i].getElement('[name="server"]').value,
                    port  : rows[i].getElement('[name="port"]').value
                });
            }

            Ajax.get('ajax_settings_memcachedTest', function () {
                self.$MemcachedTest.setAttribute('textimage', 'fa fa-bolt');
            }, {
                data: JSON.encode(data)
            });
        },

        /**
         * update the memcached data to the panel input field
         */
        $updateMemcachedData: function () {
            var i, len, server, port;

            var data = [],
                rows = this.$MemcachedTbody.getElements('tr');

            for (i = 0, len = rows.length; i < len; i++) {

                server = rows[i].getElement('[name="server"]').value;
                port   = rows[i].getElement('[name="port"]').value;

                data.push(server + ':' + port);
            }

            this.$Panel.getContent().getElements('[name="session.memcached_data"]').set(
                'value', data.join(';')
            );
        }
    });
});
