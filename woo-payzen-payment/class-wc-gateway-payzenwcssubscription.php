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

class WC_Gateway_PayzenWcsSubscription extends WC_Gateway_PayzenStd
{
    const SUBSCRIPTIONS_HANDLER = 'wc-subscriptions';
    protected $subscriptions_handler;

    public function __construct()
    {
        $this->id = 'payzenwcssubscription';
        $this->icon = apply_filters('woocommerce_' . $this->id . '_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/payzen.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('Subscription payment with WooCommerce Subscriptions', 'woo-payzen-payment');
        $this->method_description = __('Subscriptions managed by WooCommerce Subscriptions', 'woo-payzen-payment')
            . ' <b>(' . __('Recommended', 'woo-payzen-payment') . ')</b>';

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

        $this->subscriptions_handler = Payzen_Subscriptions_Loader::getInstance(self::SUBSCRIPTIONS_HANDLER);

        $this->supports = array(
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
            'subscription_payment_method_delayed_change',
            'refunds',
            'tokenization'
        );

        if ($this->payzen_is_section_loaded()) {
            // Reset subscription payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // Update subscription payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_script'));
        }

        // Generate subscription payment form action.
        add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

        // Payment method title filter.
        add_filter('woocommerce_title_' . $this->id, array($this, 'get_title'));

        // Payment method description filter.
        add_filter('woocommerce_description_' . $this->id, array($this, 'get_description'));

        // Payment method availability filter.
        add_filter('woocommerce_available_' . $this->id, array($this, 'is_available'));

        // Generate payment fields filter.
        add_filter('woocommerce_payzen_payment_fields_' . $this->id, array($this, 'get_payment_fields'));

        // Order needs payment filter.
        add_filter('woocommerce_order_needs_payment', array($this, 'payzen_order_needs_payment'), 10, 2);

        // Save payment identifier in subscription metadata.
        add_filter('woocommerce_subscription_payment_meta', array($this, 'subscription_payment_meta'), 10, 2);

        // Process subscription renewal through silent payment.
        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'process_subscription_payment'), 10, 2);

        // Return from REST payment action.
        add_action('woocommerce_api_wc_gateway_' . $this->id . '_rest', array($this, 'payzen_rest_return_response'));

        // Notification from REST payment action.
        add_action('woocommerce_api_wc_gateway_payzen_notify_rest', array($this, 'payzen_rest_notify_response'));

        // Rest payment generate temporary token.
        add_action('woocommerce_api_wc_gateway_' . $this->id . '_temporary_form_token', array($this, 'payzen_refresh_temporary_token'));

        // Rest payment generate token.
        add_action('woocommerce_api_wc_gateway_' . $this->id . '_form_token', array($this, 'payzen_refresh_form_token'));

        // Display buyer wallet on "Payment methods" menu.
        add_action('woocommerce_after_account_payment_methods', array($this, 'payment_fields'), 10, 2);

