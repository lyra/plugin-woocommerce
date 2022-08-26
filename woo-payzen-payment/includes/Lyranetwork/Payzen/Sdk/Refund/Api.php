<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Refund;

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Rest\Api as PayzenRest;

/**
 * Class allowing orders refund using REST WS.
 */
class Api {
    /**
     * refundProcessor Object
     *
     * @var refundProcessor
     */
    private $refundProcessor;

    /**
     * Private key used in REST WS.
     *
     * @var string
     */
    private $privateKey;

    /**
     * Url to REST server used in REST WS.
     *
     * @var string
     */
    private $restServerUrl;

    /**
     * Merchant site ID used in REST WS.
     *
     * @var string
     */
    private $siteId;

    /**
     * CMS name
     *
     * @var string
     */
    private $cmsName;

    /**
     * Constructor for refund API class.
     *
     * @param \Lyranetwork\Payzen\Sdk\Refund\Processor $refundProcessor
     * @param string $privateKey
     * @param string $restServerUrl
     * @param string $siteId
     * @param string $cmsName
     */
    public function __construct($refundProcessor, $privateKey, $restServerUrl, $siteId, $cmsName)
    {
        $this->refundProcessor = $refundProcessor;
        $this->privateKey = $privateKey;
        $this->restServerUrl = $restServerUrl;
        $this->siteId = $siteId;
        $this->cmsName = $cmsName;
    }

    /**
     * Refund money.
     *
     * @param \Lyranetwork\Payzen\Sdk\Refund\OrderInfo $orderInfo
     * @param float $amount
     */
    public function refund($orderInfo, $amount)
    {
        // Client has not configured private key in module backend, let CMS do offline refund.
        if (! $this->privateKey) {
            $this->refundProcessor->log("Impossible to make online refund for order #{$orderInfo->getOrderId()}: private key is not configured. Let {$this->cmsName} do offline refund.", 'WARNING');
            $this->refundProcessor->doOnError('privateKey', "Impossible to make online refund for order #{$orderInfo->getOrderId()}: private key is not configured. Let {$this->cmsName} do offline refund.");
            return true;
        }

        // Get currency.
        $currency = PayzenApi::findCurrencyByAlphaCode($orderInfo->getOrderCurrencyIsoCode());
        $amount = round($amount, $currency->getDecimals());
        $amountInCents = $currency->convertAmountToInteger($amount);

        $this->refundProcessor->log("Start refund of {$amount} {$orderInfo->getOrderCurrencySign()} for order #{$orderInfo->getOrderId()} on payment gateway.", 'INFO');

        try {
            // Get payment details.
            $getPaymentDetails = $this->getPaymentDetails($orderInfo);
            if (count($getPaymentDetails) > 1) {
                // Payment in installments, refund the desired amount from last installment to first one.
                // Check if we can refund $amount.
                $refundableAmount = 0;
                foreach ($getPaymentDetails as $key => $transaction) {
                    // Get the refundable amount of each transaction.
                    $transactionRefundableAmount = $this->getTransactionRefundableAmount($transaction, $orderInfo->getOrderCurrencyIsoCode());
                    $getPaymentDetails[$key]['transactionRefundableAmount'] = $transactionRefundableAmount;
                    $refundableAmount += $transactionRefundableAmount;
                }

                if ($amountInCents > $refundableAmount) {
                    // Unable to refund more than the sum of the refundable amount of each installment.
                    $msg = sprintf(
                        $this->refundProcessor->translate('Remaining amount (%1$s %2$s) is less than requested refund amount (%3$s %2$s).'),
                        $currency->convertAmountToFloat($refundableAmount),
                        $orderInfo->getOrderCurrencySign(),
                        $amount
                    );
                    throw new \Exception($msg);
                } else {
                    $AmountStillToRefund = $amountInCents;
                    foreach ($getPaymentDetails as $transaction) {
                        if ($transaction['transactionRefundableAmount'] > 0) {
                            $transactionAmounRefund = min($transaction['transactionRefundableAmount'], $AmountStillToRefund);
                            $AmountStillToRefund -= $transactionAmounRefund;

                            // Do not update order status till we refund all the amount.
                            $this->refundFromOneTransaction($orderInfo, $transactionAmounRefund, $transaction, $currency);

                            if ($AmountStillToRefund == 0) {
                                break;
                            }
                        }
                    }
                }
            } else {
                // Standard payment, refund on the only transaction.
                $this->refundFromOneTransaction($orderInfo, $amountInCents, reset($getPaymentDetails), $currency);
            }

            return true;
        } catch (\Exception $e) {
            $this->refundProcessor->log("{$e->getMessage()}" . ($e->getCode() > 0 ? ' (' . $e->getCode() . ').' : ''), 'ERROR');

            $errorCode = ($e->getCode() <= -1) ? -1 : $e->getCode();
            switch ((string) $errorCode) {
                case 'PSP_100':
                    // Merchant don't have offer allowing REST WS.
                    // Allow offline refund and display warning message.
                    $this->refundProcessor->doOnError($errorCode, $this->refundProcessor->translate("Payment is refunded/canceled only in {$this->cmsName}. Please, consider making necessary changes in gateway Back Office."));
                    return true;

                case 'PSP_083':
                    $message = $this->refundProcessor->translate('Chargebacks cannot be refunded.');
                    break;

                case '-1': // Manage cUrl errors.
                    $message = sprintf($this->refundProcessor->translate('Error occurred when refunding payment for order #%1$s. Please consult the payment module log for more details.'), $orderInfo->getOrderReference());
                    break;

                case '0':
                    $message = sprintf($this->refundProcessor->translate('Cannot refund payment for order #%1$s.'), $orderInfo->getOrderReference()) . ' ' . $e->getMessage();
                    break;

                default:
                    $message = $this->refundProcessor->translate('Refund error') . ': ' . $e->getMessage();
                    break;
            }

            $this->refundProcessor->doOnFailure($errorCode, $message);

            return false;
        }
    }

