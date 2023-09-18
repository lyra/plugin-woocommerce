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

/**
 * Internal dependencies.
 */
import { getPayzenServerData } from './payzen-utils';

const PAYMENT_METHOD_NAME = 'payzenstd';
var payzen_data = getPayzenServerData(PAYMENT_METHOD_NAME);

const Content = () => {
    if (payzen_data?.payment_fields) {
        var fields = <div dangerouslySetInnerHTML={{__html: payzen_data?.payment_fields}} />;

        jQuery('.wc-block-components-checkout-place-order-button').on('click', payzenstd_get_card());
        jQuery(document).ready(function() {
            payzenUpdatePaymentBlock(true);
        });

        return (
            <div>
                { fields }
            </div>
        );
    } else {
        return (payzen_data?.description);
    }
};

const Label = () => {
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
            <span>{ payzen_data?.title}</span>
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
