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

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Form\Response as PayzenResponse;
use Lyranetwork\Payzen\Sdk\Rest\Api as PayzenRest;

class WC_Gateway_PayzenStd extends WC_Gateway_Payzen
{
    const ALL_COUNTRIES = '1';
    const SPECIFIC_COUNTRIES = '2';

    protected $payzen_countries = array();
    protected $payzen_currencies = array();
    protected $payzen_other_payment_methods;

    public function __construct()
    {
        $this->id = 'payzenstd';
        $this->icon = apply_filters('woocommerce_payzenstd_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/payzen.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('Standard payment', 'woo-payzen-payment');

        // To get payment means in case of Smartform.
        $this->payzen_other_payment_methods = new WC_Gateway_PayzenRegroupedOther(false);

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

        // Payment method title filter.
        add_filter('woocommerce_title_' . $this->id, array($this, 'get_title'));

        // Payment method description filter.
        add_filter('woocommerce_description_' . $this->id, array($this, 'get_description'));

        // Payment method availability filter.
        add_filter('woocommerce_available_' . $this->id, array($this, 'is_available'));

        // Generate payment fields filter.
        add_filter('woocommerce_payzen_payment_fields_' . $this->id, array($this, 'get_payment_fields'));

        // Iframe payment endpoint action.
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'payzen_generate_iframe_form'));

        // Return from REST payment action.
        add_action('woocommerce_api_wc_gateway_payzen_rest', array($this, 'payzen_rest_return_response'));

        // Notification from REST payment action.
        add_action('woocommerce_api_wc_gateway_payzen_notify_rest', array($this, 'payzen_rest_notify_response'));

        // Rest payment generate token.
        add_action('woocommerce_api_wc_gateway_payzen_form_token', array($this, 'payzen_refresh_form_token'));

        // Set payment method in for orders created from BO.
        add_action('woocommerce_api_wc_gateway_payzen_process_order', array($this, 'process_order_payment'));

