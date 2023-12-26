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

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_Gateway_PayzenChoozeo extends WC_Gateway_PayzenStd
{
    protected $payzen_countries = array('FR', 'GP', 'MQ', 'GF', 'RE', 'YT'); // France and DOM.
    protected $payzen_currencies = array('EUR');

    public function __construct()
    {
        $this->id = 'payzenchoozeo';
        $this->icon = apply_filters('woocommerce_payzenchoozeo_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/choozeo.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME. ' - ' . __('Choozeo payment', 'woo-payzen-payment');

        // Init common vars.
        $this->payzen_init();

        // Load the form fields.
        $this->init_form_fields();

        // Load the module settings.
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_title();
        $this->description = $this->get_description();
        $this->testmode = ($this->get_general_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_general_option('debug') == 'yes') ? true : false;

        if ($this->payzen_is_section_loaded()) {
            // Reset choozeo payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // Update choozeo payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_script'));
        }

        // Generate payment form action.
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

        // Generate payment fields filter.
        add_filter('woocommerce_payzen_payment_fields_' . $this->id, array($this, 'get_payment_fields'));
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['validation_mode']);
        unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['card_data_mode']);
        unset($this->form_fields['payment_by_token']);

        // By default, disable Choozeo payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables Choozeo payment.', 'woo-payzen-payment');

        $this->form_fields['title']['default'] = __('Payment with Choozeo without fees', 'woo-payzen-payment');

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Payment with Choozeo without fees',
                'en_GB' => 'Payment with Choozeo without fees',
                'fr_FR' => 'Paiement avec Choozeo sans frais',
                'de_DE' => 'Zahlung mit Choozeo ohne zusätzliche',
                'es_ES' => 'Pago con Choozeo sin gastos',
                'pt_BR' => 'Pagamento com Choozeo sem custo'
            );
        }

        // Set amount restrictions default values.
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

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
    }

    /**
     * Generate text input HTML.
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

        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['disabled'] = empty($data['disabled']) ? false : true;
        $data['class'] = isset($data['class']) ? $data['class'] : '';
        $data['css'] = isset($data['css']) ? $data['css'] : '';
        $data['placeholder'] = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type'] = isset($data['type']) ? $data['type'] : 'text';
        $data['desc_tip'] = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description'] = isset($data['description']) ? $data['description'] : '';
        $data['columns'] = isset($data['columns']) ? (array) $data['columns'] : array();
        $data['default'] = isset($data['default']) ? (array) $data['default'] : array();

        // Description handling.
        if ($data['desc_tip'] === true) {
            $description = '';
            $tip = $data['description'];
        } elseif (! empty($data['desc_tip'])) {
            $description = $data['description'];
            $tip = $data['desc_tip'];
        } elseif (! empty($data['description'])) {
            $description = $data['description'];
            $tip = '';
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

         $html .= '<table id="' . $field_name . '_table" class="' . esc_attr($data['class']) . '" cellpadding="10" cellspacing="0" >';

        $html .= '<thead><tr>';
        foreach ($data['columns'] as $code => $column) {
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
            $html .= '<td style="padding: 0px;"><input name="' . $field_name . '[' . $code . '][label]"
                      value="' . $option['label'] . '"
                      type="text" readonly></td>';
            $html .= '<td style="padding: 0px;"><input name="' . $field_name . '[' . $code . '][amount_min]"
                      value="' . $option['amount_min'] . '"
                      type="text"></td>';
            $html .= '<td style="padding: 0px;"><input name="' . $field_name . '[' . $code . '][amount_max]"
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
            // Clean strings.
            $fnc = function_exists('wc_clean') ? 'wc_clean' : 'woocommerce_clean';
            $value[$code] = array_map('esc_attr', array_map($fnc, (array) $option));

            if (($option['amount_min'] && (! is_numeric($option['amount_min']) || $option['amount_min'] < 0 || $option['amount_min'] < $min))) {
                $value[$code]['amount_min'] = $old_value[$code]['amount_min']; // Restore old value.
            }

            if ($option['amount_max'] && (! is_numeric($option['amount_max']) || $option['amount_max'] < 0 || $option['amount_max'] > $max)) {
                $value[$code]['amount_max'] = $old_value[$code]['amount_max']; // Restore old value.
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

        if (! parent::is_available()) {
            return false;
        }

        $order_id = get_query_var('order-pay');
        if ($order_id || $woocommerce->cart) {
            // Check Choozeo payment options.
            $available_options = $this->get_available_options();
            if (empty($available_options)) {
                return false;
            }
        }

        return true;
    }

    private function get_available_options()
    {
        global $woocommerce;

        // Recover total amount either from order or from current cart if any.
        $amount = self::get_total_amount();

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
            echo '<div style="display: inline-block; margin: 10px;">';

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

            echo '<label for="payzenchoozeo_option_' . $key . '" style="display: inline;">' . '
                      <img src="' . self::LOGO_URL . strtolower($key) . '.png"
                           alt="' . $key . '"
                           title="' . $option['label'] . '"
                           style="vertical-align: middle; margin-left: 5px; max-height: 35px; display: unset;">
                  </label>
              </div>';
        }

        echo '</div><br />';
    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        $option = $_POST['payzenchoozeo_option'];
        $options = $this->get_available_options();
        $label = $options[$option]['label'];

        // Save selected payment option into session...
        set_transient('payzenchoozeo_option_' . $order_id, $option);

        // ... and into DB.
        $order = new WC_Order($order_id);
        if (PayzenTools::is_hpos_enabled()) {
            $order->set_payment_method_title($order->get_payment_method_title() . " ({$label})");
            $order->save();
        } else {
            update_post_meta(self::get_order_property($order, 'id'), '_payment_method_title', self::get_order_property($order, 'payment_method_title') . " ({$label})");
        }

        if (version_compare($woocommerce->version, '2.1.0', '<')) {
            $pay_url = add_query_arg('order', self::get_order_property($order, 'id'), add_query_arg('key', self::get_order_property($order, 'order_key'), get_permalink(woocommerce_get_page_id('pay'))));
        } else {
            $pay_url = $order->get_checkout_payment_url(true);
        }

        return array(
            'result' => 'success',
            'redirect' => $pay_url
        );
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        parent::payzen_fill_request($order);

        $option = get_transient('payzenchoozeo_option_' . self::get_order_property($order, 'id'));
        $this->payzen_request->set('payment_cards', $option);

        // By default WooCommerce does not manage customer type.
        $this->payzen_request->set('cust_status', 'PRIVATE');

        // Choozeo supports only automatic validation.
        $this->payzen_request->set('validation_mode', '0');

        // Send FR even address is in DOM-TOM unless form is rejected.
        $this->payzen_request->set('cust_country', 'FR');

        delete_transient('payzenchoozeo_option_' . self::get_order_property($order, 'id'));
    }
}
