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
final class WC_Gateway_Payzen_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name;

    protected $payment;


    public function __construct($payment_class_name)
    {
        $this->payment = new $payment_class_name();
        $this->name = $this->payment->id;
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_' . $this->name .'_settings', null);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return ($this->payment->is_available() && ($this->payment->get_option('card_data_mode') === 'DEFAULT'));
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $asset_path   = WC_PAYZEN_PLUGIN_PATH . '/build/index.asset.php';
        $version      = PayzenTools::get_contrib();

        $dependencies = array();
        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = is_array( $asset ) && isset( $asset['version'] )
                ? $asset['version']
                : $version;
            $dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
                ? $asset['dependencies']
                : $dependencies;
        }

        wp_register_script(
            'wc-' . $this->name . '-blocks-integration',
            WC_PAYZEN_PLUGIN_URL . '/build/' . $this->name .'.js',
            $dependencies,
            $version,
            true
        );

        wp_set_script_translations(
            'wc-' . $this->name . '-blocks-integration',
            'woo-payzen-payment'
        );

        return array('wc-' . $this->name . '-blocks-integration');
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $data = array(
            'title'       => $this->payment->get_title(),
            'supports'    => $this->get_supported_features(),
            'description' => $this->payment->description,
            'logo_url' => WC_PAYZEN_PLUGIN_URL . 'assets/images/',
        );

        return $data;
    }
}
