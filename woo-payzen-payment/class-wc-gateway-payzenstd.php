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

class WC_Gateway_PayzenStd extends WC_Gateway_Payzen
{
    const ALL_COUNTRIES = '1';
    const SPECIFIC_COUNTRIES = '2';

    protected $payzen_countries = array();
    protected $payzen_currencies = array();

    public function __construct()
    {
        $this->id = 'payzenstd';
        $this->icon = apply_filters('woocommerce_payzenstd_icon', WC_PAYZEN_PLUGIN_URL . '/assets/images/payzen.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('Standard payment', 'woo-payzen-payment');

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
            // Reset standard payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // Update standard payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_script'));
        }

        // Generate standard payment form action.
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

        // Iframe payment endpoint action.
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'payzen_generate_iframe_form'));

        // Return from REST payment action.
        add_action('woocommerce_api_wc_gateway_payzen_rest', array($this, 'payzen_rest_return_response'));

        // Notification from REST payment action.
        add_action('woocommerce_api_wc_gateway_payzen_notify_rest', array($this, 'payzen_rest_notify_response'));

        // Rest payment generate token.
        add_action('woocommerce_api_wc_gateway_payzen_form_token', array($this, 'payzen_refresh_form_token'));

        // Adding JS to load REST libs.
        add_action('wp_head', array($this, 'payzen_rest_head_script'));
    }

    public function payzen_rest_head_script()
    {
        if ($this->get_option('card_data_mode') === 'REST' && $this->is_available()) {
            $payzen_pub_key = $this->testmode ? $this->settings['test_public_key'] : $this->settings['prod_public_key'];

            $locale = get_locale() ? substr(get_locale(), 0, 2) : null;
            if (! $locale || ! PayzenApi::isSupportedLanguage($locale)) {
                $locale = $this->settings['language'];
            }

            $language_iso_code = $locale;
            $return_url = add_query_arg('wc-api', 'WC_Gateway_Payzen_Rest', network_home_url('/'));
            $custom_placeholders = '';

            // Custom placeholders.
            $rest_placeholders = (array) stripslashes_deep($this->settings['rest_placeholder']);
            if ($pan_label = $rest_placeholders['pan']) {
                $custom_placeholders .= ' kr-placeholder-pan="' . $pan_label . '"';
            }

            if ($expiry_label = $rest_placeholders['expiry']) {
                $custom_placeholders .= ' kr-placeholder-expiry="' . $expiry_label . '"';
            }

            if ($cvv_label = $rest_placeholders['cvv']) {
                $custom_placeholders .= ' kr-placeholder-security-code="' . $cvv_label . '"';
            }

            // Custom theme.
            $payzen_std_rest_theme = $this->settings['rest_theme'];

            ?>
                <script>
                    var PAYZEN_LANGUAGE = "<?php echo $language_iso_code; ?>"
                </script>
                <script src="<?php echo self::STATIC_URL; ?>js/krypton-client/V4.0/stable/kr-payment-form.min.js"
                        kr-public-key="<?php echo $payzen_pub_key; ?>"
                        kr-post-url-success="<?php echo $return_url; ?>"
                        kr-post-url-refused="<?php echo $return_url; ?>"
                        kr-language="<?php echo $language_iso_code; ?>"<?php echo $custom_placeholders; ?>>
                </script>

                <link rel="stylesheet" href="<?php echo self::STATIC_URL ?>js/krypton-client/V4.0/ext/<?php echo $payzen_std_rest_theme;?>-reset.css">
                <script src="<?php echo self::STATIC_URL ?>js/krypton-client/V4.0/ext/<?php echo $payzen_std_rest_theme;?>.js"></script>

                <style>
                    #payzenstd_rest_wrapper button.kr-popin-button {
                        display: none !important;
                        width: 0;
                        height: 0;
                    }
                </style>
            <?php

            // Load REST script.
            wp_register_script('rest-js', WC_PAYZEN_PLUGIN_URL . 'assets/js/rest.js');
            wp_enqueue_script('rest-js');
        }
    }

    public function payzen_admin_head_style()
    {
        parent::payzen_admin_head_style();

        ?>
        <style>
            #woocommerce_payzenstd_rest_check_url + p.description span.url {
                color: #23282d !important;
                font-size: 16px;
                font-weight: bold;
            }

            #woocommerce_payzenstd_rest_check_url + p.description span.desc {
                color: red !important;
                display: inline-block;
            }

            #woocommerce_payzenstd_rest_check_url + p.description img {
                margin-right: 5px;
            }
        </style>
        <?php
    }

    /**
     * Get icon function.
     *
     * @access public
     * @return string
     */
    public function get_icon()
    {
        global $woocommerce;
        $icon = '';

        if ($this->icon) {
            $icon = '<img style="max-width: 85px; max-height: 30px;" src="';
            $icon .= class_exists('WC_HTTPS') ? WC_HTTPS::force_https_url($this->icon) : $woocommerce->force_ssl($this->icon);
            $icon .= '" alt="' . $this->get_title() . '" />';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Get title function.
     *
     * @access public
     * @return string
     */
    public function get_title()
    {
        $title = $this->get_option('title');

        if (is_array($title)) {
            $title = isset($title[get_locale()]) && $title[get_locale()] ? $title[get_locale()] : $title['en_US'];
        }

        $title = stripslashes($title);
        return apply_filters('woocommerce_gateway_title', $title, $this->id);
    }

    /**
     * Get description function.
     *
     * @access public
     * @return string
     */
    public function get_description()
    {
        switch ($this->get_option('card_data_mode')) {
            case 'REST':
                if ($this->get_option('rest_display_mode') === 'embedded') {
                    return '';
                }

                // Break ommited intentionnally.

            case 'IFRAME':
                return __('You will enter payment data after order confirmation.', 'woo-payzen-payment');

            default:
                $description = $this->get_option('description');

                if (is_array($description)) {
                    $description = isset($description[get_locale()]) && $description[get_locale()] ? $description[get_locale()] : $description['en_US'];
                }

                return apply_filters('woocommerce_gateway_description', $description, $this->id);
        }
    }

    private function filter_allowed_countries($countries)
    {
        if (empty($this->payzen_countries)) {
            return $countries;
        } else {
            $allowed_countries = array();
            foreach ($this->payzen_countries as $code) {
                if (! isset($countries[$code])) {
                    continue;
                }

                $allowed_countries[$code] = $countries[$code];
            }

            return $allowed_countries;
        }
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        global $payzen_plugin_features;

        // Load common form fields to concat them with submodule settings.
        parent::init_form_fields();

        $countries = new WC_Countries();
        $allowed_countries = $this->filter_allowed_countries($countries->get_allowed_countries());

        $this->form_fields = array(
            // CMS config params.
            'module_settings' => array(
                'title' => __('MODULE SETTINGS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'enabled' => array(
                'title' => __('Activation', 'woo-payzen-payment'),
                'label' => __('Enable / disable', 'woo-payzen-payment'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Enables / disables standard payment.', 'woo-payzen-payment')
            ),
            'title' => array(
                'title' => __('Title', 'woo-payzen-payment'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-payzen-payment'),
                'default' => __('Payment by credit card', 'woo-payzen-payment')
            ),
            'description' => array(
                'title' => __('Description', 'woo-payzen-payment'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woo-payzen-payment'),
                'default' => __('You will be redirected to payment page after order confirmation.', 'woo-payzen-payment'),
                'css' => 'width: 35em;'
            ),

            // Amount restrictions.
            'restrictions' => array(
                'title' => __('RESTRICTIONS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'allows_specific' => array(
                'custom_attributes' => array(
                    'onchange' => 'payzenUpdateSpecificCountriesDisplay()'
                ),
                'title' => __('Restrict to some countries', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => '1',
                'options' => array(
                    self::ALL_COUNTRIES => __('All allowed countries', 'woo-payzen-payment'),
                    self::SPECIFIC_COUNTRIES => __('Specific countries', 'woo-payzen-payment')
                ),
                'class' => 'wc-enhanced-select',
                'description' => __('Buyer\'s billing countries in which this payment method is available.', 'woo-payzen-payment')
            ),
            'specific_countries' => array(
                'title' => __('Authorized countries', 'woo-payzen-payment'),
                'type' => 'multiselect',
                'default' => '',
                'options' => $allowed_countries,
                'class' => 'wc-enhanced-select'
            ),
            'amount_min' => array(
                'title' => __('Minimum amount', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => '',
                'description' => __('Minimum amount to activate this payment method.', 'woo-payzen-payment')
            ),
            'amount_max' => array(
                'title' => __('Maximum amount', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => '',
                'description' => __('Maximum amount to activate this payment method.', 'woo-payzen-payment')
            ),

            // Payment page.
            'payment_page' => array(
                'title' => __('PAYMENT PAGE', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'capture_delay' => array(
                'title' => __('Capture delay', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => '',
                'description' => sprintf(__('The number of days before the bank capture. Enter value only if different from %s general configuration.', 'woo-payzen-payment'), self::GATEWAY_NAME)
            ),
            'validation_mode' => array(
                'title' => __('Validation mode', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => '-1',
                'options' => $this->get_validation_modes(),
                'description' => sprintf(__('If manual is selected, you will have to confirm payments manually in your %s Back Office.', 'woo-payzen-payment'), self::BACKOFFICE_NAME),
                'class' => 'wc-enhanced-select'
            ),
            'payment_cards' => array(
                'title' => __('Card Types', 'woo-payzen-payment'),
                'type' => 'multiselect',
                'default' => array(),
                'options' => $this->get_supported_card_types(),
                'description' => __('The card type(s) that can be used for the payment. Select none to use gateway configuration.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            ),

            // Advanced options.
            'advanced_options' => array(
                'title' => __('ADVANCED OPTIONS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'card_data_mode' => array(
                'custom_attributes' => array(
                    'onchange' => 'payzenUpdateRestFieldDisplay()'
                ),
                'title' => __('Card data entry mode', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => 'DEFAULT',
                'options' => array(
                    'DEFAULT' => __('Card data entry on payment gateway', 'woo-payzen-payment'),
                    'MERCHANT' => __('Card type selection on merchant site', 'woo-payzen-payment'),
                    'IFRAME' => __('Payment page integrated to checkout process (iframe)', 'woo-payzen-payment'),
                ),
                'description' => __('Select how the credit card data will be entered by buyer. Think to update payment method description to match your selected mode.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            )
        );

        // Add rest fields if available for payment.
        if ($payzen_plugin_features['embedded']) {
            $this->form_fields['card_data_mode']['options']['REST'] = __('Embedded payment fields (REST API)', 'woo-payzen-payment');
            $this->get_rest_fields();
        }

        // Add payment by token fields.
        $this->form_fields['payment_by_token'] = array(
            'custom_attributes' => array(
                'onchange' => 'payzenUpdatePaymentByTokenField()',
            ),
            'title' => __('Payment by token', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => '0',
            'options' => array(
                '1' => __('Yes', 'woo-payzen-payment'),
                '0' => __('No', 'woo-payzen-payment')
            ),
            'description' => sprintf(__('The payment by token allows to pay orders without re-entering bank data at each payment. The "Payment by token" option should be enabled on your %s store to use this feature.', 'woo-payzen-payment'), self::GATEWAY_NAME),
            'class' => 'wc-enhanced-select'
        );

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['type'] = 'multilangtext';
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Payment by credit card',
                'en_GB' => 'Payment by credit card',
                'fr_FR' => 'Paiement par carte bancaire',
                'de_DE' => 'Zahlung mit EC-/Kreditkarte',
                'es_ES' => 'Pago con tarjeta de crédito'
            );

            $this->form_fields['description']['type'] = 'multilangtext';
            $this->form_fields['description']['default'] = array(
                'en_US' => 'You will be redirected to payment page after order confirmation.',
                'en_GB' => 'You will be redirected to payment page after order confirmation.',
                'fr_FR' => 'Vous allez être redirigé(e) vers la page de paiement après confirmation de la commande.',
                'de_DE' => 'Sie werden zu den Zahlungsseiten nach Zahlungsbestätigung weitergeleitet.',
                'es_ES' => 'Será redireccionado a la página de pago después de la confirmación del pedido.'
            );
        }
    }

    protected function get_rest_fields()
    {
        // Add Rest fields.
        $this->form_fields['rest_settings'] = array(
            'title' => __('REST API keys', 'woo-payzen-payment'),
            'type' => 'title',
            'description' => sprintf(__('REST API keys are available in your store Back Office %s (menu: Settings > Shops > REST API keys).', 'woo-payzen-payment'), 'PayZen')
        );

        $this->form_fields['test_private_key'] = array(
            'title' => __('Test password', 'woo-payzen-payment'),
            'type' => 'password',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['prod_private_key'] = array(
            'title' => __('Production password', 'woo-payzen-payment'),
            'type' => 'password',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['test_public_key'] = array(
            'title' => __('Public test key', 'woo-payzen-payment'),
            'type' => 'text',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['prod_public_key'] = array(
            'title' => __('Public production key', 'woo-payzen-payment'),
            'type' => 'text',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['test_return_key'] = array(
            'title' => __('HMAC-SHA-256 test key', 'woo-payzen-payment'),
            'type' => 'password',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['prod_return_key'] = array(
            'title' => __('HMAC-SHA-256 production key', 'woo-payzen-payment'),
            'type' => 'password',
            'default' => '',
            'custom_attributes' => array('autocomplete' => 'off')
        );

        $this->form_fields['rest_check_url'] = array(
            'title' => __('API REST Notification URL', 'woo-payzen-payment'),
            'type' => 'text',
            'default' => '',
            'description' => '<span class="url">' . add_query_arg('wc-api', 'WC_Gateway_Payzen_Notify_Rest', network_home_url('/')). '</span><br />' .
            '<img src="' . esc_url(WC_PAYZEN_PLUGIN_URL . 'assets/images/warn.png') . '"><span class="desc">' . sprintf(__('URL to copy into your %s Back Office > Settings > Notification rules.<br>In multistore mode, notification URL is the same for all the stores.', 'woo-payzen-payment'), self::GATEWAY_NAME). '</span>',
            'css' => 'display: none;'
        );

        $this->form_fields['rest_customization'] = array(
            'title' => __('CUSTOMIZATION', 'woo-payzen-payment'),
            'type' => 'title'
        );

        $this->form_fields['rest_display_mode'] = array(
            'title' => __('Display mode', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => 'embedded',
            'options' => array(
                'embedded' => __('Directly on merchant site', 'woo-payzen-payment'),
                'popin' => __('In a pop-in', 'woo-payzen-payment')
            ),
            'description' => __('Select the mode to use to display embedded payment fields.', 'woo-payzen-payment'),
            'class' => 'wc-enhanced-select'
        );

        $this->form_fields['rest_theme'] = array(
            'title' => __('Theme', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => 'material',
            'options' => array(
                'classic' => 'Classic',
                'material' => 'Material'
            ),
            'description' => __('Select a theme to use to display embedded payment fields. For more customization, you may edit module template manually.', 'woo-payzen-payment'),
            'class' => 'wc-enhanced-select'
        );

        $this->form_fields['rest_placeholder'] = array(
            'title' => __('Custom fields placeholders', 'woo-payzen-payment'),
            'type' => 'placeholder_table',
            'default' => array(
                'pan' => '',
                'expiry' => '',
                'cvv' => ''
            ),
            'description' => __('Texts to use as placeholders for embedded payment fields.', 'woo-payzen-payment')
        );

        $this->form_fields['rest_attempts'] = array(
            'title' => __('Payment attempts number', 'woo-payzen-payment'),
            'type' => 'text',
            'description' => __('Maximum number of payment retries after a failed payment (between 0 and 10). Leave blank to use gateway configuration.', 'woo-payzen-payment')
        );
    }

    public function generate_placeholder_table_html($key, $data)
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

        $html .= '<tbody>';

        $rest_placeholder = $this->get_option($key);
        if (! is_array($rest_placeholder) || empty($rest_placeholder)) {
            $rest_placeholder = $data['default'];
        }

        $rest_placeholder = (array) stripslashes_deep($rest_placeholder);

        $html .= '<tr>
                   <td>
                     <label style="color: #23282d; font-weight: 500;" for="' . $field_name . '_pan">' . __('Card number', 'woo-payzen-payment') . '</label>
                   </td>
                   <td>
                     <input name="' . $field_name . '[pan]" value="' . esc_attr($rest_placeholder['pan']) . '" type="text" id="' . $field_name . '_pan">
                   </td>
                  </tr>';

        $html .= '<tr>
                   <td>
                     <label style="color: #23282d; font-weight: 500;" for="' . $field_name . '_expiry">' . __('Expiry date', 'woo-payzen-payment') . '</label>
                   </td>
                   <td>
                     <input name="' . $field_name . '[expiry]" value="' . esc_attr($rest_placeholder['expiry']) . '" type="text" id="' . $field_name . '_expiry">
                   </td>
                  </tr>';

        $html .= '<tr>
                   <td>
                     <label style="color: #23282d; font-weight: 500;" for="' . $field_name . '_cvv">' . __('CVV', 'woo-payzen-payment') . '</label>
                   </td>
                   <td>
                     <input name="' . $field_name . '[cvv]" value="' . esc_attr($rest_placeholder['cvv']) . '" type="text" id="' . $field_name . '_cvv">
                   </td>
                  </tr>';

        $html .= '</tbody></table>';

        if ($description) {
            $html .= ' <p class="description">' . wp_kses_post($description) . '</p>' . "\n";
        }

        $html .= '</fieldset>';
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    public function validate_rest_placeholder_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());

        return $value;
    }

    public function validate_rest_attempts_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());
        $old_value = $this->get_option($key);

        if (($value && ! is_numeric($value)) || $value > 10) {
            $value = $old_value;
        }

        return $value;
    }

    public function validate_amount_min_field($key, $value = null)
    {
        if (empty($value)) {
            return '';
        }

        $new_value = parent::validate_text_field($key, $value);

        $new_value = str_replace(',', '.', $new_value);
        if (! is_numeric($new_value) || ($new_value < 0)) { // Invalid value, restore old.
            return $this->get_option($key);
        }

        return $new_value;
    }

    public function validate_amount_max_field($key, $value = null)
    {
        if (empty($value)) {
            return '';
        }

        $new_value = parent::validate_text_field($key, $value);

        $new_value = str_replace(',', '.', $new_value);
        if (! is_numeric($new_value) || ($new_value < 0)) { // Invalid value, restore old.
            return $this->get_option($key);
        }

        return $new_value;
    }

    protected function get_supported_card_types()
    {
        return PayzenApi::getSupportedCardTypes();
    }

    public function payzen_admin_head_script()
    {
        $prefix = $this->plugin_id . $this->id . '_';
        ?>
        <script type="text/javascript">
        //<!--
            jQuery(function() {
                payzenUpdateSpecificCountriesDisplay();
            });

            function payzenUpdateSpecificCountriesDisplay() {
                var allowSpecificElt = jQuery('#<?php echo esc_attr($prefix . 'allows_specific'); ?>');
                var allowAll = allowSpecificElt.val() === '1';
                var specificCountries = allowSpecificElt.parents('table').find('tr:eq(1)'); // Second line of RESTRICTIONS section.

                if (allowAll) {
                    specificCountries.hide();
                } else {
                    specificCountries.show();
                }
            }

            jQuery(document).ready(function() {
                payzenUpdateRestFieldDisplay();
            });

            function payzenUpdateRestFieldDisplay() {
                var isKrypton = jQuery('#<?php echo esc_attr($this->get_field_key('card_data_mode')); ?> option:selected').val() == 'REST';
                var title = jQuery('#<?php echo esc_attr($this->get_field_key('rest_settings')); ?> ');
                var titleDescription = title.next();
                var table = titleDescription.next();
                var moduleDescription = jQuery('#<?php echo esc_attr($this->get_field_key('module_settings')); ?>').next().find('tr:nth-child(4)');
                var customizationTitle = jQuery('#<?php echo esc_attr($this->get_field_key('rest_customization')); ?>');
                var customizationTable = customizationTitle.next();
                if (isKrypton) {
                    moduleDescription.hide();
                    title.show();
                    titleDescription.show();
                    table.show();
                    customizationTitle.show();
                    customizationTable.find('tr:nth-child(1)').show();
                    customizationTable.find('tr:nth-child(2)').show();
                    customizationTable.find('tr:nth-child(3)').show();
                    customizationTable.find('tr:nth-child(4)').show();
                } else {
                    moduleDescription.show();
                    title.hide();
                    titleDescription.hide();
                    table.hide();
                    customizationTitle.hide();
                    customizationTable.find('tr:nth-child(1)').hide();
                    customizationTable.find('tr:nth-child(2)').hide();
                    customizationTable.find('tr:nth-child(3)').hide();
                    customizationTable.find('tr:nth-child(4)').hide();
                }
            }

            function payzenUpdatePaymentByTokenField() {
                var isPaymentByTokenEnabled = jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?> option:selected').val() == '1';

                if (isPaymentByTokenEnabled && ! confirm('<?php echo sprintf(__('The "Payment by token" option should be enabled on your %s store to use this feature.\n\nAre you sure you want to enable this feature?', 'woo-payzen-payment'), self::GATEWAY_NAME) ?>')) {
                    jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?>').val('0');
                    jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?>').trigger('change');
                }
            }
        //-->
        </script>
        <?php
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

        // Check if authorized currency.
        if (! $this->is_supported_currency()) {
            return false;
        }

        // Check if authorized country.
        if (! $this->is_available_for_country()) {
            return false;
        }

        if ($woocommerce->cart) {
            $amount = $woocommerce->cart->total;
            if (($this->get_option('amount_max') != '' && $amount > $this->get_option('amount_max'))
                || ($this->get_option('amount_min') != '' && $amount < $this->get_option('amount_min'))) {

                return false;
            }

            return $this->is_available_for_subscriptions();
        }

        return true;
    }

    /**
     * Check if this gateway is available for the current currency.
     */
    protected function is_supported_currency()
    {
        if (! empty($this->payzen_currencies)) {
            return in_array(get_woocommerce_currency(), $this->payzen_currencies);
        }

        return parent::is_supported_currency();
    }

    protected function is_available_for_country()
    {
        global $woocommerce;

        if (! $woocommerce->customer) {
            return false;
        }

        $customer = $woocommerce->customer;
        $country = method_exists($customer, 'get_billing_country') ? $customer->get_billing_country() : $customer->get_country();

        // Check billing country.
        if ($this->get_option('allows_specific') === self::ALL_COUNTRIES) {
            return empty($this->payzen_countries) || in_array($country, $this->payzen_countries);
        } else {
            return in_array($country, $this->get_option('specific_countries'));
        }
    }

    protected function is_available_for_subscriptions()
    {
        global $woocommerce;

        if (class_exists('WC_Gateway_PayzenSubscription')) {
            $settings = get_option('woocommerce_payzensubscription_settings', null);

            $handler = is_array($settings) && isset($settings['subscriptions']) ? $settings['subscriptions'] :
                WC_Gateway_PayzenSubscription::SUBSCRIPTIONS_HANDLER;
            $subscriptions_handler = Payzen_Subscriptions_Loader::getInstance($handler);

            if ($subscriptions_handler && $subscriptions_handler->cart_contains_subscription($woocommerce->cart)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display payment fields and show method description if set.
     *
     * @access public
     * @return void
     */
    public function payment_fields()
    {
        global $woocommerce;

        $cust_id = $this->get_customer_property($woocommerce->customer, 'id');
        $can_pay_by_alias = $this->can_use_alias($cust_id) && $this->get_cust_identifier($cust_id);

        $html = '';
        switch ($this->get_option('card_data_mode')) {
            case 'MERCHANT':
                $card_keys = $this->get_option('payment_cards');
                $all_supported_cards = $this->get_supported_card_types();

                if (! is_array($card_keys) || in_array('', $card_keys)) {
                    $cards = $all_supported_cards;
                } else {
                    foreach ($card_keys as $key) {
                        $cards[$key] = $all_supported_cards[$key];
                    }
                }

                // Get first array key.
                reset($cards);
                $selected_value = key($cards);

                $html .='<div style="margin-top: 15px;">';
                foreach ($cards as $key => $value) {
                    $lower_key = strtolower($key);

                    $html .='<div style="display: inline-block;">';
                    if (count($cards) == 1) {
                        $html .='<input type="hidden" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '">';
                    } else {
                        $html .='<input type="radio" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '" style="vertical-align: middle;" '
                            . checked($key, $selected_value, false) . '>';
                    }

                    $html .='<label for="' . $this->id . '_' . $lower_key . '" style="display: inline;">';

                    if (file_exists(dirname(__FILE__) . '/assets/images/' . $lower_key . '.png')) {
                        $html .='<img src="' . WC_PAYZEN_PLUGIN_URL . '/assets/images/' . $lower_key . '.png"
                                   alt="' . $value . '"
                                   title="' . $value . '"
                                   style="vertical-align: middle; margin: 0 10px 0 5px; max-height: 35px; display: unset;">';
                    } else {
                        $html .='<span style="vertical-align: middle; margin: 0 10px 0 5px; height: 35px;">' . $value . '</span>';
                    }

                    $html .='</label>';
                    $html .='</div>';
                }

                $html .='</div>';
                break;

            case 'IFRAME':
                // Load css and create iframe.
                wp_register_style('payzen', WC_PAYZEN_PLUGIN_URL . 'assets/css/payzen.css', array(), self::PLUGIN_VERSION);
                wp_enqueue_style('payzen');

                // Iframe endpoint URL.
                $link = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));

                $html .= '<div>
                         <iframe name="payzen_iframe" id="payzen_iframe" src="' . add_query_arg('loading', 'true', $link) . '" style="display: none;">
                         </iframe>';

                $html .= "\n".'<script type="text/javascript">';
                $html .= "\njQuery('form.checkout').on('checkout_place_order_" . $this->id . "', function() {
                                jQuery('form.checkout').removeClass('processing').unblock();

                                jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                                    if ((originalOptions.url.indexOf('wc-ajax=checkout') === -1)
                                        && (originalOptions.url.indexOf('action=woocommerce-checkout') === -1)) {
                                        return;
                                    }

                                    if (options.data.indexOf('payment_method=" . $this->id . "') === -1) {
                                        return;
                                    }

                                    options.success = function(data, status, jqXHR) {
                                        if (typeof data === 'string') { // For backward compatibility.
                                            // Get the valid JSON only from the returned string.
                                            if (data.indexOf('<!--WC_START-->') >= 0)
                                                data = data.split('<!--WC_START-->')[1];

                                            if (data.indexOf('<!--WC_END-->') >= 0)
                                                data = data.split('<!--WC_END-->')[0];

                                            // Parse.
                                            data = jQuery.parseJSON(data);
                                        }

                                        var result = (data && data.result) ? data.result : false;
                                        if (result !== 'success') {
                                            originalOptions.success.call(null, data, status, jqXHR);
                                            return;
                                        }

                                        // Unblock screen.
                                        jQuery('form.checkout').unblock();

                                        jQuery('.payment_method_" . $this->id . " p:first-child').hide();
                                        jQuery('ul." . $this->id . "-view-top li.block').hide();
                                        jQuery('ul." . $this->id . "-view-bottom li.block').hide();
                                        jQuery('#payzen_iframe').show();

                                        jQuery('#payzen_iframe').attr('src', '$link');
                                        jQuery(window).unbind('beforeunload');
                                    };
                                });
                            });";

                $html .= "\njQuery('input[type=\"radio\"][name=\"payment_method\"][value!=\"" . $this->id . "\"]').click(function() {
                                jQuery('form.checkout').removeClass('processing').unblock();
                                jQuery('.payment_method_" . $this->id . " p:first-child').show();
                                jQuery('li." . $this->id . "-id-block').show();
                                jQuery('#payzen_iframe').hide();

                                jQuery('#payzen_iframe').attr('src', '" . add_query_arg('loading', 'true', $link) . "');
                            });";
                $html .= "\n</script>";
                $html .= "\n</div>";
                break;

            case 'REST':
                $html .= $this->rest_payment_fields_view($can_pay_by_alias);
                if (! $html) {
                    break;
                }

                $form_token_url = add_query_arg('wc-api', 'WC_Gateway_Payzen_Form_Token', home_url('/'));

                $html .= "\n".'<script type="text/javascript">';
                $html .= "\n  var savedData = false;";

                $html .= "\n  jQuery('form.checkout').on('checkout_place_order_payzenstd', function() {
                                jQuery('form.checkout').removeClass('processing').unblock();

                                jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                                    if ((originalOptions.url.indexOf('wc-ajax=checkout') == -1)
                                        && (originalOptions.url.indexOf('action=woocommerce-checkout') == -1)) {
                                        return;
                                    }

                                    if (options.data.indexOf('payment_method=payzenstd') == -1) {
                                        return;
                                    }

                                    jQuery('.kr-form-error').html('');
                                    var newData = options.data;

                                    options.success = function(data, status, jqXHR) {
                                        if (typeof data === 'string') { // For backward compatibility.
                                            // Get the valid JSON only from the returned string.
                                            if (data.indexOf('<!--WC_START-->') >= 0) {
                                                data = data.split('<!--WC_START-->')[1];
                                            }

                                            if (data.indexOf('<!--WC_END-->') >= 0) {
                                                data = data.split('<!--WC_END-->')[0];
                                            }

                                            // Parse.
                                            data = jQuery.parseJSON(data);
                                        }

                                        var result = (data && data.result) ? data.result : false;
                                        if (result !== 'success') {
                                            originalOptions.success.call(null, data, status, jqXHR);
                                            return;
                                        }

                                         // Unblock screen.
                                        jQuery('form.checkout').unblock();

                                        var popin = jQuery('.kr-popin-button').length > 0;
                                        if (! popin) {
                                            jQuery('#payzenstd_rest_processing').css('display', 'block');
                                            jQuery('ul." . $this->id . "-view-top li.block').hide();
                                            jQuery('ul.payzenstd-view-bottom').hide();
                                        }

                                        var registerCard = jQuery('input[name=\"kr-do-register\"]').is(':checked');

                                        if (savedData && (newData === savedData)) {
                                            // Data in checkout page has not changed no need to calculate token again.
                                            if (popin) {
                                                jQuery('button.kr-popin-button').click();
                                                jQuery('form.checkout').removeClass('processing').unblock();
                                            } else {
                                                jQuery('button.kr-payment-button').click();
                                            }
                                        } else {
                                            // Data in checkout page has changed we need to calculate token again to have correct information.
                                            var useIdentifier = jQuery('#payzen_use_identifier').length && jQuery('#payzen_use_identifier').val();
                                            savedData = newData;
                                            jQuery.ajax({
                                                'type': 'POST',
                                                'url': '" . $form_token_url . "',
                                                'data': { 'use_identifier': useIdentifier },
                                                'success': function (data) {
                                                    var parsed = JSON.parse(data);
                                                    KR.setFormToken(parsed.formToken).then(({ KR }) => {
                                                        if (registerCard) {
                                                            jQuery('input[name=\"kr-do-register\"]').attr('checked','checked');
                                                        }

                                                        if (popin) {
                                                            jQuery('button.kr-popin-button').click();
                                                            jQuery('form.checkout').removeClass('processing').unblock();
                                                        } else {
                                                            jQuery('button.kr-payment-button').click();
                                                        }
                                                    });
                                                }
                                            });
                                        }

                                        jQuery(window).unbind('beforeunload');
                                    };
                                });
                            });";
                $html .= "\n</script>";
                break ;

            default:
                break;
        }

        if ($can_pay_by_alias) {
            // Display specific description for payment by token if enabled.
            $this->payment_by_alias_view($html);
        } else {
            parent::payment_fields();

            echo $html;
        }
    }

    protected function can_use_alias($cust_id)
    {
        if (! $cust_id) {
            return false;
        }

        if ($this->id !== 'payzenstd') {
            return false;
        }

        return ($this->get_option('payment_by_token') == '1');
    }

    protected function get_cust_identifier($cust_id)
    {
        $saved_identifier = get_user_meta((int) $cust_id, $this->id . '_identifier', true);
        return $saved_identifier;
    }

    protected function payment_by_alias_view($payment_fields)
    {
        global $woocommerce;

        $embdded = in_array($this->get_option('card_data_mode'), array('REST', 'IFRAME'));
        $embedded_fields = ($this->get_option('card_data_mode') === 'REST') && ($this->settings['rest_display_mode'] === 'embedded');

        $cust_id = $this->get_customer_property($woocommerce->customer, 'id');
        $saved_masked_pan = $embedded_fields ? '' : ' <b>' . get_user_meta((int) $cust_id, $this->id . '_masked_pan', true) . '</b>';

        echo '<ul class="' . $this->id . '-view-top" style="margin-left: 0;">
                  <li class="block ' . $this->id . '-id-block">
                      <input id="payzen_use_identifier" type="hidden" value="true" name="payzen_use_identifier" />
                      <span>' . sprintf(__('You will pay with your registered means of payment%s. No data entry is needed.', 'woo-payzen-payment'), $saved_masked_pan) . '</span>
                  </li>

                  <li class="block ' . $this->id . '-cc-block">';
        parent::payment_fields(); // Display method description.
        echo '    </li>

                  <li' . ($embdded ? '' : ' class="block ' . $this->id . '-cc-block"') . '>';
        echo $payment_fields;
        echo '    </li>

              </ul>
              <ul class="payzenstd-view-bottom" style="margin-left: 0;">
                    <li style="margin: 15px 0px;" class="block ' . $this->id . '-cc-block ' . $this->id . '-id-block">
                       <span>' . __('OR', 'woo-payzen-payment') . '</span>
                    </li>

                    <li class="block ' . $this->id . '-cc-block">
                      <a href="javascript: void(0);" onclick="payzenUpdatePaymentBlock(true)">' . __('Click here to pay with your registered means of payment.', 'woo-payzen-payment') . '</a>
                    </li>

                    <li class="block ' . $this->id . '-id-block">
                      <a href="javascript: void(0);" onclick="payzenUpdatePaymentBlock(false)">' . __('Click here to pay with another means of payment.', 'woo-payzen-payment') . '</a>
                    </li>
              </ul>';

        echo '<script type="text/javascript">
                  function payzenUpdatePaymentBlock(useIdentifier) {
                      jQuery("ul.' . $this->id . '-view-top li.block").hide();
                      jQuery("ul.payzenstd-view-bottom li.block").hide();

                      var blockName = useIdentifier ? "id" : "cc";
                      jQuery("li.' . $this->id . '-" + blockName + "-block").show();

                      if (typeof payzenUpdateFormToken === "function") {
                          payzenUpdateFormToken(useIdentifier);
                      }

                      jQuery("#payzen_use_identifier").val(useIdentifier);
                  }

                  payzenUpdatePaymentBlock(true);
              </script>';
    }

    /**
     * Since Woocommerce 3.9.0, no extra update order AJAX request on checkout page load.
     *
     * @access private
     * @return boolean
     */
    private function load_by_ajax_call()
    {
        global $woocommerce;
        return ! empty($_GET['wc-ajax']) || version_compare($woocommerce->version, '3.9.0', '>=');
    }

    private function rest_payment_fields_view($use_identifier)
    {
        if (! $this->load_by_ajax_call()) {
            // Interface is loaded by ajax calls.
            return '';
        }

        $form_token = $this->get_temporary_form_token();
        if (! $form_token) {
            // No form token, use redirection.
            return '';
        }

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/loading.gif';

        $popin_attr = '';
        $button_elt = '<div style="display: none;"><button class="kr-payment-button"></button></div>';

        $html = '';

        if ($this->settings['rest_display_mode'] === 'popin') {
            $popin_attr = 'kr-popin';
            $button_elt = '<button class="kr-payment-button"></button>';
        }

         $html .= '<div id="payzenstd_rest_wrapper"></div>';

        $html .= '<script type="text/javascript">';
        $html .= "\n" . 'window.FORM_TOKEN = "' . $form_token . '";';

        if ($use_identifier) {
            $identifier_token = $this->get_temporary_form_token(true);
            $html .= "\n" . 'window.IDENTIFIER_FORM_TOKEN = "' . $identifier_token . '";';
        }

        $html .= "\n" . '
                    var payzenDrawRestPaymentFields = function(formToken) {
                        var fields = \'<div class="kr-embedded" '. $popin_attr . '>\' +
                                     \'    <div class="kr-pan"></div>\' +
                                     \'    <div class="kr-expiry"></div>\' +
                                     \'    <div class="kr-security-code"></div>\' +
                                     \'    ' . $button_elt . '\' +
                                     \'    <div class="kr-form-error"></div>\' +
                                     \'    <div id="payzenstd_rest_processing" class="kr-field processing" style="display: none; border: none;">\' +
                                     \'        <div style="background-image: url(\\\'' . $img_url . '\\\');\' +
                                     \'             margin: 0 auto; display: block; height: 35px; background-position: center;\' +
                                     \'             background-repeat: no-repeat; background-size: 35px;">\' +
                                     \'        </div>\' +
                                     \'    </div>\' +
                                     \'</div>\';

                        jQuery("#payzenstd_rest_wrapper").html(fields);

                        setTimeout(function () {
                            KR.setFormConfig({ language: PAYZEN_LANGUAGE, formToken: formToken }).then(({ KR }) => {
                                payzenInitRestEvents();
                            });
                        }, 300);
                    };

                    var payzenUpdateFormToken = function(useIdentifier) {
                        var formToken = FORM_TOKEN;

                        if (typeof IDENTIFIER_FORM_TOKEN !== "undefined" && useIdentifier) {
                            // 1-Click available.
                            formToken = IDENTIFIER_FORM_TOKEN;
                        }

                        if (window.KR && KR.vueReady) {
                            // Form already loaded.
                            KR.removeForms().then(({ KR }) => {
                                payzenDrawRestPaymentFields(formToken);
                                return {KR};
                            })
                            .then(({KR}) => KR.attachForm("#payzenstd_rest_wrapper"))
                            .then(({KR, result}) => {
                                KR.setFormToken(formToken);
                                return {KR, result};
                            })
                            .then(({KR, result}) => KR.showForm(result.formId))
                        } else {
                            payzenDrawRestPaymentFields(formToken);
                        }
                    };

                    var useIdentifier = typeof IDENTIFIER_FORM_TOKEN !== "undefined";
                    if (! useIdentifier) {
                        setTimeout(function () {
                            payzenUpdateFormToken(false);
                        }, 300);
                    }

                    var formIsValidated = false;
                    jQuery (document).ready(function(){
                        jQuery("#place_order").click(function(event) {
                            if (! jQuery("#payment_method_payzenstd").is(":checked")) {
                                return true;
                            }

                            var useIdentifier = jQuery("#payzen_use_identifier").length && jQuery("#payzen_use_identifier").val() === "true";
                            var popin = jQuery(".kr-popin-button").length > 0;

                            if (! useIdentifier && ! popin) {
                                if (formIsValidated) {
                                    formIsValidated = false;
                                    return true;
                                }

                                event.preventDefault();
                                KR.validateForm().then(({KR, result}) => {
                                    // There is no errors.
                                    formIsValidated = true;
                                    jQuery("#place_order").click();
                                }).catch(({ KR, result }) => {
                                    // Display error message.
                                    return result.doOnError();
                                });
                            }
                        });
                    });
                </script>';

        return $html;
    }

    private function get_temporary_form_token($use_identifier = false)
    {
        global $woocommerce;

        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        $params = array(
            'amount' => $currency->convertAmountToInteger($woocommerce->cart->total),
            'currency' => $currency->getAlpha3(),
            'customer' => array(
                'email' => $woocommerce->customer->get_billing_email()
            )
        );

        $cust_id = $this->get_customer_property($woocommerce->customer, 'id');
        if ($use_identifier) {
            $saved_identifier = $this->get_cust_identifier($cust_id);
            $params['paymentMethodToken'] = $saved_identifier;
        } elseif ($this->get_option('payment_by_token') === '1' && $cust_id) {
            $params['formAction'] = 'ASK_REGISTER_PAY';
        }

        $key = $this->testmode ? $this->settings['test_private_key'] : $this->settings['prod_private_key'];
        $return = false;

        try {
            $client = new PayzenRest(self::REST_URL, $this->get_general_option('site_id'), $key);
            $result = $client->post('V4/Charge/CreatePayment', json_encode($params));

            if ($result['status'] != 'SUCCESS') {
                $this->log("Error while creating payment form token for current cart: " . $result['answer']['errorMessage']
                    . ' (' . $result['answer']['errorCode'] . ').');

                if (isset($result['answer']['detailedErrorMessage']) && ! empty($result['answer']['detailedErrorMessage'])) {
                    $this->log('Detailed message: ' . $result['answer']['detailedErrorMessage']
                        . ' (' . $result['answer']['detailedErrorCode'] . ').');
                }
            } else {
                // Payment form token created successfully.
                $this->log("Form token created successfully for current cart for user: {$woocommerce->customer->get_billing_email()}.");
                $return = $result['answer']['formToken'];
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return $return;
    }

    private function get_form_token($order, $use_identifier = false)
    {
        global $woocommerce, $wpdb;

        $order_id = $this->get_escaped_var($this->payzen_request, 'order_id');
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());

        $threeds_mpi = null;
        if ($this->get_general_option('3ds_min_amount') && ($order->get_total() < $this->get_general_option('3ds_min_amount'))) {
            $threeds_mpi = '2';
        }

        $strong_auth = $threeds_mpi === '2' ? 'DISABLED' : 'AUTO';
        $params = array('orderId' => $order_id,
            'customer' => array(
                'email' => $this->get_escaped_var($this->payzen_request, 'cust_email'),
                'reference' => $this->get_escaped_var($this->payzen_request, 'cust_id'),
                'billingDetails' => array(
                    'language' => $this->get_escaped_var($this->payzen_request, 'language'),
                    'title' => $this->get_escaped_var($this->payzen_request, 'cust_title'),
                    'firstName' => $this->get_escaped_var($this->payzen_request, 'cust_first_name'),
                    'lastName' => $this->get_escaped_var($this->payzen_request, 'cust_last_name'),
                    'category' => $this->get_escaped_var($this->payzen_request, 'cust_status'),
                    'address' => $this->get_escaped_var($this->payzen_request, 'cust_address'),
                    'zipCode' => $this->get_escaped_var($this->payzen_request, 'cust_zip'),
                    'city' => $this->get_escaped_var($this->payzen_request, 'cust_city'),
                    'state' => $this->get_escaped_var($this->payzen_request, 'cust_state'),
                    'phoneNumber' => $this->get_escaped_var($this->payzen_request, 'cust_phone'),
                    'country' => $this->get_escaped_var($this->payzen_request, 'cust_country')
                ),
                'shippingDetails' => array(
                    'firstName' => $this->get_escaped_var($this->payzen_request, 'ship_to_first_name'),
                    'lastName' => $this->get_escaped_var($this->payzen_request, 'ship_to_last_name'),
                    'category' => $this->get_escaped_var($this->payzen_request, 'ship_to_status'),
                    'address' => $this->get_escaped_var($this->payzen_request, 'ship_to_street'),
                    'address2' => $this->get_escaped_var($this->payzen_request, 'ship_to_street2'),
                    'zipCode' => $this->get_escaped_var($this->payzen_request, 'ship_to_zip'),
                    'city' => $this->get_escaped_var($this->payzen_request, 'ship_to_city'),
                    'state' => $this->get_escaped_var($this->payzen_request, 'ship_to_state'),
                    'phoneNumber' => $this->get_escaped_var($this->payzen_request, 'ship_to_phone_num'),
                    'country' => $this->get_escaped_var($this->payzen_request, 'ship_to_country'),
                    'deliveryCompanyName' => $this->get_escaped_var($this->payzen_request, 'ship_to_delivery_company_name'),
                    'shippingMethod' => $this->get_escaped_var($this->payzen_request, 'ship_to_type'),
                    'shippingSpeed' => $this->get_escaped_var($this->payzen_request, 'ship_to_speed')
                )
            ),
            'transactionOptions' => array(
                'cardOptions' => array('captureDelay' => $this->get_escaped_var($this->payzen_request, 'capture_delay'),
                    'manualValidation' => ($this->get_escaped_var($this->payzen_request, 'validation_mode') == '1') ? 'YES' : 'NO',
                    'paymentSource' => 'EC'
                )
            ), 'contrib' => $this->get_escaped_var($this->payzen_request, 'contrib'), 'strongAuthenticationState' => $strong_auth,
            'currency' => $currency->getAlpha3(), 'amount' => $this->get_escaped_var($this->payzen_request, 'amount'),
            'metadata' => array(
                'orderInfo' => 'order_key=' . $this->get_order_property($order, 'order_key') . '&blog_id=' . $wpdb->blogid
            )
        );

        // Set number of attempts in case of rejected payment.
        if ($this->settings['rest_attempts']) {
            $params['transactionOptions']['cardOptions']['retry'] = $this->settings['rest_attempts'];
        }

        $cust_id = $this->get_customer_property($woocommerce->customer, 'id');
        if ($use_identifier) {
            $saved_identifier = $this->get_cust_identifier($cust_id);
            $params['paymentMethodToken'] = $saved_identifier;
        } elseif ($this->get_option('payment_by_token') === '1' && $cust_id) {
            $this->log('Customer ' . $this->payzen_request->get('cust_email') . ' will be asked for card data registration.');
            $params['formAction'] = 'ASK_REGISTER_PAY';
        }

        $key = $this->testmode ? $this->settings['test_private_key'] : $this->settings['prod_private_key'];

        $return = false;

        try {
            $client = new PayzenRest(self::REST_URL, $this->payzen_request->get('site_id'), $key);
            $result = $client->post('V4/Charge/CreatePayment', json_encode($params));

            if ($result['status'] != 'SUCCESS') {
                $this->log("Error while creating payment form token for order #$order_id: " . $result['answer']['errorMessage']
                    . ' (' . $result['answer']['errorCode'] . ').');

                if (isset($result['answer']['detailedErrorMessage']) && ! empty($result['answer']['detailedErrorMessage'])) {
                    $this->log('Detailed message: '.$result['answer']['detailedErrorMessage']
                        . ' (' . $result['answer']['detailedErrorCode'] . ').');
                }
            } else {
                // Payment form token created successfully.
                $this->log("Form token created successfully for order #$order_id.");
                $return = $result['answer']['formToken'];
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return $return;
    }

    private function get_escaped_var($request, $var)
    {
        $value = $request->get($var);

        if (empty($value)) {
            return null;
        }

        return $value;
    }

    public function payzen_refresh_form_token()
    {
        global $woocommerce;

        // Order ID from session.
        $order_id = $woocommerce->session->get('order_awaiting_payment');
        $order = new WC_Order($order_id);

        // Set flag about use of saved identifier.
        if (isset($_POST['use_identifier'])) {
            set_transient($this->id . '_use_identifier_' . $order_id, $_POST['use_identifier'] === 'true');
        }

        $this->payzen_fill_request($order);

        if ($token = $this->get_form_token($order, $_POST['use_identifier'] === 'true')) {
            $result = array('result' => 'success', 'formToken' => $token);
        } else {
            $result = array('result' => 'error');
        }

        @ob_clean();
        echo json_encode($result);
        die();
    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        if ($this->get_option('card_data_mode') === 'MERCHANT') {
            $this->save_selected_card($order_id);
        }

        // Set flag about use of saved identifier.
        if (isset($_POST['payzen_use_identifier'])) {
            set_transient($this->id . '_use_identifier_' . $order_id, $_POST['payzen_use_identifier'] === 'true');
        }

        $order = new WC_Order($order_id);

        if ($this->get_option('card_data_mode') === 'REST') {
            return array(
                'result' => 'success'
            );
        }

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

    protected function save_selected_card($order_id)
    {
        $selected_card = $_POST[$this->id . '_card_type'];

        // Save selected card into database as transcient.
        set_transient($this->id . '_card_type_' . $order_id, $selected_card);
    }

    /**
     * Order review and payment form page.
     **/
    public function payzen_generate_form($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);

        echo '<div style="opacity: 0.6; padding: 10px; text-align: center; color: #555; border: 3px solid #aaa; background-color: #fff; cursor: wait; line-height: 32px;">';

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/loading.gif';
        $img_url = class_exists('WC_HTTPS') ? WC_HTTPS::force_https_url($img_url) : $woocommerce->force_ssl($img_url);
        echo '<img src="' . esc_url($img_url) . '" alt="..." style="float:left; margin-right: 10px;"/>';
        echo __('Please wait, you will be redirected to the payment gateway.', 'woo-payzen-payment');
        echo '</div>';
        echo '<br />';
        echo '<p>' . __('If nothing happens in 10 seconds, please click the button below.', 'woo-payzen-payment') . '</p>';

        $this->payzen_fill_request($order);

        // Log data that will be sent to payment gateway.
        $this->log('Data to be sent to payment gateway : ' . print_r($this->payzen_request->getRequestFieldsArray(true /* To hide sensitive data. */), true));

        $form = "\n".'<form action="' . esc_url($this->payzen_request->get('platform_url')) . '" method="post" id="' . $this->id . '_payment_form">';
        $form .= "\n" . $this->payzen_request->getRequestHtmlFields();
        $form .= "\n" . '  <input type="submit" class="button-alt" id="' . $this->id . '_payment_form_submit" value="' . sprintf(__('Pay via %s', 'woo-payzen-payment'), self::GATEWAY_NAME).'">';
        $form .= "\n" . '  <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woo-payzen-payment') . '</a>';
        $form .= "\n" . '</form>';

        $form .= "\n".'<script type="text/javascript">';
        $form .= "\nfunction payzen_submit_form() {
                    document.getElementById('" . $this->id . "_payment_form_submit').click();
                  }";
        $form .= "\nif (window.addEventListener) { // For all major browsers.
                    window.addEventListener('load', payzen_submit_form, false);
                  } else if (window.attachEvent) { // For IE 8 and earlier versions.
                    window.attachEvent('onload', payzen_submit_form);
                  }";
        $form .= "\n</script>\n";

        echo $form;
    }

    public function payzen_generate_iframe_form()
    {
        global $woocommerce;

        if (isset($_GET['loading']) && $_GET['loading']) {
            echo '<div style="text-align: center;">
                      <img src="' . esc_url(WC_PAYZEN_PLUGIN_URL . 'assets/images/loading_big.gif') . '">
                  </div>';
            die();
        }

        // Order ID from session.
        $order_id = $woocommerce->session->get('order_awaiting_payment');

        $order = new WC_Order((int)$order_id);
        $this->payzen_fill_request($order);

        // Hide logos below payment fields.
        $this->payzen_request->set('theme_config', '3DS_LOGOS=false;');

        $this->payzen_request->set('action_mode', 'IFRAME');
        $this->payzen_request->set('redirect_enabled', '1');
        $this->payzen_request->set('redirect_success_timeout', '0');
        $this->payzen_request->set('redirect_error_timeout', '0');

        // Log data that will be sent to payment gateway.
        $this->log('Data to be sent to payment gateway : ' . print_r($this->payzen_request->getRequestFieldsArray(true /* To hide sensitive data. */), true));

        $form = "\n" . '<form action="' . esc_url($this->payzen_request->get('platform_url')) . '" method="post" id="' . $this->id . '_payment_iframe_form">';
        $form .= "\n" . $this->payzen_request->getRequestHtmlFields();
        $form .= "\n" . '</form>';

        $form .= "\n" . '<script type="text/javascript">';
        $form .= "\nfunction payzen_submit_form() {
                        document.getElementById('" . $this->id . "_payment_iframe_form').submit();
                      }";
        $form .= "\nif (window.addEventListener) { // For all major browsers.
                        window.addEventListener('load', payzen_submit_form, false);
                      } else if (window.attachEvent) { // For IE 8 and earlier versions.
                        window.attachEvent('onload', payzen_submit_form);
                      }";
        $form .= "\n</script>\n";

        echo $form;
        die();
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        global $woocommerce, $wpdb;

        $order_id = $this->get_order_property($order, 'id');
        $cust_id = $this->get_order_property($order, 'user_id');

        $this->log("Generating payment form for order #$order_id.");

        // Get currency.
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        if ($currency == null) {
            $this->log('The store currency (' . get_woocommerce_currency() . ') is not supported by payment gateway.');

            wp_die(sprintf(__('The store currency (%s) is not supported by %s.', 'woo-payzen-payment'), get_woocommerce_currency(), self::GATEWAY_NAME));
        }

        // Effective used version.
        include ABSPATH . WPINC . '/version.php'; // $wp_version.
        $version = $wp_version . '_' . $woocommerce->version;

        // Params.
        $misc_params = array(
            'amount' => $currency->convertAmountToInteger($order->get_total()),
            'contrib' => self::CMS_IDENTIFIER . '_' . self::PLUGIN_VERSION . '/' . $version . '/' . PHP_VERSION,
            'currency' => $currency->getNum(),
            'order_id' => $order_id,
            'order_info' => 'order_key=' . $this->get_order_property($order, 'order_key') . '&blog_id=' . $wpdb->blogid,

            // Billing address info.
            'cust_id' => $cust_id,
            'cust_email' => $this->get_order_property($order, 'billing_email'),
            'cust_first_name' => $this->get_order_property($order, 'billing_first_name'),
            'cust_last_name' => $this->get_order_property($order, 'billing_last_name'),
            'cust_address' => $this->get_order_property($order, 'billing_address_1') . ' ' . $this->get_order_property($order, 'billing_address_2'),
            'cust_zip' => $this->get_order_property($order, 'billing_postcode'),
            'cust_country' => $this->get_order_property($order, 'billing_country'),
            'cust_phone' => str_replace(array('(', '-', ' ', ')'), '', $this->get_order_property($order, 'billing_phone')),
            'cust_city' => $this->get_order_property($order, 'billing_city'),
            'cust_state' => $this->get_order_property($order, 'billing_state'),

            // Shipping address info.
            'ship_to_first_name' => $this->get_order_property($order, 'shipping_first_name'),
            'ship_to_last_name' => $this->get_order_property($order, 'shipping_last_name'),
            'ship_to_street' => $this->get_order_property($order, 'shipping_address_1'),
            'ship_to_street2' => $this->get_order_property($order, 'shipping_address_2'),
            'ship_to_city' => $this->get_order_property($order, 'shipping_city'),
            'ship_to_state' => $this->get_order_property($order, 'shipping_state'),
            'ship_to_country' => $this->get_order_property($order, 'shipping_country'),
            'ship_to_zip' => $this->get_order_property($order, 'shipping_postcode'),

            'shipping_amount' => $currency->convertAmountToInteger($this->get_shipping_with_tax($order)),

            // Return URLs.
            'url_return' => add_query_arg('wc-api', 'WC_Gateway_Payzen', home_url('/'))
        );
        $this->payzen_request->setFromArray($misc_params);

        // VAT amount for colombian payment means.
        $this->payzen_request->set('totalamount_vat', $currency->convertAmountToInteger($order->get_total_tax()));

        // Activate 3ds?
        $threeds_mpi = null;
        if ($this->get_general_option('3ds_min_amount') && ($order->get_total() < $this->get_general_option('3ds_min_amount'))) {
            $threeds_mpi = '2';
        }

        $this->payzen_request->set('threeds_mpi', $threeds_mpi);

        // Detect language.
        $locale = get_locale() ? substr(get_locale(), 0, 2) : null;
        if ($locale && PayzenApi::isSupportedLanguage($locale)) {
            $this->payzen_request->set('language', $locale);
        } else {
            $this->payzen_request->set('language', $this->get_general_option('language'));
        }

        // Available languages.
        $langs = $this->get_general_option('available_languages');
        if (is_array($langs) && ! in_array('', $langs)) {
            $this->payzen_request->set('available_languages', implode(';', $langs));
        }

        if (isset($this->form_fields['card_data_mode'])) {
            // Payment cards.
            if ($this->get_option('card_data_mode') === 'MERCHANT') {
                $selected_card = get_transient($this->id . '_card_type_' . $order_id);
                $this->payzen_request->set('payment_cards', $selected_card);

                delete_transient($this->id . '_card_type_' . $order_id);
            } else {
                $cards = $this->get_option('payment_cards');
                if (is_array($cards) && ! in_array('', $cards)) {
                    $this->payzen_request->set('payment_cards', implode(';', $cards));
                }
            }
        }

        // Enable automatic redirection?
        $this->payzen_request->set('redirect_enabled', ($this->get_general_option('redirect_enabled') == 'yes') ? true : false);

        // Redirection messages.
        $success_message = $this->get_general_option('redirect_success_message');
        $success_message = isset($success_message[get_locale()]) && $success_message[get_locale()] ? $success_message[get_locale()] :
            (is_array($success_message) ? $success_message['en_US'] : $success_message);
        $this->payzen_request->set('redirect_success_message', $success_message);

        $error_message = $this->get_general_option('redirect_error_message');
        $error_message = isset($error_message[get_locale()]) && $error_message[get_locale()] ? $error_message[get_locale()] :
            (is_array($error_message) ? $error_message['en_US'] : $error_message);
        $this->payzen_request->set('redirect_error_message', $error_message);

        // Other configuration params.
        $config_keys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url', 'capture_delay', 'validation_mode',
            'redirect_success_timeout', 'redirect_error_timeout', 'return_mode', 'sign_algo'
        );

        foreach ($config_keys as $key) {
            $this->payzen_request->set($key, $this->get_general_option($key));
        }

        // Check if capture_delay and validation_mode are overriden in submodules.
        if (is_numeric($this->get_option('capture_delay'))) {
            $this->payzen_request->set('capture_delay', $this->get_option('capture_delay'));
        }

        if ($this->get_option('validation_mode') !== '-1') {
            $this->payzen_request->set('validation_mode', $this->get_option('validation_mode'));
        }

        if ($this->can_use_alias($cust_id)) { // If option enabled.
            if ($saved_identifier = $this->get_cust_identifier($cust_id)) {
                $this->payzen_request->set('identifier', $saved_identifier);

                if (! get_transient($this->id . '_use_identifier_' . $order_id)) { // Customer choose to not use alias.
                    $this->payzen_request->set('page_action', 'REGISTER_UPDATE_PAY');
                }

                // Delete flag about use of saved identifier.
                delete_transient($this->id . '_use_identifier_' . $order_id);
            } else {
                $this->payzen_request->set('page_action', 'ASK_REGISTER_PAY');
            }
        }
    }

    protected function send_cart_data($order)
    {
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());

        // Add cart products info.
        foreach ($order->get_items() as $line_item) {
            $item_data = $line_item->get_data();

            $qty = (int) $item_data['quantity'];

            $product_amount = $item_data['total'] / $qty;
            $product_tax_amount = $item_data['total_tax'] / $qty;
            $product_tax_rate = $product_amount ? round($product_tax_amount / $product_amount * 100, 4) : 0;

            $this->payzen_request->addProduct(
                $item_data['name'],
                $currency->convertAmountToInteger($product_amount + $product_tax_amount), // Amount with taxes.
                $qty,
                $item_data['product_id'],
                null, // We have no product category.
                $product_tax_rate // In percentage.
            );
        }
    }

    /**
     * Check for REST return response.
     **/
    public function payzen_rest_return_response()
    {
        $this->payzen_manage_rest_notify_response(false);
    }

    /**
     * Check for REST notification response.
     **/
    public function payzen_rest_notify_response()
    {
        $this->payzen_manage_rest_notify_response(true);
    }

    public function payzen_manage_rest_notify_response($from_server_rest = false)
    {
        global $woocommerce ;

        @ob_clean();

        $raw_response = (array) stripslashes_deep($_POST);
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $woocommerce->cart->get_cart_url();

        // Check received REST parameters.
        if (! PayzenRestTools::checkResponse($raw_response)) {
            $this->log('Invalid REST request received. Content: ' . print_r($raw_response, true));

            if ($from_server_rest) {
                $this->log('SERVER URL PROCESS END');
                die('<span style="display:none">KO-Invalid IPN request received.'."\n".'</span>');
            } else {
                // Fatal error, empty cart.
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                $this->log('RETURN URL PROCESS END');
                wp_redirect($cart_url);
                exit();
            }
        }

        if ($from_server_rest) {
            $sha_key = $this->testmode ? $this->settings['test_private_key'] : $this->settings['prod_private_key'];
        } else {
            $sha_key = $this->testmode ? $this->settings['test_return_key'] : $this->settings['prod_return_key'];
        }

        // Check the authenticity of the request.
        if (! PayzenRestTools::checkHash($raw_response, $sha_key)) {
            $this->log('Received invalid response from gateway with parameters: ' . print_r($raw_response, true));

            if ($from_server_rest) {
                $this->log('SERVER URL PROCESS END');
                die('<span style="display:none">KO-An error occurred while computing the signature.'."\n".'</span>');
            } else {
                // Fatal error, empty cart.
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                $this->log('RETURN URL PROCESS END');
                wp_redirect($cart_url);
                exit();
            }
        }

        $answer = json_decode($raw_response['kr-answer'], true);
        if (! is_array($answer)) {
            $this->log('Invalid REST request received. Content of kr-answer: ' . $raw_response['kr-answer']);

            if ($from_server_rest) {
                $this->log('SERVER URL PROCESS END');
                die('<span style="display:none">KO-Invalid IPN request received.'."\n".'</span>');
            } else {
                // Fatal error, empty cart.
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                $this->log('RETURN URL PROCESS END');
                wp_redirect($cart_url);
                exit();
            }
        }

        // Wrap payment result to use traditional order creation tunnel.
        $response = PayzenRestTools::convertRestResult($answer);

        parent::payzen_manage_notify_response($response, $from_server_rest);
    }

    private function get_shipping_with_tax($order)
    {
        $shipping = 0;

        if (method_exists($order, 'get_shipping_total')) {
            $shipping += $order->get_shipping_total();
        } elseif (method_exists($order, 'get_total_shipping')) {
            $shipping += $order->get_total_shipping(); // WC old versions.
        } else {
            $shipping += $order->get_shipping(); // WC older versions.
        }

        $shipping += $order->get_shipping_tax();

        return $shipping;
    }
}
