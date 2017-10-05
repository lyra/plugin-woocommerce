<?php
/**
 * PayZen V2-Payment Module version 1.4.1 for WooCommerce 2.x-3.x. Support contact : support@payzen.eu.
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
 * @copyright 2014-2017 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html  GNU General Public License (GPL v2)
 * @category  payment
 * @package   payzen
 */

/**
 * PayZen Payment Gateway : multiple payment class.
 */
class WC_Gateway_PayzenChoozeo extends WC_Gateway_PayzenStd
{

    public function __construct()
    {
        $this->id = 'payzenchoozeo';
        $this->icon = apply_filters('woocommerce_payzenchoozeo_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/choozeo.png');
        $this->has_fields = true;
        $this->method_title = 'PayZen - ' . __('Payment with Choozeo', 'woo-payzen-payment');

        // init PayZen common vars
        $this->payzen_init();

        // load the form fields
        $this->init_form_fields();

        // load the module settings
        $this->init_settings();

        // define user set variables
        $this->title = $this->get_title();
        $this->description = $this->get_description();
        $this->testmode = ($this->get_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_option('debug') == 'yes') ? true : false;

        // reset PayZen multi payment admin form action
        add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

        // update PayZen multi payment admin form action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // generate PayZen multi payment form action
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

        // return from payment platform action
        add_action('woocommerce_api_wc_gateway_payzen', array($this, 'payzen_notify_response'));

        // filter to allow order status override
        add_filter('woocommerce_payment_complete_order_status', array($this, 'payzen_complete_order_status'), 10, 2);

        // customize email
        add_action('woocommerce_email_after_order_table', array($this, 'payzen_add_order_email_payment_result'), 10, 3);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['payment_page']);
        unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['card_data_mode']);

        // by default, disable Choozeo payment sub-module
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables Choozeo payment.', 'woo-payzen-payment');

        $this->form_fields['title']['default'] = __('Pay with Choozeo', 'woo-payzen-payment');

        // if WooCommecre Multilingual is not available (or installed version not allow gateways UI translation)
        // let's suggest our translation feature
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Pay with Choozeo',
                'en_GB' => 'Pay with Choozeo',
                'fr_FR' => 'Paiement avec Choozeo',
                'de_DE' => 'Zahlung mit Choozeo'
            );
        }

        // set amount restrictions default values
        $this->form_fields['amount_min']['default'] = '135';
        $this->form_fields['amount_max']['default'] = '2000';

        $this->form_fields['multi_options'] = array(
            'title' => __('CHOOZEO PAYMENT OPTIONS', 'woo-payzen-payment'),
            'type' => 'title'
        );

        $columns = array();
        $columns['label'] = array(
            'title' => __('Label', 'woo-payzen-payment'),
            'width' => '154px'
        );

        $columns['amount_min'] = array(
            'title' => __('Min amount', 'woo-payzen-payment'),
            'width' => '92px'
        );

        $columns['amount_max'] = array(
            'title' => __('Max amount', 'woo-payzen-payment'),
            'width' => '92px'
        );

        $this->form_fields['payment_options'] = array(
            'title' => __('Payment options', 'woo-payzen-payment'),
            'type' => 'table',
            'columns' => $columns,
            'default' => array (
                'EPNF_3X' => array('label' => 'Choozeo 3x CB', 'amount_min' => '', 'amount_max' => ''),
                'EPNF_4X' => array('label' => 'Choozeo 4x CB', 'amount_min' => '', 'amount_max' => ''),
            ),
            'description' => __('Define amount restriction for each card.', 'woo-payzen-payment')
        );
    }

    public function validate_amount_min_field($key, $value = null)
    {
        $new_value = parent::validate_text_field($key, $value);

        if (! is_numeric($new_value) || $new_value <= 0) {
            return $this->get_option($key); // restore old value
        }

        return $new_value;
    }

    public function validate_amount_max_field($key, $value = null)
    {
        $new_value = parent::validate_text_field($key, $value);

        if (! is_numeric($new_value) || $new_value <= 0) {
            return $this->get_option($key); // restore old value
        }

        return $new_value;
    }

    /**
     * Generate Text Input HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_table_html($key, $data)
    {
        global $woocommerce;

        $html = '';

        $data['title']           = isset($data['title']) ? $data['title'] : '';
        $data['disabled']        = empty($data['disabled']) ? false : true;
        $data['class']           = isset($data['class']) ? $data['class'] : '';
        $data['css']             = isset($data['css']) ? $data['css'] : '';
        $data['placeholder']     = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type']            = isset($data['type']) ? $data['type'] : 'text';
        $data['desc_tip']        = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description']     = isset($data['description']) ? $data['description'] : '';
        $data['columns']         = isset($data['columns']) ? (array) $data['columns'] : array();
        $data['default']         = isset($data['default']) ? (array) $data['default'] : array();

        // description handling
        if ($data['desc_tip'] === true) {
            $description = '';
            $tip         = $data['description'];
        } elseif (! empty($data['desc_tip'])) {
            $description = $data['description'];
            $tip         = $data['desc_tip'];
        } elseif (! empty($data['description'])) {
            $description = $data['description'];
            $tip         = '';
        } else {
            $description = $tip = '';
        }

        $field_name = esc_attr($this->plugin_id . $this->id . '_' . $key);

        $html .= '<tr valign="top">' . "\n";
        $html .= '<th scope="row" class="titledesc">';
        $html .= '<label for="' . esc_attr($this->plugin_id . $this->id . '_' . $key) . '">' . wp_kses_post($data['title']) . '</label>';

        if ($tip) {
            $html .= '<img class="help_tip" data-tip="' . esc_attr($tip) . '" src="' . $woocommerce->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
        }

        $html .= '</th>' . "\n";
        $html .= '<td class="forminp">' . "\n";
        $html .= '<fieldset><legend class="screen-reader-text"><span>' . wp_kses_post($data['title']) . '</span></legend>' . "\n";

         $html .= '<table id="' . $field_name . '_table" class="'. esc_attr($data['class']) . '" cellpadding="10" cellspacing="0" >';

        $html .= '<thead><tr>';
        $record = array();
        foreach ($data['columns'] as $code => $column) {
            $record[$code] = '';
            $html .= '<th class="' . $code . '" style="width: ' . $column['width'] . '; padding: 0px;">' . $column['title'] . '</th>';
        }

        $html .= '<th style="width: auto; padding: 0px;"></th>';
        $html .= '</tr></thead>';

        $html .= '<tbody>';

        $options = $this->get_option($key);
        if (! is_array($options) || empty($options)) {
            $options = $data['default'];
        }

        foreach ($options as $code => $option) {
            $html .= '<tr>';
            $html .= '<td style="padding: 0px;"><input name="'.$field_name.'['.$code.'][label]"
                      value="'. $option['label'] .'"
                      type="text" readonly></td>';
            $html .= '<td style="padding: 0px;"><input name="'.$field_name.'['.$code.'][amount_min]"
                      value="' . $option['amount_min'] . '"
                      type="text"></td>';
            $html .= '<td style="padding: 0px;"><input name="'.$field_name.'['.$code.'][amount_max]"
                      value="' . $option['amount_max'] . '"
                      type="text"></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if ($description) {
            $html .= ' <p class="description">' . wp_kses_post($description) . '</p>' . "\n";
        }

        $html .= '</fieldset>';
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    public function validate_payment_options_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());

        $old_value = $this->get_option($key);
        $min = $this->get_option('amount_min');
        $max = $this->get_option('amount_max');

        foreach ($value as $code => $option) {
            // clean strings
            $fnc = function_exists('wc_clean') ? 'wc_clean' : 'woocommerce_clean';
            $value[$code] = array_map('esc_attr', array_map($fnc, (array) $option));

            if (($option['amount_min'] && (! is_numeric($option['amount_min']) || $option['amount_min'] < 0 || $option['amount_min'] < $min))) {
                $value[$code]['amount_min'] = $old_value[$code]['amount_min']; // restore old value
            }

            if ($option['amount_max'] && (! is_numeric($option['amount_max']) || $option['amount_max'] < 0 || $option['amount_max'] > $max)) {
                $value[$code]['amount_max'] = $old_value[$code]['amount_max']; // restore old value
            }
        }

        return $value;
    }

    /**
     * Check if this gateway is enabled and available for the current cart.
     */
    public function is_available()
    {
        global $woocommerce;

        // check billing country
        if ($woocommerce->customer && $woocommerce->customer->get_billing_country() != 'FR') {
            // Choozeo available only in France, otherwise module is not available
            return false;
        }

        // check Choozeo payment options
        $available_options = $this->get_available_options();
        if ($woocommerce->cart && empty($available_options)) {
            return false;
        }

        // check currency
        if (get_woocommerce_currency() != 'EUR') {
            // Choozeo supports only EURO, otherwise module is not available
            return false;
        }

        return parent::is_available();
    }

    private function get_available_options()
    {
        global $woocommerce;

        $amount = $woocommerce->cart->total;

        $options = $this->get_option('payment_options');
        $enabled_options = array();

        if (isset($options) && is_array($options) && ! empty($options)) {
            foreach ($options as $code => $option) {
                if ((! $option['amount_min'] || $amount >= $option['amount_min']) && (! $option['amount_max'] || $amount <= $option['amount_max'])) {
                    $enabled_options[$code] = $option;
                }
            }
        }

        return $enabled_options;
    }

    /**
     * Display payment fields and show method description if set.
     *
     * @access public
     * @return void
     */
    public function payment_fields()
    {
        parent::payment_fields();

        $options = $this->get_available_options();

        if (empty($options)) {
            return;
        }

        if (count($options) == 1) {
            echo '<span style="font-weight: bold;">' . __('Your payment option', 'woo-payzen-payment') . '</span>';
        } else {
            echo '<span style="font-weight: bold;">' . __('Choose your payment option', 'woo-payzen-payment') . '</span>';
        }

        echo '<div>';

        $first = true;
        foreach ($options as $key => $option) {
            echo '<div style="display: inline-block;">';

            if (count($options) == 1) {
                echo '<input type="hidden"
                             id="payzenchoozeo_option_' . $key . '"
                             value="' . $key . '"
                             name="payzenchoozeo_option">';
            } else {
                echo '<input class="radio"
                             type="radio"
                             id="payzenchoozeo_option_' . $key . '"
                             value="' . $key . '"
                             name="payzenchoozeo_option"' .
                             ($first == true ? ' checked="checked"' : '') . '
                             style="vertical-align: middle;">';

                $first = false;
            }

            echo '<label for="payzenchoozeo_option_' . $key . '" style="display: inline;">' .  '
                      <img src="' . WC_PAYZEN_PLUGIN_URL . 'assets/images/' . strtolower($key) . '.png"
                           alt="' . $option['label'] . '"
                           title="' . $option['label'] . '"
                           style="vertical-align: middle; margin-right: 10px; height: 45px;">
                  </label>
              </div>';
        }

        echo '</div><br />';
    }

    /**
     * Process the payment and return the result
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        $option = $_POST['payzenchoozeo_option'];
        $options = $this->get_available_options();
        $label = $options[$option]['label'];

        // save selected payment option into session
        set_transient('payzenchoozeo_option_' . $order_id, $option);

        // ... and into DB
        $order = new WC_Order($order_id);
        update_post_meta($this->get_order_property($order, 'id'), '_payment_method_title', $this->get_order_property($order, 'payment_method_title') . " ({$label})");

        if (version_compare($woocommerce->version, '2.1.0', '<')) {
            $pay_url = add_query_arg('order', $this->get_order_property($order, 'id'), add_query_arg('key', $this->get_order_property($order, 'order_key'), get_permalink(woocommerce_get_page_id('pay'))));
        } else {
            $pay_url = $order->get_checkout_payment_url(true);
        }

        return array(
            'result' => 'success',
            'redirect' => $pay_url
        );
    }

    /**
     * Prepare PayZen form params to send to payment platform.
     **/
    protected function payzen_fill_request($order)
    {
        parent::payzen_fill_request($order);

        $option = get_transient('payzenchoozeo_option_' . $this->get_order_property($order, 'id'));
        $this->payzen_request->set('payment_cards', $option);

        // by default WooCommerce does not manage customer type
        $this->payzen_request->set('cust_status', 'PRIVATE');

        // Choozeo supports only automatic validation
        $this->payzen_request->set('validation_mode', '0');

        delete_transient('payzenchoozeo_option_' . $this->get_order_property($order, 'id'));
    }
}
