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
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getPayzenServerData } from './payzen-utils';

const PAYMENT_METHOD_NAME = 'payzenwcssubscription';
var payzen_data = getPayzenServerData(PAYMENT_METHOD_NAME);

var smartbuttonMethod = '';
var smartbuttonAll = false;
var hideSmart = payzen_data?.hide_smartbutton && (payzen_data?.hide_smartbutton === 'true');
var hideButton = false;

var savedData = false;
var newData = null;

const Content = (props) => {
    const { eventRegistration } = props || {};
    const { onCheckoutSuccess } = eventRegistration || {};

    // Display embedded payment fields once the payment method content is mounted.
    useEffect(() => {
        displayFields();
    }, []);

    useEffect(() => {
        const unsubscribes = [];

        if (typeof onCheckoutSuccess === 'function') {
            // Fallback for older checkout blocks integrations.
            let unsubscribe = onCheckoutSuccess(async (payload) => {
                await processPayment(payload);
            });

            if (typeof unsubscribe === 'function') {
                unsubscribes.push(unsubscribe);
            }
        }

        return () => {
            unsubscribes.forEach((unsubscribe) => unsubscribe());
        };
    }, [onCheckoutSuccess]);

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
    var checkedMethod = jQuery("input[type=radio][name=radio-control-wc-payment-method-options]:checked").val();
    if (checkedMethod !== PAYMENT_METHOD_NAME) {
        return;
    }

    if (payzen_data?.vars) {
        delete(window.FORM_TOKEN);
        delete(window.PAYZEN_HIDE_SINGLE_BUTTON);

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

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach((node) => {
                        if (node.className == "wc-block-components-totals-item__value") {
                            refreshTempToken();
                        }
                    })
                }
            });
        });

        var target = document.querySelector('.wc-block-components-totals-footer-item');
        if (target) {
            observer.observe(target, {characterData: true, childList: true, subtree: true});
        }
    }

    payzenUpdatePaymentBlock(true, PAYMENT_METHOD_NAME);
};

var processPayment = async function (payload) {
    if (! jQuery("#radio-control-wc-payment-method-options-" + PAYMENT_METHOD_NAME).is(":checked")) {
        return true;
    }

    // In case of form validation error, let WooCommerce deal with it.
    if (jQuery('div.wc-block-components-validation-error')[0]) {
        return true;
    }

    jQuery('.kr-form-error').html('');

    var formToken = getFormToken(payload);
    if (! formToken) {
        return {
            type: 'error',
            message: __('Unable to initialise the payment form.', 'woo-payzen-payment')
        };
    }

    var v = await KR.setFormConfig({
        formToken: formToken
    })

    KR = v.KR;

    document.cookie = PAYMENT_METHOD_NAME + '_force_redir=; Max-Age=0; path=/; domain=' + location.host;
    if (typeof window.FORM_TOKEN == 'undefined') {
        document.cookie = PAYMENT_METHOD_NAME + '_force_redir="true"; path=/; domain=' + location.host;
        return true;
    }

    if (! hideButton && ! hideSmart) {
        validateKR(KR);
    } else {
        submitForm(KR);
    }
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
        if (jQuery('div.wc-block-components-validation-error')[0]) {
            return true;
        }

        submitKR(KR);
    }
};

var submitKR = function (KR) {
    if (jQuery('div.wc-block-components-validation-error')[0]) {
        return true;
    }

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
        payzenResetWCBlocksCheckout(); // Reset WC Blocks state so a second click is possible.
    } else if (hideButton) {
        KR.openSelectedPaymentMethod();
        payzenResetWCBlocksCheckout(); // Reset WC Blocks state so a second click is possible.
    } else if (smartbuttonMethod.length > 0) {
        KR.openPaymentMethod(smartbuttonMethod);
       payzenResetWCBlocksCheckout(); // Reset WC Blocks state so a second click is possible.
    } else {
        jQuery('#payzen_rest_processing').css('display', 'block');

        KR.submit();
    }

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

var refreshTempToken = function () {
    if (jQuery("#radio-control-wc-payment-method-options-" + PAYMENT_METHOD_NAME).is(":checked")) {
        jQuery.ajax({
            method: 'POST',
            url: payzen_data?.temporary_token_url,
            success: function(data) {
                var parsed = JSON.parse(data);
                KR.setFormConfig({
                    language: window.PAYZEN_LANGUAGE,
                    formToken: parsed.formToken
                }).then(function(v) {
                    KR = v.KR;
                });
            }
        });
    }
};

var getFormToken = function (payload) {
    var formToken = payload?.processingResponse?.paymentDetails?.formToken ?? payload?.paymentDetails?.formToken ?? null;
    if (! formToken) {
        return null;
    }

    return formToken;
};
