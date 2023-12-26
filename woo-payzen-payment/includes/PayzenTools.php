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
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

class PayzenTools
{
    public static function get_contrib()
    {
        global $woocommerce;

        // Effective used version.
        include ABSPATH . WPINC . '/version.php'; // $wp_version.
        $version = $wp_version . '_' . $woocommerce->version;

        return WC_Gateway_Payzen::CMS_IDENTIFIER . '_' . WC_Gateway_Payzen::PLUGIN_VERSION . '/' . $version
            . '/' . PayzenApi::shortPhpVersion();
    }

    public static function get_support_component_language()
    {
        $parts = explode('_', get_locale());
        return $parts[0];
    }

    public static function get_integration_mode()
    {
        $std_method_settings = get_option('woocommerce_payzenstd_settings', null);
        $card_data_mode = is_array($std_method_settings) & isset($std_method_settings['card_data_mode']) ? $std_method_settings['card_data_mode'] : 'DEFAULT';

        return (($card_data_mode === 'DEFAULT') ? 'REDIRECT' : $card_data_mode);
    }

    public static function is_embedded_payment($only_smartform = true)
    {
        $std_settings = get_option('woocommerce_payzenstd_settings', null);
        $enabled = is_array($std_settings) && isset($std_settings['enabled']) && ($std_settings['enabled'] == 'yes');
        if (! $enabled) {
            return false;
        }

        $modes = array('SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS');
        if (! $only_smartform) {
            array_push($modes,'REST');
        }

        return isset($std_settings['card_data_mode']) && in_array($std_settings['card_data_mode'], $modes);
    }

    public static function get_active_plugins()
    {
        $all_active_plugins = get_option('active_plugins');

        $active_plugins = array();
        foreach ($all_active_plugins as $plugin) {
            $parts = explode('/', $plugin);
            $active_plugins[] = $parts[0];
        }

        return implode(' / ', $active_plugins);
    }

    public static function is_plugin_not_active($plugin)
    {
        return is_plugin_active($plugin) ? 'false' : 'true';
    }

    public static function get_used_discounts($order)
    {
        $coupons = array();
        $used_coupons = self::get_coupon_codes($order);
        foreach ($used_coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);

            $discount_type = $coupon->get_discount_type(); // Get coupon discount type.
            $coupon_amount = $coupon->get_amount(); // Get coupon amount.
            $currency = ($discount_type !== 'percent') ? ' ' . $order->get_currency() : '%'; // Get coupon currency.

            $coupons[] = $discount_type . ' -' . $coupon_amount . $currency;
        }

        return $coupons ? implode(' / ', $coupons) : '';
    }

    private static function get_coupon_codes($order)
    {
        if (method_exists($order, 'get_coupon_codes')) {
            return $order->get_coupon_codes();
        }

        if (method_exists($order, 'get_used_coupons')) {
            return $order->get_used_coupons();
        }

        return array();
    }

    public static function get_transaction_uuid($order)
    {
        $trans_uuid = '';
        $trans_id = (self::is_hpos_enabled()) ? $order->get_meta('Transaction ID') : get_post_meta((int) $order->get_id(), 'Transaction ID', true);

        if ($trans_id) {
            $notes = WC_Gateway_Payzen::get_order_notes($order->get_id());
            foreach ($notes as $note) {
                if (strpos($note, $trans_id) !== false) {
                    $parts = explode('.', $note);
                    $trans_uuid = $parts ? $parts[1] : '';
                    break;
                }
            }
        }

        $parts = $trans_uuid ? explode(':', $trans_uuid) : '';
        return $parts ? $parts[1] : '';
    }

    public static function is_hpos_enabled()
    {
        return version_compare(WC_VERSION, '7.1.0', '>=') && class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    public static function get_user_info()
    {
        $comment_text = 'WooCommerce user: ' . get_option('admin_email');
        $comment_text .= ' ; IP address: ' . self::get_ip_address();

        return $comment_text;
    }

    public static function get_ip_address()
    {
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2.
            // Make sure we always only send through the first IP in the list which should always be the client IP.
            return (string) rest_is_ip_address(trim(current(preg_split('/,/', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))))));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '';
    }

    public static function has_checkout_block()
    {
        global $post;

        // Checkout page for orders created from BO doesn't use checkout block.
        if (isset($_GET['pay_for_order'])) {
            return false;
        }

        if ($post) {
            return function_exists('has_block') && has_block('woocommerce/checkout', $post->ID);
        }

        return function_exists('has_block') && has_block('woocommerce/checkout', wc_get_page_id('checkout'));
    }

    public static function get_view_order_url($subsc_id)
    {
        if (function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription($subsc_id);

            return $subscription->get_view_order_url();
        }

        return null;
    }
}
