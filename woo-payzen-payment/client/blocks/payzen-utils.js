/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Payzen data comes form the server passed on a global object.
 */

export const getPayzenServerData = (name) => {
    const payzenServerData = getSetting( name + '_data', null );

    if ( ! payzenServerData ) {
        throw new Error( 'Payzen initialization data for ' + name + ' submodule is not available' );
    }

    return payzenServerData;
};