        // Adding JS to load REST libs.
        add_action('wp_head', array($this, 'payzen_rest_head_script'));
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['card_data_mode']);
        unset($this->form_fields['payment_by_token']);
        unset($this->form_fields['use_customer_wallet']);

        // By default, disable subscription payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables payment by Subscription.', 'woo-payzen-payment');
    }

    /**
    * Init settings for gateways.
    */
    public function init_settings() {
        global $payzen_plugin_features;

        parent::init_settings();
        if ($payzen_plugin_features['smartform']) {
            $this->set_smartform_params();
        }
    }

    private function set_smartform_params()
    {
        if (! PayzenTools::is_embedded_payment(true)) {
            $this->settings['card_data_mode'] = 'SMARTFORM';
            $this->settings['rest_popin'] = 'no';
            $this->settings['rest_theme'] = 'neon';
            $this->settings['smartform_compact_mode'] = 'no;';
            $this->settings['rest_register_card_label'] = '';
            $this->settings['rest_attempts'] = '';
            $this->settings['rest_placeholder'] = array(
                'pan' => '',
                'expiry' => '',
                'cvv' => ''
            );

            return;
        }

        $std_settings = get_option('woocommerce_payzenstd_settings', null);

        $this->settings['card_data_mode'] = $std_settings['card_data_mode'];
        $this->settings['rest_popin'] = $std_settings['rest_popin'];
        $this->settings['rest_theme'] = $std_settings['rest_theme'];
        $this->settings['smartform_compact_mode'] = $std_settings['smartform_compact_mode'];
        $this->settings['rest_register_card_label'] = $std_settings['rest_register_card_label'];
        $this->settings['rest_attempts'] = $std_settings['rest_attempts'];
        $this->settings['rest_placeholder'] = $std_settings['rest_placeholder'];
    }

    protected function get_rest_fields()
    {
        // Do not display REST API configuration fields for this payment method.
    }

    protected function is_available_for_subscriptions()
    {
        global $woocommerce;

        if (! $this->subscriptions_handler) {
            return false;
        }

        // In case of changing payment method of an existing subscription.
        // At this stage all conditions of is_available_for_subscriptions are guaranteed so we return true.
        if ($this->subscriptions_handler->is_subscription_update()) {
            return true;
        }

        $cust_id = self::get_customer_property($woocommerce->customer, 'id');

        // Allow subscription when no client is connected and "Allow customers to create an account during checkout" is enabled.
        if (! $cust_id && (get_option('woocommerce_enable_signup_and_login_from_checkout') !== 'yes')
            && (get_option('woocommerce_enable_signup_from_checkout_for_subscriptions') !== 'yes')) {
            return false;
        }

        if (! $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart)) {
            return false;
        }

        // Clear all response messages.
        $this->clear_notices();

        return true;
    }

    public function payment_fields()
    {
        if ($this->subscriptions_handler && $this->subscriptions_handler->is_subscription_update()) {
            $order_id = get_query_var('order-pay');
            $order = new WC_Order((int) $order_id);
            $method = self::get_order_property($order, 'payment_method');

            if ($this->use_wallet()) {
                set_transient($this->id . '_change_payment_' . $order_id, $method . '_old_pm');
            } else {
                echo '<input type="hidden" id="' . $this->id . '_old_pm" name="' . $this->id . '_old_pm" value="' . $method . '">';
            }
        }

        parent::payment_fields();
    }

    protected function payment_by_alias_view($html, $force_redir = true)
    {
        global $woocommerce;

        $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        $saved_subsc_masked_pan = get_user_meta((int) $cust_id, $this->id . '_masked_pan', true);

        // Recover card brand if saved with masked pan and check if logo exists.
        $card_brand = '';
        $card_brand_logo = '';
        if (strpos($saved_subsc_masked_pan, '|')) {
            $card_brand = substr($saved_subsc_masked_pan, 0, strpos($saved_subsc_masked_pan, '|'));
            $remote_logo = self::LOGO_URL . strtolower($card_brand) . '.png';
            if ($card_brand) {
                $card_brand_logo = '<img src="' . $remote_logo . '"
                       alt="' . $card_brand . '"
                       title="' . $card_brand . '"
                       style="vertical-align: middle; margin: 0 10px 0 5px; max-height: 20px; display: unset;">';
            }
        }

        $saved_subsc_masked_pan = $card_brand_logo ? $card_brand_logo . '<b style="vertical-align: middle;">' . substr($saved_subsc_masked_pan, strpos($saved_subsc_masked_pan, '|') + 1) . '</b>'
            : ' <b>' . str_replace('|',' ', $saved_subsc_masked_pan) . '</b>';
        $saved_masked_pan = str_replace('X', '', $saved_masked_pan);

        return '<div id="' . $this->id . '_payment_by_token_description">
                  <ul>
                      <li style="list-style-type: none;">
                          <span>' .
                              sprintf(__('You will pay with your stored means of payment %s', 'woo-payzen-payment'), $saved_subsc_masked_pan)
                              . ' (<a href="' . esc_url(wc_get_account_endpoint_url('payment-methods')) . '">' . __('manage your payment means', 'woo-payzen-payment') . '</a>).
                          </span>
                      </li>
                  </ul>
              </div>';
    }

    protected function can_use_alias($cust_id, $verify_identifier = false)
    {
        global $woocommerce;

        if (! $cust_id) {
            return false;
        }

        $amount = $woocommerce->cart ? $woocommerce->cart->total : 0;
        if (($amount <= 0) && (! $verify_identifier || (! empty($_GET['wc-ajax']) && $this->check_identifier($cust_id, $this->id)))) {
            return true;
        }

        return false;
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        parent::payzen_fill_request($order);

        $order_id = wcs_get_objects_property($order, 'id');

        $old_payment_method = get_transient($this->id . '_change_payment_' . $order_id);
        $is_payment_change = $old_payment_method ? true : false;
        delete_transient($this->id .'_change_payment_' . $order_id);

        if ($is_payment_change) {
            // Called from change payment action.
            $this->payzen_request->set('amount', 0);
            $this->payzen_request->addExtInfo('subsc_id', $order_id);
        }

        $cust_id = self::get_order_property($order, 'user_id');

        $saved_identifier = $this->get_cust_identifier($cust_id);
        $is_identifier_active = $this->is_cust_identifier_active($cust_id);

        if ($saved_identifier && $is_identifier_active) {
            $this->payzen_request->set('identifier', $saved_identifier);
            $action = ($this->payzen_request->get('amount') == 0) ? 'REGISTER_UPDATE' : 'REGISTER_UPDATE_PAY';
        } else {
            $action = ($this->payzen_request->get('amount') == 0) ? 'REGISTER' : 'REGISTER_PAY';
        }

        $this->payzen_request->set('page_action', $action);
        $this->payzen_request->addExtInfo('wcs_scheduled', true);

        if (isset($_POST['update_all_subscriptions_payment_method']) && $_POST['update_all_subscriptions_payment_method']) {
            $this->payzen_request->addExtInfo('update_identifier_all', true);
        }
    }

    public function payzen_order_needs_payment($is_active, $order)
    {
        global $woocommerce;

        if (($order->get_total() == 0) && (self::get_order_property($order, 'payment_method') === $this->id)) {
            return $this->subscriptions_handler && $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart);
        }

        return $is_active;
    }

    /**
     * Admin panel options.
     */
    public function admin_options()
    {
        if (PayzenTools::is_plugin_not_active('woocommerce-subscriptions/woocommerce-subscriptions.php') === 'true') {
            echo '<div class="inline error"><p><strong>' . sprintf(__('Warning! %s plugin must be installed and activated for the subscription payment method to work.', 'woo-payzen-payment'), 'WooCommerce Subscriptions') . '</strong></p></div>';
        }

        parent::admin_options();
    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        if (isset($_POST[$this->id . '_old_pm'])) {
            set_transient($this->id . '_change_payment_' . $order_id, $_POST[$this->id . '_old_pm']);
        }

        return parent::process_payment($order_id);
    }

    public function process_subscription_payment($renewal_total, WC_Order $renewal_order)
    {
        $subscriptionHelper = new PayzenSubscriptionTools($this->id);

        return $subscriptionHelper->process_subscription_payment($renewal_total, $renewal_order);
    }

    /**
     * @param array           $payment_meta
     * @param WC_Subscription $subscription
     */
    public function subscription_payment_meta($payment_meta, $subscription)
    {
        $saved_meta = $subscription->get_meta('payzen_token');

        if (! $saved_meta) {
            // If customer has no saved payment meta, use their identifier if it exists.
            $cust_id = self::get_order_property($subscription, 'user_id');
            $identifier = $this->get_cust_identifier($cust_id);
            $saved_meta = $identifier;
        }

        $payment_meta[$this->id] = array(
            'post_meta' => array(
                'payzen_token' => array(
                    'value' => $saved_meta,
                    'label' => sprintf(__('%s token', 'woo-payzen-payment'), self::GATEWAY_NAME)
                )
            )
        );

        return $payment_meta;
    }

    public function process_admin_options()
    {
        parent::process_admin_options();

        $subscription_settings = get_option('woocommerce_payzensubscription_settings', null);
        $subscription_enabled = is_array($subscription_settings) && isset($subscription_settings['enabled']) && ($subscription_settings['enabled'] == 'yes');

        if (! $subscription_enabled) {
            return;
        }

        $settings = get_option('woocommerce_' . $this->id . '_settings', null);
        $enabled = is_array($settings) && isset($settings['enabled']) && ($settings['enabled'] == 'yes');

        if (! $enabled) {
            return;
        }

        WC_Admin_Settings::add_error(sprintf(__('This method cannot be enabled. You have to disable "%s" method first.', 'woo-payzen-payment'),
            self::GATEWAY_NAME . ' - ' . __('Subscription payment', 'woo-payzen-payment')));

        $settings['enabled'] = 'no';
        update_option('woocommerce_' . $this->id . '_settings', $settings);
    }

    public function payzen_init()
    {
        parent::payzen_init();

        if ($this->payzen_is_section_loaded()) {
            return;
        }

        if (isset($_POST['action']) && ($_POST['action'] == 'woocommerce_toggle_gateway_enabled') && isset($_POST['gateway_id']) && ($_POST['gateway_id'] == $this->id)) {
            $subscription_settings = get_option('woocommerce_payzensubscription_settings', null);
            $subscription_enabled = is_array($subscription_settings) && isset($subscription_settings['enabled']) && ($subscription_settings['enabled'] == 'yes');

            if (! $subscription_enabled) {
                return;
            }

            $settings = get_option('woocommerce_' . $this->id . '_settings', null);
            $was_enabled = is_array($settings) && isset($settings['enabled']) && ($settings['enabled'] == 'yes');

            if ($was_enabled) {
                return;
            }

            $settings['enabled'] = 'yes';
            update_option('woocommerce_' . $this->id . '_settings', $settings);
        }
    }

    public function use_wallet($cust_id = null)
    {
        global $woocommerce;

        if (! $this->is_embedded_payment(false)) {
            return false;
        }

        if (! $cust_id) {
            $cust_id = self::get_customer_property($woocommerce->customer, 'id');
        }

        return ! is_null($cust_id);
    }

    public function is_embedded_payment($only_smartform = true)
    {
        $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');
        if (! $key) {
            return false;
        }

        $modes = array('SMARTFORM', 'SMARTFORMEXT', 'SMARTFORMEXTNOLOGOS');
        return in_array($this->get_option('card_data_mode'), $modes);
    }
}