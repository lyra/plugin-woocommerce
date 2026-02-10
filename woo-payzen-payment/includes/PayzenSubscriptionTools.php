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

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Form\Response as PayzenResponse;
use Lyranetwork\Payzen\Sdk\Rest\Api as PayzenRest;

class PayzenSubscriptionTools
{
    protected $logger;
    protected $method_id;

    public function __construct($method_id)
    {
        $this->method_id = $method_id;
        $this->logger = new WC_Logger();
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

        $key = $this->get_key();
        if (! $key) {
            $error_msg = __('Private key is not configured. Subscription renewal cannot be processed.', 'woo-payzen-payment');
            $this->set_error($renewal_order, $subscription, $error_msg);

            $this->log("Error while processing renewal payment for subscription #{$subscription->get_id()}, renewal order #{$renewal_order_id}: private key is not configured.");

            $renewal_order->update_status('failed');

            return;
        }

        $cust_id = WC_Gateway_Payzen::get_order_property($renewal_order, 'user_id');
        $saved_identifier = $this->get_identifier($cust_id, $subscription);
        if (! $saved_identifier) {
            $this->log("Customer #{$cust_id} has no valid identifier. Renewal order #{$renewal_order_id} for subscription #{$subscription->get_id()} cannot be processed.");

            $error_msg = __('Customer has no valid identifier. Subscription renewal cannot be processed.', 'woo-payzen-payment');
            $this->set_error($renewal_order, $subscription, $error_msg);

            $renewal_order->update_status('failed');

            return;
        }

        if (wcs_get_objects_property($subscription, 'payment_method') !== $this->method_id) {
            // Update payment method in subscription.
            if (method_exists($subscription, 'set_payment_method')) {
                $subscription->set_payment_method($this);
            } else {
                $subscription->payment_method = $this->method_id;
                $subscription->payment_method_title = $this->get_title();
            }

            $subscription->save();
        }

        // Allow developers to hook into the subscription renewal payment before it processed.
        do_action($this->method_id . '_before_renewal_payment_created', $renewal_order);

        $this->log("Start processing renewal payment for subscription #{$subscription->get_id()}, renewal order #{$renewal_order_id}.");

        $payment_result = $this->silent_payment($renewal_total, $renewal_order, $saved_identifier);

        if (! $payment_result) {
            $error_msg = sprintf(__('An error has occurred during the renewal of the subscription. Please consult the %s logs for more details.', 'woo-payzen-payment'), 'PayZen');
            $this->set_error($renewal_order, $subscription, $error_msg);

            $renewal_order->update_status('failed');

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

        $params = array(
            'orderId' => $order_id,
            'customer' => array(
                'email' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_email'),
                'reference' => WC_Gateway_Payzen::get_order_property($renewal_order, 'user_id'),
                'billingDetails' => array(
                    'firstName' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_first_name'),
                    'lastName' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_last_name'),
                    'address' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_address_1') . ' ' . WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_address_2'),
                    'zipCode' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_postcode'),
                    'city' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_city'),
                    'state' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_state'),
                    'phoneNumber' => str_replace(array('(', '-', ' ', ')'), '', WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_phone')),
                    'country' => WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_country'),
                    'identityCode' => $renewal_order->get_meta('_billing_persontype') == '2' ? $renewal_order->get_meta('_billing_cnpj') : $renewal_order->get_meta('_billing_cpf'),
                    'streetNumber' => $renewal_order->get_meta('_billing_number'),
                    'district' => $renewal_order->get_meta('_billing_neighborhood') ? $renewal_order->get_meta('_billing_neighborhood') : WC_Gateway_Payzen::get_order_property($renewal_order, 'billing_city'),
                    'status' =>  $renewal_order->get_meta('_billing_persontype') ? ($renewal_order->get_meta('_billing_persontype') == '2' ? 'COMPANY' : 'PRIVATE') : ''
                ),
                'shippingDetails' => array(
                    'firstName' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_first_name'),
                    'lastName' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_last_name'),
                    'address' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_address_1'),
                    'address2' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_address_2'),
                    'zipCode' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_postcode'),
                    'city' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_city'),
                    'state' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_state'),
                    'country' => WC_Gateway_Payzen::get_order_property($renewal_order, 'shipping_country')
                )
            ),
            'transactionOptions' => array(
                'cardOptions' => array(
                    'paymentSource' => 'EC'
                )
            ),
            'contrib' => PayzenTools::get_contrib(),
            'amount' => $currency->convertAmountToInteger($renewal_total),
            'currency' => $currency->getAlpha3(),
            'formAction' => 'SILENT',
            'paymentMethodToken' => $saved_identifier,
            'metadata' => array(
                'order_key' => WC_Gateway_Payzen::get_order_property($renewal_order, 'order_key'),
                'blog_id' => $wpdb->blogid,
                'wcs_renewal_order' => 'TRUE',
                'parent_order_id' => $subscription->get_parent_id(),
                'subsc_id' => $subscription->get_id()
            )
        );

        $validationMode = null;

        if (get_option('woocommerce_'. $this->method_id. '_settings')['validation_mode'] !== '-1') {
            $validationMode = get_option('woocommerce_'. $this->method_id. '_settings')['validation_mode'];
        }

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

        try {
            $client  = $this->get_rest_client();
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
            $cust_id = WC_Gateway_Payzen::get_order_property($subscription, 'user_id');
            $identifier = $this->get_cust_identifier($cust_id);
            $saved_meta = $identifier;
        }

        $payment_meta[$this->method_id] = array(
            'post_meta' => array(
                'payzen_token' => array(
                    'value' => $saved_meta,
                    'label' => sprintf(__('%s token', 'woo-payzen-payment'), WC_Gateway_Payzen::GATEWAY_NAME)
                )
            )
        );

        return $payment_meta;
    }

    private function get_identifier($cust_id, $subscription)
    {
        $saved_identifier = $subscription->get_meta('payzen_token');
        if (! $saved_identifier) {
            $saved_identifier = $this->get_cust_identifier($cust_id);
        }

        if ($saved_identifier && $this->check_identifier($cust_id, $saved_identifier)) {
            return $saved_identifier;
        }

        // Get default payment token for the customer.
        $default_token = WC_Payment_Tokens::get_customer_default_token($cust_id) ? WC_Payment_Tokens::get_customer_default_token($cust_id)->get_token() : null;
        if ($this->check_identifier($cust_id, $default_token)) {
            return $default_token;
        }

        return null;
    }

    private function get_key() {
        $general_settings = get_option('woocommerce_payzen_settings', null);

        $testmode = is_array($general_settings) && isset($general_settings['ctx_mode']) && ($general_settings['ctx_mode'] == 'TEST');
        $test_private_key = is_array($general_settings) && isset($general_settings['test_private_key'])  ? $general_settings['test_private_key'] : null;
        $prod_private_key = is_array($general_settings) && isset($general_settings['prod_private_key']) ? $general_settings['prod_private_key'] : null;

        return ($testmode) ? $test_private_key : $prod_private_key;
    }

    private function get_cust_identifier($cust_id)
    {
        $saved_identifier = get_user_meta((int) $cust_id, $this->method_id . '_identifier', true);
        $saved_identifier_decode = json_decode($saved_identifier, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $saved_identifier_decode['identifier'];
        }

        return $saved_identifier;
    }

    private function get_rest_client() {
        $general_settings = get_option('woocommerce_payzen_settings', null);
        $rest_url = is_array($general_settings) && isset($general_settings['rest_url'])  ? $general_settings['rest_url'] : WC_Gateway_Payzen::REST_URL;
        $site_id = is_array($general_settings) && isset($general_settings['site_id'])  ? $general_settings['site_id'] : null;

        return new PayzenRest($rest_url, $site_id, $this->get_key());
    }

    protected function check_identifier($cust_id, $identifier = null)
    {
        if (! $identifier) { // Customer has no saved identifier.
            return false;
        }

        $customer = new WC_Customer($cust_id);
        if (! $customer) {
            return false;
        }

        try {
            $request_data = array(
                'paymentMethodToken' => $identifier
            );

            // Perform REST request to check identifier.
            $client  = $this->get_rest_client();
            $result = $client->post('V4/Token/Get', json_encode($request_data));
            PayzenRestTools::checkResult($result);

            $cancellation_date = PayzenRestTools::getProperty($result['answer'], 'cancellationDate');
            if ($cancellation_date && (strtotime($cancellation_date) <= time())) {
                $this->log("Identifier for customer {$customer->get_billing_email()}, for {$this->method_id} submodule, is expired on payment gateway in date of: {$cancellation_date}.");

                return false;
            }

            return true;
        } catch (Exception $e) {
            $invalid_ident_codes = array('PSP_030', 'PSP_031', 'PSP_561', 'PSP_607', 'INT_905');
            if (in_array($e->getCode(), $invalid_ident_codes, true)) {
                // The identifier is invalid or doesn't exist.
                $this->log("Identifier for customer {$customer->get_billing_email()}, for {$this->method_id} submodule, is invalid or doesn't exist: {$e->getMessage()}");

                return false;
            }

            $this->log("Identifier for customer {$customer->get_billing_email()}, for " . $this->method_id . " submodule, couldn't be verified on gateway: {$e->getMessage()}.");

            return true;
        }
    }

    protected function set_error($order, $subscription, $error_msg)
    {
        $order->add_order_note($error_msg);
        $subscription->add_order_note($error_msg);

        if (is_admin()) { // Show error message only if it's made on backend.
            set_transient('payzen_renewal_error_msg', $error_msg);
        }
    }

    public function log($message)
    {
        $general_settings = get_option('woocommerce_payzen_settings', null);
        $debug = is_array($general_settings) && isset($general_settings['debug']) && ($general_settings['debug'] == 'yes');

        if (! $debug) {
            return;
        }

        $this->logger->add('payzen', $message);
    }
}
