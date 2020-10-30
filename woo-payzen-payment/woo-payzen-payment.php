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
 * Plugin Name: WooCommerce PayZen Payment
 * Description: This plugin links your WordPress WooCommerce shop to the payment gateway.
 * Author: Lyra Network
 * Contributors: Alsacréations (Geoffrey Crofte http://alsacreations.fr/a-propos#geoffrey)
 * Version: 1.8.7
 * Author URI: https://www.lyra.com/
 * License: GPLv2 or later
 * Requires at least: 3.5
 * Tested up to: 5.5
 * WC requires at least: 2.0
 * WC tested up to: 4.5
 *
 * Text Domain: woo-payzen-payment
 * Domain Path: /languages/
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WC_PAYZEN_PLUGIN_URL', plugin_dir_url(__FILE__));

/* A global var to easily enable/disable features. */
global $payzen_plugin_features;

$payzen_plugin_features = array(
    'qualif' => false,
    'prodfaq' => true,
    'restrictmulti' => false,
    'shatwo' => true,
    'embedded' => true,
    'subscr' => false,

    'multi' => true,
    'choozeo' => false,
    'klarna' => true
);

/* Check requirements. */
function woocommerce_payzen_activation()
{
    $all_active_plugins = get_option('active_plugins');
    if (is_multisite()) {
        $all_active_plugins = array_merge($all_active_plugins, wp_get_active_network_plugins());
    }

    $all_active_plugins = apply_filters('active_plugins', $all_active_plugins);

    if (! stripos(implode($all_active_plugins), '/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__)); // Deactivate ourself.

        // Load translation files.
        load_plugin_textdomain('woo-payzen-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');

        $message = sprintf(__('Sorry ! In order to use WooCommerce %s Payment plugin, you need to install and activate the WooCommerce plugin.', 'woo-payzen-payment'), 'PayZen');
        wp_die($message, 'WooCommerce PayZen Payment', array('back_link' => true));
    }
}
register_activation_hook(__FILE__, 'woocommerce_payzen_activation');

/* Delete all data when uninstalling plugin. */
function woocommerce_payzen_uninstallation()
{
    delete_option('woocommerce_payzen_settings');
    delete_option('woocommerce_payzenstd_settings');
    delete_option('woocommerce_payzenmulti_settings');
    delete_option('woocommerce_payzenchoozeo_settings');
    delete_option('woocommerce_payzenklarna_settings');
    delete_option('woocommerce_payzenregroupedother_settings');
    delete_option('woocommerce_payzensubscription_settings');
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

    if ($payzen_plugin_features['choozeo'] && ! class_exists('WC_Gateway_PayzenChoozeo')) {
        require_once 'class-wc-gateway-payzenchoozeo.php';
    }

    if ($payzen_plugin_features['klarna'] && ! class_exists('WC_Gateway_PayzenKlarna')) {
        require_once 'class-wc-gateway-payzenklarna.php';
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

    require_once 'includes/PayzenRequest.php';
    require_once 'includes/PayzenResponse.php';
    require_once 'includes/PayzenRest.php';
    require_once 'includes/PayzenRestTools.php';
}
add_action('woocommerce_init', 'woocommerce_payzen_init');

/* Add our payment methods to woocommerce methods. */
function woocommerce_payzen_add_method($methods)
{
    global $payzen_plugin_features, $woocommerce;

    $methods[] = 'WC_Gateway_Payzen';
    $methods[] = 'WC_Gateway_PayzenStd';

    if ($payzen_plugin_features['multi']) {
        $methods[] = 'WC_Gateway_PayzenMulti';
    }

    if ($payzen_plugin_features['choozeo']) {
        $methods[] = 'WC_Gateway_PayzenChoozeo';
    }

    if ($payzen_plugin_features['klarna']) {
        $methods[] = 'WC_Gateway_PayzenKlarna';
    }

    $methods[] = 'WC_Gateway_PayzenRegroupedOther';

    // Since 2.3.0, we can display other payment means as submodules.
    if (version_compare($woocommerce->version, '2.3.0', '>=') && $woocommerce->cart) {
        $regrouped_other_payments = new WC_Gateway_PayzenRegroupedOther();

        if (! $regrouped_other_payments->regroup_other_payment_means()) {
            $payment_means = $regrouped_other_payments->get_available_options();
            if (is_array($payment_means) && ! empty($payment_means)) {
                foreach ($payment_means as $option) {
                    $methods[] = new WC_Gateway_PayzenOther($option['payment_mean'], $option['label']);
                }
            }
        }
    }

    if ($payzen_plugin_features['subscr']) {
        $methods[] = 'WC_Gateway_PayzenSubscription';
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

/* Retrieve blog_id from post when this is an IPN URL call. */
if (is_multisite() && key_exists('vads_hash', $_POST) && $_POST['vads_hash']
    && key_exists('vads_order_info', $_POST) && $_POST['vads_order_info']) {
    global $wpdb, $current_blog, $current_site;

    // Parse order_info parameter.
    $parts = explode('&', $_POST['vads_order_info']);

    $blog = substr($parts[1], strlen('blog_id='));
    switch_to_blog((int)$blog);

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
