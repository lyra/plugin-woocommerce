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
import { getSetting } from '@woocommerce/settings';

/**
 * Payzen data comes form the server passed on a global object.
 */

export const getPayzenServerData = (name) => {
    const payzenServerData = getSetting( name + '_data', null );

    if (! payzenServerData) {
        return;
    }

    return payzenServerData;
};
