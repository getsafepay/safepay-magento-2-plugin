<?php

namespace Safepay\Checkout\Model\Payment;

/**
 * Class Safepay
 *
 * @package Safepay\Checkout\Model\Payment
 */
class Safepay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CODE = "safepay";

    protected $_code = self::PAYMENT_METHOD_CODE;
    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = true;
    protected $_canUseForMultishipping  = false;
    protected $_infoBlockType = \Safepay\Checkout\Block\Info::class;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}
