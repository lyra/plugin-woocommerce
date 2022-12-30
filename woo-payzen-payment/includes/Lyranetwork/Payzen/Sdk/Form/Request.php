<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Form;

/**
 * Class managing and preparing request parameters and HTML rendering of request.
 */
class Request
{

    /**
     * The fields to send to the payment gateway.
     *
     * @var array[string][Lyranetwork\Payzen\Sdk\Form\Field]
     * @access private
     */
    private $requestParameters;

    /**
     * Certificate to use in TEST mode.
     *
     * @var string
     * @access private
     */
    private $keyTest;

    /**
     * Certificate to use in PRODUCTION mode.
     *
     * @var string
     * @access private
     */
    private $keyProd;

    /**
     * URL of the payment page.
     *
     * @var string
     * @access private
     */
    private $platformUrl;

    /**
     * Set to true to send the vads_redirect_* parameters.
     *
     * @var boolean
     * @access private
     */
    private $redirectEnabled;

    /**
     * Algo used to sign forms.
     *
     * @var string
     * @access private
     */
    private $algo = Api::ALGO_SHA1;

    /**
     * The original data encoding.
     *
     * @var string
     * @access private
     */
    private $encoding;

    /**
     * The list of categories for payment with Accord bank.
     * To be sent with the products detail if you use this payment mean.
     */
    public static $ACCORD_CATEGORIES = array(
        'FOOD_AND_GROCERY',
        'AUTOMOTIVE',
        'ENTERTAINMENT',
        'HOME_AND_GARDEN',
        'HOME_APPLIANCE',
        'AUCTION_AND_GROUP_BUYING',
        'FLOWERS_AND_GIFTS',
        'COMPUTER_AND_SOFTWARE',
        'HEALTH_AND_BEAUTY',
        'SERVICE_FOR_INDIVIDUAL',
        'SERVICE_FOR_BUSINESS',
        'SPORTS',
        'CLOTHING_AND_ACCESSORIES',
        'TRAVEL',
        'HOME_AUDIO_PHOTO_VIDEO',
        'TELEPHONY'
    );

