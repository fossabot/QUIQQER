
/**
 * Group (Model)
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require classes/DOM
 *
 * @module classes/groups/Group
 * @package com.pcsg.qui.js.classes.groups
 * @namespace QUI.classes.groups
 *
 * @event onRefresh [ {QUI.classes.groups.Group} ]
 * @event onActivate [ {QUI.classes.groups.Group} ]
 * @event onDeactivate [ {QUI.classes.groups.Group} ]
 */

define('classes/groups/Group', [

    'classes/DOM'

], function(DOM)
{
    "use strict";

    QUI.namespace( 'classes.groups' );

    /**
     * A QUIQQER Group
     *
     * @class QUI.classes.groups.Group
     * @param {Integer} gid - Group-ID
     * @memberof! <global>
     */
    QUI.classes.groups.Group = new Class({

        Extends : DOM,
        Type    : 'QUI.classes.groups.Group',

        attributes : {}, // group attributes

        initialize : function(gid)
        {
            this.$gid = gid;
        },

        /**
         * Return the Group-ID
         *
         * @method QUI.classes.groups.Group#getId
         * @return {Integer} Group-ID
         */
        getId : function()
        {
            return this.$gid;
        },

        /**
         * Load the group attributes from the db
         *
         * @method QUI.classes.groups.Group#load
         * @param {Function} onfinish - [optional] callback
         */
        load: function(onfinish)
        {
            QUI.Ajax.get('ajax_groups_get', function(result, Request)
            {
                var Group = Request.getAttribute( 'Group' );

                Group.setAttributes( result );

                if ( Request.getAttribute( 'onfinish' ) ) {
                    Request.getAttribute( 'onfinish' )( Group, Request );
                }

                Group.fireEvent( 'refresh', [ Group ] );

            }, {
                gid      : this.getId(),
                Group    : this,
                onfinish : onfinish
            });
        },

        /**
         * Return the children groups of the group
         *
         * @method QUI.classes.groups.Group#load
         * @param {Function} onfinish - [optional] callback
         * @param {Object} params - [optional] binded params at the request
         */
        getChildren : function(onfinish, params)
        {
            params = QUI.Utils.combine(params, {
                gid      : this.getId(),
                Group    : this,
                onfinish : onfinish
            });

            QUI.Ajax.get('ajax_groups_children', function(result, Request)
            {
                if ( Request.getAttribute( 'onfinish' ) ) {
                     Request.getAttribute( 'onfinish' )( result, Request );
                }
            }, params);
        },

        /**
         * Save the group with its actualy attributes
         *
         * @method QUI.classes.groups.Group#save
         * @param {Function} onfinish - [optional] callback
         * @param {Object} params - [optional] binded params at the request
         */
        save : function(onfinish, params)
        {
            params = QUI.Utils.combine(params, {
                gid        : this.getId(),
                Group      : this,
                onfinish   : onfinish,
                attributes : JSON.encode( this.getAttributes() ),
                rights     : JSON.encode( this.getRights() )
            });

            QUI.Ajax.post('ajax_groups_save', function(result, Request)
            {
                var Group = Request.getAttribute( 'Group' );

                if ( Request.getAttribute( 'onfinish' ) ) {
                    Request.getAttribute( 'onfinish' )( Group, Request );
                }

                Group.fireEvent( 'refresh', [ Group ] );
                QUI.Groups.refreshGroup( Group );

            }, params);
        },

        /**
         * Is the Group active?
         *
         * @return {Bool} true or false
         */
        isActive : function()
        {
            return ( this.getAttribute( 'active' ) ).toInt() ? true : false;
        },

        /**
         * Get all users that are inside the group
         *
         * @method QUI.classes.groups.Group#getUsers
         * @param {Function} onfinish - Callback function
         *         the return of the function: {Array}
         * @param {Object} params - limit params (limit, page, field, order)
         *
         * @return {this} self
         */
        getUsers : function(onfinish, limits)
        {
            var params = {
                limit : limits.limit || 50,
                page  : limits.page  || 1,
                field : limits.field || 'name',
                order : limits.order || 'DESC'
            };

            QUI.Ajax.get('ajax_groups_users', function(result, Request)
            {
                Request.getAttribute('onfinish')( result, Request );
            }, {
                gid      : this.getId(),
                params   : JSON.encode( params ),
                onfinish : onfinish
            });

            return this;
        },

        /**
         * Attribute methods
         */

        /**
         * Set an attribute to the Object
         * You can extend the Object with everything you like
         * You can extend the Object width more than the default options
         *
         * @method QUI.classes.groups.Group#setAttribute
         * @param {String} k - Name of the Attribute
         * @param {Object|String|Integer|Array} v - value
         * @return {this} self
         */
        setAttribute : function(k, v)
        {
            this.attributes[ k ] = v;
            return this;
        },

        /**
         * If you want set more than one attribute
         *
         * @method QUI.classes.groups.Group#setAttribute
         *
         * @param {Object} attributes - Object with attributes
         * @return {this} self
         *
         * @example Object.setAttributes({
         *   attr1 : '1',
         *   attr2 : []
         * })
         */
        setAttributes : function(attributes)
        {
            attributes = attributes || {};

            for ( var k in attributes ) {
                this.setAttribute( k, attributes[ k ] );
            }

            return this;
        },

        /**
         * Return an attribute of the Object
         * returns the not the default attributes, too
         *
         * @method QUI.classes.groups.Group#setAttribute
         * @param {Object} attributes - Object width attributes
         * @return {unknown_type|Bool} wanted attribute
         */
        getAttribute : function(k)
        {
            if ( typeof this.attributes[ k ] !== 'undefined' ) {
                return this.attributes[ k ];
            }

            return false;
        },

        /**
         * Return the default attributes
         *
         * @method QUI.classes.groups.Group#getAttributes
         * @return {Object}
         */
        getAttributes : function()
        {
            return this.attributes;
        },

        /**
         * Return true if a attribute exist
         *
         * @method QUI.classes.groups.Group#existAttribute
         * @param {String} k - wanted attribute
         * @return {Bool} true | false
         */
        existAttribute : function(k)
        {
            if ( typeof this.attributes[ k ] !== 'undefined' ) {
                return true;
            }

            return false;
        }
    });

    return QUI.classes.groups.Group;
});
