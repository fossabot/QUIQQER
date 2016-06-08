/**
 * Global locale object
 *
 * @module Locale
 * @author www.pcsg.de (Henning Leutz)
 * @require qui/Locale
 */
define(['qui/Locale'], function (QUILocale) {
    "use strict";

    if (window.location.search.match('lang=false')) {
        QUILocale.no_translation = true;
    } else if (window.location.toString().match('_lang_false')) {
        QUILocale.no_translation = true;
    }

    /**
     * Translate a locale code de_DE, en_EN, de_AT
     *
     * @param localeId
     * @returns {string}
     */
    QUILocale.translateCode = function (localeId) {
        var lang    = localeId.split('_')[0],
            country = localeId.split('_')[1];

        var locLang    = QUILocale.get('quiqqer/quiqqer', 'language.' + lang),
            locCountry = QUILocale.get('quiqqer/countries', 'country.' + country);

        return locLang + ' (' + locCountry + ')';
    };

    return QUILocale;
});
