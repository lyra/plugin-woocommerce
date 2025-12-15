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

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

class PayzenRestTools
{
    public static function convertRestResult($answer, $isTransaction = false)
    {
        if (! is_array($answer) || empty($answer)) {
            return [];
        }

        $transactions = self::getProperty($answer, 'transactions');
        $multiPaymentMean = false;

        if ($isTransaction) {
            $transaction = $answer;
        } else {
            if (! is_array($transactions) || empty($transactions)) {
                $transaction = $answer;
            } else {
                $transaction = $transactions[0];
                $transactionsFiltered = array_filter($transactions, function($trs) {
                    $successStatuses = array_merge(PayzenApi::getSuccessStatuses(), PayzenApi::getPendingStatuses());

                    return $trs['operationType'] === 'DEBIT' && in_array($trs['detailedStatus'], $successStatuses);
                });

                if (count($transactionsFiltered) > 1) {
                    $multiPaymentMean = isset($transactionsFiltered[0]['transactionDetails']['cardDetails']['sequenceType'])
                        && $transactionsFiltered[0]['transactionDetails']['cardDetails']['sequenceType'] == 'MULTI_PAYMENT_MEAN';
                }
            }
        }

        $response = [];

        $response['vads_url_check_src'] = self::getProperty($answer, 'kr-src');
        $response['vads_order_cycle'] = self::getProperty($answer, 'orderCycle');
        $response['vads_order_status'] = self::getProperty($answer, 'orderStatus');

        if (($customer = self::getProperty($answer, 'customer'))) {
            $response['vads_cust_email'] = self::getProperty($customer, 'email');

            if ($billingDetails = self::getProperty($customer, 'billingDetails')) {
                $response['vads_language'] = self::getProperty($billingDetails, 'language');
            }
        }

        if ($orderDetails = self::getProperty($answer, 'orderDetails')) {
            $response['vads_order_id'] = self::getProperty($orderDetails, 'orderId');
        }

        $response = self::convertRestTransaction($response, $transaction);

        if ($multiPaymentMean) {
            $payment_seq = array(
                'trans_id' => $response['vads_trans_id'],
                'transactions' => array()
            );

            foreach ($transactions as $trs) {
                $payment_seq['transactions'][] = self::convertRestTransaction(array(), $trs, '');
            }

            $response['vads_card_brand'] = 'MULTI';
            $response['vads_payment_seq'] = json_encode($payment_seq);
            $response['vads_amount'] = self::getProperty($orderDetails, 'orderTotalAmount');
            $response['vads_authorized_amount'] = self::getProperty($orderDetails, 'orderTotalAmount');
        }

        return $response;
    }

    private static function convertRestTransaction($response, $transaction, $prefix = 'vads_') {
        $response[$prefix . 'result'] = self::getProperty($transaction, 'errorCode') ? self::getProperty($transaction, 'errorCode') : '00';
        $response[$prefix . 'extra_result'] = self::getProperty($transaction, 'detailedErrorCode');

        $response[$prefix . 'trans_status'] = self::getProperty($transaction, 'detailedStatus');
        $response[$prefix . 'trans_uuid'] = self::getProperty($transaction, 'uuid');
        $response[$prefix . 'operation_type'] = self::getProperty($transaction, 'operationType');
        $response[$prefix . 'effective_creation_date'] = self::getProperty($transaction, 'creationDate');
        $response[$prefix . 'payment_config'] = 'SINGLE'; // Only single payments are possible via REST API at this time.

        $response[$prefix . 'amount'] = self::getProperty($transaction, 'amount');
        $response[$prefix . 'currency'] = PayzenApi::getCurrencyNumCode(self::getProperty($transaction, 'currency'));

        if ($paymentToken = self::getProperty($transaction, 'paymentMethodToken')) {
            $response[$prefix . 'identifier'] = $paymentToken;
            $response[$prefix . 'identifier_status'] = 'CREATED';
        }

        if (($metadata = self::getProperty($transaction, 'metadata')) && is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                $response[$prefix . 'ext_info_' . $key] = $value;
            }
        }

