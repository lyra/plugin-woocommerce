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

/**
 * Plugin Name: PayZen for WooCommerce
 * Description: This plugin links your WordPress WooCommerce shop to the payment gateway.
 * Author: Lyra Network
 * Contributors: Alsacréations (Geoffrey Crofte http://alsacreations.fr/a-propos#geoffrey)
 * Version: 1.17.0
 * Author URI: https://www.lyra.com/
 * License: GPLv2 or later
 * Requires at least: 3.5
 * Tested up to: 6.9
 * WC requires at least: 2.0
 * WC tested up to: 10.4
 *
 * Text Domain: woo-payzen-payment
 * Domain Path: /languages/
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Lyranetwork\Payzen\Sdk\Refund\Api as PayzenRefundApi;
use Lyranetwork\Payzen\Sdk\Refund\OrderInfo as PayzenOrderInfo;

define('WC_PAYZEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_PAYZEN_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

/* A global var to easily enable/disable features. */
global $payzen_plugin_features;

$payzen_plugin_features = array(
    'qualif' => false,
    'prodfaq' => true,
    'restrictmulti' => false,
    'shatwo' => true,
    'subscr' => true,

    'multi' => true,
    'klarna' => true,
    'franfinance' => true,
    'sepa' => true
);

/* Check requirements. */
function woocommerce_payzen_activation()
{
    $all_active_plugins = get_option('active_plugins');
    if (is_multisite()) {
        $all_active_plugins = array_merge($all_active_plugins, wp_get_active_network_plugins());
    }

    $all_active_plugins = apply_filters('active_plugins', $all_active_plugins);

    if (! stripos(implode('', $all_active_plugins), '/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__)); // Deactivate ourself.

        // Load translation files.
        load_plugin_textdomain('woo-payzen-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');

        $message = sprintf(__('Sorry ! In order to use WooCommerce %s Payment plugin, you need to install and activate the WooCommerce plugin.', 'woo-payzen-payment'), 'PayZen');
        wp_die($message, 'PayZen for WooCommerce', array('back_link' => true));
    }
}
register_activation_hook(__FILE__, 'woocommerce_payzen_activation');

/* Delete all data when uninstalling plugin. */
function woocommerce_payzen_uninstallation()
{
    delete_option('woocommerce_payzen_settings');
    delete_option('woocommerce_payzenstd_settings');
    delete_option('woocommerce_payzenmulti_settings');
    delete_option('woocommerce_payzenklarna_settings');
    delete_option('woocommerce_payzenfranfinance_settings');
    delete_option('woocommerce_payzensepa_settings');
    delete_option('woocommerce_payzenregroupedother_settings');
    delete_option('woocommerce_payzensubscription_settings');
    delete_option('woocommerce_payzenwcssubscription_settings');
}
register_uninstall_hook(__FILE__, 'woocommerce_payzen_uninstallation');

/* Include gateway classes. */
function woocommerce_payzen_init()
{
    global $payzen_plugin_features;

    // Load translation files.
    load_plugin_textdomain('woo-payzen-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (! class_exists('Payzen_Subscriptions_Loader')) { // Load subscriptions processing mecanism.
        require_once 'includes/subscriptions/payzen-subscriptions-loader.php';
    }

    if (! class_exists('WC_Gateway_Payzen')) {
        require_once 'class-wc-gateway-payzen.php';
    }

    if (! class_exists('WC_Gateway_PayzenStd')) {
        require_once 'class-wc-gateway-payzenstd.php';
    }

    if ($payzen_plugin_features['multi'] && ! class_exists('WC_Gateway_PayzenMulti')) {
        require_once 'class-wc-gateway-payzenmulti.php';
    }

    if ($payzen_plugin_features['klarna'] && ! class_exists('WC_Gateway_PayzenKlarna')) {
        require_once 'class-wc-gateway-payzenklarna.php';
    }

    if ($payzen_plugin_features['franfinance'] && ! class_exists('WC_Gateway_PayzenFranfinance')) {
        require_once 'class-wc-gateway-payzenfranfinance.php';
    }

    if ($payzen_plugin_features['sepa'] && ! class_exists('WC_Gateway_PayzenSepa')) {
        require_once 'class-wc-gateway-payzensepa.php';
    }

    if (! class_exists('WC_Gateway_PayzenRegroupedOther')) {
        require_once 'class-wc-gateway-payzenregroupedother.php';
    }

    if (! class_exists('WC_Gateway_PayzenOther')) {
        require_once 'class-wc-gateway-payzenother.php';
    }

    if ($payzen_plugin_features['subscr'] && ! class_exists('WC_Gateway_PayzenSubscription')) {
        require_once 'class-wc-gateway-payzensubscription.php';
    }

    if (! class_exists('WC_Gateway_PayzenWcsSubscription')) {
        require_once 'class-wc-gateway-payzenwcssubscription.php';
    }

    require_once 'includes/sdk-autoload.php';
    require_once 'includes/PayzenRestTools.php';
    require_once 'includes/PayzenSubscriptionTools.php';
    require_once 'includes/class-wc-payzen-sepa-payment-token.php';
    require_once 'includes/PayzenTools.php';

    // Restore WC notices in case of POST as return mode.
    WC_Gateway_Payzen::restore_wc_notices();
}
add_action('woocommerce_init', 'woocommerce_payzen_init');

function woocommerce_payzen_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once 'includes/PayzenTools.php';
        if (! PayzenTools::has_checkout_block()) {
            return;
        }

        if (! class_exists('WC_Gateway_Payzen_Blocks_Support')) {
            require_once 'includes/class-wc-gateway-payzen-blocks-support.php';
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(PaymentMethodRegistry $payment_method_registry) {
                global $payzen_plugin_features;

                $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenstd'));

                if ($payzen_plugin_features['multi']) {
                    $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenmulti'));
                }

                if ($payzen_plugin_features['franfinance']) {
                    $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenfranfinance'));
                }

                if ($payzen_plugin_features['klarna']) {
                    $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenklarna'));
                }

                if ($payzen_plugin_features['sepa']) {
                    $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzensepa'));
                }

                if ($payzen_plugin_features['subscr']) {
                    $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzensubscription'));
                }

                $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenwcssubscription'));

                $payment_method_registry->register(new WC_Gateway_Payzen_Blocks_Support('payzenregroupedother'));

                if (get_transient('payzen_other_methods')) {
                    $methods = json_decode(get_transient('payzen_other_methods'), true);

                    // Virtual method to display non regrouped other payment means.
                    $payment_method_registry->register(
                        new WC_Gateway_Payzen_Blocks_Support('payzenother_lyranetwork')
                    );

                    foreach ($methods as $code => $label) {
                        $payment_method_registry->register(
                            new WC_Gateway_Payzen_Blocks_Support('payzenother_' . $code, $label)
                        );
                    }
                }
             }
        );
    }
}