    public function __construct($encoding = 'UTF-8')
    {
        // Initialize encoding.
        $this->encoding = in_array(strtoupper($encoding), Api::$SUPPORTED_ENCODINGS, true) ?
            strtoupper($encoding) : 'UTF-8';

        // Parameters' regular expressions.
        $ans = '[^<>]'; // Any character (except the dreadful "<" and ">").
        $an63 = '#^[A-Za-z0-9]{0,63}$#u';
        $ans255 = '#^' . $ans . '{0,255}$#u';
        $ans127 = '#^' . $ans . '{0,127}$#u';
        $supzero = '[1-9]\d*';
        $regex_payment_cfg = '#^(SINGLE|MULTI:first=\d+;count=' . $supzero . ';period=' . $supzero . ')$#u';
        // AAAAMMJJhhmmss
        $regex_trans_date = '#^\d{4}(1[0-2]|0[1-9])(3[01]|[1-2]\d|0[1-9])(2[0-3]|[0-1]\d)([0-5]\d){2}$#u';
        $regex_sub_effect_date = '#^\d{4}(1[0-2]|0[1-9])(3[01]|[1-2]\d|0[1-9])$#u';
        $regex_mail = '#^[^@]+@[^@]+\.\w{2,4}$#u';
        $regex_params = '#^([^&=]+=[^&=]*)?(&[^&=]+=[^&=]*)*$#u'; // name1=value1&name2=value2....
        $regex_ship_type = '#^RECLAIM_IN_SHOP|RELAY_POINT|RECLAIM_IN_STATION|PACKAGE_DELIVERY_COMPANY|ETICKET$#u';
        $regex_payment_option = '#^[a-zA-Z0-9]{0,32}$|^COUNT=([1-9][0-9]{0,2})?;RATE=[0-9]{0,4}(\\.[0-9]{1,4})?;DESC=.{0,64};?$#';

        // Defining all parameters and setting formats and default values.
        $this->addField('signature', 'Signature', '#^[0-9a-f]{40}$#u', true);

        $this->addField('vads_acquirer_transient_data', 'Acquirer transient data', $ans255);
        $this->addField('vads_action_mode', 'Action mode', '#^INTERACTIVE|SILENT$#u', true, 11);
        $this->addField('vads_amount', 'Amount', '#^' . $supzero . '$#u', true);
        $this->addField('vads_available_languages', 'Available languages', '#^(|[A-Za-z]{2}(;[A-Za-z]{2})*)$#u', false, 2);
        $this->addField('vads_capture_delay', 'Capture delay', '#^\d*$#u');
        $this->addField('vads_card_number', 'Card number', '#^\d{13,19}$#u');
        $this->addField('vads_contracts', 'Contracts', $ans255);
        $this->addField('vads_contrib', 'Contribution', $ans255);
        $this->addField('vads_ctx_mode', 'Mode', '#^TEST|PRODUCTION$#u', true);
        $this->addField('vads_currency', 'Currency', '#^\d{3}$#u', true, 3);
        $this->addField('vads_cust_address', 'Customer address', $ans255);
        $this->addField('vads_cust_antecedents', 'Customer history', '#^NONE|NO_INCIDENT|INCIDENT$#u');
        $this->addField('vads_cust_cell_phone', 'Customer cell phone', $an63, false, 63);
        $this->addField('vads_cust_city', 'Customer city', '#^' . $ans . '{0,63}$#u', false, 63);
        $this->addField('vads_cust_country', 'Customer country', '#^[A-Za-z]{2}$#u', false, 2);
        $this->addField('vads_cust_email', 'Customer email', $regex_mail, false, 127);
        $this->addField('vads_cust_first_name', 'Customer first name', $an63, false, 63);
        $this->addField('vads_cust_id', 'Customer id', $an63, false, 63);
        $this->addField('vads_cust_last_name', 'Customer last name', $an63, false, 63);
        $this->addField('vads_cust_legal_name', 'Customer legal name', '#^' . $ans . '{0,100}$#u', false, 100);
        $this->addField('vads_cust_name', 'Customer name', $ans127, false, 127);
        $this->addField('vads_cust_phone', 'Customer phone', $an63, false, 63);
        $this->addField('vads_cust_state', 'Customer state/region', '#^' . $ans . '{0,63}$#u', false, 63);
        $this->addField('vads_cust_status', 'Customer status (private or company)', '#^PRIVATE|COMPANY$#u', false, 7);
        $this->addField('vads_cust_title', 'Customer title', '#^' . $ans . '{0,63}$#u', false, 63);
        $this->addField('vads_cust_zip', 'Customer zip code', $an63, false, 63);
        $this->addField('vads_cust_national_id', 'Customer national id', $ans255);
        $this->addField('vads_cust_address_number', 'Customer address number',  '#^' . $ans . '{0,64}$#u', false, 64);
        $this->addField('vads_cust_district', 'Customer district', $ans127, false, 127);
        $this->addField('vads_cvv', 'Card verification number', '#^\d{3,4}$#u');
        $this->addField('vads_expiry_month', 'Month of card expiration', '#^\d[0-2]{1}$#u');
        $this->addField('vads_expiry_year', 'Year of card expiration', '#^20[0-9]{2}$#u');
        $this->addField('vads_identifier', 'Identifier', '#^'.$ans.'{0,50}$#u', false, 50);
        $this->addField('vads_insurance_amount', 'The amount of insurance', '#^' . $supzero . '$#u', false, 12);
        $this->addField('vads_language', 'Language', '#^[A-Za-z]{2}$#u', false, 2);
        $this->addField('vads_nb_products', 'Number of products', '#^' . $supzero . '$#u', false);
        $this->addField('vads_order_id', 'Order id', '#^[A-za-z0-9]{0,12}$#u', false, 12);
        $this->addField('vads_order_info', 'Order info', $ans255);
        $this->addField('vads_order_info2', 'Order info 2', $ans255);
        $this->addField('vads_order_info3', 'Order info 3', $ans255);
        $this->addField('vads_page_action', 'Page action', '#^PAYMENT$#u', true, 7);
        $this->addField('vads_payment_cards', 'Payment cards', '#^([A-Za-z0-9\-_]+;)*[A-Za-z0-9\-_]*$#u', false, 127);
        $this->addField('vads_payment_config', 'Payment config', $regex_payment_cfg, true);
        $this->addField('vads_payment_option_code', 'Payment option to use', $regex_payment_option, false);
        $this->addField('vads_payment_src', 'Payment source', '#^$#u', false, 0);
        $this->addField('vads_redirect_error_message', 'Redirection error message', $ans255, false);
        $this->addField('vads_redirect_error_timeout', 'Redirection error timeout', $ans255, false);
        $this->addField('vads_redirect_success_message', 'Redirection success message', $ans255, false);
        $this->addField('vads_redirect_success_timeout', 'Redirection success timeout', $ans255, false);
        $this->addField('vads_return_get_params', 'GET return parameters', $regex_params, false);
        $this->addField('vads_return_mode', 'Return mode', '#^NONE|GET|POST$#u', false, 4);
        $this->addField('vads_return_post_params', 'POST return parameters', $regex_params, false);
        $this->addField('vads_ship_to_city', 'Shipping city', '#^' . $ans . '{0,63}$#u', false, 63);
        $this->addField('vads_ship_to_country', 'Shipping country', '#^[A-Za-z]{2}$#u', false, 2);
        $this->addField('vads_ship_to_delay', 'Delay of shipping', '#^INFERIOR_EQUALS|SUPERIOR|IMMEDIATE|ALWAYS$#u', false, 15);
        $this->addField('vads_ship_to_delivery_company_name', 'Name of the delivery company', $ans127, false, 127);
        $this->addField('vads_ship_to_first_name', 'Shipping first name', $an63, false, 63);
        $this->addField('vads_ship_to_last_name', 'Shipping last name', $an63, false, 63);
        $this->addField('vads_ship_to_legal_name', 'Shipping legal name', '#^' . $ans . '{0,100}$#u', false, 100);
        $this->addField('vads_ship_to_name', 'Shipping name', '#^' . $ans . '{0,127}$#u', false, 127);
        $this->addField('vads_ship_to_phone_num', 'Shipping phone', $ans255, false, 63);
        $this->addField('vads_ship_to_speed', 'Speed of the shipping method', '#^STANDARD|EXPRESS|PRIORITY$#u', false, 8);
        $this->addField('vads_ship_to_state', 'Shipping state', $an63, false, 63);
        $this->addField('vads_ship_to_status', 'Shipping status (private or company)', '#^PRIVATE|COMPANY$#u', false, 7);
        $this->addField('vads_ship_to_street', 'Shipping street', $ans127, false, 127);
        $this->addField('vads_ship_to_street2', 'Shipping street (2)', $ans127, false, 127);
        $this->addField('vads_ship_to_type', 'Type of the shipping method', $regex_ship_type, false, 24);
        $this->addField('vads_ship_to_zip', 'Shipping zip code', $an63, false, 63);
        $this->addField('vads_shipping_amount', 'The amount of shipping', '#^' . $supzero . '$#u', false, 12);
        $this->addField('vads_shop_name', 'Shop name', $ans127);
        $this->addField('vads_shop_url', 'Shop URL', '#^https?://(\w+(:\w*)?@)?(\S+)(:[0-9]+)?[\w\#!:.?+=&%@`~;,|!\-/]*$#u');
        $this->addField('vads_site_id', 'Shop ID', '#^\d{8}$#u', true, 8);
        $this->addField('vads_tax_amount', 'The amount of tax', '#^' . $supzero . '$#u', false, 12);
        $this->addField('vads_tax_rate', 'The rate of tax', '#^\d{1,2}\.\d{1,4}$#u', false, 6);
        $this->addField('vads_theme_config', 'Theme configuration', '#^[^;=]+=[^;=]*(;[^;=]+=[^;=]*)*;?$#u');
        $this->addField('vads_totalamount_vat', 'The total amount of VAT', '#^' . $supzero . '$#u', false, 12);
        $this->addField('vads_threeds_mpi', 'Enable / disable 3D Secure', '#^[0-2]$#u', false);
        $this->addField('vads_trans_date', 'Transaction date', $regex_trans_date, true, 14);
        $this->addField('vads_trans_id', 'Transaction ID', '#^[0-8]\d{5}$#u', true, 6);
        $this->addField('vads_url_cancel', 'Cancel URL', $ans127, false, 127);
        $this->addField('vads_url_error', 'Error URL', $ans127, false, 127);
        $this->addField('vads_url_referral', 'Referral URL', $ans127, false, 127);
        $this->addField('vads_url_refused', 'Refused URL', $ans127, false, 127);
        $this->addField('vads_url_return', 'Return URL', $ans127, false, 127);
        $this->addField('vads_url_success', 'Success URL', $ans127, false, 127);
        $this->addField('vads_user_info', 'User info', $ans255);
        $this->addField('vads_validation_mode', 'Validation mode', '#^[01]?$#u', false, 1);
        $this->addField('vads_version', 'Gatway version', '#^V2$#u', true, 2);

        // Subscription payment fields.
        $this->addField('vads_sub_amount', 'Subscription amount', '#^' . $supzero . '$#u');
        $this->addField('vads_sub_currency', 'Subscription currency', '#^\d{3}$#u', false, 3);
        $this->addField('vads_sub_desc', 'Subscription description', $ans255);
        $this->addField('vads_sub_effect_date', 'Subscription effect date', $regex_sub_effect_date);
        $this->addField('vads_sub_init_amount', 'Subscription initial amount', '#^' . $supzero . '$#u');
        $this->addField('vads_sub_init_amount_number', 'subscription initial amount number', '#^\d+$#u');

        // Set some default values.
        $this->set('vads_version', 'V2');
        $this->set('vads_page_action', 'PAYMENT');
        $this->set('vads_action_mode', 'INTERACTIVE');
        $this->set('vads_payment_config', 'SINGLE');

        $timestamp = time();
        $this->set('vads_trans_id', Api::generateTransId($timestamp));
        $this->set('vads_trans_date', gmdate('YmdHis', $timestamp));
    }

