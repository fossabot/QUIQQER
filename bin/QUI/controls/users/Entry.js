/**
 * A user field / display
 * the display updates itself
 *
 * @author www.pcsg.de (Henning Leutz)
 *
 * @module controls/users/Entry
 *
 * @require controls/Control
 * @require users
 */

define('controls/users/Entry', [

    'qui/controls/Control',
    'Users',

    'css!controls/users/Entry.css'

], function(QUIControl, Users)
{
    "use strict";

    /**
     * @class controls/users/Entry
     *
     * @param {Integer} uid - user-ID
     * @param {Object} options
     *
     * @memberof! <global>
     */
    return new Class({

        Extends : QUIControl,
        Type    : 'controls/users/Entry',

        Binds : [
            '$onUserUpdate',
            'destroy'
        ],

        initialize : function(uid, options)
        {
            this.$User = Users.get( uid );
            this.parent( options );

            this.$Elm      = null;
            this.$disabled = false;
        },

        /**
         * Return the binded user
         *
         * @return {classes/users/User}
         */
        getUser : function()
        {
            return this.$User;
        },

        /**
         * Create the DOMNode of the entry
         *
         * @return {DOMNode}
         */
        create : function()
        {
            var self = this;

            this.$Elm = new Element('div', {
                'class'      : 'users-entry',
                'data-id'    : this.$User.getId(),
                'data-quiid' : this.getId(),

                html : '<div class="users-entry-icon"></div>' +
                       '<div class="users-entry-text"></div>' +
                       '<div class="users-entry-close icon-remove"></div>'
            });

            var Close = this.$Elm.getElement( '.users-entry-close' );

            Close.addEvent( 'click', function()
            {
                if ( !self.isDisabled() ) {
                    self.destroy();
                }
            });

            Close.set({
                alt   : 'Benutzer entfernen',
                title : 'Benutzer entfernen'
            });

            this.$User.addEvent( 'onRefresh', this.$onUserUpdate );
            this.refresh();

            return this.$Elm;
        },

        /**
         * event : on entry destroy
         */
        $onDestroy : function()
        {
            this.$User.removeEvent( 'refresh', this.$onUserUpdate );
        },

        /**
         * Refresh the data of the users
         *
         * @return {this}
         */
        refresh : function()
        {
            var UserIcon = this.$Elm.getElement( '.users-entry-icon' );

            UserIcon.removeClass( 'icon-user' );
            UserIcon.addClass( 'icon-refresh' );
            UserIcon.addClass( 'icon-spin' );

            if ( this.$User.getAttribute( 'name' ) )
            {
                this.$onUserUpdate( this.$User );
                return this;
            }

            this.$User.load();

            return this;
        },

        /**
         * Update the user name
         *
         * @param {classes/users/User}
         * @return {this}
         */
        $onUserUpdate : function(User)
        {
            if ( !this.$Elm ) {
                return this;
            }

            var UserIcon = this.$Elm.getElement( '.users-entry-icon' );

            UserIcon.addClass( 'icon-user' );
            UserIcon.removeClass( 'icon-refresh' );
            UserIcon.removeClass( 'icon-spin' );

            this.$Elm.getElement( '.users-entry-text' )
                     .set( 'html', User.getName() );

            return this;
        },

        /**
         * Disable the control
         * no changes are posible
         */
        disable : function()
        {
            this.$disabled = true;
        },

        /**
         * Disable the control
         * changes are posible
         */
        enable : function()
        {
            this.$disabled = false;
        },

        /**
         * Is it disabled?
         * if disabled, no changes are possible
         */
        isDisabled : function()
        {
            return this.$disabled;
        }
    });
});