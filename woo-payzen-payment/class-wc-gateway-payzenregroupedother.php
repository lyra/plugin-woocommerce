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

class WC_Gateway_PayzenRegroupedOther extends WC_Gateway_PayzenStd
{
    public function __construct($regroup = true)
    {
        $this->id = 'payzenregroupedother';
        $this->icon = apply_filters('woocommerce_' . $this->id . '_icon', WC_PAYZEN_PLUGIN_URL . 'assets/images/other.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('Other payment means', 'woo-payzen-payment');

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

        if ($this->payzen_is_section_loaded()) {
            // Reset payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'payzen_reset_admin_options'));

            // Update payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'payzen_admin_head_script'));
        }

        if ($regroup) {
            // Generate payment form action.
            add_action('woocommerce_receipt_' . $this->id, array($this, 'payzen_generate_form'));

            // Payment method title filter.
            add_filter('woocommerce_title_' . $this->id, array($this, 'get_title'));

            // Payment method description filter.
            add_filter('woocommerce_description_' . $this->id, array($this, 'get_description'));

            // Payment method availability filter.
            add_filter('woocommerce_available_' . $this->id, array($this, 'is_available'));

            // Generate payment fields filter.
            add_filter('woocommerce_payzen_payment_fields_' . $this->id, array($this, 'get_payment_fields'));
        }
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        global $woocommerce;

        parent::init_form_fields();

        unset($this->form_fields['validation_mode']);
        unset($this->form_fields['payment_cards']);
        unset($this->form_fields['advanced_options']);
        unset($this->form_fields['card_data_mode']);
        unset($this->form_fields['payment_by_token']);
        unset($this->form_fields['capture_delay']);

        // By default, disable regrouped other payment means submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables this payment method.', 'woo-payzen-payment');

        $this->form_fields['title']['default'] = __('Other payment means', 'woo-payzen-payment');

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Other payment means',
                'en_GB' => 'Other payment means',
                'fr_FR' => 'Autres moyens de paiement',
                'de_DE' => 'Anderen Zahlungsmittel',
                'es_ES' => 'Otros medios de pago',
                'pt_BR' => 'Outros meios de pagamento'
            );
        }

        $this->form_fields['payment_options'] = array(
            'title' => __('PAYMENT OPTIONS', 'woo-payzen-payment'),
            'type' => 'title'
        );