    /**
     * Shortcut function used in constructor to build requestParameters.
     *
     * @param string $name
     * @param string $label
     * @param string $regex
     * @param boolean $required
     * @param mixed $value
     * @return boolean
     */
    private function addField($name, $label, $regex, $required = false, $length = 255, $value = null)
    {
        $this->requestParameters[$name] = new Field($name, $label, $regex, $required, $length);

        if ($value !== null) {
            return $this->set($name, $value);
        }

        return true;
    }

    /**
     * Shortcut for setting multiple values with one array.
     *
     * @param array[string][mixed] $parameters
     * @return boolean
     */
    public function setFromArray($parameters)
    {
        $ok = true;
        foreach ($parameters as $name => $value) {
            $ok &= $this->set($name, $value);
        }

        return $ok;
    }

    /**
     * General getter that retrieves a request parameter with its name.
     * Adds "vads_" to the name if necessary.
     * Example : <code>$site_id = $request->get('site_id');</code>
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (! $name || ! is_string($name)) {
            return null;
        }

        // Shortcut notation compatibility.
        $name = (strpos($name, 'vads_') !== 0) ? 'vads_' . $name : $name;

        if ($name === 'vads_key_test') {
            return $this->keyTest;
        }

        if ($name === 'vads_key_prod') {
            return $this->keyProd;
        }

        if ($name === 'vads_platform_url') {
            return $this->platformUrl;
        }

        if ($name === 'vads_redirect_enabled') {
            return $this->redirectEnabled;
        }

        if (array_key_exists($name, $this->requestParameters)) {
            return $this->requestParameters[$name]->getValue();
        }

        return null;
    }

    /**
     * Set a request parameter with its name and the provided value.
     * Adds "vads_" to the name if necessary.
     * Example : <code>$request->set('site_id', '12345678');</code>
     *
     * @param string $name
     * @param mixed $value
     * @return boolean
     */
    public function set($name, $value)
    {
        if (! $name || ! is_string($name)) {
            return false;
        }

        // Shortcut notation compatibility.
        $name = (strpos($name, 'vads_') !== 0) ? 'vads_' . $name : $name;

        if (is_string($value)) {
            // Trim value before set.
            $value = trim($value);

            // Convert the parameters' values if they are not encoded in UTF-8.
            if ($this->encoding !== 'UTF-8') {
                $value = iconv($this->encoding, 'UTF-8', $value);
            }

            // Delete < and > characters from $value and replace multiple spaces by one.
            $value = preg_replace(array('#[<>]+#u', '#\s+#u'), array('', ' '), $value);
        }

        // Search appropriate setter.
        if ($name === 'vads_key_test') {
            return $this->setCertificate($value, 'TEST');
        }

        if ($name === 'vads_key_prod') {
            return $this->setCertificate($value, 'PRODUCTION');
        }

        if ($name === 'vads_platform_url') {
            return $this->setPlatformUrl($value);
        }

        if ($name === 'vads_redirect_enabled') {
            return $this->setRedirectEnabled($value);
        }

        if ($name === 'vads_sign_algo') {
            return $this->setSignAlgo($value);
        }

        if (array_key_exists($name, $this->requestParameters)) {
            return $this->requestParameters[$name]->setValue($value);
        }

        return false;
    }

