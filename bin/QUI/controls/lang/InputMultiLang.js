/**
 * @module controls/lang/InputMultiLang
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Ajax
 * @require Locale
 * @require css!controls/lang/InputMultiLang.css
 */
define('controls/lang/InputMultiLang', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale',
    'css!controls/lang/InputMultiLang.css'

], function (QUI, QUIControl, QUIAjax, QUILocale) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'controls/lang/InputMultiLang',

        Binds: [
            'toggle',
            '$onImport',
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Container = null;
            this.$Button    = null;
            this.$Input     = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onImport
            });
        },

        create: function () {
            this.$Elm = new Element('input', {
                type : 'hidden',
                value: this.getAttribute('value'),
                name : this.getAttribute('name')
            });

            return this.$Elm;
        },

        /**
         * event : on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm(),
                path = URL_BIN_DIR + '16x16/flags/';

            this.$Button = new Element('span', {
                'class': 'field-container-item quiqqer-inputmultilang-button',
                html   : '<span class="fa fa-spinner fa-spin"></span>',
                styles : {
                    textAlign: 'center',
                    width    : 50
                }
            }).inject(Elm, 'after');

            this.$Container = new Element('div', {
                'class': 'field-container-field'
            }).inject(Elm, 'after');

            this.$Container.addClass(
                'quiqqer-inputmultilang__minimize'
            );

            this.$Input      = Elm;
            this.$Input.type = 'hidden';

            QUIAjax.get('ajax_system_getAvailableLanguages', function (languages) {
                var i, len, flag, lang, LangContainer, InputField;
                var current = QUILocale.getCurrent(),
                    data    = [];

                try {
                    data = JSON.decode(Elm.value);
                } catch (e) {
                    console.error(Elm.value);
                    console.error(e);
                }

                // php <-> js -> array / object conversion fix
                if (typeOf(data) === 'array') {
                    var newData = {};

                    Array.each(data, function (o) {
                        Object.merge(newData, o);
                    });

                    data = newData;
                }

                if (typeOf(data) !== 'object') {
                    data = {};
                }

                // current language to the top
                languages.sort(function (a, b) {
                    if (a === current) {
                        return -1;
                    }

                    if (b === current) {
                        return 1;
                    }

                    return 0;
                });

                var onChange = function () {
                    self.refreshData();
                };

                for (i = 0, len = languages.length; i < len; i++) {
                    lang = languages[i];
                    flag = path + lang + '.png';

                    LangContainer = new Element('div', {
                        'class': 'quiqqer-inputmultilang-entry',
                        html   : '<input type="text" name="' + lang + '" />'
                    }).inject(self.$Container);

                    InputField = LangContainer.getElement('input');
                    InputField.setStyles({
                        backgroundImage: "url('" + flag + "')"
                    });

                    if (i > 0) {
                        LangContainer.setStyles({
                            display: 'none',
                            opacity: 0
                        });
                    }

                    if (lang in data) {
                        if (data.hasOwnProperty(lang)) {
                            InputField.value = data[lang];
                        }
                    }

                    InputField.addEvent('change', onChange);
                }

                if (languages.length <= 1) {
                    self.$Button.setStyle('display', 'none');
                }

                self.$Button.set({
                    html  : '<span class="fa fa-arrow-circle-o-right"></span>',
                    styles: {
                        cursor: 'pointer'
                    }
                });

                self.$Button.addEvent('click', self.toggle);
                self.refreshData();
            });
        },

        /**
         * Return the input value
         * @returns {String}
         */
        getValue: function () {
            return this.$Input.value;
        },

        /**
         * Return the real data
         *
         * @returns {Object}
         */
        getData: function () {
            return JSON.decode(this.getValue());
        },

        /**
         * Set data
         *
         * @param data
         */
        setData: function (data) {
            if (!this.$Input) {
                return;
            }

            if (typeOf(data) !== 'string') {
                data = JSON.encode(data);
            }

            this.$Input.value = data;

            var lang, Input;
            var inputData = this.getData();

            for (lang in inputData) {
                if (!inputData.hasOwnProperty(lang)) {
                    continue;
                }

                Input = this.getElm().getElement('[name="' + lang + '"]');

                if (Input) {
                    Input.value = inputData[lang];
                }
            }
        },

        /**
         * Toggle the open status
         */
        toggle: function () {
            if (this.$Button.getElement('span').hasClass('fa-arrow-circle-o-right')) {
                this.open();
            } else {
                this.close();
            }
        },

        /**
         * shows all translation entries
         */
        open: function () {
            var self = this,
                list = this.$Container.getElements(
                    '.quiqqer-inputmultilang-entry'
                );

            this.$Container.removeClass(
                'quiqqer-inputmultilang__minimize'
            );

            var First = list.shift();

            list.setStyles({
                display: null,
                height : 0
            });

            moofx(First).animate({
                height: 34
            });

            if (list.length) {
                moofx(list).animate({
                    height : 34,
                    opacity: 1
                }, {
                    duration: 200,
                    callback: function () {
                        self.$Button.getElement('span')
                            .addClass('fa-arrow-circle-o-down')
                            .removeClass('fa-arrow-circle-o-right');
                    }
                });
            }
        },

        /**
         * shows all translation entries
         */
        close: function () {
            var self = this,
                list = this.$Container.getElements(
                    '.quiqqer-inputmultilang-entry'
                );

            var First = list.shift();

            First.setStyle('height', null);

            if (!list.length) {
                return;
            }

            moofx(list).animate({
                height : 0,
                opacity: 0
            }, {
                duration: 200,
                callback: function () {
                    self.$Container.addClass(
                        'quiqqer-inputmultilang__minimize'
                    );

                    self.$Button.getElement('span')
                        .removeClass('fa-arrow-circle-o-down')
                        .addClass('fa-arrow-circle-o-right');
                }
            });
        },

        /**
         * Updates the data to the input field
         */
        refreshData: function () {
            var result = {};
            var fields = this.$Container.getElements('input');

            fields.each(function (Field) {
                result[Field.name] = Field.value;
            });

            this.$Input.value = JSON.encode(result);
        }
    });
});
