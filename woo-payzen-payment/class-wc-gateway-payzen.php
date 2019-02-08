<?php
/**
 * Copyright © Lyra Network and contributors.
 * This file is part of PayZen plugin for WooCommerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra-network.com/)
 * @author    Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @copyright Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL v2)
 */

if (! defined('ABSPATH')) {
    exit; // exit if accessed directly
}

class WC_Gateway_Payzen extends WC_Payment_Gateway
{
    const GATEWAY_CODE = 'PayZen';
    const GATEWAY_NAME = 'PayZen';
    const BACKOFFICE_NAME = 'PayZen';
    const GATEWAY_URL = 'https://secure.payzen.eu/vads-payment/';
    const REST_URL = 'https://api.payzen.eu/api-payment/';
    const STATIC_URL = 'https://api.payzen.eu/static/';
    const SITE_ID = '12345678';
    const KEY_TEST = '1111111111111111';
    const KEY_PROD = '2222222222222222';
    const CTX_MODE = 'TEST';
    const SIGN_ALGO = 'SHA-256';
    const LANGUAGE = 'fr';

    const CMS_IDENTIFIER = 'WooCommerce_2.x-3.x';
    const SUPPORT_EMAIL = 'support@payzen.eu';
    const PLUGIN_VERSION = '1.7.0';
    const GATEWAY_VERSION = 'V2';

    protected $admin_page;
    protected $admin_link;
    protected $reset_admin_link;

    protected $general_settings = array();
    protected $general_form_fields = array();
    protected $notices = array();

    public function __construct()
    {
        $this->id = 'payzen';
        $this->has_fields = false;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('General configuration', 'woo-payzen-payment');

        // init common vars
        $this->payzen_init();

        // load the form fields
        $this->init_form_fields();

        // load the module settings
        $this->init_settings();

        $this->title = __('General configuration', 'woo-payzen-payment');
        $this->enabled = false;
        $this->testmode = ($this->get_general_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_general_option('debug') == 'yes') ? true : false;

        if ($this->payzen_is_section_loaded()) {
            // reset payzen common admin form action
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // adding style to admin form action
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // update payzen admin form action
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // return from payment gateway action
        add_action('woocommerce_api_wc_gateway_payzen', array($this, 'payzen_notify_response'));

        // filter to allow order status override
        add_filter('woocommerce_payment_complete_order_status', array($this, 'payzen_complete_order_status'), 10, 2);

        // customize email
        add_action('woocommerce_email_after_order_table', array($this, 'payzen_add_order_email_payment_result'), 10, 3);

        // print our notices when payment was sucessful
        add_action('woocommerce_before_template_part', array($this, 'payzen_notices'), 10, 4);
    }

    protected function payzen_is_section_loaded()
    {
        $current_section = isset($_GET['section']) ? $_GET['section'] : null;
        return ($current_section === $this->id) || (strtolower($current_section) === strtolower(get_class($this)));
    }


    protected function payzen_init()
    {
        $this->logger = new WC_Logger();

        // init API
        $this->payzen_request = new PayzenRequest();

        if ($this->payzen_is_section_loaded()) {
            $this->admin_page = $_GET['page'];

            $this->admin_link = admin_url('admin.php?page=' . $_GET['page'] . '&tab=' . $_GET['tab'] . '&section=' . $_GET['section']);

            $this->reset_admin_link = add_query_arg('noheader', '', add_query_arg('reset', '', $this->admin_link));
            $this->reset_admin_link = wp_nonce_url($this->reset_admin_link, $_GET['page']);
        }
    }

    public function payzen_admin_head_style()
    {
        ?>
        <style>
            .payzen p.description {
                color: #0073aa !important;
                font-style: normal !important;
            }

            #woocommerce_payzen_url_check + p.description span.url {
                color: #23282d !important;
                font-size: 16px;
                font-weight: bold;
            }

            #woocommerce_payzen_url_check + p.description span.desc {
                color: red !important;
            }