    /**
     * Get payment details for the passed order info.
     *
     * @param \Lyranetwork\Payzen\Sdk\Refund\OrderInfo $orderInfo
     * @return array
     */
    private function getPaymentDetails($orderInfo)
    {
        /**
         * @var Lyranetwork\Payzen\Sdk\Rest\Api $client
         * */
        $client = new PayzenRest(
            $this->restServerUrl,
            $this->siteId,
            $this->privateKey
        );

        $requestData = array(
            'orderId' => $orderInfo->getOrderRemoteId(),
            'operationType' => 'DEBIT'
        );

        $getOrderResponse = $client->post('V4/Order/Get', json_encode($requestData));
        self::checkRestResult($getOrderResponse);

        // Order transactions organized by sequence numbers.
        $transBySequence = array();
        foreach ($getOrderResponse['answer']['transactions'] as $transaction) {
            $sequenceNumber = $transaction['transactionDetails']['sequenceNumber'];
            // Unpaid transactions are not considered.
            if ($transaction['status'] !== 'UNPAID') {
                $transBySequence[$sequenceNumber] = $transaction;
            }
        }

        ksort($transBySequence);
        return array_reverse($transBySequence);
    }

    // Check REST WS response.
    private function checkRestResult($response, $expectedStatuses = array())
    {
        $answer = $response['answer'];

        if ($response['status'] !== 'SUCCESS') {
            $errorMessage = $response['answer']['errorMessage'] . ' (' . $answer['errorCode'] . ').';

            if (isset($answer['detailedErrorMessage']) && ! empty($answer['detailedErrorMessage'])) {
                $errorMessage .= ' Detailed message: ' . $answer['detailedErrorMessage'] . ' (' . $answer['detailedErrorCode'] . ').';
            }

            throw new \Lyranetwork\Payzen\Sdk\Refund\WsException($errorMessage, $answer['errorCode']);
        } elseif (! empty($expectedStatuses) && ! in_array($answer['detailedStatus'], $expectedStatuses, true)) {
            throw new \Exception(sprintf($this->refundProcessor->translate('Unexpected transaction type received (%1$s).'), $answer['detailedStatus']));
        }
    }

    private function getTransactionRefundableAmount($transaction, $orderCurrencyIsoCode)
    {
        if ($transaction['detailedStatus'] === 'CAPTURED') {
            // Get transaction amount and already refunded amount.
            if ($orderCurrencyIsoCode !== $transaction['currency']) {
                $transAmount = $transaction['transactionDetails']['effectiveAmount'];
                $refundedAmount = $transaction['transactionDetails']['cardDetails']['captureResponse']['effectiveRefundAmount'];
            } else {
                $transAmount = $transaction['amount'];
                $refundedAmount = $transaction['transactionDetails']['cardDetails']['captureResponse']['refundAmount'];
            }

            if (empty($refundedAmount)) {
                $refundedAmount = 0;
            }

            $refundedableAmount = $transAmount - $refundedAmount;
        } else {
            $refundedableAmount = ($orderCurrencyIsoCode !== $transaction['currency']) ?
                $transaction['transactionDetails']['effectiveAmount'] : $transaction['amount'];
        }

        return $refundedableAmount;
    }