        if ($transactionDetails = self::getProperty($transaction, 'transactionDetails')) {
            $response[$prefix . 'sequence_number'] = self::getProperty($transactionDetails, 'sequenceNumber');

            // Workarround to adapt to REST API behavior.
            $effectiveAmount = self::getProperty($transactionDetails, 'effectiveAmount');
            $effectiveCurrency = PayzenApi::getCurrencyNumCode(self::getProperty($transactionDetails, 'effectiveCurrency'));

            if ($effectiveAmount && $effectiveCurrency) {
                // Invert only if there is currency conversion.
                if ($effectiveCurrency !== $response[$prefix . 'currency']) {
                    $response[$prefix . 'effective_amount'] = $response[$prefix . 'amount'];
                    $response[$prefix . 'effective_currency'] = $response[$prefix . 'currency'];
                    $response[$prefix . 'amount'] = $effectiveAmount;
                    $response[$prefix . 'currency'] = $effectiveCurrency;
                } else {
                    $response[$prefix . 'effective_amount'] = $effectiveAmount;
                    $response[$prefix . 'effective_currency'] = $effectiveCurrency;
                }
            }

            $response[$prefix . 'warranty_result'] = self::getProperty($transactionDetails, 'liabilityShift');
            $response[$prefix . 'wallet'] = self::getProperty($transactionDetails, 'wallet');

            if ($cardDetails = self::getProperty($transactionDetails, 'cardDetails')) {
                $response[$prefix . 'trans_id'] = self::getProperty($cardDetails, 'legacyTransId'); // Deprecated.
                $response[$prefix . 'presentation_date'] = self::getProperty($cardDetails, 'expectedCaptureDate');

                $response[$prefix . 'card_brand'] = self::getProperty($cardDetails, 'effectiveBrand');
                $response[$prefix . 'card_number'] = self::getProperty($cardDetails, 'pan');
                $response[$prefix . 'expiry_month'] = self::getProperty($cardDetails, 'expiryMonth');
                $response[$prefix . 'expiry_year'] = self::getProperty($cardDetails, 'expiryYear');

                $response[$prefix . 'payment_option_code'] = self::getProperty($cardDetails, 'installmentNumber');


                if ($authorizationResponse = self::getProperty($cardDetails, 'authorizationResponse')) {
                    $response[$prefix . 'auth_result'] = self::getProperty($authorizationResponse, 'authorizationResult');
                    $response[$prefix . 'authorized_amount'] = self::getProperty($authorizationResponse, 'amount');
                }

                if (($authenticationResponse = self::getProperty($cardDetails, 'authenticationResponse'))
                    && ($value = self::getProperty($authenticationResponse, 'value'))) {
                    $response[$prefix . 'threeds_status'] = self::getProperty($value, 'status');
                    $response[$prefix . 'threeds_auth_type'] = self::getProperty($value, 'authenticationType');
                    if ($authenticationValue = self::getProperty($value, 'authenticationValue')) {
                        $response[$prefix . 'threeds_cavv'] = self::getProperty($authenticationValue, 'value');
                    }
                } elseif (($threeDSResponse = self::getProperty($cardDetails, 'threeDSResponse'))
                    && ($authenticationResultData = self::getProperty($threeDSResponse, 'authenticationResultData'))) {
                    $response[$prefix . 'threeds_cavv'] = self::getProperty($authenticationResultData, 'cavv');
                    $response[$prefix . 'threeds_status'] = self::getProperty($authenticationResultData, 'status');
                    $response[$prefix . 'threeds_auth_type'] = self::getProperty($authenticationResultData, 'threeds_auth_type');
                }
            }

            if ($fraudManagement = self::getProperty($transactionDetails, 'fraudManagement')) {
                if ($riskControl = self::getProperty($fraudManagement, 'riskControl')) {
                    $response[$prefix . 'risk_control'] = '';

                    foreach ($riskControl as $value) {
                        if (! isset($value['name']) || ! isset($value['result'])) {
                            continue;
                        }

                        $response[$prefix . 'risk_control'] .= "{$value['name']}={$value['result']};";
                    }
                }

                if ($riskAssessments = self::getProperty($fraudManagement, 'riskAssessments')) {
                    $response[$prefix . 'risk_assessment_result'] = self::getProperty($riskAssessments, 'results');
                }
            }
        }

        return $response;
    }

    public static function getProperty($array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return null;
    }

    public static function checkHash($data, $key)
    {
        $supported_sign_algos = array('sha256_hmac');

        // Check if the hash algorithm is supported.
        if (! in_array($data['kr-hash-algorithm'], $supported_sign_algos)) {
            return false;
        }

        // On some servers, / can be escaped.
        $kr_answer = str_replace('\/', '/', $data['kr-answer']);

        $hash = hash_hmac('sha256', $kr_answer, $key);

        // Return true if calculated hash and sent hash are the same.
        return ($hash === $data['kr-hash']);
    }

    public static function checkResponse($data)
    {
        return isset($data['kr-hash']) && isset($data['kr-hash-algorithm']) && isset($data['kr-answer']);
    }

    // Check REST WS response.
    public static function checkResult($response, $expectedStatuses = array())
    {
        $answer = $response['answer'];

        if ($response['status'] != 'SUCCESS') {
            $errorMessage = $answer['errorMessage'] . ' (' . $answer['errorCode'] . ').';

            if (isset($answer['detailedErrorMessage']) && ! empty($answer['detailedErrorMessage'])) {
                $errorMessage .= ' Detailed message: ' . $answer['detailedErrorMessage'] . ($answer['detailedErrorCode'] ?
                    ' (' . $answer['detailedErrorCode'] . ').' : '');
            }

            require_once 'PayzenRestException.php';
            throw new PayzenRestException($errorMessage, $answer['errorCode']);
        } elseif (! empty($expectedStatuses) && ! in_array($answer['detailedStatus'], $expectedStatuses)) {
            throw new Exception(sprintf(__('Unexpected transaction type received (%1$s).', 'woo-payzen-payment'), $answer['detailedStatus']));
        }
    }
}
