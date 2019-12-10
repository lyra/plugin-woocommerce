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

class WC_Gateway_PayzenSubscription extends WC_Gateway_PayzenStd
{
    const SUBSCRIPTIONS_HANDLER = 'disabled';

    protected $subscriptions_handler;

    public function __construct()
    {
        $this->id = 'payzensubscription';
        $this->icon = apply_filters('woocommerce_payzensubscription_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/payzen.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME. ' - ' . __('Subscription payment', 'woo-payzen-payment');

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

        // Use the selected susbscriptions handler.
        $handler = $this->get_option('subscriptions') ? $this->get_option('subscriptions') : self::SUBSCRIPTIONS_HANDLER;
        $this->subscriptions_handler = Payzen_Subscriptions_Loader::getInstance($handler);

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

        // Iframe payment endpoint action.
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'payzen_generate_iframe_form'));

        // Order needs payment filter.
        add_filter('woocommerce_order_needs_payment', array($this, 'payzen_order_needs_payment'), 10, 2);
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        //unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['payment_by_token']);
        if (isset($this->form_fields['card_data_mode']['options']['REST'])) {
            unset($this->form_fields['card_data_mode']['options']['REST']);
        }

        // By default, disable Subscription payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables payment by Subscription.', 'woo-payzen-payment');

        // Add subscription payment fields
        $this->form_fields['subscriptions'] = array(
            'title' => __('Subscriptions management', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => 'disabled',
            'options' => array(
                'disabled' => __('Disabled', 'woo-payzen-payment'),
                'subscriptio' => __('Subscriptio', 'woo-payzen-payment'),
                'custom' => __('Custom', 'woo-payzen-payment')
            ),
            'description' => __('If you buy subscriptions on your site, choose the solution you use to manage them. At this time only Subscriptio plugin is supported. If you choose "Custom", your developper may develop a subscriptions adapter for our plugin.', 'woo-payzen-payment'),
            'class' => 'wc-enhanced-select'
        );
    }

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
    }

    protected function is_available_for_subscriptions()
    {
        global $woocommerce;

        $cust_id = $this->get_customer_property($woocommerce->customer, 'id');
        if (! $cust_id) {
            return false;
        }

        if ($this->subscriptions_handler && ($this->subscriptions_handler->cart_contains_multiple_subscriptions($woocommerce->cart) || ! $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart))) {
            return false;
        }

        return true;
    }

    protected function display_payment_by_alias_interface($cust_id)
    {
        $saved_subsc_masked_pan = get_user_meta((int) $cust_id, $this->id . '_masked_pan', true);

        echo '<div id="' . $this->id . '_payment_by_token_description">
                  <ul>
                      <li>
                          <span>' . sprintf(__('You will pay with your registered means of payment %s. No data entry is needed.', 'woo-payzen-payment'), '<b>' . $saved_subsc_masked_pan . '</b>') . '</span>
                      </li>
                  </ul>
              </div>';
    }

    protected function can_use_alias($cust_id)
    {
        global $woocommerce;

        if (! $cust_id) {
            return false;
        }

        $amount = $woocommerce->cart->total;
        if ($amount <= 0) {
            return true;
        }

        return false;
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        global $woocommerce;

        parent::payzen_fill_request($order);

        if ($this->subscriptions_handler && $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart)
            && ($info = $this->subscriptions_handler->subscription_info($order))) {

            $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
            $cust_id = $this->get_order_property($order, 'user_id');

            $this->payzen_request->set('sub_amount', $currency->convertAmountToInteger($info['amount']));
            $this->payzen_request->set('sub_currency', $currency->getNum()); // Same as general order currency.

            $desc = 'RRULE:FREQ=' . $info['frequency']. ';INTERVAL=' . $info['interval'];
            if (isset($info['end_date']) && $info['end_date']) {
                $desc .= ';UNTIL=' . $info['end_date'];
            }

            $this->payzen_request->set('sub_desc', $desc);

            $this->payzen_request->set('sub_effect_date', $info['effect_date']);

            // Initial amount.
            if (isset($info['init_amount']) && $info['init_amount'] && isset($info['init_number']) && $info['init_number']) {
                $this->payzen_request->set('sub_init_amount', $currency->convertAmountToInteger($info['init_amount']));
                $this->payzen_request->set('sub_init_amount_number', $info['init_number']);
            }

            if ($order->get_total() > 0) {
                $this->payzen_request->set('page_action', 'REGISTER_PAY_SUBSCRIBE');
            } else {
                // Only subscriptions.
                if ($saved_identifier = $this->get_cust_identifier($cust_id)) {
                    $this->payzen_request->set('identifier', $saved_identifier);
                    $this->payzen_request->set('page_action', 'SUBSCRIBE');
                } else {
                    $this->payzen_request->set('page_action', 'REGISTER_SUBSCRIBE');
                }
            }
        }
    }

    public function payzen_order_needs_payment($is_active, $order)
    {
        global $woocommerce;

        if (($order->get_total() == 0) && ($this->get_order_property($order, 'payment_method') === $this->id)) {
            return $this->subscriptions_handler && $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart);
        }

        return parent::payzen_order_needs_payment($is_active, $order);
    }
}
