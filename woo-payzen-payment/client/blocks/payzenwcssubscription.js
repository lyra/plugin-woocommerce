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
 * External dependencies.
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies.
 */
import { getPayzenServerData } from './payzen-utils';

const PAYMENT_METHOD_NAME = 'payzenwcssubscription';
var payzen_data = getPayzenServerData(PAYMENT_METHOD_NAME);

var submitButton = '.wc-block-components-checkout-place-order-button';
var smartbuttonMethod = '';
var smartbuttonAll = false;
var hideSmart = payzen_data?.hide_smartbutton && (payzen_data?.hide_smartbutton === 'true');
var hideButton = false;

var savedData = false;
var newData = null;

const Content = () => {
    if (payzen_data?.payment_fields) {
        var fields = <div dangerouslySetInnerHTML={{ __html: payzen_data?.payment_fields }} />;

        return (
            <div>
                { fields }
            </div>
        );
    } else {
        return decodeEntities(payzen_data?.description);
    }
};

var Label = () => {
    const styles = {
        divWidth: {
            width: '95%'
        },
        imgFloat: {
            float: 'right'
        }
    }

    return (
        <div style={ styles.divWidth }>
            <span>{ payzen_data?.title }</span>
            <img
                style={ styles.imgFloat }
                src={ payzen_data?.logo_url }
                alt={ payzen_data?.title }
            />
        </div>
    );
};

registerPaymentMethod({
    name: PAYMENT_METHOD_NAME,
    label: <Label />,
    ariaLabel: 'Payzen payment method',
    canMakePayment: () => true,
    content: <Content />,
    edit: <Content />,
    supports: {
        features: payzen_data?.supports ?? [],
    },
});

var displayFields = function () {
    if (jQuery(submitButton).length == 0) {
        return;
    }

    if (! jQuery("#radio-control-wc-payment-method-options-payzenwcssubscription").is(":checked")) {
        return;
    }

    if (payzen_data?.vars) {
        delete(window.FORM_TOKEN);
        delete(window.PAYZEN_HIDE_SINGLE_BUTTON);

        window.PAYZEN_BUTTON_TEXT = jQuery(submitButton).text();
        eval(payzen_data?.vars);

        hideButton = (window.PAYZEN_HIDE_SINGLE_BUTTON == true);

        KR.onFormReady(() => {
            if (hideSmart) {
                let element = jQuery(".kr-smart-button");
                if (element.length > 0) {
                    smartbuttonMethod = element.attr("kr-payment-method");
                    element.hide();
                } else {
                    element = jQuery(".kr-smart-form-modal-button");
                    if (element.length > 0) {
                        smartbuttonMethod = "all";
                        element.hide();
                    }
                }
            }
        });
    }

    payzenUpdatePaymentBlock(true, PAYMENT_METHOD_NAME);
};

var onButtonClick = function (e) {
    if (! jQuery("#radio-control-wc-payment-method-options-" + PAYMENT_METHOD_NAME).is(":checked")) {
        return true;
    }

    // In case of form validation error, let WooCommerce deal with it.
    if (jQuery('div.wc-block-components-validation-error')[0]) {
        return true;
    }

    jQuery('.kr-form-error').html('');
    window.PAYZEN_BUTTON_TEXT = jQuery(submitButton).text();

    document.cookie = 'payzenwcssubscription_force_redir=; Max-Age=0; path=/; domain=' + location.host;
    if (typeof window.FORM_TOKEN == 'undefined') {
        document.cookie = 'payzenwcssubscription_force_redir="true"; path=/; domain=' + location.host;
        return true;
    }

    block();

    if (! hideButton && ! hideSmart) {
        validateKR(KR);
    } else {
        submitForm(KR);
    }

    e.preventDefault();
};

var submitForm = function (KR) {
    jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        newData = options.data;
    });

    var registerCard = jQuery('input[name="kr-do-register"]').is(':checked');

    if (savedData && (newData === savedData)) {
        // Data in checkout page has not changed, no need to calculate token again.
        submitKR(KR);
    } else {
        savedData = newData;
        jQuery.ajax({
            method: 'POST',
            url: payzen_data?.token_url,
            data: { 'use_identifier': 0 },
            success: function(data) {
                var parsed = JSON.parse(data);
                KR.setFormConfig({
                    language: window.PAYZEN_LANGUAGE,
                    formToken: parsed.formToken
                }).then(function(v) {
                    KR = v.KR;
                    if (registerCard) {
                        jQuery('input[name="kr-do-register"]').attr('checked','checked');
                    }

                    submitKR(KR);
                });
            }
        });
    }
};

var submitKR = function (KR) {
    if (hideButton) {
        let element = jQuery('.kr-smart-button');

        if (element.length > 0) {
            smartbuttonMethod = element.attr('kr-payment-method');
        } else {
            element = jQuery('.kr-smart-form-modal-button');

            if (element.length > 0) {
                smartbuttonAll = true;
            }
        }
    }

    var popin = (jQuery(".kr-smart-form-modal-button").length > 0) || (jQuery(".kr-popin-button").length > 0);

    if (popin || smartbuttonAll) {
        KR.openPopin();
        unblock();
    } else if (hideButton) {
        KR.openSelectedPaymentMethod();
        unblock();
    } else if (smartbuttonMethod.length > 0) {
        KR.openPaymentMethod(smartbuttonMethod);
        unblock();
    } else {
        jQuery('#payzen_rest_processing').css('display', 'block');

        KR.submit();
    }

    return false;
};

var block = function() {
    jQuery('form.wc-block-components-form wc-block-checkout__form').block();
    jQuery(submitButton).prop("disabled", true);
};

var unblock = function() {
    jQuery('form.wc-block-components-form wc-block-checkout__form').unblock();
    jQuery(submitButton).prop("disabled", false);
    jQuery('.wc-block-components-button__text').text("").text(window.PAYZEN_BUTTON_TEXT);

    return false;
};

var validateKR = function(KR) {
    KR.validateForm().then(function(v) {
        submitForm(v.KR);
    }).catch(function(v) {
        // Display error message.
        var result = v.result;
        result.doOnError();
    });
};

var first = true;
var initFields = function() {
    if (! first) {
        displayFields();

        jQuery(submitButton).on('click', onButtonClick);
        jQuery('input[type=radio][name=radio-control-wc-payment-method-options]').change(function(e) {
            if (this.value === PAYMENT_METHOD_NAME) {
                displayFields();
            }
        });
    }

    first = false;
};

jQuery(document).on('ready', initFields);
jQuery(window).on('load', initFields);