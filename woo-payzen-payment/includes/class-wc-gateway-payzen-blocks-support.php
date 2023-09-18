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

    public function __construct($method_id)
    {
        $this->name = $method_id;


    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_' . $this->get_name() .'_settings', null);
        // Load utils script.
        wp_register_script('utils-js', WC_PAYZEN_PLUGIN_URL . 'assets/js/utils.js');
        wp_enqueue_script('utils-js');
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $asset_path   = WC_PAYZEN_PLUGIN_PATH . 'build/index.asset.php';
        $version = PayzenTools::get_contrib();

        $dependencies = array();
        if (file_exists($asset_path)) {
            $asset = require $asset_path;
            $version = is_array($asset) && isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies']) ? $asset['dependencies']
                : $dependencies;
        }

        $build_name = (strpos($this->name, 'payzenother_') === 0) ? 'payzenother' : $this->name;
        wp_register_script(
            'wc-' . $this->get_name() . '-blocks-integration',
            WC_PAYZEN_PLUGIN_URL . 'build/' . $this->get_name() .'.js',
            $dependencies,
            $version,
            true
        );

        if ($build_name == 'payzenother') {
            wp_register_script(
                'wc-payzenother-blocks-integration',
                WC_PAYZEN_PLUGIN_URL . '/build/payzenother.js',
                $dependencies,
                $version,
                true
            );
        }

        wp_set_script_translations(
            'wc-' . $this->get_name() . '-blocks-integration',
            'woo-payzen-payment'
        );

        return array('wc-' . $this->get_name() . '-blocks-integration');
    }

    /**
     * Returns an array of key => value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        if (! apply_filters('woocommerce_available_' . $this->get_name(), null) || ($this->get_setting('card_data_mode') != 'DEFAULT')) {
            return $this->get_name() . '_disabled';
        }

        return array(
            'title'       => apply_filters('woocommerce_title_' . $this->get_name(), null),
            'supports'    => $this->get_supported_features(),
            'description' => apply_filters('woocommerce_description_' . $this->get_name(), null),
            'logo_url'    => apply_filters('woocommerce_'. $this->get_name() .'_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/payzen.png')
        );

        switch ($this->name) {
            case 'payzenstd':
            case 'payzenmulti':
            case 'payzenfranfinance':
            case 'payzenregroupedother':
                $data['payment_fields'] = $this->payment->get_payment_fields();

                break;

            case 'payzenother_lyranetwork':
                if (get_transient('payzen_other_methods')) {
                    $methods = json_decode(get_transient('payzen_other_methods'), true);
                    $data['sub_methods'] = array_keys($methods);
                }

                break;

            case 'payzensubscription':
                $data['supports'] = array_merge($this->get_supported_features(), $this->payment->supports);

                break;

            default:
                break;
        }
    }
}
