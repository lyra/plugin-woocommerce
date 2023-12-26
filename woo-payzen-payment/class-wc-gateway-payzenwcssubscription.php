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
            'subscription_payment_method_delayed_change'
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

        // By default, disable subscription payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables payment by Subscription.', 'woo-payzen-payment');
    }

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
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
        parent::payment_fields();

        if ($this->subscriptions_handler && $this->subscriptions_handler->is_subscription_update()) {
            $order_id = get_query_var('order-pay');
            $order = new WC_Order((int) $order_id);
            $method = self::get_order_property($order, 'payment_method');
            echo '<input type="hidden" id="' . $this->id . '_old_pm" name="' . $this->id . '_old_pm" value="' . $method . '">';
        }
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

        return '<div id="' . $this->id . '_payment_by_token_description">
                  <ul>
                      <li style="list-style-type: none;">
                          <span>' .
                              sprintf(__('You will pay with your stored means of payment %s', 'woo-payzen-payment'), $saved_subsc_masked_pan)
                              . ' (<a href="' . esc_url(wc_get_account_endpoint_url($this->get_option('woocommerce_saved_cards_endpoint', 'ly_saved_cards'))) . '">' . __('manage your payment means', 'woo-payzen-payment') . '</a>).
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

        $cust_id = self::get_order_property($order, 'user_id');
        $saved_identifier = $this->get_cust_identifier($cust_id);
        $is_identifier_active = $this->is_cust_identifier_active($cust_id);

        $order_id = wcs_get_objects_property($order, 'id');
        $old_payment_method = get_transient($this->id . '_change_payment_' . $order_id);
        $is_payment_change = $old_payment_method ? true : false;
        delete_transient($this->id .'_change_payment_' . $order_id);

        if ($is_payment_change) {
            // Called from change payment action.
            $this->payzen_request->set('amount', 0);
            $this->payzen_request->addExtInfo('subsc_id', $order_id);
        }

        if ($saved_identifier && $is_identifier_active) {
            $this->payzen_request->set('identifier', $saved_identifier);
            $action = ($this->payzen_request->get('amount') == 0) ? 'REGISTER_UPDATE' : 'REGISTER_UPDATE_PAY';
        } else {
            $action = ($this->payzen_request->get('amount') == 0) ? 'REGISTER' : 'REGISTER_PAY';
        }

        $this->payzen_request->set('page_action', $action);

        // Payment schedule is managed by WooCommerce Subscriptions.
        $this->payzen_request->addExtInfo('wcs_scheduled', true);
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

        $order = new WC_Order($order_id);

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

    public function process_subscription_payment($renewal_total, WC_Order $renewal_order)
    {
        if (! $renewal_order) {
            $this->log('Could not load renewal order or process renewal payment.');
            return;
        }

        $renewal_order_id = $renewal_order->get_id();

        $subscriptions = wcs_get_subscriptions_for_order($renewal_order, array('order_type' => array('renewal')));
        $subscription = reset($subscriptions); // Get first subscription.
        if (! $subscription) {
            $this->log("No subscription found for renewal order #{$renewal_order_id}.");
            return;
        }

        $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');
        if (! $key) {
            $error_msg = __('Private key is not configured. Subscription renewal cannot be processed.', 'woo-payzen-payment');
            $this->set_error($renewal_order, $subscription, $error_msg);

            $this->log("Error while processing renewal payment for subscription #{$subscription->get_id()}, renewal order #{$renewal_order_id}: private key is not configured.");
            return;
        }

        $cust_id = self::get_order_property($renewal_order, 'user_id');
        $saved_identifier = $this->get_identifier($cust_id, $subscription);
        if (! $saved_identifier) {
            $this->log("Customer #{$cust_id} has no valid identifier. Renewal order #{$renewal_order_id} for subscription #{$subscription->get_id()} cannot be processed.");

            $error_msg = __('Customer has no valid identifier. Subscription renewal cannot be processed.', 'woo-payzen-payment');
            $this->set_error($renewal_order, $subscription, $error_msg);

            return;
        }

        if (wcs_get_objects_property($subscription, 'payment_method') !== $this->id) {
            // Update payment method in subscription.
            if (method_exists($subscription, 'set_payment_method')) {
                $subscription->set_payment_method($this);
            } else {
                $subscription->payment_method = $this->id;
                $subscription->payment_method_title = $this->get_title();
            }

            $subscription->save();
        }

        // Allow developers to hook into the subscription renewal payment before it processed.
        do_action($this->id . '_before_renewal_payment_created', $renewal_order);

        $this->log("Start processing renewal payment for subscription #{$subscription->get_id()}, renewal order #{$renewal_order_id}.");

        $payment_result = $this->silent_payment($renewal_total, $renewal_order, $saved_identifier);

        if (! $payment_result) {
            $error_msg = sprintf(__('An error has occurred during the renewal of the subscription. Please consult the %s logs for more details.', 'woo-payzen-payment'), 'PayZen');
            $this->set_error($renewal_order, $subscription, $error_msg);

            return;
        }

        $data = PayzenRestTools::convertRestResult($payment_result);
        $response = new PayzenResponse($data, null, '', '');

        $this->update_renewal_order($renewal_order, $response);
    }

    public function update_renewal_order($renewal_order, $response)
    {
        $subscriptions = wcs_get_subscriptions_for_order($renewal_order, array('order_type' => array('renewal')));
        $subscription = reset($subscriptions); // Get first subscription.
        $renewal_order_id = $renewal_order->get_id();

        if (! $renewal_order->has_status('pending')) {
            $this->log("Renewal order #{$renewal_order_id} is not in pending status: order status cannot be updated.");
            return;
        }

        try {
            $order_note = sprintf(_x('Processed renewal payment for order #%s.', 'used in order note', 'woo-payzen-payment'), $renewal_order_id);
            $subscription->add_order_note($order_note);

            WC_Gateway_Payzen::payzen_add_order_note($response, $renewal_order);

            if (WC_Gateway_Payzen::is_successful_action($response)) {
                // Payment completed.
                $renewal_order->payment_complete();
                $this->log("Payment successful, renewal order #$renewal_order_id completed.");
            } else {
                // Payment failed or pending.
                $renewal_order->update_status('failed');
                $this->log("Payment failed for renewal order #$renewal_order_id.");
            }
        } catch (Exception $e) {
            $this->log("An error occurred while processing #{$renewal_order_id} : {$e->getMessage()}.");

            $order_note = sprintf(__("Error while processing order: %s", 'woo-payzen-payment'), $e->getMessage());
            $renewal_order->update_status('pending', $order_note);

            return;
        }
    }

    protected function silent_payment($renewal_total, WC_Order $renewal_order, $saved_identifier)
    {
        global $wpdb;

        $order_id = $renewal_order->get_id();
        $currency = PayzenApi::findCurrencyByAlphaCode($renewal_order->get_currency());

        $subscriptions = wcs_get_subscriptions_for_order($renewal_order, array('order_type' => array('renewal')));
        $subscription = reset($subscriptions); // Get first subscription.

        $this->payzen_fill_request($renewal_order);

        $params = array(
            'orderId' => $order_id,
            'customer' => array(
                'email' => self::get_order_property($renewal_order, 'billing_email'),
                'reference' => self::get_order_property($renewal_order, 'user_id'),
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
                    'paymentSource' => 'EC'
                )
            ),
            'contrib' => $this->get_escaped_var($this->payzen_request, 'contrib'),
            'amount' => $currency->convertAmountToInteger($renewal_total),
            'currency' => $currency->getAlpha3(),
            'formAction' => 'SILENT',
            'paymentMethodToken' => $saved_identifier,
            'metadata' => array(
                'order_key' => self::get_order_property($renewal_order, 'order_key'),
                'blog_id' => $wpdb->blogid,
                'wcs_renewal_order' => 'TRUE',
                'parent_order_id' => $subscription->get_parent_id(),
                'subsc_id' => $subscription->get_id()
            )
        );

        $validationMode = $this->get_escaped_var($this->payzen_request, 'validation_mode');

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

        $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');

        try {
            $client = new PayzenRest(
                $this->get_general_option('rest_url'),
                $this->get_general_option('site_id'),
                $key
            );

            $result = $client->post('V4/Charge/CreatePayment', json_encode($params));

            if ($result['status'] != 'SUCCESS') {
                $this->log("Error while processing renewal payment for subscription #{$subscription->get_id()}, renewal order #{$order_id} : " . $result['answer']['errorMessage']
                    . ' (' . $result['answer']['errorCode'] . ').');

                if (isset($result['answer']['detailedErrorMessage']) && ! empty($result['answer']['detailedErrorMessage'])) {
                    $this->log('Detailed message: ' . $result['answer']['detailedErrorMessage'] . ' (' . $result['answer']['detailedErrorCode'] . ').');
                }

                $return = false;
            } else {
                // Payment form token created successfully.
                $this->log("Renewal payment successfully processed for subscription #{$subscription->get_id()}, renewal order #{$order_id}.");
                $return = $result['answer'];
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
            $return = false;
        }

        return $return;
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

    private function get_identifier($cust_id, $subscription)
    {
        $saved_identifier = $this->get_cust_identifier($cust_id);
        if (! $saved_identifier) {
            $saved_identifier = $subscription->get_meta('payzen_token');
        }

        if ($this->check_identifier($cust_id, $this->id, $saved_identifier)) {
            return $saved_identifier;
        }

        // Get default payment token for the customer.
        $default_token = WC_Payment_Tokens::get_customer_default_token($cust_id) ? WC_Payment_Tokens::get_customer_default_token($cust_id)->get_token() : null;

        if ($this->check_identifier($cust_id, $this->id, $default_token)) {
            return $default_token;
        }

        return null;
    }

    protected function set_error($order, $subscription, $error_msg)
    {
        $order->add_order_note($error_msg);
        $subscription->add_order_note($error_msg);

        if (is_admin()) { // Show error message only if it's made on backend.
            set_transient('payzen_renewal_error_msg', $error_msg);
        }
    }

    public function process_admin_options() {
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
}
