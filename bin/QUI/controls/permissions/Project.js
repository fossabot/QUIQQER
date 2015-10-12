
/**
 * Permissions Panel -> Project
 *
 * @module controls/permissions/Project
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require controls/permissions/Permission
 * @require qui/controls/buttons/Button
 * @require Locale
 */
define('controls/permissions/Project', [

    'controls/permissions/Permission',
    'qui/controls/buttons/Button',
    'Locale'

], function(Permission, QUIButton, QUILocale)
{
    "use strict";

    return new Class({

        Extends: Permission,
        Type: 'controls/permissions/Project',

        Binds : [
            '$onOpen'
        ],

        initialize : function(Project, options)
        {
            this.parent(Project, options);

            if (typeOf(Project) === 'classes/projects/Project') {
                this.$Bind = Project;
            }

            this.addEvents({
                onOpen : this.$onOpen
            });
        },

        /**
         * User select
         *
         * @returns {Promise}
         */
        $openBindSelect : function()
        {
            var self = this;

            return new Promise(function(resolve, reject) {

                require([
                    'controls/projects/SelectWindow',
                    'Projects'
                ], function(Popup, Projects) {

                    new Popup({
                        events : {
                            onSubmit : function(Popup, data) {

                                self.$Bind = Projects.get(data.project, data.lang);

                                self.$Status.set(
                                    'html',
                                    QUILocale.get('quiqqer/system', 'permission.control.edit.title', {
                                        name : '<span class="fa icon-home"></span>'+
                                               self.$Bind.getName() +' ('+ self.$Bind.getLang() +')'
                                    })
                                );

                                resolve();
                            },

                            onCancel : function() {
                                reject();
                            }
                        }
                    }).open();
                });

            });
        },

        /**
         * event on open
         */
        $onOpen : function()
        {
            new QUIButton({
                text : QUILocale.get('quiqqer/system', 'permission.control.btn.project.save'),
                title : QUILocale.get('quiqqer/system', 'permission.control.btn.project.save'),
                textimage : 'icon-save',
                styles : {
                    'float' : 'right'
                },
                events : {
                    onClick : function(Btn) {

                        Btn.setAttribute(
                            'textimage',
                            'icon-spinner icon-spin fa fa-spinner fa-spin'
                        );

                        this.save().then(function() {
                            Btn.setAttribute('textimage', 'icon-save');
                        });

                    }.bind(this)
                }
            }).inject(this.$Buttons);
        }
    });
});