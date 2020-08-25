<?php

namespace Safepay\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Store\Model\StoreManagerInterface;
use Safepay\Checkout\Helper\Data as SafepayHelper;

class Response extends Base
{

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * Response constructor.
     * @param Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param Session $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param ResultFactory $resultFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonHelper $jsonHelper
     * @param SafepayHelper $safepayHelper
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param OrderSender $orderSender
     */
    public function __construct(
        Context $context,
        \Psr\Log\LoggerInterface $logger,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        ScopeConfigInterface $scopeConfig,
        JsonHelper $jsonHelper,
        SafepayHelper $safepayHelper,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        OrderSender $orderSender
    ) {
        parent::__construct(
            $context,
            $logger,
            $checkoutSession,
            $storeManager,
            $resultFactory,
            $scopeConfig,
            $jsonHelper,
            $safepayHelper,
            $formKeyValidator
        );
        $this->orderSender = $orderSender;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Layout
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!empty($this->getRequest()->getParams())) {
            try {
                $postData = $this->getRequest()->getParams();
                $order_id = $postData['order_id']; // Generally sent by gateway
                $signature = ($postData["sig"]);
                $reference_code = ($postData["reference"]);
                $tracker = ($postData["tracker"]);

                if (empty($order_id)) {
                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure' => true]));
                    return $resultRedirect;
                }
                $order = $this->_safepayHelper->getOrderById($order_id);

                $success = false;
                $error = null;

                if (empty($order_id) || empty($signature)) {
                    $error = __('Payment to Safepay Failed. No data received');
                } elseif ($this->_safepayHelper->validateSignature($tracker, $signature) === false) {
                    $error = __('Payment is invalid. Failed security check.');
                } else {
                    $success = true;
                }

                if ($success) {
                    // Payment was successful, so update the order's state, send order email and move to the success page
                    $order->setState(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));
                    $order->setStatus(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));

                    $this->orderSender->send($order);

                    $this->_safepayHelper->createInvoice($order_id);
                    $order->addStatusHistoryComment(__('Payment Gateway Reference %s and tracker id %s', $reference_code, $tracker));

                    // $order->sendNewOrderEmail();
                    // $order->setEmailSent(true);

                    $order->save();

                    $payment = $order->getPayment();
                    $payment->setAdditionalInformation('safepay_sig', $postData['sig']);
                    $payment->setAdditionalInformation('safepay_reference', $postData['reference']);
                    $payment->setAdditionalInformation('safepay_tracker', $postData['tracker']);
                    $payment->setAdditionalInformation('safepay_token_data', $postData['token']);
                    $payment->save();

                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/success', ['_secure'=>true]));
                    return $resultRedirect;
                } else {
                    // There is a problem in the response we got
                    $this->_safepayHelper->cancelOrder($order->getId());
                    $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure' => true]));
                    return $resultRedirect;
                }
            } catch (\Exception $e) {
                $this->_messageManager->addErrorMessage(__('Error occured while processing the payment: %1', $e->getMessage()));
                $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure' => true]));
                return $resultRedirect;
            }
        } else {
            $resultRedirect->setUrl($this->_safepayHelper->getUrl('checkout/onepage/failure', ['_secure' => true]));
            return $resultRedirect;
        }
    }
}
