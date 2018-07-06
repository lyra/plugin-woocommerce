<?php
/**
  * PayZen V2-Payment Module version 1.6.1 for WooCommerce 2.x-3.x. Support contact : support@payzen.eu.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @author    Alsacréations (Geoffrey Crofte http://alsacreations.fr/a-propos#geoffrey)
 * @copyright 2014-2018 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html  GNU General Public License (GPL v2)
 * @category  payment
 * @package   payzen
 */

if (! defined('ABSPATH')) {
    exit; // exit if accessed directly
}

/**
 * PayZen Payment Gateway : standard payment class.
 */
class WC_Gateway_PayzenKlarna extends WC_Gateway_PayzenStd
{

    public function __construct()
    {
        $this->id = 'payzenklarna';
        $this->icon = apply_filters('woocommerce_payzenklarna_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/klarna.png');
        $this->has_fields = true;
        $this->method_title = 'PayZen - ' . __('Payment with Klarna', 'woo-payzen-payment');

        // init PayZen common vars
        $this->payzen_init();

        // load the form fields
        $this->init_form_fields();

        // load the module settings
        $this->init_settings();

        // define user set variables
        $this->title = $this->get_title();
        $this->description = $this->get_description();
        $this->testmode = ($this->get_general_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_general_option('debug') == 'yes') ? true : false;

        // reset PayZen klarna payment admin form action
        add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

        // update PayZen klarna payment admin form action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // generate PayZen klarna payment form action
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['validation_mode']);
        unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['card_data_mode']);

        $this->form_fields['capture_delay']['default'] = 0;
        $this->form_fields['capture_delay']['description'] = __('The number of days before the bank capture. Should be between 0 and 7.', 'woo-payzen-payment');

        // by default, disable Klarna payment sub-module
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables Klarna payment.', 'woo-payzen-payment');

        $this->form_fields['title']['default'] = __('Pay with Klarna', 'woo-payzen-payment');

        // if WooCommecre Multilingual is not available (or installed version not allow gateways UI translation)
        // let's suggest our translation feature
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Pay with Klarna',
                'en_GB' => 'Pay with Klarna',
                'fr_FR' => 'Paiement avec Klarna',
                'de_DE' => 'Zahlung mit Klarna'
            );
        }
    }

    public function validate_capture_delay_field($key, $value = null)
    {
        $new_value = parent::validate_text_field($key, $value);

        if (! is_numeric($new_value) || ($new_value < 0) || ($value > 7)) {
            return $this->get_option($key); // restore old value
        }

        return $new_value;
    }

    /**
     * Check if this gateway is enabled and available for the current cart.
     */
    public function is_available()
    {
        global $woocommerce;

        if (! $woocommerce->customer) {
            return false;
        }

        $customer = $woocommerce->customer;
        $country = method_exists($customer, 'get_billing_country') ? $customer->get_billing_country() : $customer->get_country();

        // check billing country
        if (! in_array($country, array('AT', 'DE', 'DK', 'FI', 'NL', 'NO', 'SE'))) {
            // Klarna is available in some countries
            return false;
        }

        return parent::is_available();
    }

    /**
     * Prepare PayZen form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        parent::payzen_fill_request($order);

        // specific fields for klarna payment
        $this->payzen_request->set('payment_cards', 'KLARNA');
        $this->payzen_request->set('validation_mode', '1');

        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());

        // add cart products info
        foreach ($order->get_items() as $item_id => $line_item) {
            $item_data = $line_item->get_data();

            $product_amount = $item_data['total'] / $item_data['quantity'];
            $product_tax_amount = $item_data['total_tax'] / $item_data['quantity'];
            $product_tax_rate = round($product_tax_amount / $product_amount * 100, 4);

            $this->payzen_request->addProduct(
                $item_data['name'],
                $currency->convertAmountToInteger($product_amount),
                $item_data['quantity'],
                $item_data['product_id'],
                null, // we have no product category
                $product_tax_rate
            );
        }
    }
}
