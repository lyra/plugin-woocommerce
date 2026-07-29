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
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getPayzenServerData } from './payzen-utils';

const PAYMENT_METHOD_NAME = 'payzenfranfinance';
var payzen_data = getPayzenServerData(PAYMENT_METHOD_NAME);

const Content = (props) => {
    const { eventRegistration } = props || {};
    const { onPaymentSetup } = eventRegistration || {};

    useEffect(() => {
        let unsubscribe;

        const processSelection = () => {
            payzen_get_selected_option(['payzenfranfinance_option']);
        };

        if (typeof onPaymentSetup === 'function') {
            // Fallback for older checkout blocks integrations.
            unsubscribe = onPaymentSetup(processSelection);
        }

        return () => {
            if (typeof unsubscribe === 'function') {
                return unsubscribe();
            }
        };
    }, [onPaymentSetup]);

    var fields = <ul dangerouslySetInnerHTML={{__html: payzen_data?.payment_fields}} />;

    return (
        <div>
            { payzen_data?.description }
            { fields }
        </div>
    );
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
