/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { getPayzenServerData } from './payzen-utils';

const PAYMENT_METHOD_NAME = 'payzenstd';
var payzen_data = getPayzenServerData(PAYMENT_METHOD_NAME);

const Content = () => {
    return (payzen_data?.description);
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
                src={ payzen_data?.logo_url + 'payzen.png' }
                alt={ payzen_data?.title }
            />
        </div>
    );
};

registerPaymentMethod( {
    name: PAYMENT_METHOD_NAME,
    label: <Label />,
    ariaLabel: 'Payzen payment method',
    canMakePayment: () => true,
    content: <Content />,
    edit: <Content />,
    supports: {
        features: payzen_data?.supports ?? [],
    },
} );
