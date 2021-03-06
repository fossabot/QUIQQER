/**
 * Permissions Sitemap
 *
 * @module controls/permissions/Panel
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/sitemap/Map
 * @require qui/controls/sitemap/Item
 * @require qui/utils/Object
 * @require utils/permissions/Utils
 * @require Locale
 *
 * @event itemClick [Item, value]
 */
define('controls/permissions/Sitemap', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'qui/utils/Object',
    'utils/permissions/Utils',
    'Locale'

], function (QUI, QUIControl, QUISitemap, QUISitemapItem, ObjectUtils, PermissionUtils, QUILocale) {
    "use strict";


    return new Class({

        Extends: QUIControl,
        Type   : 'controls/permissions/Sitemap',

        Binds: [
            '$onInject',
            '$onItemClick',
            '$createMap'
        ],

        initialize: function (Object, options) {
            this.parent(options);

            this.$Map  = null;
            this.$Bind = Object || false;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode ELement
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'controls-permissions-sitemap'
            });

            this.$Map = new QUISitemap({
                styles: {
                    margin: '20px 10px'
                }
            });

            this.$Map.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * refresh the map
         */
        refresh: function () {
            this.$Map.clearChildren();

            this.$Map.appendChild(
                new QUISitemapItem({
                    text  : 'Rechte',
                    icon  : 'fa fa-gears',
                    value : '',
                    events: {
                        onClick: this.$onItemClick
                    }
                })
            );

            var Permissions = PermissionUtils.Permissions;

            switch (typeOf(this.$Bind)) {
                case 'classes/users/User':
                    Permissions.getUserPermissionList(this.$Bind).then(this.$createMap);
                    break;

                case 'classes/groups/Group':
                    Permissions.getGroupPermissionList(this.$Bind).then(this.$createMap);
                    break;

                case 'classes/projects/Project':
                    Permissions.getProjectPermissionList(this.$Bind).then(this.$createMap);
                    break;

                case 'classes/projects/project/Site':
                    Permissions.getSitePermissionList(this.$Bind).then(this.$createMap);
                    break;

                case 'qui/classes/DOM':
                    Permissions.getList().then(this.$createMap);
                    break;
            }
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * Create the map
         *
         * @param {Object} permissions - list of permissions
         */
        $createMap: function (permissions) {
            var arr, permission;
            var permissionList = {};

            for (permission in permissions) {
                if (!permissions.hasOwnProperty(permission)) {
                    continue;
                }

                arr = permission.split('.');
                arr.pop(); // drop the last element

                if (arr.length) {
                    ObjectUtils.namespace(arr.join('.'), permissionList);
                }
            }

            this.$appendSitemapItemTo(
                this.$Map.firstChild(),
                '',
                permissionList
            );

            //this.$Map.openAll();
            var FirstChild = this.$Map.firstChild();

            // FirstChild.click();
            FirstChild.open();

            if (FirstChild.firstChild()) {
                FirstChild.firstChild().click();
            }
        },

        /**
         * Recursive append item helper for sitemap
         *
         * @param {Object} Parent - qui/controls/sitemap/Item
         * @param {String} name
         * @param {Object} params
         */
        $appendSitemapItemTo: function (Parent, name, params) {
            var i, len, text, right, Item, permission;

            var groups = QUILocale.getGroups(),
                list   = [];


            for (right in params) {
                if (!params.hasOwnProperty(right)) {
                    continue;
                }

                if (name.length) {
                    permission = name + '.' + right;
                } else {
                    permission = right;
                }

                text = 'permission.' + permission + '._header';

                if (QUILocale.exists('quiqqer/quiqqer', text)) {
                    text = QUILocale.get('quiqqer/quiqqer', text);

                } else {

                    for (i = 0, len = groups.length; i < len; i++) {
                        if (QUILocale.exists(groups[i], text)) {
                            text = QUILocale.get(groups[i], text);
                            break;
                        }
                    }
                }

                list.push({
                    translation: text,
                    permission : permission,
                    right      : right
                });
            }


            // sort list
            list.sort(function (a, b) {
                if (a.translation > b.translation) {
                    return 1;
                }

                if (a.translation < b.translation) {
                    return -1;
                }

                return 0;
            });

            for (i = 0, len = list.length; i < len; i++) {
                Item = new QUISitemapItem({
                    icon  : 'fa fa-gears',
                    value : list[i].permission,
                    text  : list[i].translation,
                    events: {
                        onClick: this.$onItemClick
                    }
                });

                Parent.appendChild(Item);

                this.$appendSitemapItemTo(
                    Item,
                    list[i].permission,
                    params[list[i].right]
                );
            }

        },

        /**
         * event : item on click
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $onItemClick: function (Item) {
            this.fireEvent('itemClick', [
                Item,
                Item.getAttribute('permission')
            ]);
        }
    });
});
