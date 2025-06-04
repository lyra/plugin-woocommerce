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

        if (option.length == 0) {
            continue;
        } else if (option.length > 1) {
            option = jQuery(element + ':checked');
        }

        document.cookie = name + '=' + option.val() + '; path=/';
    }
};

var payzenstd_get_card = function () {
    payzen_get_selected_option(['payzenstd_card_type']);
};

var payzenUpdatePaymentBlock = function (useIdentifier, methodId) {
    jQuery("ul." + methodId + "-view-top li.block").hide();
    jQuery("ul." + methodId + "-view-bottom li.block").hide();

    var blockName = useIdentifier ? "id" : "cc";
    jQuery("li." + methodId + "-" + blockName + "-block").show();

    if ((methodId !== "payzensepa") && (typeof window.FORM_TOKEN != 'undefined')) {
        payzenUpdateFormToken(useIdentifier);
    }

    jQuery("#payzen_use_identifier").val(useIdentifier);
};

var payzenWaitForElement = function (selector) {
    return new Promise(resolve => {
        if (document.querySelector(selector)) {
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(mutations => {
            if (document.querySelector(selector)) {
                observer.disconnect();
                resolve(document.querySelector(selector));
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
}