add_action('woocommerce_blocks_loaded', 'woocommerce_payzen_woocommerce_block_support');

/* Add our payment methods to WooCommerce methods. */
function woocommerce_payzen_add_method($methods)
{
    global $payzen_plugin_features, $woocommerce;

    $methods[] = 'WC_Gateway_Payzen';
    $methods[] = 'WC_Gateway_PayzenStd';

    if ($payzen_plugin_features['multi']) {
        $methods[] = 'WC_Gateway_PayzenMulti';
    }

    if ($payzen_plugin_features['klarna']) {
        $methods[] = 'WC_Gateway_PayzenKlarna';
    }

    if ($payzen_plugin_features['franfinance']) {
        $methods[] = 'WC_Gateway_PayzenFranfinance';
    }

    if ($payzen_plugin_features['sepa']) {
        $methods[] = 'WC_Gateway_PayzenSepa';
    }

    $methods[] = 'WC_Gateway_PayzenWcsSubscription';

    if ($payzen_plugin_features['subscr']) {
        $methods[] = 'WC_Gateway_PayzenSubscription';
    }

    $methods[] = 'WC_Gateway_PayzenRegroupedOther';

    if (get_transient('payzen_other_methods')) {
        $other_methods = json_decode(get_transient('payzen_other_methods'), true);

        foreach ($other_methods as $code => $label) {
            $methods[] = new WC_Gateway_PayzenOther($code, $label);
        }
    }

    // Since 2.3.0, we can display other payment means as submodules.
    if (version_compare($woocommerce->version, '2.3.0', '>=') && $woocommerce->cart) {
        $regrouped_other_payments = new WC_Gateway_PayzenRegroupedOther(false);

        if (! $regrouped_other_payments->regroup_other_payment_means()) {
            $payzen_other_methods = array();
            $payment_means = $regrouped_other_payments->get_available_options();

            if (is_array($payment_means) && ! empty($payment_means)) {
                foreach ($payment_means as $option) {
                    $payzen_other_methods[$option['payment_mean']] =  $option['label'];
                }
            }

            set_transient('payzen_other_methods', json_encode($payzen_other_methods));
        } else {
            delete_transient('payzen_other_methods');
        }
    }

    return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_payzen_add_method');

/* Add a link to plugin settings page from plugins list. */
function woocommerce_payzen_add_link($links, $file)
{
    global $payzen_plugin_features;

    $links[] = '<a href="' . payzen_admin_url('Payzen') . '">' . __('General configuration', 'woo-payzen-payment') . '</a>';
    $links[] = '<a href="' . payzen_admin_url('PayzenStd') . '">' . __('Standard payment', 'woo-payzen-payment') . '</a>';

    if ($payzen_plugin_features['multi']) {
        $links[] = '<a href="' . payzen_admin_url('PayzenMulti') . '">' . __('Payment in installments', 'woo-payzen-payment')
            . '</a>';
    }

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_payzen_add_link', 10, 2);

function payzen_admin_url($id)
{
    global $woocommerce;

    $base_url = 'admin.php?page=wc-settings&tab=checkout&section=';
    $section = strtolower($id); // Method id in lower case.

    // Backward compatibility.
    if (version_compare($woocommerce->version, '2.1.0', '<')) {
        $base_url = 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=';
        $section = 'WC_Gateway_' . $id; // Class name as it is.
    } elseif (version_compare($woocommerce->version, '2.6.2', '<')) {
        $section = 'wc_gateway_' . $section; // Class name in lower case.
    }

    return admin_url($base_url . $section);
}

function woocommerce_payzen_order_payment_gateways($available_gateways)
{
    global $woocommerce;
    $index_other_not_grouped_gateways_ids = array();
    $index_other_grouped_gateway_id = null;
    $gateways_ids = array();
    $index_gateways_ids = 0;
    foreach ($woocommerce->payment_gateways()->payment_gateways as $gateway) {
        if ($gateway->id === 'payzenregroupedother') {
            $index_other_grouped_gateway_id = $index_gateways_ids;
        } elseif (strpos($gateway->id, 'payzenother_') === 0) {
            $index_other_not_grouped_gateways_ids[] = $index_gateways_ids;
        }

        $gateways_ids[] = $gateway->id;
        $index_gateways_ids ++;
    }

    // Reorder custom PayZen non-grouped other payment means as they appear in WooCommerce backend.
    // And if only they are not already in last position.
    if (! empty($index_other_not_grouped_gateways_ids) && ($index_other_grouped_gateway_id !== reset($index_other_not_grouped_gateways_ids) - 1)) {
        $ordered_gateways_ids = array();
        for ($i = 0; $i < $index_other_grouped_gateway_id; $i++) {
            $ordered_gateways_ids[] = $gateways_ids[$i];
        }

        foreach ($index_other_not_grouped_gateways_ids as $index_not_grouped_other_id) {
            $ordered_gateways_ids[] = $gateways_ids[$index_not_grouped_other_id];
        }

        for ($i = $index_other_grouped_gateway_id + 1; $i < count($gateways_ids); $i++) {
            if (! in_array($i, $index_other_not_grouped_gateways_ids)) {
                $ordered_gateways_ids[] = $gateways_ids[$i];
            }
        }

        $ordered_gateways = array();
        foreach ($ordered_gateways_ids as $gateway_id) {
            if (isset($available_gateways[$gateway_id])) {
                $ordered_gateways[$gateway_id] = $available_gateways[$gateway_id];
            }
        }

        return $ordered_gateways;
    }

    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'woocommerce_payzen_order_payment_gateways');

function payzen_subscription_messages_on_order($order)
{
    $payzen_update_subscription_error_msg = get_transient('payzen_update_subscription_error_msg');
    $payzen_renewal_error_msg = get_transient('payzen_renewal_error_msg');
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
        <?php
        if ($payzen_update_subscription_error_msg) {
            delete_transient('payzen_update_subscription_error_msg');
        ?>
            var element = jQuery('#lost-connection-notice');
            if (! element.length) {
                element = jQuery("#order_data");
            }

            if (element.length) {
                element.after('<div class="error notice is-dismissible"><p><?php echo addslashes($payzen_update_subscription_error_msg); ?></p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text"><?php echo esc_html__('Dismiss this notice.', 'woocommerce') ?></span></button></div>');
            }
        <?php
        }

        if ($payzen_renewal_error_msg) {
            delete_transient('payzen_renewal_error_msg');
        ?>
            var element = jQuery('#lost-connection-notice');
            if (! element.length) {
                element = jQuery("#order_data");
            }

            if (element.length) {
                element.after('<div class="error notice is-dismissible"><p><?php echo addslashes($payzen_renewal_error_msg); ?></p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text"><?php echo esc_html__('Dismiss this notice.', 'woocommerce') ?></span></button></div>');
            }
        <?php
        }
        ?>
        });
    </script>
    <?php
}
// Display subscriptions error messages.
add_action('woocommerce_admin_order_data_after_billing_address', 'payzen_subscription_messages_on_order');

