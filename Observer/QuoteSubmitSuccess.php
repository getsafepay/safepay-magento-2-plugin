<?php

namespace Safepay\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Safepay\Checkout\Model\Payment\Safepay;

/**
 * Class QuoteSubmitSuccess
 * @package Safepay\Checkout\Observer
 */
class QuoteSubmitSuccess implements ObserverInterface
{

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        if ($order->getPayment()->getMethod() === Safepay::PAYMENT_METHOD_CODE) {
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
