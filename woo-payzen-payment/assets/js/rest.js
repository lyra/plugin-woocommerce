/**
 * Copyright © Lyra Network and contributors.
 * This file is part of PayZen plugin for WooCommerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @author    Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @copyright Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL v2)
 */

/**
 * REST API JS tools.
 */

jQuery(function() {
    if (typeof IDENTIFIER_FORM_TOKEN !== "undefined") {
        payzenUpdateFormToken(true);
    }

    setTimeout(function() {
        jQuery('.kr-payment-button').click(function(e) {
            jQuery('.kr-form-error').html('');
        });
    }, 0);
});

var PAYZEN_DFAULT_MESSAGES = ['CLIENT_300', 'CLIENT_304', 'CLIENT_502', 'PSP_539']; // Use default messages for these errors.
var PAYZEN_EXPIRY_ERRORS = ['PSP_108', 'PSP_136', 'PSP_649'];

var payzenInitRestEvents = function(KR) {
    KR.onError(function(e) {
        jQuery('#payzenstd_rest_processing').css('display', 'none');
        jQuery('form.checkout').removeClass('processing').unblock();
        jQuery('#order_review').unblock();

        jQuery('form.wc-block-components-form wc-block-checkout__form').unblock();
        jQuery('.wc-block-components-checkout-place-order-button').prop("disabled", false);

        if (typeof window.PAYZEN_BUTTON_TEXT != 'undefined') {
            jQuery('.wc-block-components-button__text').text("").text(window.PAYZEN_BUTTON_TEXT);
        }

        var msg = '';
        if (PAYZEN_DFAULT_MESSAGES.indexOf(e.errorCode) > -1) {
            msg = e.errorMessage;
            var endsWithDot = (msg.lastIndexOf('.') == (msg.length - 1) && msg.lastIndexOf('.') >= 0);

            msg += (endsWithDot ? '' : '.');
        } else {
            msg = payzenTranslate(e.errorCode);
        }

        // Expiration errors, display a link to refresh the page.
        if (PAYZEN_EXPIRY_ERRORS.indexOf(e.errorCode) >= 0) {
            msg += ' <a href="#" onclick="window.location.reload(); return false;">'
                + payzenTranslate('RELOAD_LINK') + '</a>';
        }

        jQuery('.kr-form-error').html('<span style="color: red;"><span>' + msg + '</span></span>');
        jQuery('ul.payzenstd-view-bottom').show();
    });

    KR.onFocus(function(e) {
        jQuery('.kr-form-error').html('');
    });
};

var payzenDrawRestPaymentFields = function(formToken, first) {
    var cardsForm = "";
    if (PAYZEN_CARDS_FORM == true) {
        cardsForm = '<div class="kr-pan"></div><div class="kr-expiry"></div><div class="kr-security-code"></div>';

        if (PAYZEN_POPIN_ATTR) {
            cardsForm += '<button class="kr-payment-button"></button>';
        } else {
            cardsForm += '<div style="display: none;"><button class="kr-payment-button"></button></div>';
        }
    }

    var sfStyle = (PAYZEN_HIDE_SINGLE_BUTTON == true) ? 'style="width: 100%;"' : '';
    var fields = '<div class="' + PAYZEN_KR_MODE + '" ' + PAYZEN_SINGLE_PAYMENT_BUTTON_MODE + ' ' + PAYZEN_SINGLE_PAYMENT_BUTTON_MODE + ' ' + PAYZEN_SF_EXTENDED_MODE + ' ' + PAYZEN_POPIN_ATTR + ' ' + sfStyle + ' > ' + cardsForm + ' <div class="kr-form-error"></div> <div id="payzenstd_rest_processing" class="kr-field processing" style="display: none; border: none; z-index: -1;"> <div style="background-image: url(' + PAYZEN_IMG_URL + '); margin: 0 auto; display: block; height: 35px; background-position: center; background-repeat: no-repeat; background-size: 35px;"></div></div></div>';

    jQuery("#payzenstd_rest_wrapper").html(fields);
    KR.renderElements();

    var payzenFormConfig = { language: PAYZEN_LANGUAGE, formToken: formToken };
    if (PAYZEN_HIDE_SINGLE_BUTTON == true) {
        payzenFormConfig['form'] = { smartform: { singlePaymentButton: { visibility: false }}};
    }

    if (PAYZEN_SF_COMPACT_MODE == true) {
        payzenFormConfig['cardForm'] = { layout: 'compact' };
        payzenFormConfig['smartForm'] = { layout: 'compact' };
    }

    if (PAYZEN_GROUPING_THRESHOLD != 'false') {
        payzenFormConfig['smartForm']['groupingThreshold'] = PAYZEN_GROUPING_THRESHOLD;
    }

    setTimeout(function () {
        KR.setFormConfig(payzenFormConfig).then(function(v) {
            if (first) {
                payzenInitRestEvents(v.KR);
            }
        });
    }, 300);
};