/* Retrieve blog_id from POST when this is an IPN URL call. */
require_once 'includes/sdk-autoload.php';
require_once 'includes/PayzenRestTools.php';

if (PayzenRestTools::checkResponse($_POST)) {
    $answer = json_decode($_POST['kr-answer'], true);
    $data = PayzenRestTools::convertRestResult($answer);
    $is_valid_ipn = isset($data['vads_ext_info_blog_id']);
} else {
    $is_valid_ipn = isset($_POST['vads_hash']) && isset($_POST['vads_ext_info_blog_id']);
}

if (is_multisite() && $is_valid_ipn) {
    global $wpdb, $current_blog, $current_site;

    $blog = isset($_POST['vads_ext_info_blog_id']) ? $_POST['vads_ext_info_blog_id'] : $data['vads_ext_info_blog_id'];
    switch_to_blog((int) $blog);

    // Set current_blog global var.
    $current_blog = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $wpdb->blogs WHERE blog_id = %s", $blog)
    );

    // Set current_site global var.
    $network_fnc = function_exists('get_network') ? 'get_network' : 'wp_get_network';
    $current_site = $network_fnc($current_blog->site_id);
    $current_site->blog_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s",
            $current_site->domain,
            $current_site->path
        )
    );

    $current_site->site_name = get_site_option('site_name');
    if (! $current_site->site_name) {
        $current_site->site_name = ucfirst($current_site->domain);
    }
}