        // Since 2.3.0, we can display other payment means as submodules.
        if (version_compare($woocommerce->version, '2.3.0', '>=')) {
            $this->form_fields['regroup_enabled'] = array(
                'title' => __('Regroup payment means', 'woo-payzen-payment'),
                'label' => __('Enable / disable', 'woo-payzen-payment'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If this option is enabled, all the payment means added in this section will be displayed within the same payment submodule.', 'woo-payzen-payment')
            );
        }

        $smartform_mode = PayzenTools::is_embedded_payment();

        // Payment options.
        $payment_options_desc_p1 = 'Click on « Add » button to configure one or more payment means.<br /><b>Label: </b>The label of the means of payment to display on your site.<br /><b>Means of payment: </b>Choose the means of payment you want to propose.<br /><b>Countries: </b>Countries where the means of payment will be available. Leave blank to authorize all countries.<br /><b>Min. amount: </b>Minimum amount to enable the means of payment.<br /><b>Max. amount: </b>Maximum amount to enable the means of payment.<br /><b>Validation mode: </b>If manual is selected, you will have to confirm payments manually in your %s Back Office.<br /><b>Capture delay: </b>The number of days before the bank capture. Enter value only if different from %s general configuration.';
        $integration_mode_desc = '<br /><b>Embedded mode: </b>If you enable this option, the payment mean will be displayed in the Smartform. Attention, not all available payment means are supported by the Smartform. For more information, refer to the module documentation.';
        $payment_options_desc_p2 = '<br /><b>Cart data: </b>If you disable this option, the shopping cart details will not be sent to the gateway. Attention, in some cases, this option has to be enabled. For more information, refer to the module documentation.<br /><b>Do not forget to click on « Save » button to save your modifications.</b>';

        $payment_options_desc = $smartform_mode ? $payment_options_desc_p1 . $integration_mode_desc . $payment_options_desc_p2 : $payment_options_desc_p1 . $payment_options_desc_p2;
        $descr = sprintf(__($payment_options_desc,
            'woo-payzen-payment'), 'PayZen', 'PayZen');

        $columns = array();
        $columns['label'] = array(
            'title' => __('Label', 'woo-payzen-payment'),
            'width' => '300px'
        );

        $columns['payment_mean'] = array(
            'title' => __('Means of payment', 'woo-payzen-payment'),
            'width' => '250px'
        );

        $columns['amount_min'] = array(
            'title' => __('Min amount', 'woo-payzen-payment'),
            'width' => '92px'
        );

        $columns['amount_max'] = array(
            'title' => __('Max amount', 'woo-payzen-payment'),
            'width' => '92px'
        );

        $columns['countries'] = array(
            'title' => __('Countries', 'woo-payzen-payment'),
            'width' => '175px',
        );

        $columns['validation_mode'] = array(
            'title' => __('Validation mode', 'woo-payzen-payment'),
            'width' => '175px',
        );

        $columns['capture_delay'] = array(
            'title' => __('Capture delay ', 'woo-payzen-payment'),
            'width' => '92px',
        );

        if ($smartform_mode) {
            $columns['integrated_mode'] = array(
                'title' => __('Embedded mode', 'woo-payzen-payment'),
                'width' => '92px',
            );
        }

        $columns['send_cart_data'] = array(
            'title' => __('Cart data', 'woo-payzen-payment'),
            'width' => '92px',
        );

        $this->form_fields['payment_means'] = array(
            'title' => __('Payment means', 'woo-payzen-payment'),
            'type' => 'table',
            'columns' => $columns,
            'description' => $descr
        );

        $culumns = array();
        $descr = sprintf(__('Click on « Add » button to add one or more new payment means.<br /><b>Code: </b>The code of the means of payment as expected by %s gateway.<br /><b>Label: </b>The default label of the means of payment.<br /><b>Do not forget to click on « Save » button to save your modifications.</b>',
            'woo-payzen-payment'), 'PayZen');

        $culumns['code'] = array(
            'title' => __('Code', 'woo-payzen-payment'),
            'width' => '150px'
        );

        $culumns['title'] = array(
            'title' => __('Label', 'woo-payzen-payment'),
            'width' => '300px'
        );

        $this->form_fields['extra_payment_means'] = array(
            'title' => __('Add payment means', 'woo-payzen-payment'),
            'type' => 'table',
            'columns' => $culumns,
            'description' => $descr
        );
    }

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
    }

    protected function get_method_title_field_description()
    {
        return __('Method title to display on payment means page. Used only if &laquo; Regroup payment means &raquo; option is enabled.', 'woo-payzen-payment');
    }

    protected function get_method_description_field_description()
    {
        return __('This controls the description which the user sees during checkout. Used only if &laquo; Regroup payment means &raquo; option is enabled.', 'woo-payzen-payment');
    }

    public function payzen_admin_head_script()
    {
        parent::payzen_admin_head_script();
        $cards = $this->get_supported_card_types();
        $extra_cards = $this->get_option('extra_payment_means');
        if (! empty($extra_cards)) {
            foreach ($extra_cards as $option) {
                if (! isset($cards[$option['code']])) {
                    $cards[$option['code']] = $option['code'] . ' - ' . $option['title'];
                }
            }
        }

        $smartformActivated = 'false';
        if (PayzenTools::is_embedded_payment()) {
            $smartformActivated = 'true';
        }

        ?>
        <script type="text/javascript">
        //<!--
            function payzenAddOption(fieldName, record, key) {
                var smartformActivated = <?php echo $smartformActivated; ?>;

                if (jQuery('#' + fieldName + '_table tbody tr').length == 1) {
                    jQuery('#' + fieldName + '_btn').css('display', 'none');
                    jQuery('#' + fieldName + '_table').css('display', '');
                }

                if (! key) {
                    // New line, generate key.
                    key = new Date().getTime();
                }

                var optionLine = '<tr id="' + fieldName + '_line_' + key + '">';

                // Reorder record elements.
                if (fieldName === '<?php echo esc_attr($this->plugin_id . $this->id . '_extra_payment_means')?>') {
                    var orderedRecord = {
                        'code': record.code,
                        'title': record.title
                    };
                } else {
                    var orderedRecord = {
                        'label': record.label,
                        'payment_mean': record.payment_mean,
                        'amount_min': record.amount_min,
                        'amount_max': record.amount_max,
                        'countries': record.countries,
                        'validation_mode': record.validation_mode,
                        'capture_delay': record.capture_delay ,
                        'integrated_mode': record.integrated_mode,
                        'send_cart_data': record.send_cart_data
                    };
                }

                jQuery.each(orderedRecord, function(attr, value) {
                    var width = jQuery('#' + fieldName + '_table thead tr th.' + attr).width() - 8;
                    var inputName = fieldName + '[' + key + '][' + attr + ']';

                    switch (attr) {
                        case 'payment_mean':
                            optionLine += '<td style="padding: 0px;">';
                            optionLine += '<select style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '">';
                            optionLine += '<?php foreach ($cards as $key => $value) {
                                                    echo '<option value="' . $key . '">' . $value . '</option>';
                                                } ?>';

                            optionLine = optionLine.replace('<option value="'+value+'"', '<option value="'+value+'" selected');
                            optionLine += '</td>';
                            break;

                        case 'countries':
                            optionLine += '<td style="padding: 0px;">';
                            optionLine += '<div><select style="display:none; width: ' + width + 'px; height: 150px; background-color: white; padding: 2px;" name="' + inputName + '[]" id="' + inputName +
                                         '" multiple="multiple" onblur="javascript:payzenDisplayMultiSelect(\'' + inputName + '\'); payzenDisplayLabel(\'' + inputName + '\');">';
                            optionLine += '<?php
                                                $countries = new WC_Countries();
                                                $countries = $countries->get_allowed_countries();
                                                foreach ($countries as $key => $value) {
                                                    echo '<option value="' . $key . '">' . $value . '</option>';
                                                } ?>';

                                                var labelValue = '';
                                                jQuery.each(value, function(index, country) {
                                                    labelValue += country + '; ';
                                                    optionLine = optionLine.replace('<option value="'+index+'"', '<option value="'+index+'" selected');
                                                });
                                                labelValue = labelValue.substring(0, labelValue.length - 2);
                                                if (labelValue == '') {
                                                    labelValue = '<?php echo __('Click to add countries.', 'woo-payzen-payment')?>';
                                                }

                            optionLine += '</select><label style="width:100%;" id="label_' + inputName + '" onclick="javascript:payzenDisplayMultiSelect(\'' + inputName + '\');" >' + labelValue + '</label></div>';
                            optionLine += '</td>';
                            break;

                        case 'validation_mode':
                            optionLine += '<td style="padding: 0px;">';
                            var disabled = (smartformActivated && (orderedRecord.integrated_mode === 'on')) ? 'disabled' : '';
                            optionLine += '<select style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '" ' + disabled + '>';
                            optionLine += '<?php foreach ($this->get_validation_modes() as $key => $value) {
                                                    echo '<option value="' . $key . '">' . $value . '</option>';
                                                } ?>';
                            optionLine = optionLine.replace('<option value="' + value + '"', '<option value="' + value + '" selected');
                            optionLine += '</td>';
                            break;

                        case 'integrated_mode':
                            if (smartformActivated) {
                                optionLine += '<td style="padding: 0px;">';
                                var checked = (value === 'on') ? 'checked' : '';
                                optionLine += '<input type="checkbox" name="' + inputName + '" id="' + inputName + '" ' + checked + ' onclick="javascript:payzenActivateFields(\'' + inputName + '\', \'' + orderedRecord.validation_mode + '\', \'' + orderedRecord.capture_delay + '\');" >';
                                optionLine += '</td>';
                            }

                            break;

                        case 'send_cart_data':
                            optionLine += '<td style="padding: 0px;">';
                            optionLine += '<select style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '">';
                            optionLine += '<?php $options = array('n' => __('No', 'woo-payzen-payment'), 'y' => __('Yes', 'woo-payzen-payment'));
                                                foreach ($options as $key => $value) {
                                                    echo '<option value="' . $key . '">' . $value . '</option>';
                                                } ?>';

                            optionLine = optionLine.replace('<option value="'+value+'"', '<option value="'+value+'" selected');
                            optionLine += '</td>';
                            break;

                        case 'capture_delay':
                            optionLine += '<td style="padding: 0px;">';
                            var disabled = (smartformActivated && (orderedRecord.integrated_mode === 'on')) ? 'disabled' : '';
                            var captureDelayValue = (value == null || value == 'undefined') ? '' : value;
                            optionLine += '<input class="input-text regular-input" style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '" type="text" value="' + captureDelayValue + '" ' + disabled + '>';
                            optionLine += '</td>';
                            break;

                        default:
                            optionLine += '<td style="padding: 0px;">';
                            optionLine += '<input class="input-text regular-input" style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '" type="text" value="' + value + '">';
                            optionLine += '</td>';
                    }
                });

                optionLine += '<td style="padding: 0px;"><input type="button" value="<?php echo __('Delete', 'woo-payzen-payment')?>" onclick="javascript: payzenDeleteOption(\'' + fieldName + '\', \'' + key + '\');"></td>';
                optionLine += '</tr>';

                jQuery(optionLine).insertBefore('#' + fieldName + '_add');
            }

            function payzenDeleteOption(fieldName, key) {
                jQuery('#' + fieldName + '_line_' + key).remove();

                if (jQuery('#' + fieldName + '_table tbody tr').length == 1) {
                    jQuery('#' + fieldName + '_btn').css('display', '');
                    jQuery('#' + fieldName + '_table').css('display', 'none');
                }
            }

            function payzenDisplayMultiSelect(selectId) {
                var select = document.getElementById(selectId);
                var label = document.getElementById('label_' + selectId);
                select.style.display = '';
                label.style.display = 'none';
            }

            function payzenActivateFields(checkboxId, validationMode, captureDelay) {
                var integratedModeCheckbox = document.getElementById(checkboxId);
                var validationModeId = checkboxId.replace('integrated_mode','validation_mode');
                var captureDelayId = checkboxId.replace('integrated_mode','capture_delay');

                if (integratedModeCheckbox.checked == true) {
                    document.getElementById(validationModeId).disabled = true;
                    document.getElementById(captureDelayId).disabled = true;
                } else {
                    document.getElementById(validationModeId).disabled = false;
                    document.getElementById(captureDelayId).disabled = false;
                }
            }

            function payzenDisplayLabel(selectId) {
                var select = document.getElementById(selectId);
                var label = document.getElementById('label_' + selectId);
                select.style.display = 'none';
                label.style.display = '';
                var labelText = getLabelText(select);
                label.innerHTML = labelText;
            }

            function getLabelText(select) {
                var labelText = '', option;

                for (var i = 0, len = select.options.length; i < len; i++) {
                    option = select.options[i];

                    if (option.selected) {
                        labelText += option.text + '; ';
                    }
                }

                labelText = labelText.substring(0, labelText.length - 2);
                if (! labelText) {
                    labelText = '<?php echo __('Click to add countries.', 'woo-payzen-payment')?>';
                }

                return labelText;
            }
        //-->
        </script>