var payzenUpdateFormToken = function(useIdentifier) {
    var formToken = FORM_TOKEN;

    if (typeof IDENTIFIER_FORM_TOKEN !== "undefined" && useIdentifier) {
        formToken = IDENTIFIER_FORM_TOKEN;
    }

    payzenDrawRestPaymentFields(formToken, ! KR || ! KR.vueReady);
};

// Translate error message.
var payzenTranslate = function(code) {
    var lang = PAYZEN_LANGUAGE; // Global variable that contains current language.
    var messages = PAYZEN_ERROR_MESSAGES.hasOwnProperty(lang) ? PAYZEN_ERROR_MESSAGES[lang] : PAYZEN_ERROR_MESSAGES['en'];

    if (! messages.hasOwnProperty(code)) {
        var index = code.lastIndexOf('_');
        code = code.substring(0, index + 1) + '999';
    }

    return messages[code];
};

var PAYZEN_ERROR_MESSAGES = {
    fr: {
        RELOAD_LINK: 'Veuillez rafraîchir la page.',
        CLIENT_001: 'Le paiement est refusé. Essayez de payer avec une autre carte.',
        CLIENT_101: 'Le paiement est annulé.',
        CLIENT_301: 'Le numéro de carte est invalide. Vérifiez le numéro et essayez à nouveau.',
        CLIENT_302: 'La date d\'expiration est invalide. Vérifiez la date et essayez à nouveau.',
        CLIENT_303: 'Le code de sécurité CVV est invalide. Vérifiez le code et essayez à nouveau.',
        CLIENT_999: 'Une erreur technique est survenue. Merci de réessayer plus tard.',

        INT_999: 'Une erreur technique est survenue. Merci de réessayer plus tard.',

        PSP_003: 'Le paiement est refusé. Essayez de payer avec une autre carte.',
        PSP_099: 'Trop de tentatives ont été effectuées. Merci de réessayer plus tard.',
        PSP_108: 'Le formulaire a expiré.',
        PSP_999: 'Une erreur est survenue durant le processus de paiement.',

        ACQ_001: 'Le paiement est refusé. Essayez de payer avec une autre carte.',
        ACQ_999: 'Une erreur est survenue durant le processus de paiement.'
    },

    en: {
        RELOAD_LINK: 'Please refresh the page.',
        CLIENT_001: 'Payment is refused. Try to pay with another card.',
        CLIENT_101: 'Payment is cancelled.',
        CLIENT_301: 'The card number is invalid. Please check the number and try again.',
        CLIENT_302: 'The expiration date is invalid. Please check the date and try again.',
        CLIENT_303: 'The card security code (CVV) is invalid. Please check the code and try again.',
        CLIENT_999: 'A technical error has occurred. Please try again later.',

        INT_999: 'A technical error has occurred. Please try again later.',

        PSP_003: 'Payment is refused. Try to pay with another card.',
        PSP_099: 'Too many attempts. Please try again later.',
        PSP_108: 'The form has expired.',
        PSP_999: 'An error has occurred during the payment process.',

        ACQ_001: 'Payment is refused. Try to pay with another card.',
        ACQ_999: 'An error has occurred during the payment process.'
    },

    de: {
        RELOAD_LINK: 'Bitte aktualisieren Sie die Seite.',
        CLIENT_001: 'Die Zahlung wird abgelehnt. Versuchen Sie, mit einer anderen Karte zu bezahlen.',
        CLIENT_101: 'Die Zahlung wird storniert.',
        CLIENT_301: 'Die Kartennummer ist ungültig. Bitte überprüfen Sie die Nummer und versuchen Sie es erneut.',
        CLIENT_302: 'Das Verfallsdatum ist ungültig. Bitte überprüfen Sie das Datum und versuchen Sie es erneut.',
        CLIENT_303: 'Der Kartenprüfnummer (CVC) ist ungültig. Bitte überprüfen Sie den Nummer und versuchen Sie es erneut.',
        CLIENT_999: 'Ein technischer Fehler ist aufgetreten. Bitte Versuchen Sie es später erneut.',

        INT_999: 'Ein technischer Fehler ist aufgetreten. Bitte Versuchen Sie es später erneut.',

        PSP_003: 'Die Zahlung wird abgelehnt. Versuchen Sie, mit einer anderen Karte zu bezahlen.',
        PSP_099: 'Zu viele Versuche. Bitte Versuchen Sie es später erneut.',
        PSP_108: 'Das Formular ist abgelaufen.',
        PSP_999: 'Ein Fehler ist während dem Zahlungsvorgang unterlaufen.',

        ACQ_001: 'Die Zahlung wird abgelehnt. Versuchen Sie, mit einer anderen Karte zu bezahlen.',
        ACQ_999: 'Ein Fehler ist während dem Zahlungsvorgang unterlaufen.'
    },

    es: {
        RELOAD_LINK: 'Por favor, actualice la página.',
        CLIENT_001: 'El pago es rechazado. Intenta pagar con otra tarjeta.',
        CLIENT_101: 'Se cancela el pago.',
        CLIENT_301: 'El número de tarjeta no es válido. Por favor, compruebe el número y vuelva a intentarlo.',
        CLIENT_302: 'La fecha de caducidad no es válida. Por favor, compruebe la fecha y vuelva a intentarlo.',
        CLIENT_303: 'El código de seguridad de la tarjeta (CVV) no es válido. Por favor revise el código y vuelva a intentarlo.',
        CLIENT_999: 'Ha ocurrido un error técnico. Por favor, inténtelo de nuevo más tarde.',

        INT_999: 'Ha ocurrido un error técnico. Por favor, inténtelo de nuevo más tarde.',

        PSP_003: 'El pago es rechazado. Intenta pagar con otra tarjeta.',
        PSP_099: 'Demasiados intentos. Por favor, inténtelo de nuevo más tarde.',
        PSP_108: 'El formulario ha expirado.',
        PSP_999: 'Ocurrió un error en el proceso de pago.',

        ACQ_001: 'El pago es rechazado. Intenta pagar con otra tarjeta.',
        ACQ_999: 'Ocurrió un error en el proceso de pago.'
    },

    pt: {
        RELOAD_LINK: 'Por favor, atualize a página.',
        CLIENT_001: 'O pagamento é rejeitado. Tente pagar com outro cartão.',
        CLIENT_101: 'O pagamento é cancelado.',
        CLIENT_301: 'O número do cartão é inválido. Por favor, cheque o número e tente novamente.',
        CLIENT_302: 'A data de expiração é inválida. Verifique a data e tente novamente.',
        CLIENT_303: 'O código de segurança do cartão (CVV) é inválido. Verifique o código e tente novamente.',
        CLIENT_999: 'Ocorreu um erro técnico. Por favor, tente novamente mais tarde.',

        INT_999: 'Ocorreu um erro técnico. Por favor, tente novamente mais tarde.',

        PSP_003: 'O pagamento é rejeitado. Tente pagar com outro cartão.',
        PSP_099: 'Muitas tentativas. Por favor, tente novamente mais tarde.',
        PSP_108: 'O formulário expirou.',
        PSP_999: 'Ocorreu um erro no processo de pagamento.',

        ACQ_001: 'O pagamento é rejeitado. Tente pagar com outro cartão.',
        ACQ_999: 'Ocorreu um erro no processo de pagamento.'
    }
};
