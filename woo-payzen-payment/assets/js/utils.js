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
 * JS tools.
 */
var payzen_get_selected_option = function(elementNames) {
    for (const name of elementNames) {
        let element = 'input[name="'+ name + '"]';
        let option = jQuery(element);

        if (option.size() == 0) {
            continue;
        } else if (option.size() > 1) {
            option = jQuery(element + ':checked');
        }

        document.cookie = name + '=' + option.val() + '; path=/';
    }
};

var payzenstd_get_card = function () {
    payzen_get_selected_option(['payzenstd_card_type']);
};

var payzenUpdatePaymentBlock = function (useIdentifier) {
    jQuery("ul.payzenstd-view-top li.block").hide();
    jQuery("ul.payzenstd-view-bottom li.block").hide();

    var blockName = useIdentifier ? "id" : "cc";
    jQuery("li.payzenstd-" + blockName + "-block").show();

    if (typeof window.FORM_TOKEN != 'undefined') {
        payzenUpdateFormToken(useIdentifier);
    }

    jQuery("#payzen_use_identifier").val(useIdentifier);
};

var payzenstd_show_iframe = function() {
    // Unblock screen.
    jQuery('form.wc-block-components-form wc-block-checkout__form').unblock();
    jQuery('.wc-block-components-checkout-place-order-button').prop("disabled", false);

    jQuery('.payment_method_payzenstd p:first-child').hide();
    jQuery('ul.payzenstd-view-top li.block').hide();
    jQuery('ul.payzenstd-view-bottom li.block').hide();
    jQuery('#payzen_iframe').show();
    jQuery('#payzen_iframe').attr('src', window.IFRAME_LINK);

    jQuery(window).unbind('beforeunload');
};