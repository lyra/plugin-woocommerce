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
    protected $last_error;

    public function __construct()
    {
        $this->logger = new WC_Logger();
        $this->last_error = null;
    }

    /**
     * Action to do in case of error during refund process.
     *
     */
    public function doOnError($errorCode, $message)
    {
        $this->last_error = new WP_Error(
            'payzen_error', sprintf($this->translate('There was a problem initiating an automatic refund. (ERROR %1$s)'), $errorCode),
            $message
        );

        return $this->last_error;
    }

    /**
     * Action to do after sucessful refund process.
     *
     */
    public function doOnSuccess($operationResponse, $operationType)
    {
        if ($operationType == 'frac_update') {
            return $this->doOnError(-1,
                sprintf($this->translate('Refund of split payment is not supported. Please, consider making necessary changes in %1$s Back Office.'),
                    'PayZen'
                    )
                );
        }

        return true;
    }

    /**
     * Action to do after failed refund process.
     *
     */
    public function doOnFailure($errorCode, $message)
    {
        return $this->doOnError($errorCode, $message);
    }

    /**
     * Return the last callback error captured during refund API execution.
     *
     * @return \WP_Error|null
     */
    public function get_last_error()
    {
        return $this->last_error;
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
    public function translate($message)
    {
        return __($message, 'woo-payzen-payment');
    }
}