function payzen_launch_online_refund($order, $refund_amount, $refund_currency)
{
    // Prepare order information for refund.
    require_once 'includes/sdk-autoload.php';
    require_once 'includes/PayzenRefundProcessor.php';

    $order_info = new PayzenOrderInfo();
    $order_info->setOrderRemoteId($order->get_id());
    $order_info->setOrderId($order->get_id());
    $order_info->setOrderReference($order->get_order_number());
    $order_info->setOrderCurrencyIsoCode($refund_currency);
    $order_info->setOrderCurrencySign(html_entity_decode(get_woocommerce_currency_symbol($refund_currency)));
    $order_info->setOrderUserInfo(PayzenTools::get_user_info());
    $refund_processor = new PayzenRefundProcessor();

    $std_payment_method = new WC_Gateway_PayzenStd();

    $test_mode = $std_payment_method->get_general_option('ctx_mode') == 'TEST';
    $key = $test_mode ? $std_payment_method->get_general_option('test_private_key') : $std_payment_method->get_general_option('prod_private_key');

    $refund_api = new PayzenRefundApi(
        $refund_processor,
        $key,
        $std_payment_method->get_general_option('rest_url', WC_Gateway_Payzen::REST_URL),
        $std_payment_method->get_general_option('site_id'),
        'WooCommerce'
    );

    // Do online refund.
    $refund_api->refund($order_info, $refund_amount);
}

function payzen_display_refund_result_message($order_id)
{
    $payzen_online_refund_result = get_transient('payzen_online_refund_result');

    if ($payzen_online_refund_result) {
        echo $payzen_online_refund_result;

        delete_transient('payzen_online_refund_result');
    }
}

// Display online refund result message.
add_action('woocommerce_admin_order_totals_after_discount', 'payzen_display_refund_result_message', 10, 1);

function payzen_features_compatibility()
{
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

// Declaring HPOS compatibility.
add_action('before_woocommerce_init', 'payzen_features_compatibility');

function payzen_online_refund($order_id, $refund_id)
{
    // Check if order was passed with other payment means submodule.
    $order = new WC_Order((int) $order_id);
    if (substr($order->get_payment_method(), 0, strlen('payzenother_')) !== 'payzenother_') {
        return;
    }

    $refund = new WC_Order_Refund((int) $refund_id);

    // Do online refund.
    payzen_launch_online_refund($order, $refund->get_amount(), $refund->get_currency());
}

// Do online refund after local refund.
add_action('woocommerce_order_refunded', 'payzen_online_refund', 10 , 2);

function payzen_online_cancel($order_id)
{
    $order = new WC_Order((int) $order_id);
    if (substr($order->get_payment_method(), 0, strlen('payzen')) !== 'payzen') {
        return;
    }

    payzen_launch_online_refund($order, $order->get_total(), $order->get_currency());
}

// Do online cancel after local cancellation.
add_action('woocommerce_cancelled_order', 'payzen_online_cancel');

function payzen_payment_token_deleted($token_id, $token) {
    if (strpos($token->get_gateway_id(), 'payzen') !== 0) {
        return;
    }

    $payzen_gateway = new WC_Gateway_Payzen();
    $payzen_gateway->delete_identifier_online($token->get_token(), $token->get_user_id(),  $token->get_gateway_id(), false);
}

add_action('woocommerce_payment_token_deleted', 'payzen_payment_token_deleted', 10, 2);
