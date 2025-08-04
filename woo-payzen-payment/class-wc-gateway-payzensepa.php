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

class WC_Gateway_PayzenSepa extends WC_Gateway_PayzenStd
{
    const SUBSCRIPTIONS_HANDLER = 'wc-subscriptions';

    protected $payzen_countries = array(
        'FI', 'AT', 'PT', 'BE', 'BG', 'ES', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FR', 'GF', 'DE', 'GI', 'GR',
        'GP', 'HU', 'IS', 'IE', 'LV', 'LI', 'LT', 'LU', 'PT', 'MT', 'MQ', 'YT', 'MC', 'NL', 'NO', 'PL',
        'RE', 'RO', 'BL', 'MF', 'PM', 'SM', 'SK', 'SE', 'CH', 'GB'
    );

    protected $payzen_currencies = array('EUR');
    protected $subscriptions_handler;

    public function __construct()
    {
        $this->id = 'payzensepa';
        $this->icon = apply_filters('woocommerce_payzensepa_icon', self::LOGO_URL . 'sepa.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('SEPA payment', 'woo-payzen-payment');
        $this->method_description = __('Accept payments via SEPA Direct Debit. This method can be used to pay for subscriptions managed by WooCommerce Subscriptions.', 'woo-payzen-payment');

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
            'tokenization',
            'refunds'
        );

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

        if ($this->payzen_is_section_loaded()) {
            // Reset sepa payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // Update sepa payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_script'));
        }

        // Generate sepa payment form action.
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

        add_action('woocommerce_get_customer_payment_tokens', array($this, 'payzen_get_customer_payment_tokens'), 10, 3);

        add_filter('woocommerce_payment_methods_list_item', array($this, 'payzen_get_account_payment_methods_list'), 10, 2 );
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

        // By default, disable SEPA payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables SEPA payment.', 'woo-payzen-payment');

        $this->form_fields['title']['default'] = __('Payment with SEPA', 'woo-payzen-payment');

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Payment with SEPA',
                'en_GB' => 'Payment with SEPA',
                'fr_FR' => 'Paiement avec SEPA',
                'de_DE' => 'Zahlung mit SEPA',
                'es_ES' => 'Pago con SEPA',
                'pt_BR' => 'Pagamento com SEPA'
            );
        }

        $this->form_fields['payment_options'] = array(
            'title' => __('PAYMENT OPTIONS', 'woo-payzen-payment'),
            'type' => 'title'
        );

        $this->form_fields['sepa_mandate_mode'] = array(
            'custom_attributes' => array(
                'onchange' => 'payzenUpdatePaymentByTokenField()',
            ),
            'title' => __('SEPA direct debit mode', 'woo-payzen-payment'),
            'type' => 'select',
            'default' => 'PAYMENT',
            'options' => $this->get_sepa_mandate_modes(),
            'description' => sprintf(__('Select SEPA direct debit mode. Attention, the two last choices require the payment by token option on %s.',
                'woo-payzen-payment'), self::GATEWAY_NAME),
            'class' => 'wc-enhanced-select'
        );

        $this->form_fields['payment_by_token'] = array(
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
    }

    public function payzen_admin_head_script()
    {
        parent::payzen_admin_head_script();
        ?>
        <script type="text/javascript">
         //<!--
            jQuery(document).ready(function() {
                payzenUpdatePaymentByTokenField();
            });

            function payzenUpdatePaymentByTokenField() {
                var paymentOptionsTitle = jQuery('#<?php echo esc_attr($this->get_field_key('payment_options')); ?>').next();
                var sepaMandateMode = jQuery('#<?php echo esc_attr($this->get_field_key('sepa_mandate_mode')); ?> option:selected').val();

                if (sepaMandateMode == 'REGISTER_PAY') {
                    paymentOptionsTitle.find('tr:nth-child(2)').show();
                } else {
                    paymentOptionsTitle.find('tr:nth-child(2)').hide();
                }
            }
          //-->
        </script>
        <?php
    }

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
    }

    protected function get_sepa_mandate_modes()
    {
        return array(
            'PAYMENT' => __('One-off SEPA direct debit', 'woo-payzen-payment'),
            'REGISTER_PAY' =>__('Register a recurrent SEPA mandate with direct debit', 'woo-payzen-payment'),
            'REGISTER' => __('Register a recurrent SEPA mandate without direct debit', 'woo-payzen-payment')
        );
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        global $woocommerce;

        parent::payzen_fill_request($order);

        // Specific fields for SEPA payment.
        $this->payzen_request->set('payment_cards', 'SDD');
        $this->payzen_request->addExtInfo('is_sepa', true);

        $cust_id = self::get_order_property($order, 'user_id');
        if ($this->can_use_alias($cust_id)) {
            $order_id = self::get_order_property($order, 'id');

            $saved_token = get_transient($this->id . '_token_' . $order_id);
            delete_transient($this->id . '_token_' . $order_id);

            $old_payment_method = get_transient($this->id . '_change_payment_' . $order_id);
            $is_payment_change = $old_payment_method ? true : false;
            delete_transient($this->id .'_change_payment_' . $order_id);

            if ($is_payment_change) {
                // Called from change payment action.
                $this->payzen_request->set('amount', 0);
                $this->payzen_request->addExtInfo('subsc_id', $order_id);
            }

            $action = 'PAYMENT';
            if ($saved_token) {
                $this->payzen_request->set('identifier', $saved_token);

                if ($this->payzen_request->get('amount') == 0) {
                    $action = 'REGISTER_UPDATE';
                }
            } else {
                $action = ($this->payzen_request->get('amount') == 0) ? 'REGISTER' : 'REGISTER_PAY';
            }

            $this->payzen_request->set('page_action', $action);
       } else {
           $this->payzen_request->set('page_action', $this->get_option('sepa_mandate_mode'));
       }

       if (isset($_POST['update_all_subscriptions_payment_method']) && $_POST['update_all_subscriptions_payment_method']) {
           $this->payzen_request->addExtInfo('update_identifier_all', true);
       }
    }

    protected function can_use_alias($cust_id, $verify_identifier = false)
    {
        if (! $cust_id) {
            return false;
        }

        if (($this->get_option('sepa_mandate_mode') !== 'REGISTER_PAY') || $this->get_option('payment_by_token') !== '1') {
            return false;
        }

        return true;
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
        if ($this->subscriptions_handler && $this->subscriptions_handler->cart_contains_subscription($woocommerce->cart) && ! $this->can_use_alias($cust_id)) {
            return false;
        }

        // Allow subscription when no client is connected and "Allow customers to create an account during checkout" is enabled.
        if (! $cust_id && (get_option('woocommerce_enable_signup_and_login_from_checkout') !== 'yes')
            && (get_option('woocommerce_enable_signup_from_checkout_for_subscriptions') !== 'yes')) {
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

            echo '<input type="hidden" id="' . $this->id . '_old_pm" name="' . $this->id . '_old_pm" value="' . $method . '">';
        }

        parent::payment_fields();
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

        $token = PayzenTools::get_token_from_request($_POST);
        if ($token) {
            set_transient($this->id . '_token_' . $order_id, $token);
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

    // Manage display of saved payment methods on checkout page.
    public function payzen_get_customer_payment_tokens($tokens, $user_id, $gateway_id)
    {
        foreach ($tokens as $key => $token) {
            if ($token->get_gateway_id() !== $this->id) {
                continue;
            }

            if (! $this->can_use_alias($user_id) || ! $this->check_identifier($user_id, $token->get_gateway_id(), $token->get_token())) {
                unset($tokens[$key]);
            }
        }

        return $tokens;
    }

    public function payzen_get_account_payment_methods_list($item, $token)
    {
        if ($token->get_gateway_id() == $this->id) {
            $item['method']['last4'] = $token->get_last4();
            $item['method']['brand'] = 'IBAN / BIC';
        }

        return $item;
    }
}