<?php
    }

    public function validate_payment_means_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());
        $used_cards = array();

        foreach ($value as $code => $option) {
            if (($option['amount_min'] && (! is_numeric($option['amount_min']) || $option['amount_min'] < 0))
                || ($option['amount_max'] && (! is_numeric($option['amount_max']) || $option['amount_max'] < 0))) {
                unset($value[$code]); // Not save this option.
                continue;
            } else {
                if (in_array($option['payment_mean'], $used_cards)) {
                    unset($value[$code]);
                    continue;
                } else {
                    $used_cards[] = $option['payment_mean'];
                    if (! $option['label']) {
                        $cards = PayzenApi::getSupportedCardTypes();
                        // Add extra means of payment to supported payment means.
                        $extra_cards = $this->get_option('extra_payment_means');
                        if (! empty($extra_cards)){
                            foreach ($extra_cards as $option_card) {
                                if (! isset($cards[$option_card['code']])) {
                                    $cards[$option_card['code']] = $option_card['title'];
                                }
                            }
                        }

                        $value[$code]['label'] = sprintf(__('Payment with %s', 'woo-payzen-payment'), $cards[$option['payment_mean']]);
                    }

                }
            }

            if (! isset($option['countries'])){
                $value[$code]['countries'] = array();
            } else {
                $countries = new WC_Countries();
                $countries = $countries->get_allowed_countries();
                $array_countries = array();
                foreach ($option['countries'] as $country_index) {
                    $array_countries[$country_index] = $countries[$country_index];
                }

                $value[$code]['countries'] = $array_countries;
            }

        }

        return $value;
    }

    public function validate_extra_payment_means_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());
        $used_cards = array_keys(PayzenApi::getSupportedCardTypes());

        foreach ($value as $id => $option) {
            $code = trim($option['code']);
            $title = $option['title'];
            if (empty($code)
                || ! preg_match('#^[A-Za-z0-9\-_]+$#', $code)
                || empty($title)
                || ! preg_match('#^[^<>]*$#', $title)
                || in_array($code, $used_cards)) {
                    // Invalid format of code or title, delete this means of payment.
                    unset($value[$id]);
                } else {
                    $used_cards[] = $code;
                    // Update payment means code (to apply trim).
                    $value[$id]['code'] = $code;
                }
        }

        // Update payment_means (options containing deleted payment means should not appear).
        $other_payment_means = $this->get_option('payment_means');
        foreach ($other_payment_means as $key_payment_mean => $option_payment_mean) {
            if (! in_array($option_payment_mean['payment_mean'], $used_cards)) {
                unset($other_payment_means[$key_payment_mean]);
            }
        }

        $this->update_option('payment_means', $other_payment_means);

        return $value;
    }

    /**
     * Check if this gateway is enabled and available for the current cart.
     */
    public function is_available()
    {
        return $this->is_available_ignoring_regroup() && $this->regroup_other_payment_means();
    }

    public function is_available_ignoring_regroup()
    {
        return parent::is_available();
    }

    public function regroup_other_payment_means()
    {
        global $woocommerce;

        $order_id = get_query_var('order-pay');
        $options = ($order_id || $woocommerce->cart) ? $this->get_available_options() : null;

        if (version_compare($woocommerce->version, '2.3.0', '>=')) {
            if ($this->get_option('regroup_enabled') !== 'yes') {
                return false;
            }

            if (($options !== null) && (count($options) <= 1)) {
                return false;
            }
        } elseif (($options !== null) && empty($options)) {
            return false;
        }

        return true;
    }

    public function get_available_options()
    {
        global $woocommerce;

        $options = $this->get_option('payment_means');
        if (is_admin()) {
            return (isset($options) && is_array($options)) ? $options : array();
        }

        // Recover total amount either from order or from current cart if any.
        $amount = self::get_total_amount();
        $customer_country = $woocommerce->customer->get_shipping_country();

        $enabled_options = array();
        $smartform_mode = PayzenTools::is_embedded_payment();

        if (isset($options) && is_array($options) && ! empty($options)) {
            foreach ($options as $code => $option) {
                if ((! $option['amount_min'] || $amount >= $option['amount_min']) && (! $option['amount_max'] || $amount <= $option['amount_max'])
                    && (empty($option['countries']) || array_key_exists($customer_country, $option['countries'])) && (! $smartform_mode || ! isset($option['integrated_mode']) || ($option['integrated_mode'] !== 'on'))) {
                    $enabled_options[$code] = $option;
                }
            }
        }

        return $enabled_options;
    }

    public function get_available_options_for_smartform()
    {
        global $woocommerce;

        if (! $woocommerce->customer) {
            return array();
        }

        $enabled_options = array();

        if ($this->get_option('enabled') === 'yes') {
            // Recover total amount either from order or from current cart if any.
            $amount = self::get_total_amount();
            $customer_country = $woocommerce->customer->get_shipping_country();

            $options = $this->get_option('payment_means');

            if (isset($options) && is_array($options) && ! empty($options)) {
                foreach ($options as $code => $option) {
                    if ((! $option['amount_min'] || $amount >= $option['amount_min']) && (! $option['amount_max'] || $amount <= $option['amount_max'])
                        && (empty($option['countries']) || array_key_exists($customer_country, $option['countries'])) && isset($option['integrated_mode']) && ($option['integrated_mode'] === 'on')) {
                            $enabled_options[$code] = $option;
                    }
                }
            }
        }

        return $enabled_options;
    }

    /**
     * Display payment fields and show method description if set.
     *
     * @access public
     * @return void
     */
    public function get_payment_fields($options = array())
    {
        if (isset($_GET['tab']) && ($_GET['tab'] === 'checkout')) {
            return null;
        }

        if (empty($options)) {
            $options = $this->get_available_options();
        }

        $selected_option = key($options);

        $html = '<div style="margin-bottom: 15px;" id="' . $this->id . '_display_available_other_payment_means">';
        foreach ($options as $code => $option) {
            $lower_payment_code = strtolower($option['payment_mean']);

            $html .= '<div style="display: inline-block; margin: 10px;">';
            if (count($options) == 1) {
                $html .= '<input type="hidden" id="' . $this->id . '_' . $lower_payment_code . '" name="' . $this->id . '_card_type" value="' . $option['payment_mean'] . '">';
            } else {
                $html .= '<input type="radio" id="' . $this->id . '_' . $lower_payment_code . '" name="' . $this->id . '_card_type" value="' . $option['payment_mean'] . '" style="vertical-align: middle;" '
                    . checked($code, $selected_option, false) . '>';
            }

            $html .= '<label for="' . $this->id . '_' . $lower_payment_code . '" style="display: inline;">';

            $remote_logo = self::LOGO_URL . $lower_payment_code . '.png';
            $html .= '<img src="' . $remote_logo . '"
                       alt="' . $option['payment_mean'] . '"
                       title="' . $option['label'] . '"
                       style="vertical-align: middle; margin-left: 5px; max-height: 35px; display: unset;">';
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        $options = $this->get_available_options();
        if (empty($options)) {
            // Should not happen.
            return;
        }

        echo $this->get_payment_fields($options);
    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        $this->save_selected_card($order_id);

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

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function payzen_fill_request($order)
    {
        parent::payzen_fill_request($order);

        $order_id = self::get_order_property($order, 'id');
        $selected_card = get_transient($this->id . '_card_type_' . $order_id);

        // Set selected card.
        $this->payzen_request->set('payment_cards', $selected_card);
        $option = $this->get_mean($selected_card);

        // Check if capture_delay and validation_mode are overriden.
        if (isset($option['capture_delay']) && is_numeric($option['capture_delay'])) {
            $this->payzen_request->set('capture_delay', $option['capture_delay']);
        }

        if (isset($option['validation_mode']) && ($option['validation_mode'] !== '-1')) {
            $this->payzen_request->set('validation_mode', $option['validation_mode']);
        }

        // Add cart data.
        if (isset($option['send_cart_data']) && ($option['send_cart_data'] === 'y')) {
            $this->send_cart_data($order);
        }

        delete_transient($this->id . '_card_type_' . $order_id);
    }

    public function get_mean($code)
    {
        $options = $this->get_available_options();

        foreach ($options as $option) {
            if ($option['payment_mean'] == $code) {
                return $option;
            }
        }

        return false;
    }
}