    /**
     * Set multi payment configuration.
     *
     * @param $total_in_cents total order amount in cents
     * @param $first_in_cents amount of the first payment in cents
     * @param $count total number of payments
     * @param $period number of days between 2 payments
     * @return boolean
     */
    public function setMultiPayment($total_in_cents = null, $first_in_cents = null, $count = 3, $period = 30)
    {
        $result = true;

        if (is_numeric($count) && $count > 1 && is_numeric($period) && $period > 0) {
            // Default values for first and total.
            $total_in_cents = ($total_in_cents === null) ? $this->get('amount') : $total_in_cents;
            $first_in_cents = ($first_in_cents === null) ? round($total_in_cents / $count) : $first_in_cents;

            // Check parameters.
            if (is_numeric($total_in_cents) && $total_in_cents > $first_in_cents
                && $total_in_cents > 0 && is_numeric($first_in_cents) && $first_in_cents > 0) {
                // Set value to payment_config.
                $payment_config = 'MULTI:first=' . $first_in_cents . ';count=' . $count . ';period=' . $period;
                $result &= $this->set('amount', $total_in_cents);
                $result &= $this->set('payment_config', $payment_config);
            }
        }

        return $result;
    }

    /**
     * Set target URL of the payment form.
     *
     * @param string $url
     * @return boolean
     */
    public function setPlatformUrl($url)
    {
        if (preg_match('#^https?://([^/]+/)+$#u', $url)) {
            $this->platformUrl = $url;
            return true;
        }

        return false;
    }

