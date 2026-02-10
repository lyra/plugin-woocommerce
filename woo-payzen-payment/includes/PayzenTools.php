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

    public static function get_integration_mode()
    {
        $std_method_settings = get_option('woocommerce_payzenstd_settings', null);
        $card_data_mode = is_array($std_method_settings) & isset($std_method_settings['card_data_mode']) ? $std_method_settings['card_data_mode'] : 'DEFAULT';

        switch ($card_data_mode) {
            case 'DEFAULT':
            case 'IFRAME':
                return 'REDIRECT';

            case 'REST':
                return 'SMARTFORMEXT';

            default:
                return $card_data_mode;
        }
    }

    public static function is_embedded_payment()
    {
        $std_settings = get_option('woocommerce_payzenstd_settings', null);
        $enabled = is_array($std_settings) && isset($std_settings['enabled']) && ($std_settings['enabled'] == 'yes');
        if (! $enabled) {
            return false;
        }

        $modes = array('SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS');

        return in_array(self::get_integration_mode(), $modes);
    }

    public static function use_wallet($cust_id = null, $method = 'payzenstd')
    {
        global $woocommerce;

        $std_settings = get_option('woocommerce_payzenstd_settings', null);
        if (($method == 'payzenstd') && ($std_settings['use_customer_wallet'] !== '1')) {
            return false;
        }

        if (! self::is_embedded_payment()) {
            return false;
        }

        if (! $cust_id) {
            $cust_id = WC_Gateway_Payzen::get_customer_property($woocommerce->customer, 'id');
        }

        return ! is_null($cust_id);
    }

    public static function is_plugin_not_active($plugin)
    {
        return is_plugin_active($plugin) ? 'false' : 'true';
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

    public static function get_token_from_request(array $request)
    {
        $payment_method = isset($request['payment_method']) ? $request['payment_method'] : null;
        $token_request_key = 'wc-' . $payment_method . '-payment-token';
        if (! isset($request[ $token_request_key]) || 'new' === $request[ $token_request_key]) {
            return null;
        }

        $token = \WC_Payment_Tokens::get(wc_clean($request[$token_request_key]));

        // If the token doesn't belong to this gateway or the current user it's invalid.
        if (! $token || $payment_method !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id()) {
            return null;
        }

        return $token->get_token();
    }
}