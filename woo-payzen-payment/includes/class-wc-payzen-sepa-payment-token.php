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

class WC_Payment_Token_Payzen_SEPA extends WC_Payment_Token {
    /**
     * Stores payment type.
     *
     * @var string
     */
    protected $type = 'payzen_sepa';

    /**
     * Stores SEPA payment token data.
     *
     * @var array
     */
    protected $extra_data = array(
        'last4' => '',
        'payment_method_type' => 'SDD',
    );

    public function get_display_name($deprecated = '')
    {
        return sprintf(
            __('%1$s ending in %2$s', 'woocommerce'),
            'IBAN / BIC',
            $this->get_last4()
        );
    }

    public function validate()
    {
        if (parent::validate() == false) {
            return false;
        }

        if (! $this->get_last4('edit')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the last four digits.
     *
     * @since  4.0.0
     * @version 4.0.0
     * @param  string $context What the value is for. Valid values are view and edit.
     * @return string Last 4 digits
     */
    public function get_last4($context = 'view')
    {
        return $this->get_prop('last4', $context);
    }

    public function set_last4($last4)
    {
        $this->set_prop('last4', $last4);
    }

    public function set_payment_method_type($type)
    {
        $this->set_prop('payment_method_type', $type);
    }

    public function get_payment_method_type($context = 'view')
    {
        return $this->get_prop('payment_method_type', $context);
    }
}
