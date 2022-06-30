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
        $std_payment_method = new WC_Gateway_PayzenStd();
        $card_data_mode = $std_payment_method->get_option('card_data_mode');

        switch ($card_data_mode) {
            case 'DEFAULT':
                return 'REDIRECT';
                break;
            default:
                return $card_data_mode;
        }
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
        $trans_id = get_post_meta((int) $order->get_id(), 'Transaction ID', true);
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
}
