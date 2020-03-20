<?php
namespace Safepay\Checkout\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;
use Safepay\Checkout\Model\EnvVars;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Cancel extends Base
{
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $order = $this->_checkoutSession->getLastRealOrder();

            if (empty($order->getId())) {
                throw new NoSuchEntityException(__('No Order Found.'));
            }
            $order = $this->_checkoutSession->getLastRealOrder();
            $order->setComment(__('Payment Canceled.'))->addStatusHistoryComment(__('Payment Canceled.'))->save();
            $this->_safepayHelper->cancelOrder($order->getId());
            $this->_messageManager->addWarning(__('Payment couldn\'t be completed. <a href="%1">Click here</a> to retry.', $this->_safepayHelper->getUrl('safepay/order/reorder', ['order_id' => $order->getId(), 'hide_error' => true])));
            $this->_checkoutSession->clearQuote()->clearStorage();
            $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/cart'));
            return $resultRedirect;

        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
            $this->_messageManager->addErrorMessage(__('Something went wrong, please try again.'));
            $this->_checkoutSession->clearQuote()->clearStorage();
            $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/cart'));
            return $resultRedirect;
        }
    }
}
