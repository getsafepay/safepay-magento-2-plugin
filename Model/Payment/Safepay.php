<?php

namespace Safepay\Checkout\Model\Payment;

/**
 * Class Safepay
 *
 * @package Safepay\Checkout\Model\Payment
 */
class Safepay extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "safepay";
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

