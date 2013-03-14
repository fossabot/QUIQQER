/**
 * The DOM class emulate similar methods
 * like a DOMNode to a normal Object
 *
 * Its easy to use and most classes inherit from {QUI.classes.DOM}
 * All objects that inherited from {QUI.classes.DOM} can easily extend with Attributes.
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onDestroy [this]
 *
 * @module classes/DOM
 * @package com.pcsg.qui.js.classes
 * @namespace QUI.classes
 */

define('classes/DOM', function()
{
    QUI.namespace('classes');
    QUI.$storage = {};

    /**
     * @class QUI.classes.DOM
     *
     * @param {Object} options -
     *         With options you can set attributes or extend the Object width methods and events
     *
     * @example

var Obj = new QUI.classes.DOM({

    attribute1 : true,
    attribute2 : false,

    methods : {
        myNewMethod : function() {
            // some code
        }
    }

    events : {
        onClick : function() {
            // some code
        },
        onMyEvent : function() {
            // some code
        }
    }

});

     * @memberof! <global>
     */
    QUI.classes.DOM = new Class({

        Implements : [ Options, Events ],
        Type       : 'QUI.classes.DOM',

        options : {},
        $uid    : null,

        init : function(options)
        {
            options = options || {};

            if ( options.events )
            {
                this.addEvents( options.events );
                delete options.events;
            }

            if ( options.methods )
            {
                Object.append( this, options.methods );
                delete options.methods;
            }

            this.setAttributes( options );
        },

        /**
         * If this.TYPE is set, this.Type will be return
         *
         * @return {String}
         * @ignore
         */
        $family : function()
        {
            if ( typeof this.Type !== 'undefined' ) {
                return this.Type;
            }

            return typeOf( this );
        },

        /**
         * Get the Unique ID from the Object
         *
         * @method QUI.classes.DOM#getId
         * @return {Integer}
         */
        getId : function()
        {
            if ( !this.$uid ) {
                this.$uid = String.uniqueID();
            }

            return this.$uid;
            //return Slick.uidOf( this );
            /*
            if ( !this.$ID ) {
                this.$ID = String.uniqueID();
            }

            return this.$ID;
            */
        },

        /**
         * Get the type from the Object
         *
         * @method QUI.classes.DOM#getType
         * @return {String}
         */
        getType : function()
        {
            return typeOf( this );
        },

        /**
         * Set an attribute to the Object
         * You can extend the Object with everything you like
         * You can extend the Object width more than the default options
         *
         * @method QUI.classes.DOM#setAttribute
         *
         * @param {String} k - Name of the Attribute
         * @param {Object|String|Integer|Array} v - value
         *
         * @return {this}
         */
        setAttribute : function(k, v)
        {
            this.fireEvent('onSetAttribute', [ k, v ]);

            if ( typeof this.options[ k ] !== 'undefined' )
            {
                this.options[ k ] = v;
                return;
            }

            var oid = Slick.uidOf( this );

            if ( typeof QUI.$storage[ oid ] === 'undefined' ) {
                QUI.$storage[ oid ] = {};
            }

            QUI.$storage[ oid ][ k ] = v;

            return this;
        },

        /**
         * Destroy the Object and all relationsships to some Object
         *
         * @method QUI.classes.DOM#destroy
         */
        destroy : function()
        {
            this.fireEvent( 'destroy', [this] );

            // storage clear
            var oid = Slick.uidOf( this );

            if ( typeof QUI.$storage[ oid ] !== 'undefined' ) {
                delete QUI.$storage[ oid ];
            }

            this.removeEvents();
        },

        /**
         * @method QUI.classes.DOM#setOptions
         * @see setAttributes
         */
        setOptions : function(options)
        {
            this.setAttributes( options );
        },

        /**
         * If you want set more than one attribute
         *
         * @method QUI.classes.DOM#setAttribute
         *
         * @param {Object} attributes - Object with attributes
         * @return {this}
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
                this.setAttribute( k, attributes[k] );
            }

            return this;
        },

        /**
         * Return an attribute of the Object
         * returns the not the default attributes, too
         *
         * @method QUI.classes.DOM#setAttribute
         *
         * @param {Object} attributes - Object width attributes
         * @return {unknown_type|Bool}
         */
        getAttribute : function(k)
        {
            if ( typeof this.options[ k ] !== 'undefined' ) {
                return this.options[ k ];
            }

            var oid = Slick.uidOf( this );

            if ( typeof QUI.$storage[ oid ] === 'undefined' ) {
                return false;
            }

            if ( typeof QUI.$storage[ oid ][ k ] !== 'undefined' ) {
                return QUI.$storage[ oid ][ k ];
            }

            return false;
        },

        /**
         * @method QUI.classes.DOM#getAllAttributes
         * @see QUI.classes.DOM#getAttributes()
         */
        getAllAttributes : function()
        {
            return this.getAttributes();
        },

        /**
         * Return the default attributes
         *
         * @method QUI.classes.DOM#getAttributes
         * @return {Object}
         */
        getAttributes : function()
        {
            return this.options;
        },

        /**
         * Return true if a attribute exist
         *
         * @method QUI.classes.DOM#existAttribute
         * @param {String} k - wanted attribute
         * @return {Bool}
         */
        existAttribute : function(k)
        {
            if ( typeof this.options[ k ] !== 'undefined' ) {
                return true;
            }

            var oid = Slick.uidOf( this );

            if ( QUI.$storage[ oid ] && QUI.$storage[ oid ][ k ] ) {
                return true;
            }

            return false;
        },

        /**
         * Return the binded functions of the event name
         *
         * @method QUI.classes.DOM#existAttribute
         * @param {String} eventname - wanted event
         * @return {Array|false}
         */
        getEvents : function(eventname)
        {
            if ( typeof this.$events === 'undefined') {
                return false;
            }

            if ( typeof this.$events[ eventname ] !== 'undefined') {
                return this.$events[ eventname ];
            }

            return false;
        }
    });

    return QUI.classes.DOM;
});
