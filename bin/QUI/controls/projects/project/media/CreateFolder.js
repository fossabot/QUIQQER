/**
 * @module controls/projects/project/media/CreateFolder
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 */
define('controls/projects/project/media/CreateFolder', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'controls/projects/Select',
    'controls/projects/project/media/Sitemap',
    'Projects'

], function (QUI, QUIPopup, QUIButton, ProjectSelect, MediaSitemap, Projects) {
    "use strict";

    return new Class({
        Extends: QUIPopup,
        Type   : 'controls/projects/project/media/CreateFolder',

        Binds: [
            '$onOpen',
            'prev',
            'next'
        ],

        options: {
            project  : false,
            parentId : false,
            maxHeight: 400,
            maxWidth : 600
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title  : 'Neuen Mediaordner anlegen',
                icon   : 'fa fa-plus',
                buttons: true
            });

            this.$Input    = null;
            this.$MediaMap = null;
            this.$step     = '';

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event : on open
         */
        $onOpen: function () {
            this.$Buttons.set('html', '');

            this.$Prev = new QUIButton({
                text    : 'Zurück',
                disabled: true,
                events  : {
                    click: this.prev
                }
            });

            this.$Next = new QUIButton({
                text    : 'Weiter',
                disabled: true,
                events  : {
                    click: this.next
                }
            });

            this.addButton(this.$Next);
            this.addButton(this.$Prev);

            this.showButtons();

            // content
            var Content = this.getContent();

            Content.set({
                html  : '<div class="container-sheet"></div>',
                styles: {
                    position: 'relative'
                }
            });

            this.$Container = Content.getElement('.container-sheet');

            this.$Container.setStyles({
                left    : 0,
                padding : 20,
                position: 'absolute',
                width   : '100%'
            });

            if (this.getAttribute('project') === false) {
                this.showProjectList();
                return;
            }

            this.showMediaSiteMap();
        },

        /**
         * Show the project list
         *
         * @return {Promise}
         */
        showProjectList: function () {
            return this.hideContainer().then(function () {
                var self = this;

                this.$step = 'projectList';

                this.$Container.set(
                    'html',
                    '<p>Bitte wählen Sie ein Project aus in dem der neue ' +
                    'Mediaordner erstellt werden soll.</p>'
                );

                new ProjectSelect({
                    langSelect: false,
                    styles    : {
                        'float': 'none',
                        display: 'block',
                        margin : '10px auto'
                    },
                    events    : {
                        onChange: function (value) {
                            if (value === '') {
                                return;
                            }

                            self.setAttribute('project', value);
                            self.showMediaSiteMap();
                        }
                    }
                }).inject(this.$Container);

                return this.showContainer();
            }.bind(this));
        },

        /**
         * Show the media folder sitemap
         *
         * @return {Promise}
         */
        showMediaSiteMap: function () {
            return this.hideContainer().then(function () {
                var self = this;

                this.$step = 'mediaSitemap';

                this.$Container.set(
                    'html',
                    '<p>Bitte wählen Sie den Elternordner aus.</p>'
                );

                this.$Next.disable();
                this.$Prev.enable();

                this.$MediaMap = new MediaSitemap({
                    project: this.getAttribute('project')
                }).inject(this.$Container);

                this.$MediaMap.getMap().addEvent('onChildClick', function () {
                    var selected = self.$MediaMap.getSelectedChildren();

                    if (selected.length) {
                        self.setAttribute('parentId', selected[0].getAttribute('value'));
                        self.$Next.enable();
                    } else {
                        self.$Next.disable();
                    }
                });

                return this.showContainer();
            }.bind(this));
        },

        /**
         * Show the input for the new name
         *
         * @return {Promise}
         */
        showNameInput: function () {
            return this.hideContainer().then(function () {

                this.$step = 'nameInput';

                if (this.getAttribute('parentId') === false) {
                    return this.showMediaSiteMap();
                }

                this.$Container.set(
                    'html',
                    '<p>Bitte geben Sie ein neuen Namen für den Ordner an.</p>'
                );

                this.$Container.setStyles({
                    textAlign: 'center'
                });

                this.$Input = new Element('input', {
                    type  : 'text',
                    styles: {
                        marginTop: 10,
                        width    : 200
                    }
                }).inject(this.$Container);


                return this.showContainer().then(function () {
                    this.$Input.focus();
                }.bind(this));
            }.bind(this));
        },

        /**
         * Hide the container -> FX
         *
         * @returns {Promise}
         */
        hideContainer: function () {
            return new Promise(function (resolve) {
                moofx(this.$Container).animate({
                    opacity: 0,
                    top    : -20
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Show the container -> FX
         *
         * @returns {Promise}
         */
        showContainer: function () {
            return new Promise(function (resolve) {
                moofx(this.$Container).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * submit the data
         *
         * @return {Promise}
         */
        submit: function () {
            var self     = this,
                newTitle = this.$Input.value;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                if (self.getAttribute('parentId') === false ||
                    self.getAttribute('project') === false) {

                    self.Loader.hide();

                    return self.showMediaSiteMap().then(function () {
                        reject();
                    });
                }

                var parentId = self.getAttribute('parentId'),
                    Project  = Projects.get(self.getAttribute('project')),
                    Media    = Project.getMedia();

                Media.get(parentId).then(function (Folder) {
                    if (Folder.getType() !== 'classes/projects/project/media/Folder') {
                        self.Loader.hide();

                        return reject('File is not a Folder');
                    }

                    Folder.createFolder(newTitle).then(function (result) {

                        self.close().then(function () {
                            self.fireEvent('submit', [self, result]);
                            resolve(result);
                        });

                    }).catch(function (err) {
                        self.Loader.hide();
                        return reject(err);
                    });
                });

            }.bind(this));
        },

        /**
         * Show next step
         *
         * @return {Promise}
         */
        next: function () {
            switch (this.$step) {
                case 'projectList':
                    return Promise.resolve();

                case 'mediaSitemap':
                    return this.showNameInput();

                case 'nameInput':
                    return this.submit();
            }

            return Promise.resolve();
        },

        /**
         * Show previous step
         *
         * @return {Promise}
         */
        prev: function () {
            switch (this.$step) {
                case 'projectList':
                    return Promise.resolve();

                case 'mediaSitemap':
                    this.setAttribute('parentId', false);
                    return this.showProjectList();

                case 'nameInput':
                    return this.showMediaSiteMap();
            }

            return Promise.resolve();
        }
    });
});