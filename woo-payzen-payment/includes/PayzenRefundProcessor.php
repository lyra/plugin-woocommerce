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

use Lyranetwork\Payzen\Sdk\Refund\Processor as RefundProcessor;

class PayzenRefundProcessor implements RefundProcessor
{
    protected $logger;

    public function __construct()
    {
        $this->logger = new WC_Logger();
    }

    /**
     * Action to do in case of error during refund process.
     *
     */
    public function doOnError($errorCode, $message)
    {
        $msg = '<div class="inline error" style="text-align: left;"><p><strong>' . sprintf(__('An error has occurred during the online refund process. Please, consider making necessary changes in %s Back Office.', 'woo-payzen-payment'), WC_Gateway_Payzen::BACKOFFICE_NAME) . '</strong></p></div>';
        set_transient('payzen_online_refund_result', $msg);
    }

    /**
     * Action to do after sucessful refund process.
     *
     */
    public function doOnSuccess($operationResponse, $operationType)
    {

       $msg = '<div class="inline updated" style="text-align: left;"><p><strong>' . __('Online refund successfully completed', 'woo-payzen-payment') . '</strong></p></div>';
       set_transient('payzen_online_refund_result', $msg);
    }

    /**
     * Action to do after failed refund process.
     *
     */
    public function doOnFailure($errorCode, $message)
    {
        $this->doOnError($errorCode, $message);
    }

    /**
     * Log informations.
     *
     */
    public function log($message, $level)
    {
        $general_settings = get_option('woocommerce_payzen_settings', null);
        $debug = is_array($general_settings) && isset($general_settings['debug']) && ($general_settings['debug'] == 'yes');

        if (! $debug) {
            return;
        }

        $this->logger->add('payzen', $message);
    }

    /**
     * Translate given message.
     *
     */
    public function Translate($message)
    {
        return __($message);
    }
}
