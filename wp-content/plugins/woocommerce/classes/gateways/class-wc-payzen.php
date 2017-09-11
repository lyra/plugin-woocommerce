<?php
#####################################################################################################
#
#					Module pour la plateforme de paiement PayZen
#						Version : 1.0a (révision 40262)
#									########################
#					Développé pour WooCommerce
#						Version : 1.5.6
#						Compatibilité plateforme : V2
#									########################
#					Développé par Lyra Network
#						http://www.lyra-network.com/
#						31/10/2012
#						Contact : support@payzen.eu
#
#####################################################################################################

require_once 'payzen/class-payzen-api.php';

/**
 * PayZen Payment Gateway
 * 
 * @class 		WC_Payzen
 * @package		WooCommerce
 * @category	Payment Gateways
 */
class WC_Payzen extends WC_Payment_Gateway {
	private $payzen_api; // PayZen API 
	
	public function __construct() {
		global $woocommerce;
		
        $this->id = 'payzen';
        $this->icon = apply_filters('woocommerce_payzen_icon', $woocommerce->plugin_url() . '/assets/images/icons/PayZen.jpg');
        $this->has_fields = false;
        $this->method_title = 'PayZen';
        
        // Load translation files
        load_plugin_textdomain('payzen', false, dirname(plugin_basename(__FILE__)) . '/payzen/languages/');
        
        // Init PayZen API 
        $this->payzen_api = new PayzenApi();
		
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->testmode = ($this->settings['ctx_mode'] == 'TEST');		
		$this->debug = (isset($this->settings['debug']) && $this->settings['debug'] == 'yes') ? true : false;	
		
		// logger
		if ($this->debug) {
			$this->log = $woocommerce->logger();
		}
		
		// Actions
		add_action('init', array(&$this, 'notify_response'));
		add_action('init', array(&$this, 'payzen_reset'));
		add_action('payzen_valid_notify_response', array(&$this, 'valid_notify_response'));
		
		add_action('woocommerce_receipt_payzen', array(&$this, 'generate_payzen_form'));
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
    } 
    
	/**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 */
	public function admin_options() {
		global $woocommerce;
    	
		?>
    	
    	<h3>PayZen</h3>
    	<p><?php echo sprintf(__('%s works by sending the user to %s to select their payment mean and enter their payment information.', 'payzen'), 'PayZen', 'PayZen'); ?></p>
    	
    	<?php if (key_exists('payzen_reset', $_SESSION) && $_SESSION['payzen_reset']) : 
    		unset ($_SESSION['payzen_reset']);	
    	?>
    		<div class="inline updated"><p><?php echo sprintf(__( 'Your %s configuration parameters are reset.', 'payzen'), 'PayZen'); ?></p></div>
    	<?php endif; ?>
    	
    	<?php if (! $this->is_supported_currency()) : ?>
    		<div class="inline error"><p><strong><?php _e('Gateway Disabled', 'payzen'); ?></strong>: <?php echo sprintf(__( '%s does not support your store currency.', 'payzen'), 'PayZen'); ?></p></div>
    		<br />
    	<?php endif; ?>
    	
    	<table class="form-table">
    	<?php
    		// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    	?>
    	</table>
    	
		<a id="reset_payzen_payment_settings" href="<?php echo wp_nonce_url(admin_url('admin.php?payzenListener=payzen_reset'), 'payzen_reset')?>"><?php _e('Reset configuration', 'payzen');?></a>
    	
    	<?php 
    } // End admin_options()
    
    function payzen_reset() {
    	if (isset($_GET['payzenListener']) && $_GET['payzenListener'] == 'payzen_reset') {
    		if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'payzen_reset')) die('Security check');
    		
    		@ob_clean();
    		delete_option('woocommerce_payzen_settings');
			
    		$_SESSION['payzen_reset'] = 'true';
    		
    		// Redirect back to the settings page
    		$redirect = add_query_arg('page', 'woocommerce', admin_url('admin.php'));
    		$redirect = add_query_arg('tab', 'payment_gateways', $redirect);
    		$redirect = add_query_arg('subtab', 'gateway-payzen', $redirect);
    		
