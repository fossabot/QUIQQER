{
    "name" : "quiqqer/quiqqer",
    "type" : "composer-installer",
    "description" : "A modular based management system written in JavaScript and PHP",
    "version" : "1.0.0",
    "license" : "GPL-3.0+",

    "authors" : [{
        "name": "Henning Leutz",
        "email": "leutz@pcsg.de",
        "homepage": "http://www.pcsg.de",
        "role": "Developer"
    }, {
        "name": "Moritz Scholz",
        "email": "scholz@pcsg.de",
        "homepage": "http://www.pcsg.de",
        "role": "Developer"
    }],

    "support" : {
        "email": "support@pcsg.de",
        "url": "http://www.quiqqer.com"
    },

    "repositories": {$repositories},

    "require": {$REQUIRE},

    "config": {
        "vendor-dir": "{$PACKAGE_DIR}",
        "cache-dir" : "{$VAR_COMPOSER_DIR}"
    },

    "scripts": {
        "post-install-cmd" : [
            "QUI\\Update::onInstall"
        ],

        "post-update-cmd": [
            "QUI\\Update::onUpdate"
        ]
    },

    "autoload": {
        "psr-0" : {
            "QUI" : "{$LIB_DIR}"
        }
    }
}