            #woocommerce_payzen_url_check + p.description img {
                vertical-align: middle;
                margin-right: 5px;
            }
        </style>
        <?php
    }

    /**
     * Admin panel options.
     */
    public function admin_options()
    {
        if (! $this->is_supported_currency()) {
            echo '<div class="inline error"><p><strong>' . __('Gateway disabled', 'woo-payzen-payment') . ': ' . sprintf(__('%s does not support your store currency.', 'woo-payzen-payment'), self::GATEWAY_NAME) . '</strong></p></div>';
        }

        if (get_transient($this->id . '_settings_reset')) {
            delete_transient($this->id . '_settings_reset');

            echo '<div class="inline updated"><p><strong>' . sprintf(__('Your %s module configuration is successfully reset.', 'woo-payzen-payment'), self::GATEWAY_NAME) . '</strong></p></div>';
        }
        ?>

        <br />
        <h3><?php echo self::GATEWAY_NAME; ?></h3>
        <p><?php echo sprintf(__('The module works by sending users to %s in order to select their payment mean and enter their payment information.', 'woo-payzen-payment'), self::GATEWAY_NAME); ?></p>

        <?php foreach ($this->notices as $notice) { ?>
            <p style="background: none repeat scroll 0 0 #FFFFE0; border: 1px solid #E6DB55; margin: 0 0 20px; padding: 10px; font-weight: bold;"><?php echo $notice; ?></p>
        <?php } ?>

        <section class="payzen">
        <table class="form-table">
            <?php $this->generate_settings_html(); // generate the HTML For the settings form ?>
        </table>
        </section>

        <a href="<?php echo $this->reset_admin_link; ?>"><?php _e('Reset configuration', 'woo-payzen-payment');?></a>

        <?php
    }

    public function payzen_reset_admin_options()
    {
        // if not reset action do nothing
        if (! isset($_GET['reset'])) {
            return;
        }

        // check if correct link
        if (! $this->payzen_is_section_loaded()) {
            return;
        }

        delete_option('woocommerce_' . $this->id . '_settings');

        // transcient flag to display reset message
        set_transient($this->id . '_settings_reset', 'true');

        wp_redirect($this->admin_link);
        die();
    }

    protected function get_supported_languages()
    {
        $langs = array();

        foreach (PayzenApi::getSupportedLanguages() as $code => $label) {
            $langs[$code] = __($label, 'woo-payzen-payment');
        }

        return $langs;
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        global $woocommerce, $payzen_plugin_features;

        // get log folder
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

        // get documentation links
        $docs = '';
        $minor = substr(self::PLUGIN_VERSION, 0, strrpos(self::PLUGIN_VERSION, '.'));
        $doc_pattern = plugin_dir_path(__FILE__) . 'installation_doc/';
        $doc_pattern .= self::GATEWAY_CODE . '_' . self::CMS_IDENTIFIER . '_v' . $minor . '*.pdf';
        $filenames = glob($doc_pattern);

        $languages = array(
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español'
            // complete when other languages are managed
        );

        foreach ($filenames as $filename) {
            $base_filename = basename($filename, '.pdf');
            $lang = substr($base_filename, -2); // extract language code

            $docs .= '<a style="margin-left: 10px; text-decoration: none; text-transform: uppercase;" href="' . WC_PAYZEN_PLUGIN_URL
                . 'installation_doc/' . $base_filename . '.pdf" target="_blank">' . $languages[$lang] . '</a>';
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
                'description' => '<b><a href="mailto:' . self::SUPPORT_EMAIL . '">' . self::SUPPORT_EMAIL . '</a></b>',
                'css' => 'display: none;'
            ),
            'contrib_version' => array(
                'title' => __('Module version', 'woo-payzen-payment'),
                'type' => 'text',
                'description' => self::PLUGIN_VERSION,
                'css' => 'display: none;'
            ),
            'platform_version' => array(
                'title' => __('Gateway version', 'woo-payzen-payment'),
                'type' => 'text',
                'description' => self::GATEWAY_VERSION,
                'css' => 'display: none;'
            ),
            'doc_link' => array(
                'title' => __('Click to view the module configuration documentation :', 'woo-payzen-payment') . $docs,
                'type' => 'label',
                'css' => 'font-weight: bold; color: red; cursor: auto !important; text-transform: uppercase;'
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

            // payment gateway access params
            'payment_gateway_access' => array(
                'title' => __('PAYMENT GATEWAY ACCESS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'site_id' => array(
                'title' => __('Shop ID', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => self::SITE_ID,
                'description' => sprintf(__('The identifier provided by %s.', 'woo-payzen-payment'), self::GATEWAY_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'key_test' => array(
                'title' => __('Key in test mode', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => self::KEY_TEST,
                'description' => sprintf(__('Key provided by %s for test mode (available in %s Back Office).', 'woo-payzen-payment'), self::GATEWAY_NAME, self::BACKOFFICE_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'key_prod' => array(
                'title' => __('Key in production mode', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => self::KEY_PROD,
                'description' => sprintf(__('Key provided by %s (available in %s Back Office after enabling production mode).', 'woo-payzen-payment'), self::GATEWAY_NAME, self::BACKOFFICE_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'ctx_mode' => array(
                'title' => __('Mode', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => self::CTX_MODE,
                'options' => array(
                    'TEST' => __('TEST', 'woo-payzen-payment'),
                    'PRODUCTION' => __('PRODUCTION', 'woo-payzen-payment')
                ),
                'description' => __('The context mode of this module.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'sign_algo' => array(
                'title' => __('Signature algorithm', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => self::SIGN_ALGO,
                'options' => array(
                    PayzenApi::ALGO_SHA1 => 'SHA-1',
                    PayzenApi::ALGO_SHA256 => 'HMAC-SHA-256'
                ),
                'description' => sprintf(__('Algorithm used to compute the payment form signature. Selected algorithm must be the same as one configured in the %s Back Office.<br /><b>The HMAC-SHA-256 algorithm should not be activated if it is not yet available in the %s Back Office, the feature will be available soon.</b>', 'woo-payzen-payment'), self::BACKOFFICE_NAME, self::BACKOFFICE_NAME),
                'class' => 'wc-enhanced-select'
            ),
            'url_check' => array(
                'title' => __('Instant Payment Notification URL', 'woo-payzen-payment'),
                'type' => 'text',
                'description' => '<span class="url">' . add_query_arg('wc-api', 'WC_Gateway_Payzen', network_home_url('/')) . '</span><br />' .
                    '<img src="' . esc_url(WC_PAYZEN_PLUGIN_URL . 'assets/images/warn.png') . '"><span class="desc">' . sprintf(__('URL to copy into your %s Back Office > Settings > Notification rules.', 'woo-payzen-payment'), self::BACKOFFICE_NAME) . '</span>',
                'css' => 'display: none;'
            ),
            'platform_url' => array(
                'title' => __('Payment page URL', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => self::GATEWAY_URL,
                'description' => __('Link to the payment page.', 'woo-payzen-payment'),
                'css' => 'width: 350px;'
            ),

            // payment page params
            'payment_page' => array(
                'title' => __('PAYMENT PAGE', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            'language' => array(
                'title' => __('Default language', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => self::LANGUAGE,
                'options' => $this->get_supported_languages(),
                'description' => __('Default language on the payment page.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'available_languages' => array(
                'title' => __('Available languages', 'woo-payzen-payment'),
                'type' => 'multiselect',
                'default' => array(),
                'options' => $this->get_supported_languages(),
                'description' => __('Languages available on the payment page. If you do not select any, all the supported languages will be available.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'capture_delay' => array(
                'title' => __('Capture delay', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => '',
                'description' => sprintf(__('The number of days before the bank capture (adjustable in your %s Back Office).', 'woo-payzen-payment'), self::BACKOFFICE_NAME)
            ),
            'validation_mode' => array(
                'title' => __('Validation mode', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => '',
                'options' => array(
                    '' => sprintf(__('%s Back Office configuration', 'woo-payzen-payment'), self::BACKOFFICE_NAME),
                    '0' => __('Automatic', 'woo-payzen-payment'),
                    '1' => __('Manual', 'woo-payzen-payment')
                ),
                'description' => sprintf(__('If manual is selected, you will have to confirm payments manually in your %s Back Office.', 'woo-payzen-payment'), self::BACKOFFICE_NAME),
                'class' => 'wc-enhanced-select'
            ),

            // selective 3DS
            'selective_3ds' => array(
                'title' => __('SELECTIVE 3DS', 'woo-payzen-payment'),
                'type' => 'title'
            ),
            '3ds_min_amount' => array(
                'title' => __('Disable 3DS', 'woo-payzen-payment'),
                'type' => 'text',
                'default' => '',
                'description' => __('Amount below which 3DS will be disabled. Needs subscription to selective 3DS option. For more information, refer to the module documentation.', 'woo-payzen-payment')
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
                    'de_DE' => 'Weiterleitung zum Shop in Kürze...',
                    'es_ES' => 'Redirección a la tienda en unos momentos...'
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
                    'de_DE' => 'Weiterleitung zum Shop in Kürze...',
                    'es_ES' => 'Redirección a la tienda en unos momentos...'
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
                'description' => __('Method that will be used for transmitting the payment result from the payment page to your shop.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'order_status_on_success' => array(
                'title' => __('Order Status', 'woo-payzen-payment'),
                'type' => 'select',
                'default' => 'default',
                'options' => self::get_success_order_statuses(true),
                'description' => __('Defines the status of orders paid with this payment mode.', 'woo-payzen-payment'),
                'class' => 'wc-enhanced-select'
            )
        );

        if ($payzen_plugin_features['qualif']) {
            // tests will be made on qualif, no test mode available
            unset($this->form_fields['key_test']);

            $this->form_fields['ctx_mode']['disabled'] = true;
        }

        if ($payzen_plugin_features['shatwo']) {
            // HMAC-SHA-256 already available, update field description
            $desc = preg_replace('#<br /><b>[^<>]+</b>#', '', $this->form_fields['sign_algo']['description']);
            $this->form_fields['sign_algo']['description'] = $desc;
        }

        // save general form fields
        foreach ($this->form_fields as $k => $v) {
            $this->general_form_fields[$k] = $v;
        }
    }

    protected function init_general_settings()
    {
        $this->general_settings = get_option('woocommerce_payzen_settings', null);

        // if there are no settings defined, use defaults
        if (! is_array($this->general_settings) || empty($this->general_settings)) {
            $this->general_settings = array();

            foreach ($this->general_form_fields as $k => $v) {
                $this->general_settings[$k] = isset($v['default']) ? $v['default'] : '';
            }
        }
    }

    protected function get_general_option($key, $empty_value = null)
    {
        if (empty($this->general_settings)) {
            $this->init_general_settings();
        }

        // get empty string if unset
        if (! isset($this->general_settings[$key])) {
            $this->general_settings[$key] = '';
        }

        if (! is_null($empty_value) && ($this->general_settings[$key] === '')) {
            $this->general_settings[$key] = $empty_value;
        }

        return $this->general_settings[$key];
    }

    public function generate_label_html($key, $data)
    {
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
        if ($data != null) {
        ?>
            <tr valign="top">
                <td class="forminp" colspan="2" style="padding-left: 0;">
                    <fieldset>
                        <label class="<?php echo esc_attr($data['class']); ?>" style="<?php echo esc_attr($data['css']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                        <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                    </fieldset>
                </td>
            </tr>
        <?php
        }

        return ob_get_clean();
    }

    public function generate_multilangtext_html($key, $data)
    {
        global $wp_version;

        $data['title']           = isset($data['title']) ? $data['title'] : '';
        $data['disabled']        = empty($data['disabled']) ? false : true;
        $data['class']           = isset($data['class']) ? ' ' . $data['class'] : '';
        $data['css']             = isset($data['css']) ? $data['css'] : '';
        $data['placeholder']     = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type']            = isset($data['type']) ? $data['type'] : 'array';
        $data['desc_tip']        = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description']     = isset($data['description']) ? $data['description'] : '';
        $data['default']         = isset($data['default']) ? $data['default'] : array('en_US' => '');

        $languages = get_available_languages();
        foreach ($languages as $lang) {
            if (! isset($data['default'][$lang])) {
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
                            value="<?php echo $default_input_value; ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>>

                    <?php
                    if (version_compare($wp_version, '4.0.0', '>=')) {
                        $select = wp_dropdown_languages(array(
                            'name'         => esc_attr($field) . '[lang]',
                            'id'           => esc_attr($field) . '_lang',
                            'selected'     => get_locale(), // default selected is current admin locale
                            'languages'    => $languages,
                            'translations' => array(),
                            'show_available_translations' => false,
                            'echo' => false
                        ));

                        echo str_replace('<select', '<select style="width: auto; height: auto; vertical-align: top;"', $select);
                    } else {
                        $languages = array();
                    }

                    $languages[] = 'en_US';
                    foreach ($languages as $lang) {
                        $v = isset($value[$lang]) ? $value[$lang] : $data['default'][$lang]; ?>
                        <input type="hidden" id="<?php echo esc_attr($field) . '_' . $lang; ?>"
                                name="<?php echo esc_attr($field) . '[' .  $lang . ']'; ?>"
                                value="<?php echo esc_attr($v); ?>">
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

    public function validate_multilangtext_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $old_value = $this->get_option($key);
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : '');

        if (isset($new_value) && is_array($new_value) && ! empty($new_value)) {
            unset($new_value['text']);
            unset($new_value['lang']);

            $languages = get_available_languages();
            $languages[] = 'en_US'; // en_US locale is always available for WP
            foreach ($languages as $lang) {
                if (! isset($new_value[$lang]) || ! $new_value[$lang]) {
                    $new_value[$lang] = $old_value[$lang];
                }
            }

            return $new_value;
        } else {
            return $old_value;
        }
    }

    /**
     * Validate multiselect field.
     *
     * @return array
     */
    public function validate_multiselect_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array(''));

        if (isset($new_value) && is_array($new_value) && in_array('', $new_value)) {
            return array('');
        } else {
            return parent::validate_multiselect_field($key, $value);
        }
    }

    public function validate_ctx_mode_field($key, $value = null)
    {
        global $payzen_plugin_features;

        $name = $this->plugin_id . $this->id . '_' . $key;
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : null);

        if (! $new_value && $payzen_plugin_features['qualif']) {
            // when using qualif for testing, mode is always PRODUCTION
            return 'PRODUCTION';
        }

        return parent::validate_select_field($key, $value);
    }

    public function validate_3ds_min_amount_field($key, $value = null)
    {
        if (empty($value)) {
            return '';
        }

        $new_value = parent::validate_text_field($key, $value);

        $new_value = str_replace(',', '.', $new_value);
        if (! is_numeric($new_value) || ($new_value < 0)) { // invalid value, restore old
            return $this->get_option($key);
        }

        return $new_value;
    }

    /**
     * Check if this gateway is available for the current currency.
     */
    protected function is_supported_currency()
    {
        $currency = PayzenApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        if ($currency == null) {
            return false;
        }

        return true;
    }

    /**
     * Check for notify response.
     **/
    public function payzen_notify_response()
    {
        global $woocommerce;

        @ob_clean();

        $raw_response = (array) stripslashes_deep($_REQUEST);

        $payzen_response = new PayzenResponse(
            $raw_response,
            $this->get_general_option('ctx_mode'),
            $this->get_general_option('key_test'),
            $this->get_general_option('key_prod'),
            $this->get_general_option('sign_algo')
        );

        $from_server = $payzen_response->get('hash') != null;

        if (! $payzen_response->isAuthentified()) {
            $this->log('Authentication failed: received invalid response with parameters: ' . print_r($raw_response, true));
            $this->log('Signature algorithm selected in module settings must be the same as one selected in gateway Back Office.');

            if ($from_server) {
                $this->log('IPN URL PROCESS END');
                die($payzen_response->getOutputForGateway('auth_fail'));
            } else {
                // fatal error, empty cart
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                $this->log('RETURN URL PROCESS END');

                $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $woocommerce->cart->get_cart_url();
                $iframe = $payzen_response->get('action_mode') === 'IFRAME';
                $this->payzen_redirect($cart_url, $iframe);
            }
        } else {
            header('HTTP/1.1 200 OK');

            $this->payzen_manage_notify_response($payzen_response);
        }
    }

    /**
     * Valid payment process : update order, send mail, ...
     **/
    public function payzen_manage_notify_response($payzen_response)
    {
        global $woocommerce, $payzen_plugin_features;

        // clear all response messages
        $this->clear_notices();

        $order_id = $payzen_response->get('order_id');
        $from_server = $payzen_response->get('hash') != null;
        $iframe = $payzen_response->get('action_mode') == 'IFRAME';

        // cart URL
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $woocommerce->cart->get_cart_url();

        $order = new WC_Order((int) $order_id);
        if (! $this->get_order_property($order, 'id') || ($this->get_order_property($order, 'order_key') !== $payzen_response->get('order_info'))) {
            $this->log("Error: order #$order_id not found or key does not match received invoice ID.");

            if ($from_server) {
                $this->log('IPN URL PROCESS END');
                die($payzen_response->getOutputForGateway('order_not_found'));
            } else {
                // fatal error, empty cart
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                $this->log('RETURN URL PROCESS END');
                $this->payzen_redirect($cart_url, $iframe);
            }
        }

        if (!$from_server && $this->testmode && $payzen_plugin_features['prodfaq']) {
            $msg = __('<p><u>GOING INTO PRODUCTION</u></p>You want to know how to put your shop into production mode, please read chapters « Proceeding to test phase » and « Shifting the shop to production mode » in the documentation of the module.', 'woo-payzen-payment');

            $this->add_notice($msg);
        }

        // checkout payment URL to allow re-order
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : $woocommerce->cart->get_checkout_url();

        // backward compatibility
        if (version_compare($woocommerce->version, '2.1.0', '<')) {
            $checkout_url = $cart_url = $order->get_cancel_order_url();
        }

        // use the selected susbscriptions handler
        $method = $this->get_order_property($order, 'payment_method');
        $subscriptions_handler = self::payzen_subscriptions_handler($method);

        if ($this->is_new_order($order, $payzen_response->get('trans_id'))) {
            // order not processed yet or a failed payment (re-order)

            // add order note
            self::payzen_add_order_note($payzen_response, $order);

            // delete old saved transaction details
            delete_post_meta((int) $order_id, 'Transaction ID');
            delete_post_meta((int) $order_id, 'Card number');
            delete_post_meta((int) $order_id, 'Means of payment');
            delete_post_meta((int) $order_id, 'Card expiry');

            // store transaction details
            update_post_meta((int) $order_id, 'Transaction ID', $payzen_response->get('trans_id'));
            update_post_meta((int) $order_id, 'Card number', $payzen_response->get('card_number'));
            update_post_meta((int) $order_id, 'Means of payment', $payzen_response->get('card_brand'));

            $expiry = '';
            if ($payzen_response->get('expiry_month') && $payzen_response->get('expiry_year')) {
                $expiry = str_pad($payzen_response->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $payzen_response->get('expiry_year');
            }

            update_post_meta((int) $order_id, 'Card expiry', $expiry);

            if (self::is_successful_action($payzen_response)) {
                if ($payzen_response->isPendingPayment()) {
                    // payment is pending
                    $this->log("Payment is pending, make order #$order_id in on-hold status.");

                    $order->update_status('on-hold');
                } else {
                    // payment completed
                    $this->log("Payment successfull, let's complete order #$order_id.");

                    $order->payment_complete();
                }

                // try to save identifier if any
                $this->payzen_save_identifier($order, $payzen_response);

                if ($subscriptions_handler) {
                    // try to save subscritption info if any
                    $this->payzen_save_recurrence($order, $payzen_response);

                    $subscriptions_handler->process_subscription($order, $payzen_response);
                }

                if ($from_server) {
                    $this->log("Payment processed successfully by IPN URL call for order #$order_id.");
                    $this->log('IPN URL PROCESS END');

                    die($payzen_response->getOutputForGateway('payment_ok'));
                } else {
                    $this->log("Warning ! IPN URL call has not worked. Payment completed by return URL call for order #$order_id.");

                    if ($this->testmode) {
                        $ipn_url_warn = sprintf(__('The automatic validation has not worked. Have you correctly set up the notification URL in the %s Back Office?', 'woo-payzen-payment'), self::BACKOFFICE_NAME);
                        $ipn_url_warn .= '<br />';
                        $ipn_url_warn .= __('For understanding the problem, please read the documentation of the module : <br />&nbsp;&nbsp;&nbsp;- Chapter &laquo; To read carefully before going further &raquo;<br />&nbsp;&nbsp;&nbsp;- Chapter &laquo; Notification URL settings &raquo;', 'woo-payzen-payment');

                        $this->add_notice($ipn_url_warn, 'error');
                    }

                    $this->log('RETURN URL PROCESS END');
                    $this->payzen_redirect($this->get_return_url($order), $iframe);
                }
            } else {
                $order->update_status('failed');
                $this->log("Payment failed or cancelled for order #$order_id. {$payzen_response->getLogMessage()}");

                if ($subscriptions_handler) {
                    // try to manage subscription if any
                    $subscriptions_handler->process_subscription($order, $payzen_response);
                }

                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($payzen_response->getOutputForGateway('payment_ko'));
                } else {
                    $this->log('RETURN URL PROCESS END');

                    if (! $payzen_response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
                        $this->payzen_redirect($cart_url, $iframe);
                    } else {
                        $this->payzen_redirect($checkout_url, $iframe);
                    }
                }
            }
        } else {
            $this->log("Order #$order_id is already saved.");

            if ($payzen_response->get('recurrence_number') && $subscriptions_handler) {
                // IPN URL called for each recurrence creation on gateway
                $this->log("New recurrence created for order #$order_id. Let subscriptions handler do the work.");

                $subscriptions_handler->update_subscription($order, $payzen_response);

                if (self::is_successful_action($payzen_response)) {
                    $this->log("Payment recurrence processed successfully by IPN URL call for order #$order_id.");
                    echo($payzen_response->getOutputForGateway('payment_ok'));
                } else {
                    echo($payzen_response->getOutputForGateway('payment_ko'));
                }

                $this->log('IPN URL PROCESS END');
                die();
            }

            if ($from_server && ($this->get_order_property($order, 'status') === 'on-hold')) {
                switch (true) {
                    case $payzen_response->isPendingPayment():
                        $this->log("Order #$order_id is in a pending status and stay in the same status.");
                        echo($payzen_response->getOutputForGateway('payment_ok_already_done'));
                        break;
                    case self::is_successful_action($payzen_response):
                        $this->log("Order #$order_id is in a pending status and payment is accepted. Complete order payment.");
                        $order->payment_complete();

                        echo($payzen_response->getOutputForGateway('payment_ok'));
                        break;
                    default:
                        $this->log("Order #$order_id is in a pending status and payment failed. Cancel order.");

                        // add order note
                        self::payzen_add_order_note($payzen_response, $order);

                        $order->update_status('failed');
                        echo($payzen_response->getOutputForGateway('payment_ko'));
                        break;
                }

                $this->log('IPN URL PROCESS END');
                die();
            } elseif (self::is_successful_action($payzen_response) && key_exists($this->get_order_property($order, 'status'), self::get_success_order_statuses())) {
                $status = $payzen_response->isPendingPayment() ? 'pending' : 'successfull';
                $this->log("Payment $status confirmed for order #$order_id.");

                // order success registered and payment succes received
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($payzen_response->getOutputForGateway('payment_ok_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');
                    $this->payzen_redirect($this->get_return_url($order), $iframe);
                }
            } elseif (! self::is_successful_action($payzen_response) && ($this->get_order_property($order, 'status') === 'failed' || $this->get_order_property($order, 'status') === 'cancelled')) {
                $this->log("Payment failed confirmed for order #$order_id.");

                // order failure registered and payment error received
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($payzen_response->getOutputForGateway('payment_ko_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');

                    if (! $payzen_response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-payzen-payment'), 'error');
                        $this->payzen_redirect($cart_url, $iframe);
                    } else {
                        $this->payzen_redirect($checkout_url, $iframe);
                    }
                }
            } else {
                $this->log("Error ! Invalid payment result received for already saved order #$order_id. Payment result : {$payzen_response->getTransStatus()}, Order status : {$this->get_order_property($order, 'status')}.");

                // registered order status not match payment result
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($payzen_response->getOutputForGateway('payment_ko_on_order_ok'));
                } else {
                    // fatal error, empty cart
                    $woocommerce->cart->empty_cart();
                    $this->add_notice(__('An error has occurred in the payment process.', 'woo-payzen-payment'), 'error');

                    $this->log('RETURN URL PROCESS END');
                    $this->payzen_redirect($cart_url, $iframe);
                }
            }
        }
    }

    private function is_new_order($order, $trs_id)
    {
        if ($this->get_order_property($order, 'status') === 'pending') {
            return true;
        }

        if ($this->get_order_property($order, 'status') === 'failed'
            || $this->get_order_property($order, 'status') === 'cancelled') {
            return get_post_meta((int) $this->get_order_property($order, 'id'), 'Transaction ID', true) !== $trs_id;
        }

        return false;
    }

    public function payzen_complete_order_status($status, $order_id)
    {
        $order = new WC_Order((int)$order_id);

        if ((strpos($this->get_order_property($order, 'payment_method'), 'payzen') === 0)
            && ($this->get_general_option('order_status_on_success') != 'default')) {
            return $this->get_general_option('order_status_on_success');
        }

        return  $status;
    }

    protected function log($msg)
    {
        if (! $this->debug) {
            return;
        }

        $this->logger->add('payzen', $msg);
    }

    protected function clear_notices()
    {
        global $woocommerce;

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        } else {
            $woocommerce->clear_messages();
        }
    }

    protected function add_notice($msg, $type = 'success')
    {
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

    public function payzen_add_order_email_payment_result($order, $sent_to_admin, $plain_text = false)
    {
        if (strpos($this->get_order_property($order, 'payment_method'), 'payzen') !== 0) {
            return;
        }

        $trans_id = get_post_meta((int) $this->get_order_property($order, 'id'), 'Transaction ID', true);
        if (! $trans_id) {
            return;
        }

        $notes = self::get_order_notes($this->get_order_property($order, 'id'));
        foreach ($notes as $note) {
            if (strpos($note, $trans_id) !== false) {
                $payzen_order_note = $note;
                break;
            }
        }

        if (isset($payzen_order_note)) {
            if ($plain_text) {
                echo strtoupper(__('Payment', 'woo-payzen-payment')) . "\n\n" . $payzen_order_note . "\n\n";
            } else {
                echo '<h2>' . __('Payment', 'woo-payzen-payment') . '</h2><p>' . str_replace("\n", '<br >', $payzen_order_note) . '</p>';
            }
        }
    }

    /**
     * Get all notes of a specified order.
     * @return array[string]
     */
    private static function get_order_notes($order_id)
    {
        $exclude_fnc = class_exists('WC_Comments') ? array('WC_Comments', 'exclude_order_comments') :
            'woocommerce_exclude_order_comments';

        remove_filter('comments_clauses', $exclude_fnc, 10);

        $comments = get_comments(array(
            'post_id' => $order_id,
            'status' => 'approve',
            'type'    => 'order_note'
        ));
        $notes = wp_list_pluck($comments, 'comment_content');

        add_filter('comments_clauses', $exclude_fnc, 10, 1);

        return $notes;
    }

    protected function get_order_property($order, $property_name)
    {
        $method = 'get_' . $property_name;

        if (method_exists($order, $method)) {
            return $order->$method();
        } else {
            return isset($order->$property_name) ? $order->$property_name : null;
        }
    }

    protected function get_customer_property($customer, $property_name)
    {
        $method = 'get_' . $property_name;

        if (method_exists($customer, $method)) {
            return $customer->$method();
        } else {
            return isset($customer->$property_name) ? $customer->$property_name : null;
        }
    }

    private function payzen_redirect($url, $iframe = false)
    {
        if (! $iframe) {
            wp_redirect($url);
        } else {
            echo '<div style="text-align: center;">
                      <img src="' . esc_url(WC_PAYZEN_PLUGIN_URL . 'assets/images/loading_big.gif') . '">
                  </div>';

            echo '<script type="text/javascript">
                    var url = "'.$url.'";

                    if (window.top) {
                      window.top.location = url;
                    } else {
                      window.location = url;
                    }
                  </script>';
        }

        exit();
    }

    public static function payzen_add_order_note($payzen_response, $order)
    {
        $note = $payzen_response->getCompleteMessage("\n");

        if ($payzen_response->get('brand_management')) {
            $brand_info = json_decode($payzen_response->get('brand_management'));
            $msg_brand_choice = "\n";

            if (isset($brand_info->userChoice) && $brand_info->userChoice) {
                $msg_brand_choice .= __('Card brand chosen by buyer.', 'woo-payzen-payment');
            } else {
                $msg_brand_choice .= __('Default card brand used.', 'woo-payzen-payment');
            }

            $note .= $msg_brand_choice;
        }

        $order->add_order_note($note);

        $note = '';
        if (! $payzen_response->isCancelledPayment()) {
            $note .= sprintf(__('Transaction ID: %s.', 'woo-payzen-payment'), $payzen_response->get('trans_id'));

            if ($payzen_response->get('trans_uuid')) {
                $note .= "\n";
                $note .= sprintf(__('Transaction UUID: %s.', 'woo-payzen-payment'), $payzen_response->get('trans_uuid'));
            }
        }

        if ($payzen_response->getTransStatus()) {
            $note .= "\n";
            $note .= sprintf(__('Transaction status: %s.', 'woo-payzen-payment'), $payzen_response->getTransStatus());
        }

        $order->add_order_note($note);
    }

    public function payzen_notices($template_name, $template_path, $located, $args)
    {
        global $woocommerce;

        if ($template_name !== 'checkout/thankyou.php') {
            return;
        }

        if (! isset($args['order'])) {
            return;
        }

        if (strpos($this->get_order_property($args['order'], 'payment_method'), 'payzen') === 0) {
            if (function_exists('wc_print_notices')) {
                wc_print_notices();
            } else {
                $woocommerce->show_messages();
            }
        }
    }

    private static function get_success_order_statuses($default = false) {
        $statuses = array();

        if (function_exists('wc_get_order_statuses')) { // from WooCommerce 2.2.0
            $other_statues = array('pending', 'cancelled', 'refunded', 'failed');

            foreach (wc_get_order_statuses() as $key => $value) {
                $status = substr($key, 3);

                if (in_array($status, $other_statues)) {
                    continue;
                }

                $statuses[$status] = $value;
            }
        } else {
            $statuses =  array(
                'on-hold' => __('On Hold', 'woo-payzen-payment'),
                'processing' => __('Processing', 'woo-payzen-payment'),
                'completed' => __('Complete', 'woo-payzen-payment')
            );
        }

        if ($default) {
            $statuses = array('default' => __('Default', 'woo-payzen-payment')) + $statuses;
        }

        return $statuses;
    }

    private static function is_successful_action($payzen_response)
    {
        if ($payzen_response->isAcceptedPayment()) {
            return true;
        }

        // this is a backward compatibility feature: it is used as a workarround as long as transcation
        // creation on REGISTER in not enabled on payment gateway
        if ($payzen_response->get('subscription') && ($payzen_response->get('recurrence_status') === 'CREATED')) {
            return true;
        }

        if ($payzen_response->get('identifier') && (
            $payzen_response->get('identifier_status') == 'CREATED' /* page_action is REGISTER_PAY or ASK_REGISTER_PAY */ ||
            $payzen_response->get('identifier_status') == 'UPDATED' /* page_action is REGISTER_UPDATE_PAY */
        )) {
            return true;
        }

        return false;
    }

    private function payzen_save_identifier($order, $payzen_response)
    {
        $cust_id = $this->get_order_property($order, 'user_id');
        if (! $cust_id) {
            return;
        }

        if ($payzen_response->get('identifier') && (
            $payzen_response->get('identifier_status') == 'CREATED' /* page_action is REGISTER_PAY or ASK_REGISTER_PAY */ ||
            $payzen_response->get('identifier_status') == 'UPDATED' /* page_action is REGISTER_UPDATE_PAY */
        )) {
            $this->log("Identifier for customer #{$cust_id} successfully created or updated on payment gateway. Let's save it and save masked card and expiry date.");

            update_user_meta((int) $cust_id, 'payzen_identifier', $payzen_response->get('identifier'));

            // mask all card digits unless the last 4 ones
            $number = $payzen_response->get('card_number');
            $masked = '';

            $matches = array();
            if (preg_match('#^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})(_[A-Z0-9]{8,11})?$#i', $number, $matches)) {
                // IBAN(_BIC)
                $masked .= isset($matches[2]) ? str_replace('_', '', $matches[2]) . ' / ' : ''; // BIC

                $iban = $matches[1];
                $masked .= substr($iban, 0, 4) . str_repeat('X', strlen($iban) - 8) . substr($iban, - 4);
            } elseif (strlen($number) > 4) {
                $masked = str_repeat('X', strlen($number) - 4) . substr($number, - 4);

                if ($payzen_response->get('expiry_month') && $payzen_response->get('expiry_year')) {
                    // format card expiration data
                    $masked .= ' (';
                    $masked .= str_pad($payzen_response->get('expiry_month'), 2, '0', STR_PAD_LEFT);
                    $masked .= '/';
                    $masked .= $payzen_response->get('expiry_year');
                    $masked .= ')';
                }
            }

            update_user_meta((int) $cust_id, 'payzen_masked_pan', $masked);

            $this->log("Identifier for customer #{$cust_id} and his masked PAN data are successfully saved.");
        }
    }

    private function payzen_save_recurrence($order, $payzen_response)
    {
        $order_id = (int) $this->get_order_property($order, 'id');

        if ($payzen_response->get('subscription') && ($payzen_response->get('recurrence_status') === 'CREATED')) {
            $this->log("Subscription for order #{$order_id} successfully created on payment gateway. Let's save subscription information.");

            $currency_code = $payzen_response->get('sub_currency');

            delete_post_meta($order_id, 'Subscription ID');
            delete_post_meta($order_id, 'Amount');
            delete_post_meta($order_id, 'Effect date');
            delete_post_meta($order_id, 'Initial amount');
            delete_post_meta($order_id, 'Initial amount count');

            // store subscription details
            update_post_meta($order_id, 'Subscription ID', $payzen_response->get('subscription'));
            update_post_meta($order_id, 'Amount', self::display_amount($payzen_response->get('sub_amount'), $currency_code));
            update_post_meta($order_id, 'Effect date', preg_replace('#^(\d{4})(\d{2})(\d{2})$#', '\1-\2-\3', $payzen_response->get('sub_effect_date')));

            if ($payzen_response->get('sub_init_amount')) {
                update_post_meta($order_id, 'Initial amount', self::display_amount($payzen_response->get('sub_init_amount'), $currency_code));
                update_post_meta($order_id, 'Initial amount count', $payzen_response->get('sub_init_amount_number'));
            }

            $this->log("Subscription information for order #{$order_id} is successfully saved.");
        }
    }

    public static function display_amount($amount_in_cents, $currency_code)
    {
        if (! $amount_in_cents) {
            return '';
        }

        $currency = PayzenApi::findCurrencyByNumCode($currency_code);
        return $currency->convertAmountToFloat($amount_in_cents) . ' ' . $currency->getAlpha3();
    }

    private static function payzen_subscriptions_handler($method)
    {
        if ($method !== 'payzenstd') {
            return null;
        }

        return Payzen_Subscriptions_Loader::getInstance(WC_Gateway_PayzenStd::SUBSCRIPTIONS_HANDLER);
    }
}
