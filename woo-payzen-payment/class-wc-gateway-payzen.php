<?php
/**
 * PayZen V2-Payment Module version 1.3.2 for WooCommerce 2.x-3.x. Support contact : support@payzen.eu.
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
 * PayZen Payment Gateway : common class.
 */
class WC_Gateway_Payzen extends WC_Payment_Gateway {

	private static $success_order_statues = array(
			'on-hold' => 'On Hold',
			'processing' => 'Processing',
			'completed' => 'Complete'
	);

	public function __construct() {
		$this->id = 'payzen';
		$this->has_fields = false;
		$this->method_title = 'PayZen - ' . __('General configuration', 'woo-payzen-payment');

		// init PayZen common vars
		$this->payzen_init();

		// load the form fields
		$this->init_form_fields();

		// load the module settings
		$this->init_settings();

		$this->title = __('General configuration', 'woo-payzen-payment');
		$this->enabled = false;
		$this->testmode = ($this->settings['ctx_mode'] == 'TEST');
		$this->debug = (isset($this->settings['debug']) && $this->settings['debug'] == 'yes') ? true : false;

		// reset payzen common admin form action
		add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

		// update payzen admin form action
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		// return from payment platform action
		add_action('woocommerce_api_wc_gateway_payzen', array($this, 'payzen_notify_response'));
	}

