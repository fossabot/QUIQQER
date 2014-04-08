/**
 * A projects field / display
 * the display updates itself
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @module controls/projects/Entry
 * @package com.pcsg.qui.js.controls.projects
 * @namespace QUI.controls.projects
 *
 * @require controls/Control
 * @require Projects
 */

define('controls/projects/project/Entry', [

    'qui/controls/Control',
    'Projects',

    'css!controls/projects/project/Entry.css'

], function(QUIControl, Projects)
{
    "use strict";

    /**
     * A projects field / display
     *
     * @class controls/projects/project/Entry
     *
     * @param {String} project - Project name
     * @param {String} lang - Project language
     * @param {Object} options
     *
     * @memberof! <global>
     */
    return new Class({

        Extends : QUIControl,
        Type    : 'controls/projects/project/Entry',

        Binds : [
            '$onProjectUpdate',
            '$onInject',
            '$onDestroy'
        ],

        initialize : function(project, lang, options)
        {
            this.parent( options );

            this.$Project = Projects.get( project, lang );

            this.$Elm      = null;
            this.$Close    = null;
            this.$Text     = null;
            this.$Icon     = null;
            this.$IconSpan = null;

            this.addEvents({
                onDestroy : this.$onDestroy,
                onInject  : this.$onInject
            });
        },

        /**
         * Return the binded project
         *
         * @method controls/projects/project/Entry#getProject
         * @return {classes/projects/Project} Binded Project
         */
        getProject : function()
        {
            return this.$Project;
        },

        /**
         * Create the DOMNode of the entry
         *
         * @method controls/projects/project/Entry#create
         * @return {DOMNode} Main DOM-Node Element
         */
        create : function()
        {
            var self = this;

            this.$Elm = new Element('div', {
                'class'        : 'project-entry',
                'data-project' : this.getProject().getName(),
                'data-lang'    : this.getProject().getLang(),

                html : '<div class="project-entry-icon">' +
                           '<span class="icon-home"></span>' +
                       '</div>' +
                       '<div class="project-entry-text"></div>' +
                       '<div class="project-entry-close">' +
                           '<span class="icon-remove"></span>' +
                       '</div>',
                events :
                {
                    mouseover : function() {
                        this.addClass( 'hover' );
                    },
                    mouseout : function() {
                        this.removeClass( 'hover' );
                    }
                }
            });

            this.$Close = this.$Elm.getElement( '.project-entry-close' ),
            this.$Icon  = this.$Elm.getElement( '.project-entry-icon' ),
            this.$Text  = this.$Elm.getElement( '.project-entry-text' );

            this.$IconSpan = this.$Icon.getElement( 'span' );

            this.$Close.addEvent('click', function() {
                self.destroy();
            });

            this.$Close.set({
                alt   : 'Projekt entfernen',
                title : 'Projekt entfernen'
            });

            this.getProject().addEvent( 'onRefresh', this.$onProjectUpdate );
            this.refresh();

            console.log( this.$Elm.getSize() );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject : function()
        {
            var iconWidth  = this.$Icon.getSize().x,
                textWidth  = this.$Text.getSize().x,
                closeWidth = this.$Close.getSize().x;


            this.$Elm.setStyles({
                width : ( iconWidth ).toInt() +
                        ( textWidth ).toInt() +
                        ( closeWidth ).toInt()
            });
        },

        /**
         * event : on entry destroy
         *
         * @method controls/projects/project/Entry#$onDestroy
         */
        $onDestroy : function()
        {
            this.getProject().removeEvent( 'refresh', this.$onProjectUpdate );
        },

        /**
         * Refresh the data of the projects
         *
         * @method controls/projects/project/Entry#refresh
         * @return {this} self
         */
        refresh : function()
        {
            this.$IconSpan.removeClass( 'icon-home' );
            this.$IconSpan.addClass( 'icon-spinner icon-spin' );

            if ( this.getProject().getName() )
            {
                this.$onProjectUpdate( this.getProject() );

                return this;
            }

            this.getProject().load();

            return this;
        },

        /**
         * Update the project name
         *
         * @method controls/projects/project/Entry#$onProjectUpdate
         * @param {classes/projects/Project} Project
         * @return {this} self
         */
        $onProjectUpdate : function(Project)
        {
            if ( !this.$Elm ) {
                return this;
            }

            this.$Text.set( 'html', Project.getName() );

            this.$IconSpan.addClass( 'icon-home' );
            this.$IconSpan.removeClass( 'icon-spinner' );
            this.$IconSpan.removeClass( 'icon-spin' );

            this.$onInject();

            return this;
        }
    });
});