<?php
namespace Safepay\Checkout\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;
use Safepay\Checkout\Model\EnvVars;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Response extends Base
{
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!empty($this->getRequest()->getPostValue())) {
            try {
                $postData = $this->getRequest()->getPostValue();
                $order_id = $postData['order_id']; // Generally sent by gateway
                $signature = ($postData["sig"]);
                $reference_code = ($postData["reference"]);
                $tracker = ($postData["tracker"]);

                if (empty($order_id)) {
                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure'=>true]));
                    return $resultRedirect;
                }
                $order = $this->_safepayHelper->getOrderById($order_id);
                
                $success = false;
                $error = null;

                if (empty($order_id) || empty($signature)) {
                    $error = __('Payment to Safepay Failed. No data received');
                }  else if ($this->_safepayHelper->validateSignature($tracker, $signature) === false) {
                    $error = __('Payment is invalid. Failed security check.');
                } else {
                    $success = true;
                }

                if($success) {
                    // Payment was successful, so update the order's state, send order email and move to the success page
                    $order->setState(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));
                    $order->setStatus(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));
                    $order->addStatusHistoryComment(__('Payment Gateway Reference %s and tracker id %s',$reference_code,$tracker));

                    // $order->sendNewOrderEmail();
                    // $order->setEmailSent(true);
                    
                    $order->save();
                    
                    $payment = $order->getPayment();
                    $payment->setAdditionalInformation('safepay_sig', $postData['sig']);
                    $payment->setAdditionalInformation('safepay_reference', $postData['reference']);
                    $payment->setAdditionalInformation('safepay_tracker', $postData['tracker']);
                    $payment->setAdditionalInformation('safepay_token_data', $postData['token']);
                    $payment->save();
        
                    $this->_checkoutSession->unsQuoteId();

                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/success', ['_secure'=>true]));
                    return $resultRedirect;
                } else {
                    // There is a problem in the response we got
                    $this->_safepayHelper->cancelOrder($order->getId());
                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure'=>true]));
                    return $resultRedirect;
                }
            } catch (\Exception $e) {
                print_r($e->getMessage());
                $this->_messageManager->addErrorMessage(__('Error occured while processing the payment: %1', $e->getMessage()));
                $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure'=>true]));
                return $resultRedirect;
            }
        } else {
            $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure'=>true]));
            return $resultRedirect;
        }
    }
}
