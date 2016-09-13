/**
 * A User Panel
 * Here you can change / edit the user
 *
 * @module controls/users/User
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/windows/Confirm
 * @require controls/grid/Grid
 * @require qui/utils/Form
 * @require utils/Controls
 * @require Users
 * @require Ajax
 * @require Locale
 * @require css!controls/users/User.css
 */
define('controls/users/User', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonSwitch',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'qui/utils/Form',
    'utils/Controls',
    'Users',
    'Ajax',
    'Locale',

    'css!controls/users/User.css'

], function (QUI, QUIPanel, QUIButton, QUIButtonSwitch, QUIConfirm, Grid,
             FormUtils, ControlUtils, Users, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/system';

    /**
     * @class controls/users/User
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIPanel,
        Type   : 'controls/users/User',

        Binds: [
            'openPermissions',
            'savePassword',
            'generatePassword',

            '$onCreate',
            '$onDestroy',
            '$onStatusButtonChange',
            '$onButtonActive',
            '$onButtonNormal',
            '$onUserRefresh',
            '$onUserDelete',
            '$onClickSave',
            '$onClickDel'
        ],

        initialize: function (uid, options) {
            if (typeOf(uid) === 'string' || typeOf(uid) === 'number') {
                this.$User = Users.get(uid);
                this.setAttribute('name', 'user-panel-' + uid);
            }

            this.$AddressGrid = null;

            // defaults
            this.parent(options);

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy,
                onShow   : function () {
                    var Status = this.getButtons('status');

                    if (Status) {
                        Status.resize();
                    }
                }
            });

            Users.addEvent('onDelete', this.$onUserDelete);
        },

        /**
         * Save the group panel to the workspace
         *
         * @return {Object} data
         */
        serialize: function () {
            return {
                attributes: this.getAttributes(),
                userid    : this.getUser().getId(),
                type      : this.getType()
            };
        },

        /**
         * import the saved data form the workspace
         *
         * @param {Object} data
         * @return {Object} this (controls/users/User)
         */
        unserialize: function (data) {
            this.setAttributes(data.attributes);

            this.$User = Users.get(data.userid);

            return this;
        },

        /**
         * Return the user of the panel
         *
         * @return {Object} classes/users/User
         */
        getUser: function () {
            return this.$User;
        },

        /**
         * Opens the user permissions
         */
        openPermissions: function () {
            var Parent = this.getParent(),
                User   = this.getUser();

            require(['controls/permissions/Panel'], function (PermPanel) {
                Parent.appendChild(
                    new PermPanel(null, User)
                );
            });
        },

        /**
         * Create the user panel
         */
        $onCreate: function () {
            var self = this;

            this.Loader.show();

            this.addButton({
                name     : 'userSave',
                text     : QUILocale.get(lg, 'users.user.btn.save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: this.$onClickSave
                }
            });

            this.addButton({
                type: 'seperator'
            });

            this.addButton(
                new QUIButtonSwitch({
                    name    : 'status',
                    text    : QUILocale.get('quiqqer/quiqqer', 'isActivate'),
                    status  : true,
                    disabled: true,
                    events  : {
                        onChange: this.$onStatusButtonChange
                    }
                })
            );

            this.addButton({
                name  : 'userDelete',
                title : QUILocale.get(lg, 'users.user.btn.delete'),
                icon  : 'fa fa-trash-o',
                events: {
                    onClick: this.$onClickDel
                },
                styles: {
                    'float': 'right'
                }
            });

            // permissions
            new QUIButton({
                image : 'fa fa-gears',
                alt   : QUILocale.get(lg, 'users.user.btn.permissions.alt'),
                title : QUILocale.get(lg, 'users.user.btn.permissions.title'),
                events: {
                    onClick: this.openPermissions
                },
                styles: {
                    'float': 'right'
                }
            }).inject(this.getHeader());

            Users.addEvent('onRefresh', this.$onUserRefresh);
            Users.addEvent('onSave', this.$onUserRefresh);
            Users.addEvent('switchStatus', this.$onUserRefresh);


            QUIAjax.get('ajax_users_getCategories', function (result) {
                var i, len;
                var User   = self.getUser(),
                    Status = self.getButtons('status');

                for (i = 0, len = result.length; i < len; i++) {
                    result[i].events = {
                        onActive: self.$onButtonActive,
                        onNormal: self.$onButtonNormal
                    };

                    self.addCategory(result[i]);
                }

                self.setAttribute('icon', 'fa fa-user');

                var Load = Promise.resolve();

                if (User.getAttribute('title') === false) {
                    Load = User.load();
                }

                Load.then(function () {
                    self.setAttribute('title', QUILocale.get(lg, 'users.user.title', {
                        username: User.getAttribute('username')
                    }));

                    Status.enable();

                    if (!User.isActive()) {
                        Status.off();
                        Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
                    } else {
                        Status.on();
                        Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isActivate'));
                    }
                });
            }, {
                uid: this.getUser().getId()
            });
        },

        /**
         * the panel on destroy event
         * remove the binded events
         */
        $onDestroy: function () {
            Users.removeEvent('refresh', this.$onUserRefresh);
            Users.removeEvent('save', this.$onUserRefresh);
            Users.removeEvent('switchStatus', this.$onUserRefresh);
            Users.removeEvent('delete', this.$onUserDelete);
        },

        /**
         * the button active event
         * load the template of the category, parse the controls and insert the values
         *
         * @param {Object} Btn - qui/controls/buttons/Button
         */
        $onButtonActive: function (Btn) {
            var self = this;

            this.Loader.show();

            QUIAjax.get('ajax_users_getCategory', function (result) {
                var Body = self.getBody(),
                    User = self.getUser();

                if (!result) {
                    result = '';
                }

                Body.set('html', '<form>' + result + '</form>');

                // insert the values
                var attributes = User.getAttributes();
                var extras     = JSON.decode(attributes.extra);

                FormUtils.setDataToForm(extras, Body.getElement('form'));
                FormUtils.setDataToForm(attributes, Body.getElement('form'));

                // parse all the controls
                QUI.parse(Body).then(function () {
                    return ControlUtils.parse(Body);
                }).then(function () {

                    // insert the values
                    // var attributes = User.getAttributes();
                    // var extras     = JSON.decode(attributes.extra);
                    //
                    // FormUtils.setDataToForm(attributes, Body.getElement('form'));
                    // FormUtils.setDataToForm(extras, Body.getElement('form'));

                    QUI.Controls.getControlsInElement(Body).each(function (Control) {
                        if (!('setValue' in Control)) {
                            return;
                        }

                        var name = Control.getAttribute('name');

                        if (!name || name === '') {
                            return;
                        }

                        if (name in attributes) {
                            Control.setValue(attributes[name]);
                        }

                        if (extras && name in extras) {
                            Control.setValue(extras[name]);
                        }
                    });

                    // password save
                    var PasswordField  = Body.getElement('input[name="password2"]'),
                        PasswordExpire = Body.getElements('input[name="expire"]'),
                        ShowPasswords  = Body.getElement('input[name="showPasswords"]'),
                        AddressList    = Body.getElement('.address-list');

                    if (PasswordField) {
                        PasswordField.setStyle('float', 'left');

                        new QUIButton({
                            textimage: 'fa fa-lock',
                            text     : QUILocale.get(lg, 'users.user.btn.password.generate'),
                            events   : {
                                onClick: self.generatePassword
                            }
                        }).inject(PasswordField, 'after');

                        ShowPasswords.addEvent('change', function () {
                            var PasswordFields = Body.getElements(
                                '[name="password2"],[name="password"]'
                            );

                            if (this.checked) {
                                PasswordFields.set('type', 'text');
                                return;
                            }

                            PasswordFields.set('type', 'password');
                        });

                        if (ShowPasswords.checked) {
                            ShowPasswords.checked = false;
                        }

                        // has a password?
                        if (!self.getUser().getAttribute('hasPassword')) {
                            new Element('tr', {
                                html: '<td colspan="2">' +
                                      '    <div class="content-message-error">' +
                                      QUILocale.get('quiqqer/quiqqer', 'message.user.has.no.password') +
                                      '    </div>' +
                                      '</td>'
                            }).inject(Body.getElement('tbody'));
                        }
                    }

                    // password expire
                    if (PasswordExpire.length) {
                        var expire = attributes.expire || false;

                        if (!expire || expire == '0000-00-00 00:00:00') {
                            PasswordExpire[0].checked = true;

                        } else {
                            PasswordExpire[1].checked = true;

                            Body.getElement('input[name="expire_date"]').value = expire;
                        }
                    }

                    if (AddressList) {
                        self.$createAddressTable();
                    }


                    if (!Btn.getAttribute('onload_require') && !Btn.getAttribute('onload')) {
                        self.Loader.hide();
                        return;
                    }

                    // require onload
                    try {
                        var exec = Btn.getAttribute('onload'),
                            req  = Btn.getAttribute('onload_require');

                        if (req) {
                            require([req], function (result) {
                                self.Loader.hide();

                                if (typeOf(result) == 'class') {
                                    new result(self);
                                }

                                if (typeOf(result) == 'function') {
                                    result(self);
                                }

                                if (exec) {
                                    eval(exec + '( self )');
                                }
                            });

                            return;
                        }

                        eval(exec + '( self )');

                        self.Loader.hide();

                    } catch (Exception) {
                        console.error('some error occurred ' + Exception.getMessage());
                        self.Loader.hide();
                    }
                });

            }, {
                Tab   : Btn,
                plugin: Btn.getAttribute('plugin'),
                tab   : Btn.getAttribute('name'),
                uid   : this.getUser().getId()
            });
        },

        /**
         * if the button was active and know normal
         * = unload event of the button
         */
        $onButtonNormal: function () {
            var Content = this.getBody(),
                Frm     = Content.getElement('form'),
                data    = FormUtils.getFormData(Frm);

            if (data.expire_date) {
                data.expire = data.expire_date;
            }

            this.getUser().setAttributes(data);
        },

        /**
         * Refresh the Panel if the user is refreshed
         */
        $onUserRefresh: function () {
            this.setAttribute('title', QUILocale.get(lg, 'users.user.title', {
                username: this.getUser().getAttribute('username')
            }));

            var Active = this.getCategoryBar().getActive(),
                Status = this.getButtons('status');

            if (this.getUser().isActive()) {
                Status.setSilentOn();
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isActivate'));
            } else {
                Status.setSilentOff();
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
            }

            this.setAttribute('icon', 'fa fa-user');
            this.refresh();

            if (!Active) {
                Active = this.getCategoryBar().firstChild();
            }

            if (Active) {
                Active.click();
            }
        },

        /**
         * event: of button status on / off changes
         *
         * @param {Object} Button - qui/controls/buttons/ButtonSwitch
         */
        $onStatusButtonChange: function (Button) {
            var buttonStatus = Button.getStatus(),
                User         = this.getUser(),
                userStatus   = User.isActive();

            if (buttonStatus == userStatus) {
                return;
            }

            this.Loader.show();

            var Prom;

            if (buttonStatus) {
                Prom = User.activate();
            } else {
                Prom = User.deactivate();
            }

            Prom.then(function () {
                if (User.isActive()) {
                    Button.on();
                    Button.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isActivate'));
                } else {
                    Button.off();
                    Button.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
                }

                this.Loader.hide();
            }.bind(this));
        },

        /**
         * event on user delete
         *
         * @param {Object} Users - qui/classes/users/Manager
         * @param {Array} uids - user ids, which are deleted
         */
        $onUserDelete: function (Users, uids) {
            var uid = this.getUser().getId();

            for (var i = 0, len = uids.length; i < len; i++) {
                if (uid == uids[i]) {
                    this.destroy();
                    break;
                }
            }
        },

        /**
         * Event: click on save
         *
         * @method controls/users/User#$onClickSave
         */
        $onClickSave: function () {
            var Active = this.getActiveCategory(),
                User   = this.getUser();

            if (Active) {
                this.$onButtonNormal(Active);
            }

            if (this.getUser().getAttribute('password')) {
                this.savePassword(function () {
                    User.save();
                });

                return;
            }

            User.save();
        },

        /**
         * Event: click on delete
         *
         * @method controls/users/User#$onClickDel
         */
        $onClickDel: function () {
            var uid = this.getUser().getId();

            new QUIConfirm({
                name       : 'DeleteUser',
                icon       : 'fa fa-trash-o',
                texticon   : 'fa fa-trash-o',
                title      : QUILocale.get(lg, 'users.user.window.delete.title'),
                text       : QUILocale.get(lg, 'users.user.window.delete.text', {
                    userid  : this.getUser().getId(),
                    username: this.getUser().getName()
                }),
                information: QUILocale.get(lg, 'users.user.window.delete.information'),
                maxWidth   : 600,
                maxHeight  : 400,
                autoclose  : false,
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();
                        Users.deleteUsers([uid]).then(function () {
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        /**
         * Saves the password to the user
         * only triggerd if the password tab are open
         *
         * @method controls/users/User#savePassword
         * @param {Function} callback -  callback function
         */
        savePassword: function (callback) {
            var Control = this,
                Body    = this.getBody(),
                Form    = Body.getElement('form'),
                Pass1   = Form.elements.password,
                Pass2   = Form.elements.password2;

            if (!Pass1 || !Pass2) {
                return;
            }

            this.Loader.show();

            this.getUser().savePassword(
                Pass1.value,
                Pass2.value,
                {},
                function () {
                    Control.Loader.hide();

                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            );
        },

        /**
         * Generate a random password and set it to the password fields
         * it saves not the passwords!!
         */
        generatePassword: function () {
            var Body  = this.getBody(),
                Form  = Body.getElement('form'),
                Pass1 = Form.elements.password,
                Pass2 = Form.elements.password2,
                Show  = Form.elements.showPasswords;

            if (!Pass1 || !Pass2) {
                return;
            }

            var newPassword = Math.random().toString(36).slice(-8);

            Pass1.value = newPassword;
            Pass2.value = newPassword;

            if (!Show.checked) {
                Show.checked = true;
                Show.fireEvent('change');
            }
        },

        /**
         * Addresses
         */

        /**
         * Create the address table
         */
        $createAddressTable: function () {
            var self        = this,
                Content     = this.getContent(),
                size        = Content.getSize(),
                AddressList = Content.getElement('.address-list');

            if (!AddressList) {
                return;
            }

            this.$AddressGrid = new Grid(AddressList, {
                columnModel: [{
                    header   : QUILocale.get(lg, 'id'),
                    dataIndex: 'id',
                    dataType : 'string',
                    width    : 60
                }, {
                    header   : QUILocale.get(lg, 'salutation'),
                    dataIndex: 'salutation',
                    dataType : 'string',
                    width    : 60
                }, {
                    header   : QUILocale.get(lg, 'firstname'),
                    dataIndex: 'firstname',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'lastname'),
                    dataIndex: 'lastname',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'users.user.address.table.phone'),
                    dataIndex: 'phone',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'email'),
                    dataIndex: 'mail',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'company'),
                    dataIndex: 'company',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'street'),
                    dataIndex: 'street_no',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'zip'),
                    dataIndex: 'zip',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'city'),
                    dataIndex: 'city',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'country'),
                    dataIndex: 'country',
                    dataType : 'string',
                    width    : 100
                }],

                buttons: [{
                    name     : 'add',
                    text     : QUILocale.get(lg, 'users.user.address.table.btn.add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function () {
                            self.createAddress();
                        }
                    }
                }, {
                    type: 'seperator'
                }, {
                    name     : 'edit',
                    text     : QUILocale.get(lg, 'users.user.address.table.btn.edit'),
                    textimage: 'fa fa-edit',
                    disabled : true,
                    events   : {
                        onClick: function () {
                            self.editAddress(
                                self.$AddressGrid.getSelectedData()[0].id
                            );
                        }
                    }
                }, {
                    name     : 'delete',
                    text     : QUILocale.get(lg, 'users.user.address.table.btn.delete'),
                    textimage: 'fa fa-remove',
                    disabled : true,
                    events   : {
                        onClick: function () {
                            self.deleteAddress(
                                self.$AddressGrid.getSelectedData()[0].id
                            );
                        }
                    }
                }],

                height   : 300,
                onrefresh: function () {
                    self.$refreshAddresses();
                }
            });

            this.$AddressGrid.addEvents({
                onClick: function () {
                    var buttons = self.$AddressGrid.getButtons(),
                        sels    = self.$AddressGrid.getSelectedIndices();

                    if (!sels) {
                        buttons.each(function (Btn) {
                            if (Btn.getAttribute('name') != 'add') {
                                Btn.disable();
                            }
                        });

                        return;
                    }

                    buttons.each(function (Btn) {
                        Btn.enable();
                    });
                },

                onDblClick: function () {
                    self.editAddress(
                        self.$AddressGrid.getSelectedData()[0].id
                    );
                }
            });

            this.$AddressGrid.setWidth(size.x - 60);
            this.$AddressGrid.refresh();
        },

        /**
         * Load / refresh the adresses
         */
        $refreshAddresses: function () {
            if (!this.$AddressGrid) {
                return;
            }

            var self = this;

            QUIAjax.get('ajax_users_address_list', function (result) {
                self.$AddressGrid.setData({
                    data: result
                });
            }, {
                uid: this.getUser().getId()
            });
        },

        /**
         * Creates a new address and opens the edit control
         */
        createAddress: function () {
            var self = this;

            QUIAjax.post('ajax_users_address_save', function (newId) {
                self.editAddress(newId);
                self.$AddressGrid.refresh();
            }, {
                uid : this.getUser().getId(),
                aid : 0,
                data: JSON.encode([])
            });
        },

        /**
         * Edit an address
         *
         * @param {Number} addressId - ID of the address
         */
        editAddress: function (addressId) {
            var self  = this,
                Sheet = this.createSheet({
                    title: QUILocale.get(lg, 'users.user.address.edit'),
                    icon : 'fa fa-edit'
                });

            Sheet.addEvents({
                onOpen: function (Sheet) {
                    require(['controls/users/Address'], function (Address) {
                        var UserAddress = new Address({
                            addressId: addressId,
                            uid      : self.getUser().getId(),
                            events   : {
                                onSaved: function () {
                                    Sheet.hide();
                                    self.$AddressGrid.refresh();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton({
                            textimage: 'fa fa-save',
                            text     : QUILocale.get(lg, 'save'),
                            events   : {
                                onClick: function () {
                                    UserAddress.save();
                                }
                            }
                        });
                    });
                }
            });

            Sheet.show();
        },

        /**
         * Delete an address
         *
         * @param {Number} addressId - ID of the address
         */
        deleteAddress: function (addressId) {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'users.user.address.window.delete.title'),
                text       : QUILocale.get(lg, 'users.user.address.window.delete.text'),
                information: QUILocale.get(lg, 'users.user.address.window.delete.information'),
                events     : {
                    onSubmit: function () {
                        QUIAjax.post('ajax_users_address_delete', function () {
                            self.$AddressGrid.refresh();
                        }, {
                            aid: addressId,
                            uid: self.getUser().getId()
                        });
                    }
                }
            }).open();
        }

    });
});
