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

class Payzen_Disabled_Subscriptions_Handler implements Payzen_Subscriptions_Handler_Interface
{
    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cart_contains_subscription()
     */
    public function cart_contains_subscription($cart)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cart_contains_multiple_subscriptions()
     */
    public function cart_contains_multiple_subscriptions($cart)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::subscription_info()
     */
    public function subscription_info($order)
    {
        return array();
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::process_subscription()
     */
    public function process_subscription($order, $response)
    {
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::update_subscription()
     */
    public function update_subscription($order, $response)
    {
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cancel_subscription()
     */
    public function cancel_subscription($order, $data)
    {
    }
}