    		wp_redirect($redirect);
    		exit;
    	}
    }
    
	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
	    		// Module informations
				'developped_by' => array(
						'title' => __('Developed by', 'payzen'), 
						'type' => 'text', 
						'description' => 'Lyra network',
						'css' => 'display: none;'
				),
				'contact' => array(
						'title' => __( 'Contact email', 'payzen' ), 
						'type' => 'text', 
						'description' => 'support@payzen.eu',
						'css' => 'display: none;'
				), 
				'contrib_version' => array(
						'title' => __( 'Module version', 'payzen' ), 
						'type' => 'text', 
						'description' => '1.0a',
						'css' => 'display: none;'
				), 
				'platform_version' => array(
						'title' => __( 'Gateway version', 'payzen' ), 
						'type' => 'text', 
						'description' => 'V2',
						'css' => 'display: none;'
				), 
				'cms_version' => array(
						'title' => __( 'Tested with', 'payzen' ), 
						'type' => 'text', 
						'description' => 'WooCommerce1.5.6_1.0a',
						'css' => 'display: none;'
				),
	    			
    			// CMS config params
    			'enabled' => array(
    					'title' => __('Status', 'payzen'),
    					'type' => 'checkbox',
    					'label' => sprintf(__('Enable %s', 'payzen'), 'PayZen'),
    					'default' => 'yes'
    			),
    			'title' => array(
    					'title' => __('Title', 'payzen'),
    					'type' => 'text',
    					'description' => __('This controls the title which the user sees during checkout.', 'payzen'),
    					'default' => 'PayZen'
    			),
    			'description' => array(
    					'title' => __( 'Description', 'payzen' ),
    					'type' => 'textarea',
    					'description' => __( 'This controls the description which the user sees during checkout.', 'payzen' ),
    					'default' => sprintf(__('Pay via secured %s platform.', 'payzen'), 'PayZen')
    			),
    			'debug' => array(
    					'title' => __( 'Debug logging', 'payzen' ),
    					'type' => 'checkbox',
    					'label' => __( 'Enable', 'payzen' ),
    					'default' => 'no',
    					'description' => sprintf(__('Log %s events, such as requests, inside <code>woocommerce/logs/%s.txt</code>', 'payzen'), 'PayZen', 'payzen'),
    			),
	    			
    			// payment platform access params
				'site_id' => array(
						'title' => __('Site ID', 'payzen'), 
						'type' => 'text', 
						'default' => '12345678',
						'description' => __('The identifier provided by your bank', 'payzen')
				),
				'key_test' => array(
						'title' => __('Certificate in test mode', 'payzen'), 
						'type' => 'text', 
						'default' => '1111111111111111',
						'description' => sprintf(__( 'Certificate provided by your bank for test (available on the back office %s)', 'payzen'), 'PayZen')
				),
	    		'key_prod' => array(
		    			'title' => __('Certificate in production mode', 'payzen'),
		    			'type' => 'text',
		    			'default' => '2222222222222222',
		   				'description' => sprintf(__('Certificate provided by your bank (available on the back office %s after validation)', 'payzen'), 'PayZen')
	    		),
	    		'ctx_mode' => array(
		    			'title' => __('Mode', 'payzen'),
		    			'type' => 'select',
		    			'default' => 'TEST',
		    			'options' => array(
		    					'TEST' => __('TEST', 'payzen'), 
		    					'PRODUCTION' => __('PRODUCTION', 'payzen')
		    			),
		    			'description' => __('The context mode of this module', 'payzen')
	    		),
	    		'platform_url' => array(
		    			'title' => __('Platform URL', 'payzen'),
		    			'type' => 'text',
		    			'default' => 'https://secure.payzen.eu/vads-payment/',
		    			'description' => __('Link to the payment platform', 'payzen'),
	    				'css' => 'width: 350px;'
	    		),
	    			
    			// payment page params
	    		'language' => array(
		    			'title' => __('Default language', 'payzen'),
		    			'type' => 'select',
		    			'default' => 'fr',
		    			'options' => array(
		    					'fr' => __('French', 'payzen'),
		    					'de' => __('German', 'payzen'),
		    					'en' => __('English', 'payzen'),
		    					'es' => __('Spanish', 'payzen'),
		    					'zh' => __('Chinese', 'payzen'),
		    					'it' => __('Italian', 'payzen'),
		    					'ja' => __('Japanese', 'payzen'),
		    					'pt' => __('Portuguese', 'payzen'),
		    					'nl' => __('Dutch', 'payzen')
		    			),
		    			'description' => __('Select the language to use on the payment page by default', 'payzen')
	    		),
	    		'available_languages' => array(
		    			'title' => __('Available languages', 'payzen'),
		    			'type' => 'multiselect',
		    			'default' => '',
		    			'options' => array(
		    					'' => __('All', 'payzen'),
		    					'fr' => __('French', 'payzen'),
		    					'de' => __('German', 'payzen'),
		    					'en' => __('English', 'payzen'),
		    					'es' => __('Spanish', 'payzen'),
		    					'zh' => __('Chinese', 'payzen'),
		    					'it' => __('Italian', 'payzen'),
		    					'ja' => __('Japanese', 'payzen'),
		    					'pt' => __('Portuguese', 'payzen'),
		    					'nl' => __('Dutch', 'payzen')
		    			),
		    			'description' => __( 'Available languages on payment page, select all to use platform configuration', 'payzen')
	    		),
	    		'capture_delay' => array(
		    			'title' => __('Capture delay', 'payzen'),
		    			'type' => 'text',
		    			'default' => '',
		    			'description' => sprintf(__('The number of days before the bank restoration (adjustable in your back office %s)', 'payzen'), 'PayZen')
	    		),
	    		'validation_mode' => array(
		    			'title' => __('Validation mode', 'payzen'),
		    			'type' => 'select',
		    			'default' => '',
		    			'options' => array(
		    					'' => __('Default', 'payzen'),
		    					'0' => __('Automatic', 'payzen'),
		    					'1' => __('Manual', 'payzen')
		    			),
		    			'description' => __('If manual is selected, you will have to confirm payments manually in your store back office', 'payzen')
	    		),
	    		'payment_cards' => array(
		    			'title' => __('Card Types', 'payzen'),
	    				'type' => 'multiselect',
	    				'default' => array(''),
	    				'options' => array(
	    						'' => __('All', 'payzen'),
	    						'AMEX' => 'American express',
	    						'CB' => 'CB',
	    						'MASTERCARD' => 'Mastercard',
	    						'VISA' => 'Visa'
	    				),
		    			'description' => __('The card type(s) that can be used for the payment', 'payzen')
	    		),
    			
    			// amount restrictions 
    			'amount_min' => array(
    					'title' => __('Minimum amount', 'payzen'),
    					'type' => 'text',
    					'default' => '',
    					'description' => __('Minimum amount for which this payment method is available', 'payzen')
    			),
    			'amount_max' => array(
    					'title' => __('Maximum amount', 'payzen'),
    					'type' => 'text',
    					'default' => '',
    					'description' => __( 'Maximum amount for which this payment method is available', 'payzen')
    			),
    			
    			// return to store params
    			'redirect_enabled' => array(
    					'title' => __('Automatic forward', 'payzen'),
    					'type' => 'checkbox',
    					'default' => 'no',
    					'label' => __('Enable', 'payzen'),
     					'description' => __('If enabled, the client is automatically forwarded to your site at the end of the payment process', 'payzen')
    			),
    			'redirect_success_timeout' => array(
    					'title' => __('Success forward timeout', 'payzen'),
    					'type' => 'text',
    					'default' => '5',
    					'description' => __('Time in seconds (0-300) before the client is automatically forwarded to your site when the payment was successful', 'payzen')
    			),
    			'redirect_success_message' => array(
    					'title' => __('Success forward message', 'payzen'),
    					'type' => 'text',
    					'default' => 'Votre paiement a bien été pris en compte, vous allez être redirigé dans quelques instants.',
    					'description' => __('Message posted on the payment platform before forwarding when the payment was successful', 'payzen'),
    					'css' => 'width: 350px;'
    			),
    			'redirect_error_timeout' => array(
    					'title' => __('Failure forward timeout', 'payzen'),
    					'type' => 'text',
    					'default' => '5',
    					'description' => __('Time in seconds (0-300) before the client is automatically forwarded to your site when the payment failed', 'payzen')
    			),
    			'redirect_error_message' => array(
    					'title' => __('Failure forward message', 'payzen'),
    					'type' => 'text',
    					'default' => 'Une erreur est survenue, vous allez être redirigé dans quelques instants.',
    					'description' => __('Message posted on the payment platform before forwarding when the payment failed', 'payzen'),
    					'css' => 'width: 350px;'
    			),
    			'return_mode' => array(
    					'title' => __('Return mode', 'payzen'),
    					'type' => 'select',
    					'default' => 'GET',
    					'options' => array(
    							'GET' => 'GET',
    							'POST' => 'POST'
    					),
    					'description' => __('Method that will be used for transmitting the payment result from the payment gateway to your store', 'payzen')
    			),
    			'url_check' => array(
    					'title' => __('Server URL to copy in your store back office', 'payzen'),
    					'type' => 'text',
    					'description' => trailingslashit(home_url()).'?payzenListener=payzen_notify',
    					'css' => 'display: none'
    			)
		);
    
    } // End init_form_fields()
    
    /**
     * Validate multiselect field.
     *
     * @return array
     */
    function validate_multiselect_field ($key) {
    	$newValue = $_POST[$this->plugin_id . $this->id . '_' . $key];
    	
    	if(isset($newValue) && is_array($newValue) && in_array('', $newValue)) {
    		return array('');
    	} else {
    		return parent::validate_multiselect_field ($key);
    	}
    }
    
    /**
     * Check if this gateway is available for the current currency.
     */
    function is_supported_currency() {
    	$currency = $this->payzen_api->findCurrencyByAlphaCode(get_woocommerce_currency());
    	if($currency == null) {
    		return false;
    	}
    	
    	return true;
    }
    
    /**
     * Check if this gateway is enabled and available for the current currency and the current cart amount.
     */
    function is_available() {
    	global $woocommerce;
    	 
    	if(!$this->is_supported_currency()) {
    		return false;
    	}
    	 
    	$amount = $woocommerce->cart->total;
    	if (($this->settings['amount_max'] != '' && $amount > $this->settings['amount_max'])
    			|| ($this->settings['amount_min'] != '' && $amount < $this->settings['amount_min'])) {
    		return false;
    	}
    
    	return parent::is_available();
    }
    
    
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id) {
    	$order = new WC_Order($order_id);
    	
    	return array(
    			'result' 	=> 'success',
    			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
    	);
    }
    
    /**
     * Order review and payment form page.
     **/
    function generate_payzen_form($order_id) {
    	global $woocommerce;
    	
    	$order = new WC_Order($order_id);
    	
    	echo '<div style="opacity: 0.6; padding: 10px; text-align: center; color: #555;	border: 3px solid #aaa; background-color: #fff; cursor: wait; line-height: 32px;">';
    	echo '<img src="' . esc_url( $woocommerce->plugin_url() ) . '/assets/images/ajax-loader.gif" alt="Redirecting..." style="float:left; margin-right: 10px;"/>';
    	echo sprintf(__('We are now redirecting you to %s to make payment.', 'payzen'), 'PayZen');
    	echo '</div>';
    	echo '<p>'.sprintf(__('Thank you for your order. If you are not redirected in 10 s, please click the button below.', 'payzen'), 'PayZen').'</p>';
    
    	$woocommerce->add_inline_js('jQuery("#submit_payzen_payment_form").click();');
    	
    	$this->fill_payzen_api($order);
    	
    	$form = '<form action="' . esc_url($this->payzen_api->platformUrl) . '" method="post" id="payzen_payment_form" target="_top">';
    	$form .= $this->payzen_api->getRequestFieldsHtml();
		$form .= '<input type="submit" class="button-alt" id="submit_payzen_payment_form" value="'.sprintf(__('Pay via %s', 'payzen'), 'PayZen').'"/>'; 
		$form .= '<a class="button cancel" href="'.esc_url($order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'payzen').'</a>';
		$form .= '</form>';
		
		echo $form;
    }
    
    /**
	 * Prepare PayZen args for passing to payment server
	 **/
	function fill_payzen_api($order) {
		global $woocommerce;
		
		if ($this->debug) {
			$this->log->add('payzen', 'Generating payment form for order #' . $order->id . '. Notify URL: ' . trailingslashit(home_url()).'?payzenListener=payzen_notify');
		}
		
		// get currency
		$currency = $this->payzen_api->findCurrencyByAlphaCode(get_woocommerce_currency());
		if($currency == null) {
			if ($this->debug) {
				$this->log->add('payzen', 'The store currency (' . get_woocommerce_currency() . ') is not supported by PayZen.');
			}
			
			wp_die(sprintf(__('The store currency (%s) is not supported by %s.'), get_woocommerce_currency(), 'PayZen'));
		}
		
		// PayZen Args
		$misc_params = array(
				'amount' => $currency->convertAmountToInteger($order->get_total()),
				'contrib' => 'WooCommerce1.5.6_1.0a',
				'currency' => $currency->num,
				'order_id' => $order->id,
				'order_info' => $order->order_key,
				
				// billing address info
				'cust_id' => $order->user_id,
				'cust_email' => $order->billing_email,
				'cust_first_name' => $order->billing_first_name,
				'cust_last_name' => $order->billing_last_name,
				'cust_address' => $order->billing_address_1 . ' ' .  $order->billing_address_2,
				'cust_zip' => $order->billing_postcode,
				'cust_country' => $order->billing_country,
				'cust_phone' => str_replace(array('(', '-', ' ', ')'), '', $order->billing_phone),
				'cust_city' => $order->billing_city,
				'cust_state' => $order->billing_state,
				
				// shipping address info
				'ship_to_first_name' => $order->shipping_first_name,
				'ship_to_last_name' => $order->shipping_last_name,
				'ship_to_street' => $order->shipping_address_1,
				'ship_to_street2' => $order->shipping_address_2,
				'ship_to_city' => $order->shipping_city,
				'ship_to_state' => $order->shipping_state,
				'ship_to_country' => $order->shipping_country,
				'ship_to_zip' => $order->shipping_postcode,
				'ship_to_phone' => str_replace(array('(', '-', ' ', ')'), '', $order->shipping_phone),
				
				// return URLs
				'url_return' => trailingslashit(home_url()).'?payzenListener=payzen_notify',
				'url_cancel' => $order->get_cancel_order_url(),
		);
		$this->payzen_api->setFromArray($misc_params);
		
		// detect language
		$locale = get_locale() ? substr(get_locale(), 0, 2) : null;
		if($locale && in_array($locale, $this->payzen_api->getSupportedLanguages())) {
			$this->payzen_api->set('language', $locale);
		} else {
			$this->payzen_api->set('language', $this->settings['language']);
		}
		
		// available languages 
		$langs = $this->settings['available_languages'];
		if(is_array($langs) && !in_array('', $langs)) {
			$this->payzen_api->set('available_languages', implode(';', $langs));
		}
		
		// payment cards
		$cards = $this->settings['payment_cards'];
		if(is_array($cards) && !in_array('', $cards)) {
			$this->payzen_api->set('payment_cards', implode(';', $cards));
		}
		
		// enable automatic redirection ?
		$this->payzen_api->set('redirect_enabled', ($this->settings['redirect_enabled'] == 'yes') ? true : false);
		
		// other configuration params
		$config_keys = array(
				'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url', 'capture_delay', 'validation_mode', 
				'redirect_success_timeout', 'redirect_success_message', 'redirect_error_timeout', 
				'redirect_error_message', 'return_mode'
		);
		
		foreach($config_keys as $key) {
			$this->payzen_api->set($key, $this->settings[$key]);
		}
	}

	/**
	 * Check for PayZen notify Response
	 **/
	function notify_response() {
		if (isset($_GET['payzenListener']) && $_GET['payzenListener'] == 'payzen_notify') {
			@ob_clean();
			
			$raw_response = array_map('stripslashes', $_REQUEST);
			
			$payzen_response = new PayzenResponse(
					$raw_response, 
					$this->settings['ctx_mode'], 
					$this->settings['key_test'], 
					$this->settings['key_prod']
			);
			
			if ($this->debug) {
				$this->log->add('payzen', 'Response received from PayZen : ' . print_r($raw_response, true));
			}
			
			if(!$payzen_response->isAuthentified()) {
				if ($this->debug) {
					$this->log->add('payzen', 'Received invalid response from PayZen: authentication failed.' );
				}
				
				$from_server = $payzen_response->get('hash') != null;
				 
				if($from_server) {
					die($payzen_response->getOutputForGateway('auth_fail'));
				} else {
					wp_die(sprintf(__('%s response authentication failure.', 'payzen'), 'PayZen'));
				}
			} else {
				header('HTTP/1.1 200 OK');
				
				do_action("payzen_valid_notify_response", $payzen_response);
			}
		}
	}
	
	/**
	 * Valid payment process : update order, send mail, ... 
 	 **/
	function valid_notify_response($payzen_response) {
		$order_id = $payzen_response->get('order_id');
		$from_server = $payzen_response->get('hash') != null;
		
		$order = new WC_Order((int) $order_id);
		if (!isset($order->id) || $order->order_key !== $payzen_response->get('order_info')) {
			if ($this->debug) {
				$this->log->add('payzen', 'Error: Order (' . $order_id . ') nor found or key does not match received invoice id.');
			}
			
			if ($from_server) {
				die($payzen_response->getOutputForGateway('order_not_found'));
			} else {
				wp_die(sprintf(__('Error : order with id #%s cannot be found.', 'payzen'), $order_id));
			}			
		}
		
		if($order->status === 'pending' || ($order->status === 'failed' && get_post_meta((int) $order_id, 'Transaction ID', true) !== $payzen_response->get('trans_id'))) { 
			// Order not processed yet or a failed order payment retry
			
			// Store transaction details
			update_post_meta((int) $order_id, 'Transaction ID', $payzen_response->get('trans_id'));
			update_post_meta((int) $order_id, 'Card number', $payzen_response->get('card_number'));
			update_post_meta((int) $order_id, 'Payment mean', $payzen_response->get('card_brand'));
				
			$expiry = str_pad($payzen_response->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $payzen_response->get('expiry_year');
			update_post_meta((int) $order_id, 'Card expiry', $expiry);
			
			if($payzen_response->isAcceptedPayment()) {
				// Payment completed
				$order->add_order_note(__('Payment completed successfully.', 'payzen'));
				$order->payment_complete();
				 
				if ($this->debug) {
					$this->log->add('payzen', 'Payment completed successfully.');
				}
				
				if ($from_server) {
					die ($payzen_response->getOutputForGateway('payment_ok'));
				} else {
					wp_redirect($this->get_return_url($order));
					die();
				}
			} else {
				$status = $payzen_response->isCancelledPayment() ? 'cancelled' : 'failed';
				$order->update_status($status, sprintf(__('Payment failed. Error message: %s (%s)', 'payzen') . "\n", $payzen_response->message, $payzen_response->code));
				
				if ($this->debug) {
					$this->log->add('payzen', 'Payment failed. ' . $payzen_response->getLogString());
				}
		
				if ($from_server) {
					die($payzen_response->getOutputForGateway('payment_ko'));
				} else {
					$redirect = $payzen_response->isCancelledPayment() ? $order->get_cancel_order_url() : $this->get_return_url($order);
					wp_redirect($redirect);
					die();
				}
			}
		} else {
			if ($this->debug) {
				$this->log->add('payzen', 'Order #' . $order_id . ' is already processed.' );
			}
			
			if($payzen_response->isAcceptedPayment() && ($order->status === 'completed' || $order->status === 'processing')) {
				// order success registered and payment succes received 
				if ($from_server) {
					die ($payzen_response->getOutputForGateway('payment_ok_already_done'));
				} else {
					wp_redirect($this->get_return_url($order));
					die();
				}
			} elseif($payzen_response->isCancelledPayment() && $order->status === 'cancelled') {
				// order failure registered and payment error received
				if ($from_server) {
					die($payzen_response->getOutputForGateway('payment_ko_already_done'));
				} else {
					wp_redirect($order->get_cancel_order_url());
					die();
				}
			} elseif(!$payzen_response->isAcceptedPayment() && !$payzen_response->isCancelledPayment() && $order->status === 'failed') {
				// order failure registered and payment error received
				if ($from_server) {
					die($payzen_response->getOutputForGateway('payment_ko_already_done'));
				} else {
					wp_redirect($this->get_return_url($order));
					die();
				}
			} else {
				// registered order status not match payment result
				if ($from_server) {
					die($payzen_response->getOutputForGateway('payment_ko_on_order_ok'));
				} else {
					wp_die(sprintf(__('Error : invalid payment code received for already processed order (%s).', 'payzen'), $order_id));
				}
			}
		}
	}

}

/**
 * Add the gateway to WooCommerce
 **/
function add_payzen_gateway($methods) {
	$methods[] = 'WC_Payzen'; 
	return $methods;
}


add_filter('woocommerce_payment_gateways', 'add_payzen_gateway' );
