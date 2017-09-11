<?php
/**
 * PayZen V2-Payment Module version 1.4.0 for WooCommerce 2.x-3.x. Support contact : support@payzen.eu.
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
 * PayZen Payment Gateway : standard payment class.
 */
class WC_Gateway_PayzenStd extends WC_Gateway_Payzen
{

    public function __construct()
    {
        $this->id = 'payzenstd';
        $this->icon = apply_filters('woocommerce_payzenstd_icon', WC_PAYZEN_PLUGIN_URL . '/assets/images/payzen.png');
        $this->has_fields = false;
        $this->method_title = 'PayZen - ' . __('One-time Payment', 'woo-payzen-payment');

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

        // reset PayZen standard payment admin form action
        add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

        // update PayZen standard payment admin form action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // generate PayZen standard payment form action
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

        // return from payment platform action
        add_action('woocommerce_api_wc_gateway_payzen', array($this, 'payzen_notify_response'));

        // filter to allow order status override
        add_filter('woocommerce_payment_complete_order_status', array($this, 'payzen_complete_order_status'), 10, 2);

        // customize email
        add_action('woocommerce_email_after_order_table', array($this, 'payzen_add_order_email_payment_result'), 10, 3);
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
            $icon = '<img style="width: 85px;" src="';
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
        $description = $this->get_option('description');

        if (is_array($description)) {
            $description = isset($description[get_locale()]) && $description[get_locale()] ? $description[get_locale()] : $description['en_US'];
        }

        return apply_filters('woocommerce_gateway_description', $description, $this->id);
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        // load common form fields to concat them with sub-module settings
        parent::init_form_fields();

        foreach ($this->form_fields as $k => $v) {
            $this->payzen_common_fields[$k] = $v;
        }

        $this->form_fields = array(
                // CMS config params
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
                'default' => __('Pay by credit card', 'woo-payzen-payment')
            ),
            'description' => array(
                'title' => __('Description', 'woo-payzen-payment'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woo-payzen-payment'),
                'default' => __('You will be redirected to payment page after order confirmation.', 'woo-payzen-payment'),
                'css' => 'width: 35em;'
            ),

            // amount restrictions
            'amount_restrictions' => array(
                'title' => __('AMOUNT RESTRICTIONS', 'woo-payzen-payment'),
                'type' => 'title'
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

            // Payment page
            'payment_page' => array(
                'title' => __('PAYMENT PAGE', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'payment_cards' => array(
                'title' => __('Card Types', 'woo-payzen-payment'),
                'type' => 'multiselect',
                'default' => array(''),
                'options' => array_merge(array('' => __('All', 'woo-payzen-payment')), $this->get_supported_card_types()),
                'description' => __('The card type(s) that can be used for the payment. Select none to use platform configuration.', 'woo-payzen-payment')
            ),

            // Advanced options
            'advanced_options' => array(
                'title' => __('ADVANCED OPTIONS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'card_data_mode' => array(
                'title' => __('Card type selection', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => 'DEFAULT',
                'options' => array(
                    'DEFAULT' => __('On payment platform', 'woo-payzen-payment'),
                    'MERCHANT' => __('On merchant site', 'woo-payzen-payment')
                ),
                'description' => __('Select where card type will be selected by buyer.', 'woo-payzen-payment')
            )
        );

        // if WooCommecre Multilingual is not available (or installed version not allow gateways UI translation)
        // let's suggest our translation feature
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['type'] = 'multilangtext';
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Pay by credit card',
                'en_GB' => 'Pay by credit card',
                'fr_FR' => 'Paiement par carte bancaire',
                'de_DE' => 'Zahlung mit EC-/Kreditkarte'
            );

            $this->form_fields['description']['type'] = 'multilangtext';
            $this->form_fields['description']['default'] = array(
                'en_US' => 'You will be redirected to payment page after order confirmation.',
                'en_GB' => 'You will be redirected to payment page after order confirmation.',
                'fr_FR' => 'Vous allez être redirigé(e) vers la page de paiement après confirmation de la commande.',
                'de_DE' => 'Sie werden zu den Zahlungsseiten nach Zahlungsbestätigung weitergeleitet.'
            );
        }
    }

    protected function get_supported_card_types()
    {
        return PayzenApi::getSupportedCardTypes();
    }

    /**
     * Override init_settings methode to retrieve PayZen common settings.
     */
    public function init_settings()
    {
        parent::init_settings();

        $common_settings = get_option('woocommerce_payzen_settings', null);

        // if there are no settings defined, load defaults
        if ((! $common_settings || ! is_array($common_settings)) && isset($this->payzen_common_fields) && is_array($this->payzen_common_fields)) {
            foreach ($this->payzen_common_fields as $k => $v) {
                $this->settings[$k] = isset($v['default']) ? $v['default'] : '';
            }
        } else {
            foreach ($common_settings as $k => $v) {
                $this->settings[$k] = $v ;
            }
        }
    }

    /**
     * Check if this gateway is enabled and available for the current cart.
     */
    public function is_available()
    {
        global $woocommerce;

        if (! $this->is_supported_currency()) {
            return false;
        }

        $amount = $woocommerce->cart->total;
        if (($this->get_option('amount_max') != '' && $amount > $this->get_option('amount_max'))
            || ($this->get_option('amount_min') != '' && $amount < $this->get_option('amount_min'))) {
            return false;
        }

        return parent::is_available();
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

        if ($this->get_option('card_data_mode') == 'MERCHANT') {
            $card_keys = $this->get_option('payment_cards');
            $all_supported_cards = $this->get_supported_card_types();

            if (! is_array($card_keys) || in_array('', $card_keys)) {
                $cards = $all_supported_cards;
            } else {
                foreach ($card_keys as $key) {
                    $cards[$key] = $all_supported_cards[$key];
                }
            }

            // get first array key
            reset($cards);
            $selected_value = key($cards);

            echo '<div>';
            foreach ($cards as $key => $value) {
                $lower_key = strtolower($key);

                echo '<div style="display: inline-block;">';
                if (count($cards) == 1) {
                    echo '<input type="hidden" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '">';
                } else {
                    echo '<input type="radio" id="' . $this->id . '_' . $lower_key . '" name="' . $this->id . '_card_type" value="' . $key . '" style="vertical-align: middle;" '
                            . checked($key, $selected_value, false) . '>';
                }

                echo '<label for="' . $this->id . '_' . $lower_key . '" style="display: inline;">';

                if (file_exists(dirname(__FILE__) . '/assets/images/' . $lower_key . '.png')) {
                    echo '<img src="' . WC_PAYZEN_PLUGIN_URL . '/assets/images/' . $lower_key . '.png"
                               alt="' . $value . '"
                               title="' . $value . '"
                               style="vertical-align: middle; margin-right: 10px; height: 25px;">';
                } else {
                    echo '<span style="vertical-align: middle; margin-right: 10px; height: 25px;">' . $value . '</span>';
                }

                echo '</label>';
                echo '</div>';
            }

            echo '</div><br />';
        }

    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        if ($this->get_option('card_data_mode') == 'MERCHANT') {
            $this->save_selected_card($order_id);
        }

        $order = new WC_Order($order_id);

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

        // save selected card into database as transcient
        set_transient($this->id . '_card_type_' . $order_id, $selected_card);
    }

    /**
     * Order review and payment form page.
     **/
    public function payzen_generate_form($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);

        echo '<div style="opacity: 0.6; padding: 10px; text-align: center; color: #555;    border: 3px solid #aaa; background-color: #fff; cursor: wait; line-height: 32px;">';

        $img_url = WC_PAYZEN_PLUGIN_URL . 'assets/images/loading.gif';
        $img_url = class_exists('WC_HTTPS') ? WC_HTTPS::force_https_url($img_url) : $woocommerce->force_ssl($img_url);
        echo '<img src="' . esc_url($img_url) . '" alt="..." style="float:left; margin-right: 10px;"/>';
        echo __('Please wait, you will be redirected to the payment platform.', 'woo-payzen-payment');
        echo '</div>';
        echo '<br />';
        echo '<p>'.__('If nothing happens in 10 seconds, please click the button below.', 'woo-payzen-payment').'</p>';

        $this->payzen_fill_request($order);

        $form = "\n".'<form action="' . esc_url($this->payzen_request->get('platform_url')) . '" method="post" name="' . $this->id . '_payment_form" target="_top">';
        $form .= "\n".$this->payzen_request->getRequestHtmlFields();
        $form .= "\n".'<input type="submit" class="button-alt" id="submit_' . $this->id . '_payment_form" value="' . sprintf(__('Pay via %s', 'woo-payzen-payment'), 'PayZen').'">';
        $form .= "\n".'<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">'.__('Cancel order &amp; restore cart', 'woo-payzen-payment') . '</a>';
        $form .= "\n".'</form>';

        $form .= "\n".'<script type="text/javascript">';
        $form .= "\nfunction payzen_submit_form() {
                    document.getElementById('submit_" . $this->id . "_payment_form').click();
                  }";
        $form .= "\nif (window.addEventListener) { // for all major browsers, except IE 8 and earlier
                    window.addEventListener('load', payzen_submit_form, false);
                  } else if (window.attachEvent) { // for IE 8 and earlier versions
                    window.attachEvent('onload', payzen_submit_form);
                  }";
        $form .= "\n</script>\n";

        echo $form;
    }

