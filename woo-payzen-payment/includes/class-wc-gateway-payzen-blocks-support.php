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

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Payzen payment method integration
 *
 * @since 1.10.0
 */
final class WC_Gateway_Payzen_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * ID of the payment method.
     *
     * @var string
     */
    protected $name;

    /**
     * Label of the payment method.
     *
     * @var string
     */
    protected $label = null;

    public function __construct($method_id, $label = null)
    {
        $this->name = strtolower($method_id);

        if ($label) {
            $this->label = $label;
        }
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        if (strpos($this->name, 'payzenother_') === 0) {
            $this->settings = get_option('woocommerce_payzenregroupedother_settings', null);
        } else {
            $this->settings = get_option('woocommerce_' . $this->name .'_settings', null);
        }

        // Load utils script.
        wp_register_script('payzen-utils', WC_PAYZEN_PLUGIN_URL . 'assets/js/utils.js');
        wp_enqueue_script('payzen-utils');
    }

    public function get_supported_features()
    {
        if ($this->name === 'payzensubscription') {
            return array(
                'products',
                'subscriptions',
                'subscription_cancellation',
                'subscription_payment_method_change',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change_customer',
                'gateway_scheduled_payments',
                'subscription_suspension',
                'subscription_reactivation'
            );
        } elseif ($this->get_name() === 'payzenwcssubscription') {
            return array(
                'products',
                'subscriptions',
                'subscription_cancellation',
                'subscription_payment_method_change',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change_customer',
                'subscription_suspension',
                'subscription_reactivation',
                'multiple_subscriptions',
                'subscription_payment_method_change_admin',
                'subscription_payment_method_delayed_change'
            );
        }

        return parent::get_supported_features();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $asset_path = WC_PAYZEN_PLUGIN_PATH . 'build/index.asset.php';
        $version = PayzenTools::get_contrib();

        $dependencies = array();
        if (file_exists($asset_path)) {
            $asset = require $asset_path;
            $version = is_array($asset) && isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies']) ? $asset['dependencies']
                : $dependencies;
        }

        $build_name = (strpos($this->get_name(), 'payzenother_') === 0) ? 'payzenother' : $this->get_name();
        if (! wp_script_is('wc-' . $this->get_name() . '-blocks-integration', 'registered')) {
            wp_register_script(
                'wc-' . $this->name . '-blocks-integration',
                WC_PAYZEN_PLUGIN_URL . 'build/' . $build_name . '.js',
                $dependencies,
                $version,
                true
            );
        }

        if (($build_name == 'payzenother') && ! wp_script_is('wc-payzenother-blocks-integration', 'registered')) {
            wp_register_script(
                'wc-payzenother-blocks-integration',
                WC_PAYZEN_PLUGIN_URL . 'build/payzenother.js',
                $dependencies,
                $version,
                true
            );
        }

        wp_set_script_translations(
            'wc-' . $this->name . '-blocks-integration',
            'woo-payzen-payment'
        );

        return array('wc-' . $this->name . '-blocks-integration');
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        if (isset($this->settings['enabled']) && ($this->settings['enabled'] == 'yes')) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array of key => value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        if (! is_admin() && ($this->get_name() !== 'payzenother_lyranetwork') && ! apply_filters('woocommerce_available_' . $this->get_name(), null)) {
            return;
        }

        delete_transient('payzen_token_' . wp_get_session_token());
        delete_transient('payzen_id_token_' . wp_get_session_token());

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/payzen.png';

        switch ($this->get_name()) {
            case 'payzenklarna':
            case 'payzenfranfinance':
                $img_url = WC_Gateway_Payzen::LOGO_URL . substr($this->get_name(), strlen('payzen')) . '.png';
                break;

            case 'payzenregroupedother':
                $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/other.png';

                break;

            default:
                if (strpos($this->get_name(), 'payzenother_') === 0) {
                    $img_url = WC_Gateway_Payzen::LOGO_URL . substr($this->get_name(), strlen('payzenother_')) . '.png';
                }

                break;
        }

        $data = array(
            'title'       => $this->label ? $this->label : apply_filters('woocommerce_title_' . $this->name, null),
            'supports'    => $this->get_supported_features(),
            'description' => apply_filters('woocommerce_description_' . $this->name, null),
            'logo_url'    => apply_filters('woocommerce_' . $this->name . '_icon', $img_url)
        );

        switch ($this->get_name()) {
            case 'payzenstd':
            case 'payzenmulti':
            case 'payzenfranfinance':
            case 'payzenregroupedother':
                $data['payment_fields'] = apply_filters('woocommerce_payzen_payment_fields_' . $this->get_name(), null);

                if ($this->get_name() === 'payzenstd') {
                    $data['payment_mode'] = isset($this->settings['card_data_mode']) ? $this->settings['card_data_mode'] : 'REDIRECT';
                    if ($data['payment_mode'] === 'IFRAME') {
                        $data['link'] = add_query_arg('wc-api', 'WC_Gateway_payzenstd', home_url('/'));
                        $data['src'] = add_query_arg('loading', 'true', $data['link']);
                    } elseif (in_array($data['payment_mode'], ['REST', 'SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS'])) {
                        if ($vars = get_transient('payzen_js_vars_' . wp_get_session_token())) {
                            $data['vars'] = $vars;
                            $data['hide_smartbutton'] = get_transient('payzen_hide_smartbutton_' . wp_get_session_token());
                            $data['token_url'] = add_query_arg('wc-api', 'WC_Gateway_Payzen_Form_Token', home_url('/'));

                            delete_transient('payzen_js_vars_' . wp_get_session_token());
                            delete_transient('payzen_hide_smartbutton_' . wp_get_session_token());
                        }
                    }
                }

                break;

            case 'payzenother_lyranetwork':
                if (get_transient('payzen_other_methods')) {
                    $methods = json_decode(get_transient('payzen_other_methods'), true);
                    $data['sub_methods'] = array_keys($methods);
                }

                break;

            default:
                break;
        }

        return $data;
    }
}