        // Adding JS to load REST libs.
        add_action('wp_head', array($this, 'payzen_rest_head_script'));
    }

    public function payzen_rest_head_script()
    {
        if (PayzenTools::is_embedded_payment(false) && $this->is_available()) {
            $payzen_pub_key = $this->testmode ? $this->get_general_option('test_public_key') : $this->get_general_option('prod_public_key');

            $locale = get_locale() ? substr(get_locale(), 0, 2) : null;
            if (! $locale || ! PayzenApi::isSupportedLanguage($locale)) {
                $locale = $this->settings['language'];
            }

            $language_iso_code = $locale;
            $return_url = add_query_arg('wc-api', 'WC_Gateway_Payzen_Rest', home_url('/'));
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

            // Custom "Register my card" checkbox label.
            $card_label = $this->settings['rest_register_card_label'];
            if (is_array($card_label)) {
                $card_label = isset($card_label[get_locale()]) && $card_label[get_locale()] ?
                   $card_label[get_locale()] : $card_label['en_US'];
            }

            $card_label = stripslashes($card_label);

            // Custom theme.
            $payzen_std_rest_theme = $this->settings['rest_theme'];
            $payzen_static_url = $this->get_general_option('static_url', self::STATIC_URL);

            $payzen_display = 'flex';
            if (PayzenTools::is_embedded_payment() && ($this->get_option('rest_popin') == 'yes')) {
                $payzen_display = 'none !important';
            }
            ?>
                <script>
                    var PAYZEN_LANGUAGE = "<?php echo $language_iso_code; ?>"
                </script>
                <script src="<?php echo $payzen_static_url; ?>js/krypton-client/V4.0/stable/kr-payment-form.min.js"
                        kr-public-key="<?php echo $payzen_pub_key; ?>"
                        kr-post-url-success="<?php echo $return_url; ?>"
                        kr-post-url-refused="<?php echo $return_url; ?>"
                        kr-language="<?php echo $language_iso_code; ?>"<?php echo $custom_placeholders; ?>
                        kr-label-do-register="<?php echo $card_label; ?>">
               </script>

                <link rel="stylesheet" href="<?php echo $payzen_static_url; ?>js/krypton-client/V4.0/ext/<?php echo $payzen_std_rest_theme;?>-reset.css">
                <script src="<?php echo $payzen_static_url; ?>js/krypton-client/V4.0/ext/<?php echo $payzen_std_rest_theme;?>.js"></script>

                <style>
                    #payzenstd_rest_wrapper button.kr-popin-button {
                        display: none !important;
                        width: 0;
                        height: 0;
                    }

                    .kr-smart-form .kr-smart-form-wrapper.kr-type-popin .kr-smart-form-modal-button,
                    .kr-smart-form .kr-smart-button {
                        display: <?php echo $payzen_display; ?>;
                    }
                </style>
            <?php

            // Load REST script.
            if (! wp_script_is('payzen-rest-js', 'registered')) {
                wp_register_script('payzen-rest-js', WC_PAYZEN_PLUGIN_URL . 'assets/js/rest.js');
                wp_enqueue_script('payzen-rest-js');
            }
        }
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
        if ($this->id === 'payzenstd' && PayzenTools::is_embedded_payment() && ($this->get_option('rest_popin') !== 'yes')) {
            return '';
        } else {
            return parent::get_description();
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
                'description' => $this->get_method_title_field_description(),
                'default' => __('Payment by credit card', 'woo-payzen-payment')
            ),
            'description' => array(
                'title' => __('Description', 'woo-payzen-payment'),
                'type' => 'textarea',
                'description' => $this->get_method_description_field_description(),
                'default' => __('You will enter payment data after order confirmation.', 'woo-payzen-payment'),
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
                    'onchange' => 'payzenUpdateRestFieldDisplay(false)'
                ),
                'title' => __('Payment data entry mode', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => 'DEFAULT',
                'options' => array(
                    'DEFAULT' => __('Card data entry on payment gateway', 'woo-payzen-payment'),
                    'MERCHANT' => __('Card type selection on merchant site', 'woo-payzen-payment'),
                    'IFRAME' => __('Payment page integrated to checkout process (iframe)', 'woo-payzen-payment'),
                ),
                'description' => __('Select how the payment data will be entered by buyer. Think to update payment method description to match your selected mode.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            )
        );

        // Add REST fields if available for payment.
        if ($payzen_plugin_features['embedded']) {
            $this->form_fields['card_data_mode']['options']['REST'] = __('Embedded payment fields on merchant site (REST API)', 'woo-payzen-payment');
            if ($payzen_plugin_features['smartform']) {
                $this->form_fields['card_data_mode']['options']['SMARTFORM'] = __('Embedded Smartform on merchant site (REST API)', 'woo-payzen-payment');
                $this->form_fields['card_data_mode']['options']['SMARTFORMEXT'] = __('Embedded Smartform extended on merchant site with logos (REST API)', 'woo-payzen-payment');
                $this->form_fields['card_data_mode']['options']['SMARTFORMEXTNOLOGOS'] = __('Embedded Smartform extended on merchant site without logos (REST API)', 'woo-payzen-payment');
            }

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
                'es_ES' => 'Pago con tarjeta de crédito',
                'pt_BR' => 'Pagamento com cartão de crédito'
            );

            $this->form_fields['description']['type'] = 'multilangtext';
            $this->form_fields['description']['default'] = array(
                'en_US' => 'You will enter payment data after order confirmation.',
                'en_GB' => 'You will enter payment data after order confirmation.',
                'fr_FR' => 'Vous allez saisir les informations de paiement après confirmation de la commande.',
                'de_DE' => 'Sie werden die Zahlungsdaten nach Auftragsbestätigung ein.',
                'es_ES' => 'Usted ingresará los datos de pago después de la confirmación del pedido.',
                'pt_BR' => 'Poderá acessar os dados de pagamento após a confirmação do pedido.'
            );
        }
    }

    protected function get_rest_fields()
    {
        // Add Rest fields.
        $this->form_fields['rest_customization'] = array(
            'title' => __('CUSTOMIZATION', 'woo-payzen-payment'),
            'type' => 'title',
        );

        $this->form_fields['rest_popin'] = array(
            'title' => __('Display in a pop-in', 'woo-payzen-payment'),
            'label' => __('Enable / disable', 'woo-payzen-payment'),
            'type' => 'checkbox',
            'default' => 'no',
            'description' => __('If enabled, the selected payment mode will be displayed in a pop-in.', 'woo-payzen-payment')
        );

        $this->form_fields['rest_theme'] = array(
            'title' => __('Theme', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => 'neon',
            'options' => array(
                'neon' => 'Neon',
                'classic' => 'Classic'
            ),
            'description' => __('Select a theme to use to display the embedded payment fields or the Smartform.', 'woo-payzen-payment'),
            'class' => 'wc-enhanced-select'
        );

        $this->form_fields['smartform_compact_mode'] = array(
            'title' => __('Compact mode', 'woo-payzen-payment'),
            'label' => __('Enable / disable', 'woo-payzen-payment'),
            'type' => 'checkbox',
            'default' => 'no',
            'description' => __('If enabled, the Smartform will be displayed in a compact mode.', 'woo-payzen-payment')
        );

        $this->form_fields['smartform_grouping_threshold'] = array(
            'title' => __('Payment means grouping threshold', 'woo-payzen-payment'),
            'type' => 'text',
            'description' => __('Number from which payment means will be grouped.', 'woo-payzen-payment')
        );

        $this->form_fields['rest_placeholder'] = array(
            'title' => __('Custom fields placeholders', 'woo-payzen-payment'),
            'type' => 'placeholder_table',
            'default' => array(
                'pan' => '',
                'expiry' => '',
                'cvv' => ''
            ),
            'description' => __('Texts to use as placeholders for embedded payment fields, Smartform or Smartform extended.', 'woo-payzen-payment')
        );

        $this->form_fields['rest_register_card_label'] = array(
            'title' => __('Register card label', 'woo-payzen-payment'),
            'type' => 'text',
            'default' => __('Register my card', 'woo-payzen-payment'),
            'description' => __('Label displayed to invite buyers to register their card data.', 'woo-payzen-payment')
        );

        $this->form_fields['rest_attempts'] = array(
            'title' => __('Payment attempts number for cards', 'woo-payzen-payment'),
            'type' => 'text',
            'description' => __('Maximum number of payment by cards retries after a failed payment (between 0 and 2). If blank, the gateway default value is 2.', 'woo-payzen-payment')
        );

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['rest_register_card_label']['type'] = 'multilangtext';
            $this->form_fields['rest_register_card_label']['default'] = array(
                'en_US' => 'Register my card',
                'en_GB' => 'Register my card',
                'fr_FR' => 'Enregistrer ma carte',
                'de_DE' => 'Registriere meine Karte',
                'es_ES' => 'Registrar mi tarjeta',
                'pt_BR' => 'Salvar meu cartão'
            );
        }
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

        $html .= '<tr class="payzen-placeholder">
                   <td>
                     <label style="color: #23282d; font-weight: 500;" for="' . $field_name . '_pan">' . __('Card number', 'woo-payzen-payment') . '</label>
                   </td>
                   <td>
                     <input name="' . $field_name . '[pan]" value="' . esc_attr($rest_placeholder['pan']) . '" type="text" id="' . $field_name . '_pan">
                   </td>
                  </tr>';

        $html .= '<tr class="payzen-placeholder">
                   <td>
                     <label style="color: #23282d; font-weight: 500;" for="' . $field_name . '_expiry">' . __('Expiry date', 'woo-payzen-payment') . '</label>
                   </td>
                   <td>
                     <input name="' . $field_name . '[expiry]" value="' . esc_attr($rest_placeholder['expiry']) . '" type="text" id="' . $field_name . '_expiry">
                   </td>
                  </tr>';

        $html .= '<tr class="payzen-placeholder">
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

        if (($value && ! is_numeric($value)) || $value > 3) {
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

    protected function get_supported_card_types($codeInLabel = true)
    {
        $cards = PayzenApi::getSupportedCardTypes();
        foreach ($cards as $code => $label) {
            $cards[$code] = ($codeInLabel ? $code . ' - ' : '') . $label;
        }

        return $cards;
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

            function payzenUpdateRestFieldDisplay(ignoreIframe = true) {
                var cardDataMode = jQuery('#<?php echo esc_attr($this->get_field_key('card_data_mode')); ?> option:selected').val();
                var popinMode = jQuery('#<?php echo esc_attr($this->get_field_key('rest_popin')); ?> option:selected').val();
                var moduleDescription = jQuery('#<?php echo esc_attr($this->get_field_key('module_settings')); ?>').next().find('tr:nth-child(7)');

                if ((jQuery.inArray(cardDataMode, ['REST', 'SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS']) != -1) && (popinMode !== 'yes')) {
                    moduleDescription.hide();
                } else {
                    moduleDescription.show();
                }

                var customizationTitle = jQuery('#<?php echo esc_attr($this->get_field_key('rest_customization')); ?>');
                var customizationTable = customizationTitle.next();

                if (jQuery.inArray(cardDataMode, ['REST', 'SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS']) != -1) {
                    customizationTitle.show();
                    customizationTable.find('tr:nth-child(1)').show();
                    if (jQuery.inArray(cardDataMode, ['SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS']) != -1) {
                        customizationTable.find('tr:nth-child(3)').show();
                        customizationTable.find('tr:nth-child(4)').show();
                    } else{
                        customizationTable.find('tr:nth-child(3)').hide();;
                        customizationTable.find('tr:nth-child(4)').hide();
                    }

                    customizationTable.find('tr:nth-child(2)').show();
                    customizationTable.find('tr:nth-child(5)').show();
                    customizationTable.find('tr:nth-child(7)').show();
                    customizationTable.find('tr:nth-child(8)').show();

                    var isPaymentByTokenEnabled = jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?> option:selected').val() === '1';
                    if (isPaymentByTokenEnabled) {
                        customizationTable.find('tr:nth-child(6)').show();
                    } else {
                        customizationTable.find('tr:nth-child(6)').hide();
                        jQuery('.payzen-placeholder').show();
                    }
                } else {
                    customizationTitle.hide();
                    customizationTable.find('tr:nth-child(1)').hide();
                    customizationTable.find('tr:nth-child(2)').hide();
                    customizationTable.find('tr:nth-child(3)').hide();
                    customizationTable.find('tr:nth-child(4)').hide();
                    customizationTable.find('tr:nth-child(5)').hide();
                    customizationTable.find('tr:nth-child(6)').hide();
                    customizationTable.find('tr:nth-child(7)').hide();
                    customizationTable.find('tr:nth-child(8)').hide();

                    if (! ignoreIframe) {
                        if ((cardDataMode === 'IFRAME') &&
                            ! confirm('<?php echo __('Warning, some payment means are not compatible with an integration by iframe. Please consult the documentation for more details.', 'woo-payzen-payment')?>')) {
                            jQuery('#<?php echo esc_attr($this->get_field_key('card_data_mode')); ?>').val("<?php echo esc_attr($this->get_option('card_data_mode')); ?>");
                            jQuery('#<?php echo esc_attr($this->get_field_key('card_data_mode')); ?>').trigger('change');
                        }
                    }
                }
            }

            function payzenUpdatePaymentByTokenField() {
                var isPaymentByTokenEnabled = jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?> option:selected').val() === '1';
                var customizationTable = jQuery('#<?php echo esc_attr($this->get_field_key('rest_customization')); ?>').next();
                var cardDataMode = jQuery('#<?php echo esc_attr($this->get_field_key('card_data_mode')); ?> option:selected').val();
                if (isPaymentByTokenEnabled) {
                    if (! confirm('<?php echo sprintf(addcslashes(__('The "Payment by token" option should be enabled on your %s store to use this feature.\n\nAre you sure you want to enable this feature?', 'woo-payzen-payment'), '\''), self::GATEWAY_NAME) ?>')) {
                        jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?>').val('0');
                        jQuery('#<?php echo esc_attr($this->get_field_key('payment_by_token')); ?>').trigger('change');
                        customizationTable.find('tr:nth-child(6)').hide();
                    } else if (jQuery.inArray(cardDataMode, ['REST', 'SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS']) != -1) {
                        customizationTable.find('tr:nth-child(6)').show();
                    } else {
                        customizationTable.find('tr:nth-child(6)').hide();
                        jQuery('.payzen-placeholder').show();
                    }
                } else {
                    customizationTable.find('tr:nth-child(6)').hide();
                    jQuery('.payzen-placeholder').show();
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

        if (is_admin()) {
            return true;
        }

        // Check if authorized currency.
        if (! $this->is_supported_currency()) {
            return false;
        }

        // Check if authorized country.
        if (! $this->is_available_for_country()) {
            return false;
        }

        if ($amount = self::get_total_amount()) {
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
        }

        return in_array($country, $this->get_option('specific_countries'));
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
    public function get_payment_fields()
    {
        global $woocommerce;

        if ($order = self::order_created_from_bo()) {
            $cust_id = self::get_order_property($order, 'user_id');
        } else {
            $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        }

        $can_pay_by_alias = $this->can_use_alias($cust_id, true) && $this->get_cust_identifier($cust_id);
        $process_order_url = add_query_arg('wc-api', 'WC_Gateway_Payzen_Process_Order', home_url('/'));

        $html = '';
        $force_redir = false;
        $option = $this->get_option('card_data_mode');
        switch ($option) {
            case 'MERCHANT':
                $card_keys = $this->get_option('payment_cards');
                $all_supported_cards = $this->get_supported_card_types(false);

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

                $html .= '<div style="margin-top: 15px;">';
                foreach ($cards as $key => $value) {
                    $lower_key = strtolower($key);

                    $html .= '<div style="display: inline-block; margin: 10px;">';
                    if (count($cards) == 1) {
                        $html .= '<input type="hidden" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '">';
                    } else {
                        $html .= '<input type="radio" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '" style="vertical-align: middle;" '
                            . checked($key, $selected_value, false) . '>';
                    }

                    $html .= '<label for="' . $this->id . '_' . $lower_key . '" style="display: inline;">';

                    $remote_logo = self::LOGO_URL . $lower_key . '.png';
                    $html .= '<img src="' . $remote_logo . '"
                               alt="' . $key . '"
                               title="' . $value . '"
                               style="vertical-align: middle; margin-left: 5px; max-height: 35px; display: unset;">';

                    $html .= '</label>';
                    $html .= '</div>';
                }

                $html .= '</div>';
                break;

            case 'IFRAME':
                // Load css and create iframe.
                wp_register_style('payzen', WC_PAYZEN_PLUGIN_URL . 'assets/css/payzen.css', array(), self::PLUGIN_VERSION);
                wp_enqueue_style('payzen');

                // Iframe endpoint URL.
                $link = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
                $src = add_query_arg('loading', 'true', $link);

                $html .= '<div>
                         <iframe name="payzen_iframe" id="payzen_iframe" src="' . $src . '" style="display: none;">
                         </iframe>';

                if ($order = self::order_created_from_bo()) {
                    set_transient($this->id . '_current_order_pay', self::get_order_property($order, 'id'));

                    $html .= '<script type="text/javascript">';
                    $html .= "jQuery('#order_review').on('submit', function(e) {
                        if (! jQuery('#payment_method_" . $this->id . "').is(':checked')) {
                            return true;
                        }
                        e.preventDefault();
                        // Unblock screen.
                        jQuery.when().then(function( x ) {
                            jQuery('#order_review').unblock();
                        });
                        jQuery('.payment_method_" . $this->id . " p:first-child').hide();
                        jQuery('ul." . $this->id . "-view-top li.block').hide();
                        jQuery('ul." . $this->id . "-view-bottom li.block').hide();
                        jQuery('#payzen_iframe').show();
                        jQuery('#payzen_iframe').attr('src', '$link');
                        jQuery.ajax({
                            method: 'POST',
                            url: '" . $process_order_url . "',
                            data: { 'payzen_order_id': ". self::get_order_property($order, 'id'). " },
                            success: function(data) {
                               return;
                            }
                       });
                    });";

                    $html .= "jQuery('input[type=\"radio\"][name=\"payment_method\"][value!=\"" . $this->id . "\"]').click(function() {
                                jQuery('#order_review').unblock();
                                jQuery('.payment_method_" . $this->id . " p:first-child').show();
                                jQuery('li." . $this->id . "-id-block').show();
                                jQuery('#payzen_iframe').hide();
                                jQuery('#payzen_iframe').attr('src', '" . $src . "');
                    });</script>";
                    break;
                }

                $html .= '<script type="text/javascript">';
                $html .= "jQuery('form.checkout').on('checkout_place_order_" . $this->id . "', function() {
                                jQuery('form.checkout').removeClass('processing').unblock();
                                jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                                    if ((originalOptions.url.indexOf('wc-ajax=checkout') === -1)
                                        && (originalOptions.url.indexOf('action=woocommerce-checkout') === -1)
                                        && (originalOptions.url.indexOf('wc-ajax=complete_order') === -1)) {
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
                                // For WooCommerce 7.5.
                                const _fetch = window.fetch;
                                window.fetch = (...args) => {
                                    let [url, options] = args;
                                    let data = options.body;
                                    return Promise.resolve(_fetch.apply(window, args))
                                      .then(async (response) => {
                                        if ((url.indexOf('wc-ajax=checkout') == -1)
                                            && (url.indexOf('action=woocommerce-checkout') == -1)) {
                                            return response;
                                        }
                                        if (data.indexOf('payment_method=payzenstd') == -1) {
                                            return response;
                                        }
                                        var status = (response && response.ok) ? response.ok : false;
                                        if (! status) {
                                            return response;
                                        }
                                        var result = '';
                                        await response.clone().json().then(data => {
                                            result = data.result;
                                            return;
                                        });
                                        if (result !== 'success') {
                                            return response;
                                        }
                                        // Unblock screen.
                                        jQuery('form.checkout').unblock();
                                        jQuery('.payment_method_" . $this->id . " p:first-child').hide();
                                        jQuery('ul." . $this->id . "-view-top li.block').hide();
                                        jQuery('ul." . $this->id . "-view-bottom li.block').hide();
                                        jQuery('#payzen_iframe').show();
                                        jQuery('#payzen_iframe').attr('src', '$link');
                                        jQuery(window).unbind('beforeunload');
                                        return response;
                                    });
                                }
                            });";

                $html .= "jQuery('input[type=\"radio\"][name=\"payment_method\"][value!=\"" . $this->id . "\"]').click(function() {
                                jQuery('form.checkout').removeClass('processing').unblock();
                                jQuery('.payment_method_" . $this->id . " p:first-child').show();
                                jQuery('li." . $this->id . "-id-block').show();
                                jQuery('#payzen_iframe').hide();
                                jQuery('#payzen_iframe').attr('src', '" . $src . "');
                            });";
                $html .= "</script>";
                $html .= "</div>";
                break;

            case 'REST':
            case 'SMARTFORM':
            case 'SMARTFORMEXT':
            case 'SMARTFORMEXTNOLOGOS':
                wp_register_style('payzen', WC_PAYZEN_PLUGIN_URL . 'assets/css/payzen.css', array(), self::PLUGIN_VERSION);
                wp_enqueue_style('payzen');

                $hide_smart_button_popin = 'false';
                $hide_single_payment_button = 'false';
                $sf_form_config_js = '';
                if ($option !== 'REST') {
                    if ($this->get_option('rest_popin') === 'yes') {
                        $hide_smart_button_popin = 'true';
                    } else {
                        $sf_form_config_js = 'form: { smartform: { singlePaymentButton: { visibility: false } } },';
                        $hide_single_payment_button = 'true';
                    }

                    $groupingThreshold = $this->get_option('smartform_grouping_threshold');
                    if (($this->get_option('smartform_compact_mode') === 'yes') && is_numeric($groupingThreshold)) {
                        $sf_form_config_js .= "cardForm: { layout: 'compact' }, smartForm: { layout: 'compact',  groupingThreshold : " . $groupingThreshold . " },";
                    } elseif (($this->get_option('smartform_compact_mode') === 'yes')) {
                        $sf_form_config_js .= "cardForm: { layout: 'compact' }, smartForm: { layout: 'compact' },";
                    } elseif (is_numeric($groupingThreshold)) {
                        $sf_form_config_js .= "smartForm: { groupingThreshold : " . $groupingThreshold . " },";
                    }
                }

                $check_smart_button_js = "var smartbuttonMethod = '';
                        var smartbuttonAll = false;
                        if (" . $hide_smart_button_popin . ") {
                            var element = jQuery('.kr-smart-button');
                            if (element.length > 0) {
                                smartbuttonMethod = element.attr('kr-payment-method');
                            } else {
                                element = jQuery('.kr-smart-form-modal-button');
                                if (element.length > 0) {
                                    smartbuttonAll = true;
                                }
                            }
                        }
                        var popin = jQuery('.kr-popin-button').length > 0;
                        if ((! popin) && (! smartbuttonAll) && (smartbuttonMethod.length <= 0)) {
                            if(! " . $hide_single_payment_button . ") {
                                if(! " . $hide_smart_button_popin . ") {
                                    jQuery('#payzenstd_rest_processing').css('display', 'block');
                                    jQuery('ul.payzenstd-view-top li.block').hide();
                                    jQuery('ul.payzenstd-view-bottom').hide();
                                }
                            }
                        }";

                $validate_rest_form_js = "if (popin || smartbuttonAll) {
                            KR.openPopin();
                            jQuery('form.checkout').removeClass('processing').unblock();
                        } else if ( " . $hide_single_payment_button . ") {
                            KR.openSelectedPaymentMethod();
                            jQuery('form.checkout').removeClass('processing').unblock();
                        } else if (smartbuttonMethod.length > 0) {
                            KR.openPaymentMethod(smartbuttonMethod);
                            jQuery('form.checkout').removeClass('processing').unblock();
                        } else {
                            KR.submit();
                        }";

                $html .= $this->rest_payment_fields_view($can_pay_by_alias);
                if (! $html) {
                    // Force payment by redirection.
                    $force_redir = true;
                    break;
                }

                if ($order = self::order_created_from_bo()) {
                    $html .= '<script type="text/javascript">';
                    $html .= "jQuery('#order_review').on('submit', function(e) {
                        if (! jQuery('#payment_method_payzenstd').is(':checked')) {
                            return true;
                        }
                        e.preventDefault();
                        // Unblock screen.
                        jQuery.when().then(function(e) {
                            jQuery('#order_review').unblock();
                        });" . $check_smart_button_js . $validate_rest_form_js . "
                        jQuery.ajax({
                            method: 'POST',
                            url: '" . $process_order_url . "',
                            data: { 'payzen_order_id': ". self::get_order_property($order, 'id'). " },
                            success: function(data) {
                               return;
                            }
                       });
                    });";

                    $html .= "</script>";
                    break;
                }

                $form_token_url = add_query_arg('wc-api', 'WC_Gateway_Payzen_Form_Token', home_url('/'));

                $html .= '<script type="text/javascript">';
                $html .= "var savedData = false;";

                $html .= "jQuery('form.checkout').on('checkout_place_order_" . $this->id . "', function() {
                                jQuery('form.checkout').removeClass('processing').unblock();
                                jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                                    if ((originalOptions.url.indexOf('wc-ajax=checkout') == -1)
                                        && (originalOptions.url.indexOf('action=woocommerce-checkout') == -1)
                                        && (originalOptions.url.indexOf('wc-ajax=complete_order') == -1)) {
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
                                        " . $check_smart_button_js . "
                                        var registerCard = jQuery('input[name=\"kr-do-register\"]').is(':checked');
                                        if (newData === savedData) {
                                            // Data in checkout page has not changed no need to calculate token again.
                                            " . $validate_rest_form_js . "
                                        } else {
                                            // Data in checkout page has changed we need to calculate token again to have correct information.
                                            var useIdentifier = jQuery('#payzen_use_identifier').length && jQuery('#payzen_use_identifier').val();
                                            savedData = newData;
                                            jQuery.ajax({
                                                method: 'POST',
                                                url: '" . $form_token_url . "',
                                                data: { 'use_identifier': useIdentifier },
                                                success: function(data) {
                                                    var parsed = JSON.parse(data);
                                                    KR.setFormConfig({" . $sf_form_config_js . "language: PAYZEN_LANGUAGE, formToken: parsed.formToken}).then(function(v) {
                                                        var KR = v.KR;
                                                        if (registerCard) {
                                                            jQuery('input[name=\"kr-do-register\"]').attr('checked','checked');
                                                        }" . $validate_rest_form_js . "
                                                    });
                                                }
                                            });
                                        }
                                        jQuery(window).unbind('beforeunload');
                                    };
                                });
                                // For WooCommerce 7.5.
                                const _fetch = window.fetch;
                                window.fetch = (...args) => {
                                    let [url, options] = args;
                                    let data = options.body;
                                    return Promise.resolve(_fetch.apply(window, args))
                                      .then(async (response) => {
                                        if ((url.indexOf('wc-ajax=checkout') == -1)
                                            && (url.indexOf('action=woocommerce-checkout') == -1)) {
                                            return response;
                                        }
                                        if (data.indexOf('payment_method=payzenstd') == -1) {
                                            return response;
                                        }
                                        var status = (response && response.ok) ? response.ok : false;
                                        if (! status) {
                                            return response;
                                        }
                                        var result = '';
                                        await response.clone().json().then(data => {
                                            result = data.result;
                                            return;
                                        });
                                        if (result !== 'success') {
                                            return response;
                                        }
                                        jQuery('.kr-form-error').html('');
                                        // Unblock screen.
                                        jQuery('form.checkout').unblock();
                                        " . $check_smart_button_js . "
                                        var popin = jQuery('.kr-popin-button').length > 0;
                                        if (! popin) {
                                            jQuery('#payzenstd_rest_processing').css('display', 'block');
                                            jQuery('ul.payzenstd-view-top li.block').hide();
                                            jQuery('ul.payzenstd-view-bottom').hide();
                                        }
                                        var registerCard = jQuery('input[name=\"kr-do-register\"]').is(':checked');
                                        if (savedData && (data === savedData)) {
                                            // Data in checkout page has not changed no need to calculate token again.
                                            " . $validate_rest_form_js . "
                                        } else {
                                            // Data in checkout page has changed we need to calculate token again to have correct information.
                                            var useIdentifier = jQuery('#payzen_use_identifier').length && jQuery('#payzen_use_identifier').val();
                                            savedData = data;
                                            jQuery.ajax({
                                                method: 'POST',
                                                url: '" . $form_token_url . "',
                                                data: { 'use_identifier': useIdentifier },
                                                async: false,
                                                success: function(data) {
                                                    var parsed = JSON.parse(data);
                                                    KR.setFormConfig({" . $sf_form_config_js . "language: PAYZEN_LANGUAGE, formToken: parsed.formToken}).then(function(v) {
                                                        var KR = v.KR;
                                                        if (registerCard) {
                                                            jQuery('input[name=\"kr-do-register\"]').attr('checked','checked');
                                                        }
                                                        " . $validate_rest_form_js . "
                                                    });
                                                }
                                            });
                                        }
                                        jQuery(window).unbind('beforeunload');
                                        return response;
                                    });
                                }
                            });";
                $html .= "</script>";
                break ;

            default:
                break;
        }

        if ($can_pay_by_alias) {
            // Display specific description for payment by token if enabled.
            return $this->payment_by_alias_view($html, $force_redir);
        } else {
            if ($force_redir) {
                $html = '<input type="hidden" name="payzen_force_redir" value="true">';
            }

            if ($this->id === 'payzenmulti') {
                return $html;
            }

            return '<div>' . wpautop(wptexturize($this->get_description())) . '</div>' . $html;
        }
    }

    public function payment_fields() {
        echo $this->get_payment_fields();
    }

    protected function can_use_alias($cust_id, $verify_identifier = false)
    {
        if (! $cust_id) {
            return false;
        }

        if ($this->id !== 'payzenstd') {
            return false;
        }

        if ($this->get_option('payment_by_token') !== '1') {
            return false;
        }

        return (! $verify_identifier ||
            ((PayzenTools::has_checkout_block() || self::order_created_from_bo() || ! empty($_GET['wc-ajax'])) && $this->check_identifier($cust_id, $this->id)));
    }

    protected function payment_by_alias_view($payment_fields, $force_redir)
    {
        global $woocommerce;

        $embedded = in_array($this->get_option('card_data_mode'), array('REST', 'SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS', 'IFRAME')) && ! empty($payment_fields);
        $embedded_fields = PayzenTools::is_embedded_payment(false) && ($this->get_option('rest_popin') !== 'yes') && ! empty($payment_fields);

        if ($order= self::order_created_from_bo()) {
            $cust_id = self::get_order_property($order, 'user_id');
        } else {
            $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        }

        $saved_masked_pan = $embedded_fields ? '' : get_user_meta((int) $cust_id, $this->id . '_masked_pan', true);
        if ($saved_masked_pan) {
            // Recover card brand if saved with masked pan and check if logo exists.
            $card_brand = '';
            $card_brand_logo = '';
            if (strpos($saved_masked_pan, '|')) {
                $card_brand = substr($saved_masked_pan, 0, strpos($saved_masked_pan, '|'));
                $remote_logo = self::LOGO_URL . strtolower($card_brand) . '.png';
                if ($card_brand) {
                    $card_brand_logo = '<img src="' . $remote_logo . '"
                           alt="' . $card_brand . '"
                           title="' . $card_brand . '"
                           style="vertical-align: middle; margin: 0 10px 0 5px; max-height: 20px; display: unset;">';
                }
            }

            $saved_masked_pan = $card_brand_logo ? $card_brand_logo . '<b style="vertical-align: middle;">' . substr($saved_masked_pan, strpos($saved_masked_pan, '|') + 1) . '</b>'
                    : ' <b>' . str_replace('|',' ', $saved_masked_pan) . '</b>';
        }

        $html = '<ul class="' . $this->id . '-view-top" style="margin-left: 0; margin-top: 0;">
                   <li style="list-style-type: none;" class="block ' . $this->id . '-cc-block">';

        $html .= wpautop(wptexturize(parent::get_description()));
        if ($force_redir) {
            $html .= '<input type="hidden" name="payzen_force_redir" value="true">';
        }

        $html .= '    </li>

                  <li style="list-style-type: none;" class="block ' . $this->id . '-id-block">
                      <input id="payzen_use_identifier" type="hidden" value="true" name="payzen_use_identifier">
                      <span>' .
                          sprintf(__('You will pay with your stored means of payment %s', 'woo-payzen-payment'), $saved_masked_pan)
                          . ' (<a href="' . esc_url(wc_get_account_endpoint_url($this->get_option('woocommerce_saved_cards_endpoint', 'ly_saved_cards'))) . '">' . __('manage your payment means', 'woo-payzen-payment') . '</a>).
                      </span>
                  </li>';

        if (! empty($payment_fields)) { // There is extra HTML/JS to display.
            $html .= '<li style="list-style-type: none;"' . ($embedded ? '' : ' class="block ' . $this->id . '-cc-block"') . '>';
            $html .= $payment_fields;
            $html .= '</li>';
        }

        $html .= '</ul>

              <ul class="payzenstd-view-bottom" style="margin-left: 0; margin-top: 0;">
                  <li style="list-style-type: none; margin: 15px 0px;" class="block ' . $this->id . '-cc-block ' . $this->id . '-id-block">
                      <span>' . __('OR', 'woo-payzen-payment') . '</span>
                  </li>

                  <li style="list-style-type: none;" class="block ' . $this->id . '-cc-block">
                      <a href="javascript: void(0);" onclick="payzenUpdatePaymentBlock(true)">' . __('Click here to pay with your registered means of payment.', 'woo-payzen-payment') . '</a>
                  </li>

                  <li style="list-style-type: none;" class="block ' . $this->id . '-id-block">
                      <a href="javascript: void(0);" onclick="payzenUpdatePaymentBlock(false)">' . __('Click here to pay with another means of payment.', 'woo-payzen-payment') . '</a>
                  </li>
              </ul>';

        $html .= '<script type="text/javascript">
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

        return $html;
    }

    /**
     * Return true if fields are loaded by AJAX call.
     *
     * @access private
     * @return boolean
     */
    private function load_by_ajax_call()
    {
        return ! empty($_GET['wc-ajax']);
    }

    private function rest_payment_fields_view($use_identifier)
    {
        // Disable this patch and load JS fields always, this is safer.
        // if (! $this->load_by_ajax_call()) {
        //     // Interface is loaded by ajax calls.
        //     return '';
        // }

        if ($order = self::order_created_from_bo()) {
            $this->payzen_fill_request($order);
            $form_token = $this->get_form_token($order);
        } else {
            $form_token = $this->get_temporary_form_token();
        }

        if (! $form_token) {
            // No form token, use redirection.
            return '';
        }

        $jsVars = 'window.FORM_TOKEN = "' . $form_token . '";';

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/loading.gif';
        $popin_attr = '';
        $cards_form = 'false';
        $html = '';
        $sfExpanded = '';
        $hide_smart_button_popin = 'false';
        $hide_single_payment_button = 'false';
        $single_payment_button = '';
        $compact_mode = 'false';
        $groupingThreshold = '';
        $sfStyle = '';

        if (PayzenTools::is_embedded_payment(false)) {
            if ($this->get_option('rest_popin') === 'yes') {
                $popin_attr = 'kr-popin';
            }

            switch ($this->get_option('card_data_mode')) {
                case 'REST':
                    $krMode = 'kr-embedded';
                    $cards_form = 'true';
                    break;

                case 'SMARTFORM':
                    $krMode = 'kr-smart-form';
                    break;

                case 'SMARTFORMEXT':
                    $krMode = 'kr-smart-form';
                    $sfExpanded = 'kr-card-form-expanded';
                    break;

                case 'SMARTFORMEXTNOLOGOS':
                    $krMode = 'kr-smart-form';
                    $sfExpanded = 'kr-card-form-expanded kr-no-card-logo-header';
                    break;

                default:
                    break;
            }

            if (PayzenTools::is_embedded_payment()) {
                $groupingThreshold = $this->get_option('smartform_grouping_threshold');
                $compact_mode = ($this->get_option('smartform_compact_mode') === 'yes') ? 'true' : 'false';
                if ($this->get_option('rest_popin') === 'yes') {
                    $hide_smart_button_popin = 'true';
                } else {
                    $single_payment_button = 'kr-single-payment-button';
                    $hide_single_payment_button = 'true';
                }
            }
        }

        set_transient('payzen_hide_smartbutton_' . wp_get_session_token(), $hide_smart_button_popin);

        $html .= '<div id="payzenstd_rest_wrapper"></div>';
        $html .= '<script type="text/javascript">';

        if ($use_identifier) {
            if ($order) {
                $this->payzen_fill_request($order);
                $identifier_token = $this->get_form_token($order, true);
            } else {
                $identifier_token = $this->get_temporary_form_token(true);
            }

            $jsVars .= 'window.IDENTIFIER_FORM_TOKEN = "' . $identifier_token . '";';
        }

        $jsVars .= 'window.PAYZEN_KR_MODE = "' . $krMode . ' ";';
        $jsVars .= 'window.PAYZEN_SINGLE_PAYMENT_BUTTON_MODE = "' . $single_payment_button . '";';
        $jsVars .= 'window.PAYZEN_SF_EXTENDED_MODE = "' . $sfExpanded . '";';
        $jsVars .= 'window.PAYZEN_POPIN_ATTR = "' . $popin_attr . '";';
        $jsVars .= 'window.PAYZEN_SF_STYLE = new Boolean(' . (empty($sfStyle) ? 'false' : 'true') . ');';
        $jsVars .= 'window.PAYZEN_CARDS_FORM = new Boolean(' . $cards_form . ');';
        $jsVars .= 'window.PAYZEN_IMG_URL = "' . $img_url . '";';
        $jsVars .= 'window.PAYZEN_SF_COMPACT_MODE = new Boolean(' . $compact_mode . ');';
        $jsVars .= 'window.PAYZEN_GROUPING_THRESHOLD = "' . (is_numeric($groupingThreshold) ? $groupingThreshold : 'false') . '";';
        $jsVars .= 'window.PAYZEN_HIDE_SINGLE_BUTTON = new Boolean(' . $hide_single_payment_button . ');';

        set_transient('payzen_js_vars_' . wp_get_session_token(), $jsVars);

        $html .= $jsVars;

        $html .= 'var formIsValidated = false;
            jQuery(document).ready(function() {
                if (typeof IDENTIFIER_FORM_TOKEN === "undefined") {
                    setTimeout(function () {
                        payzenUpdateFormToken(false);
                    }, 300);
                }
                jQuery("#place_order").click(function(event) {
                    if (! jQuery("#payment_method_payzenstd").is(":checked")) {
                        return true;
                    }
                    var useIdentifier = jQuery("#payzen_use_identifier").length && jQuery("#payzen_use_identifier").val() === "true";
                    var popin = (jQuery(".kr-popin-button").length > 0);
                    if ((! useIdentifier) && (! popin)  && (! ' . $hide_single_payment_button . ') && (! ' . $hide_smart_button_popin . ')) {
                        if (formIsValidated) {
                            formIsValidated = false;
                            return true;
                        }
                        event.preventDefault();
                        KR.validateForm().then(function(v) {
                            // There is no errors.
                            formIsValidated = true;
                            jQuery("#place_order").click();
                        }).catch(function(v) {
                            // Display error message.
                            var result = v.result;
                            return result.doOnError();
                        });
                    }
                });
            });';
        $html .= '</script>';

        return $html;
    }

    private function get_smartform_cards()
    {
        $cards = $this->get_option('payment_cards');
        if (PayzenTools::is_embedded_payment() && $this->payzen_other_payment_methods->is_available_ignoring_regroup()) {
            $available_options = $this->payzen_other_payment_methods->get_available_options_for_smartform();
            if (! empty($available_options)) {
                if (! is_array($cards) || in_array('', $cards)) {
                    return array();
                }

                foreach ($available_options as $code => $option) {
                    if (! array_key_exists($option['payment_mean'], $cards)) {
                        array_push($cards, $option['payment_mean']);
                    }
                }
            }
        }

        return $cards;
    }

    private function get_temporary_form_token($use_identifier = false)
    {
        global $woocommerce;

        if (! $woocommerce->cart || ($woocommerce->cart->total <= 0)) {
            return false;
        }

        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        $email = method_exists($woocommerce->customer, 'get_billing_email') ? $woocommerce->customer->get_billing_email() : $woocommerce->customer->user_email;
        $params = array(
            'amount' => $currency->convertAmountToInteger($woocommerce->cart->total),
            'currency' => $currency->getAlpha3(),
            'customer' => array(
                'email' => $email
            )
        );

        if (PayzenTools::is_embedded_payment()) {
            $cards = $this->get_smartform_cards();
            if (is_array($cards) && ! in_array('', $cards)) {
                $params['paymentMethods'] = $cards;
            }
        }

        $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        if ($use_identifier) {
            $saved_identifier = $this->get_cust_identifier($cust_id);
            $params['paymentMethodToken'] = $saved_identifier;
        } elseif ($this->get_option('payment_by_token') === '1' && $cust_id) {
            $params['formAction'] = 'ASK_REGISTER_PAY';
        }

        // Check if capture delay is overriden in standard submodule.
        $captureDelay = is_numeric($this->get_option('capture_delay')) ? $this->get_option('capture_delay') :
            $this->get_general_option('capture_delay');

        // In case of Smartform, only payment means supporting capture delay will be shown.
        if (is_numeric($captureDelay)) {
            $params['transactionOptions']['cardOptions']['captureDelay'] = $captureDelay;
        }

        // Do not refresh token if data didn't change.
        $last_token_data = get_transient('payzen_token_data_' . ($use_identifier ? 'identifier_' : '') . wp_get_session_token());
        $last_token = get_transient('payzen_token_' . ($use_identifier ? 'identifier_' : '') . wp_get_session_token());

        $token_data = base64_encode(serialize($params));
        if ($last_token && $last_token_data && ($last_token_data === $token_data)) {
            // Cart data does not change from last payment attempt, do not re-create payment token.
            $this->log("Cart data did not change since last token creation, use last created token for current cart for user: {$email}.");
            return $last_token;
        }

        $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');

        $return = false;

        try {
            $client = new PayzenRest($this->get_general_option('rest_url'), $this->get_general_option('site_id'), $key);
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
                $this->log("Form token created successfully for current cart for user: {$email}.");
                $return = $result['answer']['formToken'];

                set_transient('payzen_token_data_' . ($use_identifier ? 'identifier_' : '') . wp_get_session_token(), $token_data);
                set_transient('payzen_token_' . ($use_identifier ? 'identifier_' : '') . wp_get_session_token(), $return);
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
        $params = array(
            'orderId' => $order_id,
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
                    'country' => $this->get_escaped_var($this->payzen_request, 'cust_country'),
                    'identityCode' => $this->get_escaped_var($this->payzen_request, 'cust_national_id'),
                    'streetNumber' => $this->get_escaped_var($this->payzen_request, 'cust_address_number'),
                    'district' => $this->get_escaped_var($this->payzen_request, 'cust_district'),
                    'status' => $this->get_escaped_var($this->payzen_request, 'cust_status')
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
                'cardOptions' => array(
                    'paymentSource' => 'EC',
                    'captureDelay' => $this->get_escaped_var($this->payzen_request, 'capture_delay') // In case of Smartform, only payment means supporting capture delay will be shown.
                )
            ),
            'contrib' => $this->get_escaped_var($this->payzen_request, 'contrib'),
            'strongAuthentication' => $strong_auth,
            'currency' => $currency->getAlpha3(),
            'amount' => $this->get_escaped_var($this->payzen_request, 'amount'),
            'metadata' => array(
                'order_key' => self::get_order_property($order, 'order_key'),
                'blog_id' => $wpdb->blogid
            )
        );

        $validationMode = $this->payzen_request->get('validation_mode');

        switch ($validationMode) {
            case '0' :
                $params['transactionOptions']['cardOptions']['manualValidation'] = 'NO';
                break;

            case '1':
                $params['transactionOptions']['cardOptions']['manualValidation'] = 'YES';
                break;

            default:
                break;
        }

        // Set number of attempts in case of rejected payment.
        if ($this->settings['rest_attempts'] !== null) {
            $params['transactionOptions']['cardOptions']['retry'] = $this->settings['rest_attempts'];
        }

        if ($order= self::order_created_from_bo()) {
            $cust_id = self::get_order_property($order, 'user_id');
        } else {
            $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        }

        if (PayzenTools::is_embedded_payment()) {
            $cards = $this->get_smartform_cards();
            if (is_array($cards) && ! in_array('', $cards)) {
                $params['paymentMethods'] = $cards;
            }
        }

        if ($use_identifier) {
            $saved_identifier = $this->get_cust_identifier($cust_id);
            $params['paymentMethodToken'] = $saved_identifier;
        } elseif ($this->get_option('payment_by_token') === '1' && $cust_id) {
            $this->log('Customer ' . $this->payzen_request->get('cust_email') . ' will be asked for card data registration.');
            $params['formAction'] = 'ASK_REGISTER_PAY';
        }

        $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');

        $return = false;

        try {
            $client = new PayzenRest($this->get_general_option('rest_url'), $this->payzen_request->get('site_id'), $key);
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

    protected function get_escaped_var($request, $var)
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

        // Get order ID from session.
        $order_id = $woocommerce->session->get('order_awaiting_payment') ? $woocommerce->session->get('order_awaiting_payment') : $woocommerce->session->get('store_api_draft_order');
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
        if (isset($_POST['payzen_use_identifier']) || isset($_COOKIE['payzen_use_identifier'])) {
            $use_identifier = isset($_POST['payzen_use_identifier']) ? $_POST['payzen_use_identifier'] : $_COOKIE['payzen_use_identifier'];
            set_transient($this->id . '_use_identifier_' . $order_id, $use_identifier === 'true');
        }

        $order = new WC_Order($order_id);
        $force_redir = isset($_POST['payzen_force_redir']) || (isset($_COOKIE['payzen_force_redir']) || ! empty($_COOKIE['payzen_force_redir']));

        // If ($_POST || $_COOKIE)['payzen_force_redir'] is set, force payment by redirection.
        if (($this->id === 'payzenstd') && (PayzenTools::is_embedded_payment(false) && ! $force_redir || $this->get_option('card_data_mode') === 'IFRAME')) {
            $return = array(
                'result' => 'success',
                'messages' => '<div></div>'
            );

            // To avoid "undefined key" notice.
            if (PayzenTools::has_checkout_block()) {
                $return['redirect'] = null;
            }

            return $return;
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
     * Set payment method for orders created from BO.
     **/
    public function process_order_payment() {
        global $woocommerce;

        if (isset($_POST['payzen_order_id'])) {
            $order = new WC_Order($_POST['payzen_order_id']);
            $order->set_payment_method($this);
            $order->save();
        }

        @ob_clean();
        die();
    }

    protected function save_selected_card($order_id)
    {
        $selected_card = isset($_POST[$this->id . '_card_type']) ? $_POST[$this->id . '_card_type'] : $_COOKIE[$this->id . '_card_type'];

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

        $form = '<form action="' . esc_url($this->payzen_request->get('platform_url')) . '" method="post" id="' . $this->id . '_payment_form">';
        $form .= '  <input type="submit" class="button-alt" id="' . $this->id . '_payment_form_submit" value="' . sprintf(__('Pay via %s', 'woo-payzen-payment'), self::GATEWAY_NAME).'">';
        $form .= '  <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woo-payzen-payment') . '</a>';
        $form .= $this->payzen_request->getRequestHtmlFields();
        $form .= '</form>';

        $form .= '<script type="text/javascript">';
        $form .= "function payzen_submit_form() {document.getElementById('" . $this->id . "_payment_form_submit').click();}";
        $form .= "if (window.addEventListener) {window.addEventListener('load', payzen_submit_form, false);} else if (window.attachEvent) { window.attachEvent('onload', payzen_submit_form);}";
        $form .= "</script>";

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

        // Check if it's an order created from WordPress BO.
        if ($order_id = get_transient($this->id . '_current_order_pay')) {
            delete_transient($this->id . '_current_order_pay');
        } else {
            // Get order ID from session.
            $order_id = $woocommerce->session->get('order_awaiting_payment') ? $woocommerce->session->get('order_awaiting_payment') : $woocommerce->session->get('store_api_draft_order');
        }

        if (! $order_id) {
            die();
        }

        if (isset($_COOKIE['payzen_use_identifier'])) {
            set_transient($this->id . '_use_identifier_' . $order_id, $_COOKIE['payzen_use_identifier'] === 'true');
        }

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

        $form = '<form action="' . esc_url($this->payzen_request->get('platform_url')) . '" method="post" id="' . $this->id . '_payment_iframe_form">';
        $form .= $this->payzen_request->getRequestHtmlFields();
        $form .= '</form>';

        $form .= '<script type="text/javascript">';
        $form .= "function payzen_submit_form() {document.getElementById('" . $this->id . "_payment_iframe_form').submit();}";
        $form .= "if (window.addEventListener) { window.addEventListener('load', payzen_submit_form, false);} else if (window.attachEvent) { window.attachEvent('onload', payzen_submit_form);}";
        $form .= "</script>";

        echo $form;
        die();
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        global $wpdb;

        $order_id = self::get_order_property($order, 'id');
        $cust_id = self::get_order_property($order, 'user_id');

        $this->log("Generating payment form for order #$order_id.");

        // Get currency.
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        if ($currency == null) {
            $this->log('The store currency (' . get_woocommerce_currency() . ') is not supported by payment gateway.');

            wp_die(sprintf(__('The store currency (%s) is not supported by %s.', 'woo-payzen-payment'), get_woocommerce_currency(), self::GATEWAY_NAME));
        }

        // Params.
        $misc_params = array(
            'amount' => $currency->convertAmountToInteger($order->get_total()),
            'contrib' => PayzenTools::get_contrib(),
            'currency' => $currency->getNum(),
            'order_id' => $order_id,

            // Billing address info.
            'cust_id' => $cust_id,
            'cust_email' => self::get_order_property($order, 'billing_email'),
            'cust_first_name' => self::get_order_property($order, 'billing_first_name'),
            'cust_last_name' => self::get_order_property($order, 'billing_last_name'),
            'cust_address' => self::get_order_property($order, 'billing_address_1') . ' ' . self::get_order_property($order, 'billing_address_2'),
            'cust_zip' => self::get_order_property($order, 'billing_postcode'),
            'cust_country' => self::get_order_property($order, 'billing_country'),
            'cust_phone' => str_replace(array('(', '-', ' ', ')'), '', self::get_order_property($order, 'billing_phone')),
            'cust_city' => self::get_order_property($order, 'billing_city'),
            'cust_state' => self::get_order_property($order, 'billing_state'),
            'cust_national_id' => $order->get_meta('_billing_persontype') == '2' ? $order->get_meta('_billing_cnpj') : $order->get_meta('_billing_cpf'),
            'cust_address_number' => $order->get_meta('_billing_number'),
            'cust_district' => $order->get_meta('_billing_neighborhood') ? $order->get_meta('_billing_neighborhood') : self::get_order_property($order, 'billing_city'),
            'cust_status' => $order->get_meta('_billing_persontype') ? ($order->get_meta('_billing_persontype') == '2' ? 'COMPANY' : 'PRIVATE') : '',

            // Shipping address info.
            'ship_to_first_name' => self::get_order_property($order, 'shipping_first_name'),
            'ship_to_last_name' => self::get_order_property($order, 'shipping_last_name'),
            'ship_to_street' => self::get_order_property($order, 'shipping_address_1'),
            'ship_to_street2' => self::get_order_property($order, 'shipping_address_2'),
            'ship_to_city' => self::get_order_property($order, 'shipping_city'),
            'ship_to_state' => self::get_order_property($order, 'shipping_state'),
            'ship_to_country' => self::get_order_property($order, 'shipping_country'),
            'ship_to_zip' => self::get_order_property($order, 'shipping_postcode'),

            'shipping_amount' => $currency->convertAmountToInteger($this->get_shipping_with_tax($order)),

            // Return URLs.
            'url_return' => add_query_arg('wc-api', 'WC_Gateway_Payzen', home_url('/'))
        );

        $this->payzen_request->setFromArray($misc_params);

        $this->payzen_request->addExtInfo('order_key', self::get_order_property($order, 'order_key'));
        $this->payzen_request->addExtInfo('blog_id', $wpdb->blogid);
        $this->payzen_request->addExtInfo('session_id', wp_get_session_token());

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
            $saved_identifier = $this->get_cust_identifier($cust_id);
            $is_identifier_active = $this->is_cust_identifier_active($cust_id);
            if ($saved_identifier && $is_identifier_active) {
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
                $this->to_gateway_category($item_data['product_id']),
                $product_tax_rate // In percentage.
           );
        }
    }

    public function to_gateway_category($product_id)
    {
        // Commmon category if any.
        $common_category = $this->get_general_option('common_category');

        if (empty($common_category)) {
            return null;
        } elseif ($common_category !== 'CUSTOM_MAPPING') {
            return $common_category;
        }

        $category_mapping = $this->get_general_option('category_mapping');
        $product = new WC_Product($product_id);
        $category_ids = $product->get_category_ids();

        if (is_array($category_mapping) && ! empty($category_mapping)) {
            if (is_array($category_ids) && ! empty($category_ids)) {
                foreach ($category_mapping as $code => $category) {
                    if (in_array($code, $category_ids)) {
                        return $category['category'];
                    }
                }
            }

            // In cas product categories are not top level.
            $top_level_category = $this->get_product_top_level_category($product_id);
            if (isset($category_mapping[$top_level_category])) {
                return $category_mapping[$top_level_category]['category'];
            }
        }

        return null;
    }

    public function to_gateway_carrier($method_code)
    {
        $shipping_mapping = $this->get_general_option('shipping_options');

        if (is_array($shipping_mapping) && ! empty($shipping_mapping)) {
            foreach ($shipping_mapping as $code => $shipping_method) {
                if ($code === $method_code) {
                    return $shipping_method;
                }
            }
        }

        return null;
    }

    protected function send_shipping_data($order)
    {
        $this->payzen_request->set('cust_status', 'PRIVATE');
        $this->payzen_request->set('ship_to_status', 'PRIVATE');

        $not_allowed_chars_regex = '#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ -]#ui';

        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $selected_shipping = $chosen_shipping_methods ? substr($chosen_shipping_methods[0], 0, strpos($chosen_shipping_methods[0], ':')) : '';

        if (! $selected_shipping) { // There is no shipping method.
            $this->payzen_request->set('ship_to_type', 'ETICKET');
            $this->payzen_request->set('ship_to_speed', 'EXPRESS');
        } else {
            $shipping_method = $this->to_gateway_carrier($selected_shipping);

            if (! $shipping_method) {
                $this->log('Cannot get mapped data for the order shipping method: ' . $shipping_method_code);
                return;
            }

            // Get carrier name.
            $carrier_name = $order->get_shipping_method();

            // Delivery point name.
            switch ($shipping_method['type']) {
                case 'RELAY_POINT':
                case 'RECLAIM_IN_STATION':
                case 'RECLAIM_IN_SHOP':
                    $name = $carrier_name;

                    $address = self::get_order_property($order, 'shipping_address_1');
                    $address .= self::get_order_property($order, 'shipping_address_2') ? ' ' . self::get_order_property($order, 'shipping_address_2') : '';

                    // Send delivery point name, address, postcode and city in field ship_to_delivery_company_name.
                    $name .= ' ' . $address;
                    $name .= ' ' . self::get_order_property($order, 'shipping_postcode');
                    $name .= ' ' . self::get_order_property($order, 'shipping_city');

                    // Delete not allowed chars.
                    $this->payzen_request->set('ship_to_delivery_company_name', preg_replace($not_allowed_chars_regex, ' ', $name));

                    break;

                default:
                    $this->payzen_request->set('ship_to_delivery_company_name', preg_replace($not_allowed_chars_regex, ' ', $carrier_name));

                    break;
            }

            $this->payzen_request->set('ship_to_type', empty($shipping_method['type']) ? null : $shipping_method['type']);
            $this->payzen_request->set('ship_to_speed', empty($shipping_method['speed']) ? null : $shipping_method['speed']);

            if ($shipping_method['speed'] === 'PRIORITY') {
                $this->payzen_request->set('ship_to_delay', empty($shipping_method['delay']) ? null : $shipping_method['delay']);
            }
        }
    }

    private function get_product_top_level_category($product_id)
    {
        $product_terms = get_the_terms($product_id, 'product_cat');

        // Check if one of the product categories is top level.
        foreach ($product_terms as $term) {
            if ($term->parent == 0) {
                return $term->term_id;
            }
        }

        $product_category = $product_terms[0]->parent;
        $product_category_term = get_term($product_category, 'product_cat');
        $product_category_parent = $product_category_term->parent;
        $product_top_category = $product_category_term->term_id;

        // Recursive test to find top level caegory.
        while ($product_category_parent != 0) {
            $product_category_term = get_term($product_category_parent, 'product_cat');
            $product_category_parent = $product_category_term->parent;
            $product_top_category = $product_category_term->term_id;
        }

        return $product_top_category;
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
            $sha_key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');
        } else {
            $sha_key = $this->testmode ? $this->get_general_option('test_return_key') : $this->get_general_option('prod_return_key');
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
        if (! is_array($answer) || empty($answer)) {
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

        $update_on_failure = isset($answer['orderCycle']) && ($answer['orderCycle'] === 'CLOSED');

        // Wrap payment result to use traditional order creation tunnel.
        $data = PayzenRestTools::convertRestResult($answer);
        $response = new PayzenResponse($data, null, '', '');

        parent::payzen_manage_notify_response($response, $from_server_rest, $update_on_failure);
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

    public static function order_created_from_bo()
    {
        if ($order_id = get_query_var('order-pay')) {
            return new WC_Order((int) $order_id);
        }

        return false;
    }

    public static function get_total_amount()
    {
        global $woocommerce;

        if ($order = self::order_created_from_bo()) {
            return $order->get_total();
        } elseif ($woocommerce->cart) {
            return $woocommerce->cart->total;
        }

        return 0;
    }
}
