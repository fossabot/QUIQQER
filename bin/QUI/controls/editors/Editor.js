/**
 * Editor Main Class
 *
 * The editor main class is the parent class for all WYSIWYG editors.
 * Every WYSIWYG editor must inherit from this class
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @requires qui/controls/Control
 *
 * @module classes/editor/Editor
 * @package com.pcsg.qui.js.classes.editor
 */

define('controls/editor/Editor', ['qui/controls/Control'], function(Control)
{
    "use strict";

    /**
     * Editor Main Class
     *
     * @class QUI.classes.Editor
     *
     * @param {QUI.controls.editor.Manager} Manager
     * @param {Object} options
     *
     * @fires onInit [this]
     * @fires onDraw [DOMNode, this]
     * @fires onDestroy [this]
     * @fires onSetContent [String, this]
     * @fires onGetContent [this]
     * @fires onLoaded [Editor, Instance]
     *
     * @memberof! <global>
     */
    return new Class({

        Extends : Control,
        Type    : 'controls/editor/Editor',

        Binds : [
            '$onDrop'
        ],

        options : {
            content : ''
        },

        initialize : function(Manager, options)
        {
            this.$Manager = Manager;

            this.parent( options );

            this.$Instance  = null;
            this.$Container = null;

            this.addEvents({
                onLoaded : function(Editor, Instance)
                {
                    if ( Editor.getAttribute( 'content' ) ) {
                        Editor.setContent( Editor.getAttribute( 'content' ) );
                    }
                }
            });

            this.fireEvent( 'init', [ this ] );
        },

        /**
         * Returns the Editor Manager
         *
         * @method QUI.controls.editor.Editor#getManager
         * @return {QUI.controls.editor.Manager} Editor Manager
         */
        getManager : function()
        {
            return this.$Manager;
        },

        /**
         * Draw the editor
         *
         * @method QUI.controls.editor.Editor#draw
         * @fires onDraw [DOMNode, this]
         * @param {DOMNode} Container - The DOMNode in which the editor should be displayed
         */
        draw : function(Container)
        {
            this.$Container = Container;
            this.$Container.addClass( 'media-drop' );
            this.$Container.set( 'data-quiid', this.getId() );

            this.fireEvent( 'draw', [ this.$Container, this ] );
        },

        /**
         * Destroy the editor
         *
         * @method QUI.controls.editor.Editor#destroy
         * @fires onDestroy [this]
         */
        destroy : function()
        {
            this.fireEvent( 'destroy', [ this ] );
            this.removeEvents();

            this.getManager().destroyEditor( this );
        },

        /**
         * Set the content to the editor
         *
         * @method QUI.controls.editor.Editor#setContent
         * @fires onSetContent [content, this]
         * @param {String} content - HTML String
         */
        setContent : function(content)
        {
            this.setAttribute( 'content', content );
            this.fireEvent( 'setContent', [ content, this ] );
        },

        /**
         * Get the content from the editor
         *
         * @method QUI.controls.editor.Editor#getContent
         * @return {String} content
         */
        getContent : function()
        {
            this.fireEvent( 'getContent', [ this ] );

            return this.getAttribute( 'content' );
        },

        /**
         * Set the editor instance
         *
         * @method QUI.controls.editor.Editor#setInstance
         * @param {Editor Instance} Instance
         */
        setInstance : function(Instance)
        {
            this.$Instance = Instance;
        },

        /**
         * Get the editor instance
         * ckeditor, tinymce and so on
         *
         * @method QUI.controls.editor.Editor#getInstance
         * @return {Editor Instance} Instance
         */
        getInstance : function()
        {
            return this.$Instance;
        },

        /**
         * Highlight the editor
         *
         * @method QUI.controls.editor.Editor#highlight
         */
        highlight : function()
        {
            if ( this.$Container ) {
                this.$Container.addClass( 'highlight' );
            }
        },

        /**
         * Normalize the Editor, if the editor was highlighted
         *
         * @method QUI.controls.editor.Editor#normalize
         */
        normalize : function()
        {
            if ( this.$Container ) {
                this.$Container.removeClass( 'highlight' );
            }
        }
    });
});