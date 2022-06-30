<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Refund;

class OrderInfo {
    // $orderRemoteId is the order ID in the payment platform (in some CMS corresponds to cart ID).
    private $orderRemoteId;
    private $orderId;
    private $orderReference;
    private $orderCurrencyIsoCode;
    private $orderCurrencySign;
    private $orderUserInfo;

    /**
     * @return string
     */
    public function getOrderRemoteId()
    {
        return $this->orderRemoteId;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return string
     */
    public function getOrderReference()
    {
        return $this->orderReference;
    }

    /**
     * @return string
     */
    public function getOrderCurrencyIsoCode()
    {
        return $this->orderCurrencyIsoCode;
    }

    /**
     * @return string
     */
    public function getOrderCurrencySign()
    {
        return $this->orderCurrencySign;
    }

    /**
     * @return string
     */
    public function getOrderUserInfo()
    {
        return $this->orderUserInfo;
    }

    /**
     * @param string $orderRemoteId
     */
    public function setOrderRemoteId($orderRemoteId)
    {
        $this->orderRemoteId = $orderRemoteId;

        return $this;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @param string $orderReference
     */
    public function setOrderReference($orderReference)
    {
        $this->orderReference = $orderReference;

        return $this;
    }

    /**
     * @param string $orderCurrencyIsoCode
     */
    public function setOrderCurrencyIsoCode($orderCurrencyIsoCode)
    {
        $this->orderCurrencyIsoCode = $orderCurrencyIsoCode;

        return $this;
    }

    /**
     * @param string $orderCurrencySign
     */
    public function setOrderCurrencySign($orderCurrencySign)
    {
        $this->orderCurrencySign = $orderCurrencySign;

        return $this;
    }

    /**
     * @param string $orderUserInfo
     */
    public function setOrderUserInfo($orderUserInfo)
    {
        $this->orderUserInfo = $orderUserInfo;

        return $this;
    }
}