    private function refundFromOneTransaction($orderInfo, $amountInCents, $transaction, $currency)
    {
        $amount = $currency->convertAmountToFloat($amountInCents, $currency->getDecimals());
        $successStatuses = array_merge(
            PayzenApi::getSuccessStatuses(),
            PayzenApi::getPendingStatuses()
        );

        $transStatus = $transaction['detailedStatus'];
        $uuid = $transaction['uuid'];
        $commentText = $orderInfo->getOrderUserInfo();

        /**
         * @var Lyranetwork\Payzen\Sdk\Rest\Api $client
         */
        $client = new PayzenRest(
            $this->restServerUrl,
            $this->siteId,
            $this->privateKey
        );

        if ($transStatus === 'CAPTURED') { // Transaction captured, we can do refund.
            $real_refund_amount = $amountInCents;
            // Get transaction amount and already transaction refunded amount.
            if ($orderInfo->getOrderCurrencyIsoCode() != $transaction['currency']) {
                $currency_conversion = true;
                $transAmount = $transaction['transactionDetails']['effectiveAmount'];
                $refundedAmount = $transaction['transactionDetails']['cardDetails']['captureResponse']['effectiveRefundAmount'];
            } else {
                $currency_conversion = false;
                $transAmount = $transaction['amount'];
                $refundedAmount = $transaction['transactionDetails']['cardDetails']['captureResponse']['refundAmount'];
            }

            if (empty($refundedAmount)) {
                $refundedAmount = 0;
            }

            $remainingAmount = $transAmount - $refundedAmount; // Calculate remaining amount.
            $currency_alpha3 = $currency->getAlpha3();

            if ($remainingAmount < $amountInCents) {
                if (! $currency_conversion) {
                    $remainingAmountFloat = $currency->convertAmountToFloat($remainingAmount);
                    $msg = sprintf(
                        $this->refundProcessor->translate('Remaining amount (%1$s %2$s) is less than requested refund amount (%3$s %2$s).'),
                        $remainingAmountFloat,
                        $orderInfo->getOrderCurrencySign(),
                        $amount
                    );
                    throw new \Exception($msg);
                } else {
                    // It may be caused by currency conversion.
                    // We refund all the transaction refundable remaining amount in the gateway currency to avoid also conversions rounding.
                    $amountInCents = $transaction['amount'] - $transaction['transactionDetails']['cardDetails']['captureResponse']['refundAmount'];
                    $currency_alpha3 = $transaction['currency'];
                }
            }

            $requestData = array(
                'uuid' => $uuid,
                'amount' => $amountInCents,
                'currency' => $currency_alpha3,
                'resolutionMode' => 'REFUND_ONLY',
                'comment' => $commentText
            );

            $refundPaymentResponse = $client->post('V4/Transaction/CancelOrRefund', json_encode($requestData));

            self::checkRestResult(
                $refundPaymentResponse,
                $successStatuses
            );

            // Check operation type.
            $transType = $refundPaymentResponse['answer']['operationType'];

            if ($transType !== 'CREDIT') {
                throw new \Exception(sprintf($this->refundProcessor->translate('Unexpected transaction type received (%1$s).'), $transType));
            }

            // Refund success do after refund function.
            $this->refundProcessor->log("Online refund $amount {$orderInfo->getOrderCurrencySign()} for transaction with uuid #$uuid for order #{$orderInfo->getOrderId()} is successful.", 'INFO');
            $this->refundProcessor->doOnSuccess($refundPaymentResponse['answer'], 'refund');
        } else {
            $transAmount = $transaction['amount'];

            // If order currency different than transaction currency we use transaction effective amount.
            if ($orderInfo->getOrderCurrencyIsoCode() != $transaction['currency']) {
                $transAmount = $transaction['transactionDetails']['effectiveAmount'];
            }

            if ($amountInCents > $transAmount) {
                $transAmountFloat = $currency->convertAmountToFloat($transAmount);
                $msg = sprintf($this->refundProcessor->translate('Transaction amount (%1$s %2$s) is less than requested refund amount (%3$s %2$s).'), $transAmountFloat, $orderInfo->getOrderCurrencySign(), $amount);
                throw new \Exception($msg);
            }

            if ($amountInCents == $transAmount) { // Transaction cancel in gateway.
                $requestData = array(
                    'uuid' => $uuid,
                    'resolutionMode' => 'CANCELLATION_ONLY',
                    'comment' => $commentText
                );

                $cancelPaymentResponse = $client->post('V4/Transaction/CancelOrRefund', json_encode($requestData));
                self::checkRestResult($cancelPaymentResponse, array('CANCELLED'));

                // Refund success do after refund function.
                $this->refundProcessor->log("Online transaction with uuid #$uuid cancel for order #{$orderInfo->getOrderId()} is successful.", 'INFO');
                $this->refundProcessor->doOnSuccess($cancelPaymentResponse['answer'], 'cancel');
            } else {
                // Partial transaction cancel, call update WS.
                $new_transaction_amount = $transAmount - $amountInCents;
                $requestData = array(
                    'uuid' => $uuid,
                    'cardUpdate' => array(
                        'amount' => $new_transaction_amount,
                        'currency' => $currency->getAlpha3()
                    ),
                    'comment' => $commentText
                );

                $updatePaymentResponse = $client->post('V4/Transaction/Update', json_encode($requestData));

                self::checkRestResult(
                    $updatePaymentResponse,
                    array(
                        'AUTHORISED',
                        'AUTHORISED_TO_VALIDATE',
                        'WAITING_AUTHORISATION',
                        'WAITING_AUTHORISATION_TO_VALIDATE'
                    )
                );

                // Refund success do after refund function.
                $this->refundProcessor->log("Online transaction with uuid #$uuid update for order #{$orderInfo->getOrderId()} is successful.", 'INFO');
                $this->refundProcessor->doOnSuccess($updatePaymentResponse['answer'], 'update');
            }
        }
    }
}