    /**
     * Enable/disable vads_redirect_* parameters.
     *
     * @param mixed $enabled false, 0, null or 'false' to disable
     * @return boolean
     */
    public function setRedirectEnabled($enabled)
    {
        $this->redirectEnabled = ($enabled && (! is_string($enabled) || strtolower($enabled) !== 'false'));
        return true;
    }

    /**
     * Set TEST or PRODUCTION certificate.
     *
     * @param string $key
     * @param string $mode
     * @return boolean
     */
    public function setCertificate($key, $mode)
    {
        if ($mode === 'TEST') {
            $this->keyTest = $key;
        }
        elseif ($mode === 'PRODUCTION') {
            $this->keyProd = $key;
        }
        else {
            return false;
        }

        return true;
    }

    /**
     * Set signature algorithm.
     *
     * @param string $algo
     * @return boolean
     */
    public function setSignAlgo($algo)
    {
        if (in_array($algo, Api::$SUPPORTED_ALGOS, true)) {
            $this->algo = $algo;
            return true;
        }

        return false;
    }

    /**
     * Add a product info as request parameters.
     *
     * @param string $label
     * @param int $amount
     * @param int $qty
     * @param string $ref
     * @param string $type
     * @param float vat
     * @return boolean
     */
    public function addProduct($label, $amount, $qty, $ref, $type = null, $vat = null)
    {
        $index = $this->get('nb_products') ? $this->get('nb_products') : 0;
        $ok = true;

        // Add product info as request parameters.
        $ok &= $this->addField('vads_product_label' . $index, 'Product label', '#^[^<>"+-]{0,255}$#u', false, 255, $label);
        $ok &= $this->addField('vads_product_amount' . $index, 'Product amount', '#^[1-9]\d*$#u', false, 12, $amount);
        $ok &= $this->addField('vads_product_qty' . $index, 'Product quantity', '#^[1-9]\d*$#u', false, 255, $qty);
        $ok &= $this->addField('vads_product_ref' . $index, 'Product reference', '#^[A-Za-z0-9]{0,64}$#u', false, 64, $ref);
        $ok &= $this->addField('vads_product_type' . $index, 'Product type', '#^' . implode('|', self::$ACCORD_CATEGORIES) . '$#u', false, 30, $type);
        $ok &= $this->addField('vads_product_vat' . $index, 'Product tax rate', '#^((\d{1,12})|(\d{1,2}\.\d{1,4}))$#u', false, 12, $vat);

        // Increment the number of products.
        $ok &= $this->set('nb_products', $index + 1);

        return $ok;
    }

