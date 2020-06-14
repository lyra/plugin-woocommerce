<?php
/**
 * Copyright © Lyra Network and contributors.
 * This file is part of PayZen plugin for WooCommerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @author    Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @copyright Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL v2)
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

interface Payzen_Subscriptions_Handler_Interface
{
    /**
     * Return true if cart contains a subscription product.
     *
     * @param $cart the WooCommerce shopping cart
     * @return bool
     */
    public function cart_contains_subscription($cart);

    /**
     * Return true if cart contains <b>more than</b> one subscription product.
     *
     * @param $cart the WooCommerce shopping cart
     * @return bool
     */
    public function cart_contains_multiple_subscriptions($cart);

    /**
     * Return all information about subscription as an associative array containing:
     *   effect_date: The date on which subscription will start formatted as YYYYMMDD.
     *   init_amount: The amount of initial recurrences.
     *   init_number: The number of initial recurrences.
     *   amount: The amount of the subscription.
     *   frequency: One of YEARLY, MONTHLY, WEEKLY or DAILY.
     *   interval: The number of frequency.
     *   end_date: The date on which subscription will end formatted as YYYYMMDD.
     *
     * @param $order the WooCommerce order
     * @return array
     */
    public function subscription_info($order);

    /**
     * Called when a subscription is created. Save subscription data and update corresponding subscription status.
     *
     * @param $order the WooCommerce order
     * @param $response the gateway response
     */
    public function process_subscription($order, $response);

    /**
     * Called when the payment of each subscription installment is processed. Save installment status and update subscription.
     *
     * @param $order the WooCommerce order
     * @param $response the gateway response
     */
    public function update_subscription($order, $response);

    /**
     * Called when a subscription is cancelled on the gateway or from WS.
     *
     * @param $order the WooCommerce order
     * @param $data Data identifying the subscription to cancel.
     */
    public function cancel_subscription($order, $data);
}