    /**
     * Prepare PayZen form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        global $woocommerce, $wpdb;

        $this->log('Generating payment form for order #' . $this->get_order_property($order, 'id') . '.');

        // get currency
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        if ($currency == null) {
            $this->log('The store currency (' . get_woocommerce_currency() . ') is not supported by PayZen.');

            wp_die(sprintf(__('The store currency (%s) is not supported by %s.'), get_woocommerce_currency(), 'PayZen'));
        }

        // effective used version
        include ABSPATH . WPINC . '/version.php';
        $version = $wp_version . '-' . $woocommerce->version;

        // PayZen params
        $misc_params = array(
            'amount' => $currency->convertAmountToInteger($order->get_total()),
            'contrib' => 'WooCommerce2.x-3.x_1.4.0/' . $version . '/' . PHP_VERSION,
            'currency' => $currency->getNum(),
            'order_id' => $this->get_order_property($order, 'id'),
            'order_info' => $this->get_order_property($order, 'order_key'),
            'order_info2' => 'blog_id=' . $wpdb->blogid, // save blog_id for multisite cases

            // billing address info
            'cust_id' => $this->get_order_property($order, 'user_id'),
            'cust_email' => $this->get_order_property($order, 'billing_email'),
            'cust_first_name' => $this->get_order_property($order, 'billing_first_name'),
            'cust_last_name' => $this->get_order_property($order, 'billing_last_name'),
            'cust_address' => $this->get_order_property($order, 'billing_address_1') . ' ' .  $this->get_order_property($order, 'billing_address_2'),
            'cust_zip' => $this->get_order_property($order, 'billing_postcode'),
            'cust_country' => $this->get_order_property($order, 'billing_country'),
            'cust_phone' => str_replace(array('(', '-', ' ', ')'), '', $this->get_order_property($order, 'billing_phone')),
            'cust_city' => $this->get_order_property($order, 'billing_city'),
            'cust_state' => $this->get_order_property($order, 'billing_state'),

            // shipping address info
            'ship_to_first_name' => $this->get_order_property($order, 'shipping_first_name'),
            'ship_to_last_name' => $this->get_order_property($order, 'shipping_last_name'),
            'ship_to_street' => $this->get_order_property($order, 'shipping_address_1'),
            'ship_to_street2' => $this->get_order_property($order, 'shipping_address_2'),
            'ship_to_city' => $this->get_order_property($order, 'shipping_city'),
            'ship_to_state' => $this->get_order_property($order, 'shipping_state'),
            'ship_to_country' => $this->get_order_property($order, 'shipping_country'),
            'ship_to_zip' => $this->get_order_property($order, 'shipping_postcode'),
            'ship_to_phone_num' => str_replace(array('(', '-', ' ', ')'), '', $this->get_order_property($order, 'shipping_phone')),

            // return URLs
            'url_return' => add_query_arg('wc-api', 'WC_Gateway_Payzen', home_url('/'))
        );
        $this->payzen_request->setFromArray($misc_params);

        // activate 3ds ?
        $threeds_mpi = null;
        if ($this->get_option('3ds_min_amount') != '' && $order->get_total() < $this->get_option('3ds_min_amount')) {
            $threeds_mpi = '2';
        }

        $this->payzen_request->set('threeds_mpi', $threeds_mpi);

        // detect language
        $locale = get_locale() ? substr(get_locale(), 0, 2) : null;
        if ($locale && PayzenApi::isSupportedLanguage($locale)) {
            $this->payzen_request->set('language', $locale);
        } else {
            $this->payzen_request->set('language', $this->get_option('language'));
        }

        // available languages
        $langs = $this->get_option('available_languages');
        if (is_array($langs) && ! in_array('', $langs)) {
            $this->payzen_request->set('available_languages', implode(';', $langs));
        }

        if ($this->id != 'payzenchoozeo') {
            // payment cards
            if ($this->get_option('card_data_mode') == 'MERCHANT') {
                $selected_card = get_transient($this->id . '_card_type_' . $this->get_order_property($order, 'id'));
                $this->payzen_request->set('payment_cards', $selected_card);

                delete_transient($this->id . '_card_type_' . $this->get_order_property($order, 'id'));
            } else {
                $cards = $this->get_option('payment_cards');
                if (is_array($cards) && ! in_array('', $cards)) {
                    $this->payzen_request->set('payment_cards', implode(';', $cards));
                }
            }
        }

        // enable automatic redirection ?
        $this->payzen_request->set('redirect_enabled', ($this->get_option('redirect_enabled') == 'yes') ? true : false);

        // redirection messages
        $success_message = $this->get_option('redirect_success_message');
        $success_message = isset($success_message[get_locale()]) && $success_message[get_locale()] ? $success_message[get_locale()] :
            (is_array($success_message) ? $success_message['en_US'] : $success_message);
        $this->payzen_request->set('redirect_success_message', $success_message);

        $error_message = $this->get_option('redirect_error_message');
        $error_message = isset($error_message[get_locale()]) && $error_message[get_locale()] ? $error_message[get_locale()] :
            (is_array($error_message) ? $error_message['en_US'] : $error_message);
        $this->payzen_request->set('redirect_error_message', $error_message);

        // other configuration params
        $config_keys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url', 'capture_delay', 'validation_mode',
            'redirect_success_timeout', 'redirect_error_timeout', 'return_mode'
        );

        foreach ($config_keys as $key) {
            $this->payzen_request->set($key, $this->get_option($key));
        }

        $this->log('Data to be sent to payment platform : ' . print_r($this->payzen_request->getRequestFieldsArray(true /* to hide sensitive data */), true));
    }
}