    /**
     * Add extra info as a request parameter.
     *
     * @param string $key
     * @param string $value
     * @return boolean
     */
    public function addExtInfo($key, $value)
    {
        return $this->addField('vads_ext_info_' . $key, 'Extra info ' . $key, '#^.{0,255}$#u', false, 255, $value);
    }

    /**
     * Return certificate according to current mode, false if mode was not set.
     *
     * @return string|boolean
     */
    private function getCertificate()
    {
        switch ($this->requestParameters['vads_ctx_mode']->getValue()) {
            case 'TEST':
                return $this->keyTest;

            case 'PRODUCTION':
                return $this->keyProd;

            default:
                return false;
        }
    }

    /**
     * Generate signature from a list of fields.
     *
     * @param array[string][Lyranetwork\Payzen\Sdk\Form\Field] $fields already filtered fields list
     * @param bool $hashed
     * @return string
     */
    private function generateSignature($fields, $hashed = true)
    {
        $params = array();
        foreach ($fields as $field) {
            $params[$field->getName()] = $field->getValue();
        }

        return Api::sign($params, $this->getCertificate(), $this->algo, $hashed);
    }

    /**
     * Unset the value of optionnal fields if they are invalid.
     */
    public function clearInvalidOptionnalFields()
    {
        $fields = $this->getRequestFields();
        foreach ($fields as $field) {
            if (! $field->isValid() && ! $field->isRequired()) {
                $field->setValue(null);
            }
        }
    }

    /**
     * Check all payment fields.
     *
     * @param array[string] $errors will be filled with the names of invalid fields
     * @return boolean
     */
    public function isRequestReady(&$errors = null)
    {
        $errors = is_array($errors) ? $errors : array();

        foreach ($this->getRequestFields() as $field) {
            if (! $field->isValid()) {
                $errors[] = $field->getName();
            }
        }

        return count($errors) === 0;
    }

