/**
 * Context Menu Seperator
 *
 * @author www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.js.controls.contextmenu
 * @class QUI.controls.contextmenu.Seperator
 */

define('controls/contextmenu/Seperator', [

    'controls/Control',

    'css!controls/contextmenu/Seperator.css'

], function(Control)
{
    QUI.namespace( 'controls.contextmenu' );

    /**
     * @class QUI.controls.contextmenu.Item
     *
     * @fires onClick [this]
     * @fires onMouseDown [this]
     *
     * @param {Object} options
     *
     * @memberof! <global>
     */
    QUI.controls.contextmenu.Seperator = new Class({

        Implements : [ Control ],
        Type       : 'QUI.controls.contextmenu.Seperator',

        options : {
            styles : null
        },

        initialize : function(options)
        {
            this.init( options );

            this.$Elm = null;
        },

        /**
         * Create the DOMNode for the Element
         *
         * @method QUI.controls.contextmenu.Seperator#create
         *
         * @return {DOMNode}
         */
        create : function()
        {
            this.$Elm = new Element('div.qui-context-seperator', {
                'data-quiid' : this.getId()
            });

            if ( this.getAttribute( 'styles' ) ) {
                this.$Elm.setStyles( this.getAttribute( 'styles' ) );
            }

            return this.$Elm;
        },

        /**
         * if the seperator is in a baritem
         * @ignore
         */
        setNormal : function()
        {

        },

        /**
         * if the seperator is in a baritem
         * @ignore
         */
        setActive : function()
        {

        }
    });
});