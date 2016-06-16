/**
 * Project settings panel
 *
 * @module controls/projects/project/Settings
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/windows/Confirm
 * @require qui/utils/Form
 * @require utils/Template
 * @require controls/lang/Popup
 * @require Projects
 * @require Ajax
 * @require Locale
 * @require utils/Controls
 * @require css!controls/projects/project/Settings.css
 */
define('controls/projects/project/Settings', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'utils/Template',
    'controls/lang/Popup',
    'Projects',
    'Ajax',
    'Locale',
    'utils/Controls',
    'package/quiqqer/translator/bin/controls/VariableTranslation',

    'css!controls/projects/project/Settings.css'

], function (QUI,
             QUIPanel,
             QUIButton,
             QUIConfirm,
             QUIFormUtils,
             UtilsTemplate,
             LangPopup,
             Projects,
             Ajax,
             Locale,
             ControlUtils,
             Translation) {
    "use strict";

    var lg = 'quiqqer/system';

    /**
     * The Project settings panel
     *
     * @class controls/projects/project/Settings
     *
     * @param {String} project
     * @param {Object} options
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIPanel,
        Type   : 'controls/projects/project/Settings',

        Binds: [
            '$onCreate',
            '$onResize',
            '$onCategoryEnter',
            '$onCategoryLeave',

            'save',
            'del',
            'openSettings',
            'openCustomCSS',
            'openAdminSettings',
            'openBackup',
            'openMediaSettings'
        ],

        options: {
            project: ''
        },

        initialize: function (options) {
            this.parent(options);

            if (!this.getAttribute('project') && "attributes" in options) {
                this.parent(options.attributes);
            }

            // defaults
            this.$Project = Projects.get(
                this.getAttribute('project')
            );

            this.$config   = {};
            this.$defaults = {};

            this.addEvents({
                onCreate       : this.$onCreate,
                onResize       : this.$onResize,
                onCategoryEnter: this.$onCategoryEnter
            });
        },

        /**
         * Return the Project of the Panel
         *
         * @method controls/projects/project/Settings#getProject
         * @return {Object} classes/projects/Project -  Project of the Panel
         */
        getProject: function () {
            return this.$Project;
        },

        /**
         * Create the project settings body
         *
         * @method controls/projects/project/Settings#$onCreate
         */
        $onCreate: function () {
            var self = this;

            this.Loader.show();
            this.getContent().addClass('qui-project-settings');

            this.addButton({
                text     : Locale.get(lg, 'projects.project.panel.settings.btn.save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: this.save
                }
            });

            this.addButton({
                text     : Locale.get(lg, 'projects.project.panel.settings.btn.remove'),
                textimage: 'fa fa-remove',
                events   : {
                    onClick: this.del
                }
            });

            this.addCategory({
                name  : 'settings',
                text  : Locale.get(lg, 'projects.project.panel.settings.btn.settings'),
                icon  : 'fa fa-gear',
                events: {
                    onClick: this.openSettings
                }
            });

            this.addCategory({
                name  : 'adminSettings',
                text  : Locale.get(lg, 'projects.project.panel.settings.btn.adminSettings'),
                icon  : 'fa fa-gear',
                events: {
                    onClick: this.openAdminSettings
                }
            });

            this.addCategory({
                name  : 'mediaSettings',
                text  : Locale.get(lg, 'projects.project.panel.settings.btn.media'),
                icon  : 'fa fa-picture-o',
                events: {
                    onClick: this.openMediaSettings
                }
            });

            this.addCategory({
                name  : 'customCSS',
                text  : Locale.get(lg, 'projects.project.panel.settings.btn.customCSS'),
                icon  : 'fa fa-css3',
                events: {
                    onClick: this.openCustomCSS
                }
            });

            this.$Container = new Element('div', {
                styles: {
                    left    : 0,
                    height  : '100%',
                    padding : 10,
                    position: 'absolute',
                    top     : 0,
                    width   : '100%'
                }
            }).inject(this.getBody());

            this.getBody().setStyles({
                position: 'relative'
            });


            Ajax.get('ajax_project_panel_categories_get', function (list) {
                for (var i = 0, len = list.length; i < len; i++) {
                    self.addCategory(list[i]);
                }

                self.refresh();

            }, {
                project: this.getProject().encode()
            });
        },

        /**
         * Refresh the project data
         */
        refresh: function () {
            this.parent();
            this.Loader.show();

            var self = this;

            Promise.all([
                this.getProject().getConfig(),
                this.getProject().getDefaults()
            ]).then(function (result) {

                self.setAttributes({
                    name : 'projects-panel',
                    icon : 'fa fa-home',
                    title: self.getProject().getName()
                });

                self.$Title.set('html', self.getAttribute('title'));

                self.$config   = result[0];
                self.$defaults = result[1];

                self.getCategoryBar().firstChild().click();
                self.Loader.hide();
            });
        },

        /**
         * Save the project settings
         */
        save: function () {
            var self = this;

            this.Loader.show();
            this.$onCategoryLeave(false);

            var Project = this.getProject();

            // clear config for projects
            var name = Project.getName();

            for (var project in Projects.$projects) {
                if (!Projects.$projects.hasOwnProperty(project)) {
                    continue;
                }

                if (project.match(name + '-')) {
                    if ("$config" in Projects.$projects[project]) {
                        Projects.$projects[project].$config = false;
                    }
                }
            }

            var callback = function () {
                self.Loader.hide();
            };

            console.log(this.$config);

            Project.setConfig(this.$config).then(callback).catch(callback);
        },

        /**
         * Opens the delete dialog
         */
        del: function () {
            var self = this;

            new QUIConfirm({
                icon       : 'fa fa-exclamation-circle',
                title      : Locale.get(lg, 'projects.project.project.delete.window.title'),
                text       : Locale.get(lg, 'projects.project.project.delete.window.text'),
                texticon   : 'fa fa-exclamation-circle',
                information: Locale.get(lg, 'projects.project.project.delete.window.information'),
                maxWidth   : 450,
                maxHeight  : 300,
                events     : {
                    onSubmit: function () {
                        new QUIConfirm({
                            icon     : 'fa fa-exclamation-circle',
                            title    : Locale.get(lg, 'projects.project.project.delete.window.title'),
                            text     : Locale.get(lg, 'projects.project.project.delete.window.text.2'),
                            texticon : 'fa fa-exclamation-circle',
                            maxWidth : 450,
                            maxHeight: 300,
                            events   : {
                                onSubmit: function () {
                                    Projects.deleteProject(self.$Project.getName(), function () {
                                        self.destroy();
                                    });
                                }
                            }
                        }).open();
                    }
                }
            }).open();
        },

        /**
         * Opens the Settings
         *
         * @method controls/projects/project/Settings#openSettings
         *
         * @return {Promise}
         */
        openSettings: function () {
            var self = this,
                Body = this.$Container;

            return new Promise(function (resolve) {

                self.$hideBody().then(function () {

                    Ajax.get('ajax_project_panel_settings', function (result) {
                        Body.set('html', result);

                        // set data
                        var Form      = Body.getElement('Form'),
                            Standard  = Form.elements.country,
                            Template  = Form.elements.template,
                            Langs     = Form.elements.countries,
                            countries = self.$config.countries;

                        try {
                            countries = JSON.decode(countries);
                        } catch (e) {
                            countries = {};
                        }

                        var i, countryTL;

                        for (i in countries) {
                            if (!countries.hasOwnProperty(i)) {
                                continue;
                            }

                            countryTL = Locale.get('quiqqer/countries', 'country.' + i.toUpperCase());

                            new Element('option', {
                                html : countryTL,
                                value: i.toUpperCase()
                            }).inject(Standard);

                            new Element('option', {
                                html : countryTL + ': ' + Locale.translateCode(countries[i]),
                                value: i + ':' + countries[i]
                            }).inject(Langs);
                        }

                        // prefix
                        new Translation({
                            'group': 'project/' + self.$Project.getName(),
                            'var'  : 'template.prefix',
                            'type' : 'php,js'
                        }).inject(
                            Body.getElement('.prefix-settings-container')
                        );

                        // suffix
                        new Translation({
                            'group': 'project/' + self.$Project.getName(),
                            'var'  : 'template.suffix',
                            'type' : 'php,js'
                        }).inject(
                            Body.getElement('.suffix-settings-container')
                        );

                        new QUIButton({
                            text     : Locale.get(lg, 'projects.project.panel.btn.addlanguage'),
                            textimage: 'fa fa-plus',
                            styles   : {
                                width: 200,
                                clear: 'both'
                            },
                            events   : {
                                onClick: function () {
                                    self.openLanguageDialog();
                                }
                            }
                        }).inject(Langs, 'after');

                        Langs.addEvent('dblclick', function () {
                            self.openLanguageDialog(this.value);
                        });

                        QUIFormUtils.setDataToForm(self.$config, Form);

                        Standard.value = self.$config.country.toUpperCase();
                        Template.value = self.$config.template;

                        Promise.all([
                            QUI.parse(Body),
                            ControlUtils.parse(Body)
                        ]).then(function () {

                            QUI.Controls.getControlsInElement(Body).each(function (Control) {
                                if ("setProject" in Control) {
                                    Control.setProject(self.$Project);
                                }
                            });

                            self.$showBody().then(resolve);
                        });

                    }, {
                        project: self.getProject().encode()
                    });

                });
            });
        },

        /**
         * Opens the Settings for the administration
         *
         * @method controls/projects/project/Settings#openAdminSettings
         *
         * @return {Promise}
         */
        openAdminSettings: function () {
            return new Promise(function (resolve) {

                var self = this,
                    Body = this.$Container;

                this.$onCategoryLeave().then(function () {

                    UtilsTemplate.get('project/settingsAdmin', function (result) {
                        Body.set('html', result);

                        QUIFormUtils.setDataToForm(self.$config, Body.getElement('Form'));

                        self.$showBody().then(resolve);
                    });
                });

            }.bind(this));
        },

        /**
         * Open Custom CSS
         *
         * @return {Promise}
         */
        openCustomCSS: function () {
            var self = this;

            return this.$onCategoryLeave().then(function () {

                return new Promise(function (resolve) {
                    self.$Container.set('html', '<form></form>');

                    require([
                        'controls/projects/project/settings/CustomCSS'
                    ], function (CustomCSS) {
                        var css  = false,
                            Form = self.getBody().getElement('form');

                        if ("project-custom-css" in self.$config) {
                            css = self.$config["project-custom-css"];
                        }

                        new CustomCSS({
                            Project: self.getProject(),
                            css    : css,
                            events : {
                                onLoad: function () {
                                    self.$showBody().then(resolve);
                                }
                            }
                        }).inject(Form);

                        Form.setStyles({
                            'float': 'left',
                            height : '100%',
                            width  : '100%'
                        });
                    });
                });
            });
        },

        /**
         * Opens the Media Settings
         *
         * @method controls/projects/project/Settings#openMediaSettings
         *
         * @return {Promise}
         */
        openMediaSettings: function () {
            return this.$onCategoryLeave().then(function () {
                var self      = this,
                    Container = this.$Container;

                Container.set('html', '');

                return new Promise(function (resolve) {

                    require([
                        'controls/projects/project/settings/Media'
                    ], function (MediaSettings) {
                        new MediaSettings({
                            config : self.$config,
                            Project: self.$Project,
                            events : {
                                onLoad: function () {
                                    self.$showBody().then(resolve);
                                }
                            }
                        }).inject(Container);
                    });

                });
            }.bind(this));
        },

        /**
         * Open the language / country assignment window
         *
         * @param {String} [countryCode] - edit the country code
         */
        openLanguageDialog: function (countryCode) {
            var self  = this,
                title = 'Sprache hinzufügen';

            if (typeof countryCode !== 'undefined') {
                title = 'Sprache editieren';
            }

            new QUIConfirm({
                icon     : URL_BIN_DIR + '16x16/flags/default.png',
                title    : title,
                maxHeight: 300,
                maxWidth : 450,
                autoclose: false,
                events   : {
                    onOpen  : function (Win) {
                        Win.Loader.show();

                        Win.getContent().set({
                            'class': 'qui-project-settings-edit',
                            html   : '<label>' +
                                     '     <span class="qui-project-settings-edit-title">Land</span>' +
                                     '     <select name="country"></select>' +
                                     '</label>' +
                                     '<label>' +
                                     '     <span class="qui-project-settings-edit-title">Zugwiesene Sprache</span>' +
                                     '     <select name="languages"></select>' +
                                     '</label>'
                        });

                        Ajax.get([
                            'ajax_system_getAvailableLanguages',
                            'ajax_system_getCountries'
                        ], function (languages, countries) {
                            var Content   = Win.getContent(),
                                Country   = Content.getElement('[name="country"]'),
                                Languages = Content.getElement('[name="languages"]');

                            if (typeof countryCode !== 'undefined') {
                                var code = countryCode.split(':')[0].toUpperCase();

                                new Element('option', {
                                    value: code,
                                    html : Locale.get('quiqqer/countries', 'country.' + code)
                                }).inject(Country);

                                Country.disabled = true;
                            } else {
                                countries.sort(function (a, b) {
                                    var nameA = Locale.get('quiqqer/countries', 'country.' + a);
                                    var nameB = Locale.get('quiqqer/countries', 'country.' + b);

                                    if (nameA > nameB) {
                                        return 1;
                                    }

                                    if (nameA < nameB) {
                                        return -1;
                                    }

                                    return 0;
                                });

                                for (i = 0, len = countries.length; i < len; i++) {
                                    new Element('option', {
                                        value: countries[i],
                                        html : Locale.get('quiqqer/countries', 'country.' + countries[i]) +
                                               ' (' + countries[i] + ')'
                                    }).inject(Country);
                                }
                            }

                            for (var i = 0, len = languages.length; i < len; i++) {
                                new Element('option', {
                                    value: languages[i],
                                    html : Locale.translateCode(languages[i])
                                }).inject(Languages);
                            }

                            if (typeof countryCode !== 'undefined') {
                                Languages.value = countryCode.split(':')[1];
                            }

                            Win.Loader.hide();
                        });
                    },
                    onSubmit: function (Win) {
                        var Content   = Win.getContent(),
                            Country   = Content.getElement('[name="country"]'),
                            Languages = Content.getElement('[name="languages"]'),
                            Form      = self.getElm().getElement('form'),
                            Countries = Form.elements.countries;

                        if (!Countries) {
                            return;
                        }

                        var country  = Country.value;
                        var language = Languages.value;
                        var value    = country + ':' + language;
                        var html     = Locale.get('quiqqer/countries', 'country.' + country.toUpperCase()) + ': ' +
                                       Locale.translateCode(language);

                        // add language
                        if (typeof countryCode === 'undefined') {
                            new Element('option', {
                                value: value,
                                html : html
                            }).inject(Countries);

                            return;
                        }

                        var Option = Countries.getElement('[value="' + countryCode + '"]');

                        Option.set({
                            value: value,
                            html : html
                        });

                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * event : on panel resize
         *
         * @method controls/projects/project/Settings#$onResize
         */
        $onResize: function () {

        },

        /**
         * unload the category and set the values into the config
         *
         * @param {Boolean} [noHide] - hide effect, default = true
         * @return {Promise}
         */
        $onCategoryLeave: function (noHide) {
            var Content = this.getContent(),
                Form    = Content.getElement('form');

            if (!Form) {
                return Promise.resolve();
            }

            noHide = noHide || true;

            var data = QUIFormUtils.getFormData(Form);

            for (var i in data) {
                if (data.hasOwnProperty(i)) {
                    this.$config[i] = data[i];
                }
            }

            // exist langs?
            if (typeof Form.elements.langs !== 'undefined') {
                var Langs = Form.elements.langs,
                    langs = Langs.getElements('option').map(function (Elm) {
                        return Elm.value;
                    });

                this.$config.langs = langs.join(',');
            }

            if (typeof Form.elements.countries !== 'undefined') {
                var Countries = Form.elements.countries,
                    countries = {};

                Countries.getElements('option').each(function (Option) {
                    countries[Option.value.split(':')[0]] = Option.value.split(':')[1];
                });

                this.$config.countries = JSON.encode(countries);
            }

            if (noHide === true) {
                return Promise.resolve();
            }

            if (typeOf(this.$config.langs) === 'array') {
                this.$config.langs = this.$config.langs.join(',');
            }

            return this.$hideBody();
        },

        /**
         * Add a language to the project
         *
         * @param {String} lang
         * @deprecated
         */
        addLangToProject: function (lang) {
            var self = this;

            self.Loader.show();

            this.$Project.getConfig(function (config) {
                var langs = config.langs.split(',');
                langs.push(lang);

                self.$Project.setConfig({
                    langs: langs.join(',')
                }).then(function () {
                    // self.Loader.hide();
                    self.refresh();
                }).catch(function () {
                    // self.Loader.hide();
                    self.refresh();
                });
            });
        },

        /**
         * event : on category enter
         *
         * @param {Object} Panel - qui/controls/desktop/Panel
         * @param {Object} Category - qui/controls/buttons/Button
         *
         * @return {Promise}
         */
        $onCategoryEnter: function (Panel, Category) {
            var self = this,
                name = Category.getAttribute('name');

            switch (name) {
                case "settings":
                case "adminSettings":
                case "customCSS":
                case "mediaSettings":
                    return Promise.resolve(1);
            }

            this.$onCategoryLeave().then(function () {
                this.$Container.set('html', '');

                return new Promise(function (resolve) {

                    Ajax.get('ajax_settings_category', function (result) {
                        var Body = self.$Container;

                        if (!result) {
                            result = '';
                        }

                        Body.set('html', '<form>' + result + '</form>');
                        Body.getElements('tr td:first-child').addClass('first');

                        var Form = Body.getElement('form');

                        Form.name = Category.getAttribute('name');
                        Form.addEvent('submit', function (event) {
                            event.stop();
                        });

                        // set data to the form
                        QUIFormUtils.setDataToForm(self.$config, Form);

                        Form.getElements('input').each(function (Input) {
                            var name = Input.get('name');
                            if (name in self.$defaults) {
                                Input.set('data-qui-options-defaultcolor', self.$defaults[name]);
                            }
                        });

                        Promise.all([
                            QUI.parse(Body),
                            ControlUtils.parse(Body)
                        ]).then(function () {
                            var i, len, Control;
                            var quiids = Body.getElements('[data-quiid]');

                            for (i = 0, len = quiids.length; i < len; i++) {
                                Control = QUI.Controls.getById(quiids[i].get('data-quiid'));

                                if (!Control) {
                                    continue;
                                }

                                if (typeOf(Control.setProject) == 'function') {
                                    Control.setProject(self.getProject());
                                }
                            }

                            self.$showBody().then(resolve);
                        });

                    }, {
                        file    : Category.getAttribute('file'),
                        category: Category.getAttribute('name')
                    });

                });
            }.bind(this));
        },

        /**
         * Hide the body
         *
         * @returns {Promise}
         */
        $hideBody: function () {
            return new Promise(function (resolve) {
                moofx(this.$Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Show the body
         *
         * @returns {Promise}
         */
        $showBody: function () {
            var Body = this.$Container;

            Body.setStyles({
                top: -50
            });

            return new Promise(function (resolve) {
                moofx(Body).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        }
    });
});