	protected function payzen_init() {
		global $woocommerce;

		$this->logger = new WC_Logger();

		// init PayZen API
		$this->payzen_request = new PayzenRequest();

		$this->admin_section = 'wc_gateway_' . $this->id;
		$this->admin_tab = 'checkout';
		$this->admin_page = 'wc-settings';

		// backward compatibility
		if (version_compare($woocommerce->version, '2.1.0', '<')) {
			$this->admin_section = get_class($this);
			$this->admin_tab = 'payment_gateways';
			$this->admin_page = 'woocommerce_settings';
		}

		// admin settings page URL
		$this->admin_link = add_query_arg('section', $this->admin_section,
				add_query_arg('tab', $this->admin_tab,
						add_query_arg('page', $this->admin_page, admin_url('admin.php'))));

		// reset admin settings URL
		$this->reset_admin_link = $this->admin_link;
		$this->reset_admin_link = add_query_arg('noheader', 'true', add_query_arg('reset', 'true', $this->reset_admin_link));
		$this->reset_admin_link = wp_nonce_url($this->reset_admin_link, $this->admin_page);
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {
		if (!$this->is_supported_currency()) {
			echo '<div class="inline error"><p><strong>' . __('Platform disabled', 'woo-payzen-payment') . ': ' . sprintf(__('%s does not support your store currency.', 'woo-payzen-payment'), 'PayZen') . '</strong></p></div>';
		}

		if (get_transient($this->id . '_settings_reset')) {
			delete_transient($this->id . '_settings_reset');

			echo '<div class="inline updated"><p><strong>' . sprintf(__('Your %s module configuration is successfully reset.', 'woo-payzen-payment'), 'PayZen') . '</strong></p></div>';
		}
		?>

		<br />
		<h3>PayZen</h3>
		<p><?php echo sprintf(__('The module works by sending users to %s in order to select their payment mean and enter their payment information.', 'woo-payzen-payment'), 'PayZen'); ?></p>

		<table>
			<?php $this->generate_settings_html(); // generate the HTML For the settings form ?>
		</table>

		<a href="<?php echo $this->reset_admin_link; ?>"><?php _e('Reset configuration', 'woo-payzen-payment');?></a>

		<?php
	}


	public function payzen_reset_admin_options() {
		// if not reset action do nothing
		if (!isset($_GET['reset'])) {
			return;
		}

		// check if correct link
		if ($this->admin_section != $_GET['section']) {
			return;
		}

		delete_option('woocommerce_' . $this->id . '_settings');

		// transcient flag to display reset message
		set_transient($this->id . '_settings_reset', 'true');

		wp_redirect($this->admin_link);
		die();
	}

	protected function get_supported_languages($all = false) {
		$langs = array();
		if ($all) {
			$langs[''] = __('All', 'woo-payzen-payment');
		}

		foreach (PayzenApi::getSupportedLanguages() as $code => $label) {
			$langs[$code] = __($label, 'woo-payzen-payment');
		}

		return $langs;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		global $woocommerce;

		if (function_exists('wc_get_log_file_path')) {
			$log_folder = dirname(wc_get_log_file_path('payzen')) . '/';
		} else {
			$log_folder = $woocommerce->plugin_path() . '/logs/';
		}
		$log_folder = str_replace('\\', '/', $log_folder);

		// get relative path
		$base_dir = str_replace('\\', '/', ABSPATH);
		if (strpos($log_folder, $base_dir) === 0) {
			$log_folder = str_replace($base_dir, '', $log_folder);
		} else {
			$base_dir = str_replace('\\', '/', dirname(ABSPATH));
			$log_folder = str_replace($base_dir, '..', $log_folder);
		}

		// prepare succes order statuses array
		$statues = array('default' => __('Default', 'woo-payzen-payment'));
		foreach (self::$success_order_statues as $key => $value) {
			$statues[$key] = __($value, 'woo-payzen-payment');
		}

		$this->form_fields = array(
				// module information
				'module_details' => array(
						'title' => __('MODULE DETAILS', 'woo-payzen-payment'),
						'type' => 'title'
				),

				'developped_by' => array(
						'title' => __('Developed by', 'woo-payzen-payment'),
						'type' => 'text',
						'description' => '<b><a href="http://www.lyra-network.com/" target="_blank">Lyra Network</a></b>',
						'css' => 'display: none;'
				),
				'contact' => array(
						'title' => __('Contact us', 'woo-payzen-payment'),
						'type' => 'text',
						'description' => '<b><a href="mailto:support@payzen.eu">support@payzen.eu</a></b>',
						'css' => 'display: none;'
				),
				'contrib_version' => array(
						'title' => __('Module version', 'woo-payzen-payment'),
						'type' => 'text',
						'description' => '1.3.2',
						'css' => 'display: none;'
				),
				'platform_version' => array(
						'title' => __('Platform version', 'woo-payzen-payment'),
						'type' => 'text',
						'description' => 'V2',
						'css' => 'display: none;'
				),
				'doc_link' => array(
						'title' => '<a style="color: red;" href="'. WC_PAYZEN_PLUGIN_URL.'installation_doc/Integration_PayZen_WooCommerce_2.x-3.x_v1.3.2.pdf" target="_blank">'.
									__('Click here to view the module configuration documentation.', 'woo-payzen-payment') . '</a>',
						'type' => 'label',
						'css' => 'font-weight: bold; text-transform: uppercase;'
				),

				'base_settings' => array(
						'title' => __('BASE SETTINGS', 'woo-payzen-payment'),
						'type' => 'title'
				),
				'debug' => array(
						'title' => __('Logs', 'woo-payzen-payment'),
						'label' => __('Enable / disable', 'woo-payzen-payment'),
						'type' => 'checkbox',
						'default' => 'yes',
						'description' => sprintf(__('Enable / disable module logs. The log file will be inside <code>%s</code>.', 'woo-payzen-payment'), $log_folder),
				),

				// payment platform access params
				'payment_platform_access' => array(
						'title' => __('PAYMENT PLATFORM ACCESS', 'woo-payzen-payment'),
						'type' => 'title'
				),
				'site_id' => array(
						'title' => __('Shop ID', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '12345678',
						'description' => sprintf(__('The identifier provided by %s.', 'woo-payzen-payment'), 'PayZen'),
				),
				'key_test' => array(
						'title' => __('Certificate in test mode', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '1111111111111111',
						'description' => sprintf(__('Certificate provided by %s for test mode (available in %s Back Office).', 'woo-payzen-payment'), 'PayZen', 'PayZen')
				),
				'key_prod' => array(
						'title' => __('Certificate in production mode', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '2222222222222222',
						'description' => sprintf(__('Certificate provided by %s (available in %s Back Office after enabling production mode).', 'woo-payzen-payment'), 'PayZen', 'PayZen')
				),
				'ctx_mode' => array(
						'title' => __('Mode', 'woo-payzen-payment'),
						'type' => 'select',
						'default' => 'TEST',
						'options' => array(
								'TEST' => __('TEST', 'woo-payzen-payment'),
								'PRODUCTION' => __('PRODUCTION', 'woo-payzen-payment')
						),
						'description' => __('The context mode of this module.', 'woo-payzen-payment')
				),
				'platform_url' => array(
						'title' => __('Payment page URL', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => 'https://secure.payzen.eu/vads-payment/',
						'description' => __('Link to the payment page.', 'woo-payzen-payment'),
						'css' => 'width: 350px;'
				),
				'url_check' => array(
						'title' => sprintf(__('Instant Payment Notification URL to copy into your %s Back Office', 'woo-payzen-payment'), 'PayZen'),
						'type' => 'text',
						'description' => add_query_arg('wc-api', 'WC_Gateway_Payzen', network_home_url('/')),
						'css' => 'display: none;'
				),

				// payment page params
				'payment_page' => array(
						'title' => __('PAYMENT PAGE', 'woo-payzen-payment'),
						'type' => 'title'
				),
				'language' => array(
						'title' => __('Default language', 'woo-payzen-payment'),
						'type' => 'select',
						'default' => 'fr',
						'options' => $this->get_supported_languages(),
						'description' => __('Default language on the payment page.', 'woo-payzen-payment')
				),
				'available_languages' => array(
						'title' => __('Available languages', 'woo-payzen-payment'),
						'type' => 'multiselect',
						'default' => array(''),
						'options' => $this->get_supported_languages(true),
						'description' => __('Languages available on the payment page. If you do not select any, all the supported languages will be available.', 'woo-payzen-payment')
				),
				'capture_delay' => array(
						'title' => __('Capture delay', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '',
						'description' => sprintf(__('The number of days before the bank capture (adjustable in your %s Back Office).', 'woo-payzen-payment'), 'PayZen')
				),
				'validation_mode' => array(
						'title' => __('Validation mode', 'woo-payzen-payment'),
						'type' => 'select',
						'default' => '',
						'options' => array(
								'' => __('Back Office configuration', 'woo-payzen-payment'),
								'0' => __('Automatic', 'woo-payzen-payment'),
								'1' => __('Manual', 'woo-payzen-payment')
						),
						'description' => sprintf(__('If manual is selected, you will have to confirm payments manually in your %s Back Office.', 'woo-payzen-payment'), 'PayZen')
				),

				// selective 3DS
				'selective_3ds' => array(
						'title' => __('SELECTIVE 3DS', 'woo-payzen-payment'),
						'type' => 'title'
				),
				'3ds_min_amount' => array(
						'title' => __('Minimum amount to activate 3-DS', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '',
						'description' => __('Needs subscription to Selective 3-D Secure option.', 'woo-payzen-payment')
				),

				// return to store params
				'return_options' => array(
						'title' => __('RETURN OPTIONS', 'woo-payzen-payment'),
						'type' => 'title'
				),
				'redirect_enabled' => array(
						'title' => __('Automatic redirection', 'woo-payzen-payment'),
						'label' => __('Enable / disable', 'woo-payzen-payment'),
						'type' => 'checkbox',
						'default' => 'no',
	 					'description' => __('If enabled, the buyer is automatically redirected to your site at the end of the payment.', 'woo-payzen-payment')
				),
				'redirect_success_timeout' => array(
						'title' => __('Redirection timeout on success', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '5',
						'description' => __('Time in seconds (0-300) before the buyer is automatically redirected to your website after a successful payment.', 'woo-payzen-payment')
				),
				'redirect_success_message' => array(
						'title' => __('Redirection message on success', 'woo-payzen-payment'),
						'type' => 'multilangtext',
						'default' => array(
								'en_US' => 'Redirection to shop in a few seconds...',
								'en_GB' => 'Redirection to shop in a few seconds...',
								'fr_FR' => 'Redirection vers la boutique dans quelques instants...',
								'de_DE' => 'Weiterleitung zum Shop in Kürze...'
						),
						'description' => __('Message displayed on the payment page prior to redirection after a successful payment.', 'woo-payzen-payment'),
						'css' => 'width: 35em;'
				),
				'redirect_error_timeout' => array(
						'title' => __('Redirection timeout on failure', 'woo-payzen-payment'),
						'type' => 'text',
						'default' => '5',
						'description' => __('Time in seconds (0-300) before the buyer is automatically redirected to your website after a declined payment.', 'woo-payzen-payment')
				),
				'redirect_error_message' => array(
						'title' => __('Redirection message on failure', 'woo-payzen-payment'),
						'type' => 'multilangtext',
						'default' => array(
								'en_US' => 'Redirection to shop in a few seconds...',
								'en_GB' => 'Redirection to shop in a few seconds...',
								'fr_FR' => 'Redirection vers la boutique dans quelques instants...',
								'de_DE' => 'Weiterleitung zum Shop in Kürze...'
						),
						'description' => __('Message displayed on the payment page prior to redirection after a declined payment.', 'woo-payzen-payment'),
						'css' => 'width: 35em;'
				),
				'return_mode' => array(
						'title' => __('Return mode', 'woo-payzen-payment'),
						'type' => 'select',
						'default' => 'GET',
						'options' => array(
								'GET' => 'GET',
								'POST' => 'POST'
						),
						'description' => __('Method that will be used for transmitting the payment result from the payment page to your shop.', 'woo-payzen-payment')
				),
				'order_status_on_success' => array(
						'title' => __('Order Status', 'woo-payzen-payment'),
						'type' => 'select',
						'default' => 'default',
						'options' => $statues,
						'description' => __('Defines the status of orders paid with this payment mode.', 'woo-payzen-payment')
				)
		);
	}

	public function generate_label_html($key, $data) {
		$defaults = array(
				'title'             => '',
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'label',
				'description'       => ''
		);

		$data = wp_parse_args($data, $defaults);

		ob_start();
		if ($data != null)
		?>
			<tr valign="top">
				<td class="forminp" colspan="2">
					<fieldset>
						<label class="<?php echo esc_attr($data['class']); ?>" style="<?php echo esc_attr($data['css']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
						<p class="description"><?php echo wp_kses_post($data['description']); ?></p>
					</fieldset>
				</td>
			</tr>
		<?php
		return ob_get_clean();
	}

	public function generate_multilangtext_html($key, $data) {
		global $wp_version;

		$data['title']			= isset($data['title']) ? $data['title'] : '';
		$data['disabled']		= empty($data['disabled']) ? false : true;
		$data['class'] 			= isset($data['class']) ? ' ' . $data['class'] : '';
		$data['css'] 			= isset($data['css']) ? $data['css'] : '';
		$data['placeholder'] 	= isset($data['placeholder']) ? $data['placeholder'] : '';
		$data['type'] 			= isset($data['type']) ? $data['type'] : 'array';
		$data['desc_tip']		= isset($data['desc_tip']) ? $data['desc_tip'] : false;
		$data['description']	= isset($data['description']) ? $data['description'] : '';
		$data['default']		= isset($data['default']) ? $data['default'] : array('en_US' => '');

		$languages = get_available_languages();
		foreach ($languages as $lang) {
			if (!isset($data['default'][$lang])) {
				$data['default'][$lang] = $data['default']['en_US'];
			}
		}
		$field = $this->plugin_id . $this->id . '_' . $key;
		$value = $this->get_option($key);

		// set input default value
		$default_input_value = isset($value[get_locale()]) ? $value[get_locale()] : $data['default'][get_locale()];
		$default_input_value = esc_attr($default_input_value);

		ob_start();
		?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr($field); ?>_text"><?php echo wp_kses_post($data['title']); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
					<input class="input-text regular-input<?php echo esc_attr($data['class']); ?>" type="text"
							name="<?php echo esc_attr($field) . '[text]'; ?>" id="<?php echo esc_attr($field) . '_text'; ?>" style="<?php echo esc_attr($data['css']); ?>"
							value="<?php echo $default_input_value; ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>
							<?php if (function_exists('get_custom_attribute_html')) {
								  	echo $this->get_custom_attribute_html($data);
								   }?>
					/>

					<?php
					if (version_compare($wp_version, '4.0.0', '>=')) {
						wp_dropdown_languages(array(
								'name'         => esc_attr($field) . '[lang]',
								'id'           => esc_attr($field) . '_lang',
								'selected'     => get_locale(), // default selected is current admin locale
								'languages'    => $languages,
								'translations' => array(),
								'show_available_translations' => false,
						));
					} else {
						$languages = array();
					}

					$languages[] = 'en_US';
					foreach ($languages as $lang) {
						$v = isset($value[$lang]) ? $value[$lang] : $data['default'][$lang]; ?>
						<input type="hidden" id="<?php echo esc_attr($field) . '_' . $lang; ?>"
								name="<?php echo esc_attr($field) . '[' .  $lang . ']'; ?>"
								value="<?php echo esc_attr($v); ?>" />
					<?php
					}
					?>

					<p class="description"><?php echo wp_kses_post($data['description']); ?></p>
				</fieldset>
			</td>
		</tr>

		<script type="text/javascript">
			jQuery(document).ready(function() {
				var key = '#<?php echo esc_attr($field); ?>';
				jQuery(key + '_lang').bind('change', function() {
					var sl = jQuery(key + '_lang').val() || 'en_US';
					var value = jQuery(key + '_' + sl).val();
					jQuery(key + '_text').val(value);
				});

				jQuery(key + '_text').bind('change', function() {
					var sl = jQuery(key + '_lang').val() || 'en_US';
					var value = jQuery(key + '_text').val();
					jQuery(key + '_' + sl).val(value);
				});
			});
		</script>
		<?php

		return ob_get_clean();
	}

	public function validate_multilangtext_field($key, $value = null) {
		$name = $this->plugin_id . $this->id . '_' . $key;
		$oldValue = $this->get_option($key);
		$newValue = !is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : '');

		if (isset($newValue) && is_array($newValue) && !empty($newValue)) {
			unset($newValue['text']);
			unset($newValue['lang']);

			$languages = get_available_languages();
			$languages[] = 'en_US'; // en_US locale is always available for WP
			foreach ($languages as $lang) {
				if (!isset($newValue[$lang]) || !$newValue[$lang]) {
					$newValue[$lang] = $oldValue[$lang];
				}
			}

			return $newValue;
		} else {
			return $oldValue;
		}
	}

	/**
	 * Validate multiselect field.
	 *
	 * @return array
	 */
	public function validate_multiselect_field($key, $value = null) {
		$newValue = !is_null($value) ? $value : ($_POST[$this->plugin_id . $this->id . '_' . $key]);

		if (isset($newValue) && is_array($newValue) && in_array('', $newValue)) {
			return array('');
		} else {
			return parent::validate_multiselect_field($key, $value);
		}
	}

	/**
	 * Check if this gateway is available for the current currency.
	 */
	protected function is_supported_currency() {
		$currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
		if ($currency == null) {
			return false;
		}

		return true;
	}

	/**
	 * Check for PayZen notify Response.
	 **/
	public function payzen_notify_response() {
		@ob_clean();

		$raw_response = (array)stripslashes_deep($_REQUEST);

		$payzen_response = new PayzenResponse(
				$raw_response,
				$this->settings['ctx_mode'],
				$this->settings['key_test'],
				$this->settings['key_prod']
		);

		$from_server = $payzen_response->get('hash') != null;

		if ($from_server) {
			$this->log('Response received from PayZen server URL: ' . print_r($raw_response, true));
		}

		if (!$payzen_response->isAuthentified()) {
			$this->log('Received invalid response from PayZen: authentication failed.');

			if ($from_server) {
				$this->log('SERVER URL PROCESS END');
				die($payzen_response->getOutputForPlatform('auth_fail'));
			} else {
				$this->log('RETURN URL PROCESS END');
				wp_die(sprintf(__('%s response authentication failure.', 'woo-payzen-payment'), 'PayZen'));
			}
		} else {
			header('HTTP/1.1 200 OK');

			$this->payzen_manage_notify_response($payzen_response);
		}
	}

	/**
	 * Valid payment process : update order, send mail, ...
	 **/
	public function payzen_manage_notify_response($payzen_response) {
		global $woocommerce;

		// clear all response messages
		$this->clear_notices();

		$order_id = $payzen_response->get('order_id');
		$from_server = $payzen_response->get('hash') != null;

		$order = new WC_Order((int)$order_id);
		if (!$this->get_order_property($order, 'id') || $this->get_order_property($order, 'order_key') !== $payzen_response->get('order_info')) {
			$this->log('Error: Order (' . $order_id . ') nor found or key does not match received invoice ID.');

			if ($from_server) {
				$this->log('SERVER URL PROCESS END');
				die($payzen_response->getOutputForPlatform('order_not_found'));
			} else {
				$this->log('RETURN URL PROCESS END');
				wp_die(sprintf(__('Error : order with ID #%s cannot be found.', 'woo-payzen-payment'), $order_id));
			}
		}

		if ($this->testmode) {
			$msg = __('<p><u>GOING INTO PRODUCTION</u></p>You want to know how to put your shop into production mode, please go to this URL: ', 'woo-payzen-payment');
			$msg .= '<a href="https://secure.payzen.eu/html/faq/prod" target="_blank">https://secure.payzen.eu/html/faq/prod</a>';

			$this->add_notice($msg);
		}

		// checkout payment URL to allow re-order
		$error_url = $woocommerce->cart->get_checkout_url();

		// backward compatibility
		if (version_compare($woocommerce->version, '2.1.0', '<')) {
			$error_url = $order->get_cancel_order_url();
		}

		if ($this->is_new_order($order, $payzen_response->get('trans_id'))) {
			// order not processed yet or a failed payment (re-order)

			// delete old saved transaction details
			delete_post_meta((int) $order_id, 'Transaction ID');
			delete_post_meta((int) $order_id, 'Card number');
			delete_post_meta((int) $order_id, 'Payment mean');
			delete_post_meta((int) $order_id, 'Card expiry');

			// store transaction details
			update_post_meta((int) $order_id, 'Transaction ID', $payzen_response->get('trans_id'));
			update_post_meta((int) $order_id, 'Card number', $payzen_response->get('card_number'));
			update_post_meta((int) $order_id, 'Payment mean', $payzen_response->get('card_brand'));

			$expiry = str_pad($payzen_response->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $payzen_response->get('expiry_year');
			if (! $payzen_response->get('expiry_month')) {
				$expiry = '';
			}
			update_post_meta((int) $order_id, 'Card expiry', $expiry);
			$note = $payzen_response->getCompleteMessage("\n");

			if ($payzen_response->isAcceptedPayment()) {
				$this->log('Payment successfull, let\'s save order #' . $order_id);

				// payment completed
				$note .= "\n";
				$note .= sprintf(__('Transaction %s.', 'woo-payzen-payment'), $payzen_response->get('trans_id'));
				$order->add_order_note($note);
				$order->payment_complete();

				if ($from_server) {
					$this->log('Payment completed successfully by server URL call.');
					$this->log('SERVER URL PROCESS END');

					die ($payzen_response->getOutputForPlatform('payment_ok'));
				} else {
					$this->log('Warning ! IPN URL call has not worked. Payment completed by return URL call.');

					if ($this->testmode) {
						$ipn_url_warn = sprintf(__('The automatic notification (peer to peer connection between the payment platform and your shopping cart solution) hasn\'t worked. Have you correctly set up the notification URL in the %s Back Office ?', 'woo-payzen-payment'), 'PayZen');
						$ipn_url_warn .= '<br />';
						$ipn_url_warn .= __('For understanding the problem, please read the documentation of the module : <br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;To read carefully before going further&raquo;<br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;Notification URL settings&raquo;', 'woo-payzen-payment');

						$this->add_notice($ipn_url_warn, 'error');
					}

					$this->log('RETURN URL PROCESS END');
					wp_redirect($this->get_return_url($order));
					die();
				}
			} else {
				if (!$payzen_response->isCancelledPayment()) {
					$note .= "\n";
					$note .= sprintf(__('Transaction %s.', 'woo-payzen-payment'), $payzen_response->get('trans_id'));
				}
				$order->add_order_note($note);
				$order->update_status('failed');

				$this->log('Payment failed or cancelled. ' . $payzen_response->getLogString());

				if ($from_server) {
					$this->log('SERVER URL PROCESS END');
					die($payzen_response->getOutputForPlatform('payment_ko'));
				} else {
					if (!$payzen_response->isCancelledPayment()) {
						$this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
					}

					$this->log('RETURN URL PROCESS END');
					wp_redirect($error_url);
					die();
				}
			}
		} else {
			$this->log('Order #' . $order_id . ' is already processed. Just show payment result.');

			if ($payzen_response->isAcceptedPayment() && key_exists($this->get_order_property($order, 'status'), self::$success_order_statues)) {
				$this->log('Payment successfull reconfirmed.');

				// order success registered and payment succes received
				if ($from_server) {
					$this->log('SERVER URL PROCESS END');
					die ($payzen_response->getOutputForPlatform('payment_ok_already_done'));
				} else {
					$this->log('RETURN URL PROCESS END');
					wp_redirect($this->get_return_url($order));
					die();
				}
			} elseif (!$payzen_response->isAcceptedPayment() && ($this->get_order_property($order, 'status') === 'failed' || $this->get_order_property($order, 'status') === 'cancelled')) {
				$this->log('Payment failed reconfirmed.');

				// order failure registered and payment error received
				if ($from_server) {
					$this->log('SERVER URL PROCESS END');
					die($payzen_response->getOutputForPlatform('payment_ko_already_done'));
				} else {
					$this->log('RETURN URL PROCESS END');

					if (!$payzen_response->isCancelledPayment()) {
						$this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
					}

					wp_redirect($error_url);
					die();
				}
			} else {
				$this->log('Error ! Invalid payment result received for already saved order. Payment result : ' . $payzen_response->get('result') . ', Order status : ' . $this->get_order_property($order, 'status'));

				// registered order status not match payment result
				if ($from_server) {
					$this->log('SERVER URL PROCESS END');
					die($payzen_response->getOutputForPlatform('payment_ko_on_order_ok'));
				} else {
					$this->log('RETURN URL PROCESS END');
					wp_die(sprintf(__('Error : invalid payment code received for already processed order (%s).', 'woo-payzen-payment'), $order_id));
				}
			}
		}
	}

	private function is_new_order($order, $trs_id) {
		if ($this->get_order_property($order, 'status') === 'pending') {
			return true;
		}

		if ($this->get_order_property($order, 'status') === 'failed' || $this->get_order_property($order, 'status') === 'cancelled') {
			return get_post_meta((int) $this->get_order_property($order, 'id'), 'Transaction ID', true) !== $trs_id;
		}

		return false;
	}

	public function payzen_complete_order_status($status, $order_id) {
		$order = wc_get_order($order_id);

		if ($this->get_order_property($order, 'payment_method') == $this->id && $this->settings['order_status_on_success'] != 'default') {
			return $this->settings['order_status_on_success'];
		}

		return  $status;
	}

	protected function log($msg) {
		if (!$this->debug) {
			return;
		}

		$this->logger->add('payzen', $msg);
	}

	protected function clear_notices() {
		global $woocommerce;

		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		} else {
			$woocommerce->clear_messages();
		}
	}

	protected function add_notice($msg, $type='success') {
		global $woocommerce;

		if (function_exists('wc_add_notice')) {
			wc_add_notice($msg, $type);
		} else {
			if ($type == 'error') {
				$woocommerce->add_error($msg);
			} else {
				$woocommerce->add_message($msg);
			}
		}
	}

	public function payzen_add_order_email_payment_result($order, $sent_to_admin, $plain_text) {
		$trans_id = get_post_meta((int) $this->get_order_property($order, 'id'), 'Transaction ID', true);

		$notes = $this->get_order_notes($this->get_order_property($order, 'id'));
		foreach ($notes as $note) {
			if (strpos($note, $trans_id) !== false) {
				$payzen_order_note = $note;
				break;
			}
		}

		if (isset($payzen_order_note) && $this->get_order_property($order, 'payment_method') == $this->id) {
			if ($plain_text) {
				echo strtoupper(__('Payment', 'woo-payzen-payment')) . "\n\n" . $payzen_order_note . "\n\n";
			} else {
				echo '<h2>' . __('Payment', 'woo-payzen-payment') . '</h2><p>' . str_replace("\n", '<br>', $payzen_order_note) . '</p>';
			}
		}
	}

	/**
	 * Get all notes of a specified order.
	 * @return array[string]
	 */
	private function get_order_notes($order_id) {
		remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));

		$comments = get_comments(array(
				'post_id' => $order_id,
				'approve' => 'approve',
				'type'    => 'order_note',
		));
		$notes = wp_list_pluck($comments, 'comment_content');

		add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
		return $notes;
	}

	protected function get_order_property($order, $property_name) {
		$method = 'get_' . $property_name;

		if (method_exists($order, $method)) {
			return $order->$method();
		} else {
			return isset($order->$property_name) ? $order->$property_name : null;
		}
	}
}