    /**
     * Return the list of fields to send to the payment gateway.
     *
     * @return array[string][Lyranetwork\Payzen\Sdk\Form\Field] a list of fields
     */
    public function getRequestFields()
    {
        $fields = $this->requestParameters;

        // Filter redirect_* parameters if redirect is disabled.
        if (! $this->redirectEnabled) {
            $redirect_fields = array(
                'vads_redirect_success_timeout',
                'vads_redirect_success_message',
                'vads_redirect_error_timeout',
                'vads_redirect_error_message'
            );

            foreach ($redirect_fields as $field_name) {
                unset($fields[$field_name]);
            }
        }

        foreach ($fields as $field_name => $field) {
            if (! $field->isFilled() && ! $field->isRequired()) {
                unset($fields[$field_name]);
            }
        }

        // Compute signature.
        $fields['signature']->setValue($this->generateSignature($fields));

        // Return the list of fields.
        return $fields;
    }

    /**
     * Return the URL of the payment page with urlencoded parameters (GET-like URL).
     *
     * @return string
     */
    public function getRequestUrl()
    {
        $fields = $this->getRequestFields();

        $url = $this->platformUrl . '?';
        foreach ($fields as $field) {
            if (! $field->isFilled()) {
                continue;
            }

            $url .= $field->getName() . '=' . rawurlencode($field->getValue()) . '&';
        }

        $url = substr($url, 0, - 1); // Remove last &.
        return $url;
    }

    /**
     * Return the HTML form to send to the payment gateway.
     *
     * @param string $form_add
     * @param string $input_type
     * @param string $input_add
     * @param string $btn_type
     * @param string $btn_value
     * @param string $btn_add
     * @return string
     */
    public function getRequestHtmlForm(
        $form_add = '',
        $input_type = 'hidden',
        $input_add = '',
        $btn_type = 'submit',
        $btn_value = 'Pay',
        $btn_add = '',
        $escape = true
    ) {
        $html = '<form action="' . $this->platformUrl . '" method="POST" ' . $form_add . '>';
        $html .= "\n";
        $html .= $this->getRequestHtmlFields($input_type, $input_add, $escape);
        $html .= '<input type="' . $btn_type . '" value="' . $btn_value . '" ' . $btn_add . '/>';
        $html .= "\n";
        $html .= '</form>';

        return $html;
    }

    /**
     * Return the HTML inputs of fields to send to the payment page.
     *
     * @param string $input_type
     * @param string $input_add
     * @return string
     */
    public function getRequestHtmlFields($input_type = 'hidden', $input_add = '', $escape = true)
    {
        $fields = $this->getRequestFields();

        $html = '';
        $format = '<input name="%s" value="%s" type="' . $input_type . '" ' . $input_add . "/>\n";
        foreach ($fields as $field) {
            if (! $field->isFilled()) {
                continue;
            }

            // Convert special chars to HTML entities to avoid data truncation.
            if ($escape) {
                $value = htmlspecialchars($field->getValue(), ENT_QUOTES, 'UTF-8');
            }

            $html .= sprintf($format, $field->getName(), $value);
        }

        return $html;
    }

    /**
     * Return the html fields to send to the payment page as a key/value array.
     *
     * @param bool $for_log
     * @return array[string][string]
     */
    public function getRequestFieldsArray($for_log = false, $escape = true)
    {
        $fields = $this->getRequestFields();

        $sensitive_data = array('vads_card_number', 'vads_cvv', 'vads_expiry_month', 'vads_expiry_year', 'vads_cust_national_id');

        $result = array();
        foreach ($fields as $field) {
            if (! $field->isFilled()) {
                continue;
            }

            $value = $field->getValue();
            if ($for_log && in_array($field->getName(), $sensitive_data, true)) {
                $value = str_repeat('*', strlen($value));
            }

            // Convert special chars to HTML entities to avoid data truncation.
            if ($escape) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }

            $result[$field->getName()] = $value;
        }

        return $result;
    }
}
