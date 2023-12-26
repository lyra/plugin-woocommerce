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

class Payzen_Subscriptio_Subscriptions_Handler implements Payzen_Subscriptions_Handler_Interface
{
    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::init_hooks()
     */
    public function init_hooks()
    {
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cart_contains_subscription()
     */
    public function cart_contains_subscription($cart)
    {
        if (! class_exists('Subscriptio')) {
            return false;
        }

        return Subscriptio::cart_contains_subscription();
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cart_contains_multiple_subscriptions()
     */
    public function cart_contains_multiple_subscriptions($cart)
    {
        if (! class_exists('Subscriptio_Subscription_Product')) {
            return false;
        }

        $count = 0;

        if (! empty($cart->cart_contents)) {
            foreach ($cart->cart_contents as $item) {
                $itemId = isset($item['variation_id']) && $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
                if (Subscriptio_Subscription_Product::is_subscription($itemId)) {
                    $count++;
                }
            }
        }

        return $count > 1;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::is_subscription_update()
     */
    public function is_subscription_update() {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::get_parent_order()
     */
    public function get_parent_order($id) {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::subscription_info()
     */
    public function subscription_info($order)
    {
        $order_id = RightPress_WC_Legacy::order_get_id($order);

        $subscriptions = Subscriptio_Order_Handler::get_subscriptions_from_order_id($order_id);
        $subscription = reset($subscriptions); // Get first subscription.

        // Check if need to charge shipping.
        $charge_shipping = (Subscriptio::option('shipping_renewal_charge') == 1) ? true : false;

        $info = array(
            'effect_date' => self::get_effect_date($subscription),
            'init_amount' => null, // Not available in Subscriptio plugin.
            'init_number' => null, // Not available in Subscriptio plugin.
            'amount' => $charge_shipping ? $subscription->renewal_order_total : $subscription->renewal_order_subtotal,
            'frequency' => self::get_frequency($subscription),
            'interval' => (int) $subscription->price_time_value,
            'end_date' => self::get_end_date($subscription)
        );

        return $info;
    }

    private static function get_effect_date($subscription)
    {
        // Subscription starts after a trial period.
        $time_unit = $subscription->free_trial_time_unit;
        $time_value = (int) $subscription->free_trial_time_value;

        if (! $time_unit || ! $time_value) {
            // No trial, start subscription on 2nd recurrence because the 1st recurrence is paid with the initial cart.
            $time_unit = $subscription->price_time_unit;
            $time_value = (int) $subscription->price_time_value;
        }

        $time = strtotime("+{$time_value}{$time_unit}");

        return date('Ymd', $time);
    }

    private static function get_frequency($subscription)
    {
        $mapping = array(
            'year' => 'YEARLY',
            'month' => 'MONTHLY',
            'week' => 'WEEKLY',
            'day' => 'DAILY'
        );

        return $mapping[$subscription->price_time_unit];
    }

    private static function get_end_date($subscription)
    {
        $time_unit = $subscription->max_length_time_unit;
        $time_value = (int) $subscription->max_length_time_value;

        if ($time_unit && $time_value) {
            return date('Ymd', strtotime("+{$time_value}{$time_unit}"));
        }

        return null; // Subscription in unlimited.
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::process_subscription()
     */
    public function process_subscription($order, $response)
    {
        // Do nothing, action already managed by order status change.
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::update_subscription()
     */
    public function process_subscription_renewal($order, $response)
    {
        $order_id = RightPress_WC_Legacy::order_get_id($order);

        $subscriptions = Subscriptio_Order_Handler::get_subscriptions_from_order_id($order_id);
        $subscription = reset($subscriptions); // Get first subscription.

        if ($renewal_order_id = (int) $subscription->last_order_id) { // Get last generated order.
            $renewal_order = new WC_Order($renewal_order_id);

            WC_Gateway_Payzen::payzen_add_order_note($response, $renewal_order);

            $currency_code = $response->get('currency');

            if (PayzenTools::is_hpos_enabled()) {
                // HPOS usage is enabled.
                $renewal_order->delete_meta_data('Subscription ID');
                $renewal_order->delete_meta_data('Subscription amount');
                $renewal_order->delete_meta_data('Effect date');

                $renewal_order->update_meta_data('Subscription ID', $response->get('subscription'));
                $renewal_order->update_meta_data('Subscription amount', WC_Gateway_Payzen::display_amount($response->get('sub_amount'), $currency_code));
                $renewal_order->update_meta_data('Recurrence number', $response->get('recurrence_number'));

                $renewal_order->save();
            } else {
                delete_post_meta($renewal_order_id, 'Subscription ID');
                delete_post_meta($renewal_order_id, 'Subscription amount');
                delete_post_meta($renewal_order_id, 'Recurrence number');

                update_post_meta($renewal_order_id, 'Subscription ID', $response->get('subscription'));
                update_post_meta($renewal_order_id, 'Subscription amount', WC_Gateway_Payzen::display_amount($response->get('sub_amount'), $currency_code));
                update_post_meta($renewal_order_id, 'Recurrence number', $response->get('recurrence_number'));
            }

            if ($response->isPendingPayment()) {
                // Payment is pending.
                $renewal_order->update_status('on-hold');
            } elseif ($response->isAcceptedPayment()) {
                // Payment completed.
                $renewal_order->payment_complete();
            } else {
                // Payment failed.
                $renewal_order->update_status('failed');
            }
        }
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::cancel_subscription()
     */
    public function cancel_subscription()
    {
        // Not implemented.
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::update_subscription()
     */
    public function update_subscription()
    {
        // Not implemented.
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::get_view_order_url()
     */
    public function get_view_order_url($subsc_id)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see Payzen_Subscriptions_Handler_Interface::get_subscription_statuses()
     */
    public function get_subscription_statuses()
    {
        return array();
    }
}
