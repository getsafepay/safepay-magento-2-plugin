<?php

namespace Safepay\Checkout\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;

use Safepay\Checkout\Helper\Data as SafepayHelper;

class Receiver extends Action
{
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    protected $creditMemoFactory;
    protected $creditMemoService;
    protected $invoice;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var File
     */
    private $file;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private $historyRepository;

    /**
     * @var HistoryFactory
     */
    private $historyFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    private $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transaction;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /** @var  \Magento\Sales\Model\Order */
    private $order;

    protected $_safepayHelper;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $order,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $jsonResultFactory,
        LoggerInterface $logger,
        File $file,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Registry $registry,
        HistoryFactory $historyFactory,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        InvoiceRepositoryInterface $invoiceRepository,
        CreditmemoFactory $creditMemoFactory,
        CreditmemoService $creditMemoService,
        Invoice $invoice,
        SafepayHelper $safepayHelper
    ) {
        parent::__construct($context);
        $this->orderRepository = $order;
        $this->scopeConfig = $scopeConfig;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->logger = $logger;
        $this->file = $file;
        $this->_safepayHelper = $safepayHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->registry = $registry;
        $this->historyFactory = $historyFactory;
        $this->historyRepository = $historyRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceRepository = $invoiceRepository;
        $this->creditMemoFactory = $creditMemoFactory;
        $this->creditMemoService = $creditMemoService;
        $this->invoice = $invoice;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json|null
     * @throws LocalizedException
     */
    public function execute()
    {
        try {
            $input = $this->file->read('php://input');
            $success = false;
            $error = "";

            if (!empty($input) && $this->authenticate($input)) {
                $data       = json_decode( $input, true );
                $hook_data  = $data['data'];
                $this->logger->info("Safepay Webhook received event: " . print_r($hook_data, true));

                if( $hook_data['type']  == "error:occurred" ){
                    return null;
                }

                if ( ! isset( $hook_data['notification']['metadata']['order_id'] ) && $hook_data['notification']['metadata']['source'] != 'magento2' ) {
                    // Probably a charge not created by us.
                    return null;
                }else{
                    $success = true;
                }

                $event = $this->getEventData($hook_data);
                $orderObject = $this->_safepayHelper->getOrderById($event['orderId']);

                if (!$this->_safepayHelper->getOrderById($event['orderId'])) {
                    return null;
                }

                if (($success === true) && $event['type'] == 'payment:created') {
                    $this->paymentSuccessAction($event);
                }elseif($event['type'] == "refund:created"){
                    $this->refund($event);
                }elseif($event['type'] == "error:occurred"){
                    $this->paymentFailedAction($event);
                }

                $this->getResponse()->setStatusHeader(200);
                $result = $this->jsonResultFactory->create();
                return $result;
            }
            return null;

        } catch (\Exception $e) {
            $this->logger->critical('Safepay Webhook Receive Error', ['exception' => $e]);
            throw new LocalizedException(__('Something went wrong while Webhook recieving Api Response'.$e));
        }
    }

    /**
     * @param $payload
     * @return bool
     */
    private function authenticate($payload) {
        $data        = json_decode( $payload, true );
        $hook_data   = $data['data'];
        $key = $this->_safepayHelper->getSharedSecret();
        $headerSignature = $this->getRequest()->getHeader('X-Sfpy-Signature');
        $computedSignature = hash_hmac('sha512', json_encode($hook_data,JSON_UNESCAPED_SLASHES), $key);

        return $headerSignature === $computedSignature;
    }

    /**
     * @param $input
     * @return array
     */
    private function getEventData($input) {
        $data['orderId'] = isset($input['notification']['metadata']['order_id']) ?
        $input['notification']['metadata']['order_id'] : null;
        $data['chargeCode'] = isset($input['notification']['reference']) ? $input['notification']['reference'] : '';
        $data['type'] = $input['type'];
        $data['error'] = isset($input['notification']['message']) ? $input['notification']['message'] : '';
        $data['refund_amount'] = $input['notification']['amount'];
        $data['tracker'] = $input['notification']['tracker'];
        $data['currency'] = $input['notification']['currency'];
        return $data;
    }

    /**
     * Remove order from store
     */
    private function paymentFailedAction($event)
    {

        $orderObject = $this->_safepayHelper->getOrderById($event['orderId']);

        $history = $this->historyFactory->create();
        $history->setParentId($order->getId())->setComment('EXPIRED')
            ->setEntityName('order')
            ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
        $this->historyRepository->save($history);
        $orderObject->cancel();
        return $this->orderRepository->save($orderObject);
    }

    /**
     * @param $event
     * @return null
     */
    private function paymentSuccessAction($event)
    {
        $orderObject = $this->_safepayHelper->getOrderById($event['orderId']);
        

        $orderObject->setState(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));
        $orderObject->setStatus(Order::STATE_PROCESSING, true, __('Gateway has authorized the payment.'));
        $this->_safepayHelper->createInvoice($event['orderId'], $event['refund_amount']);
        $comment = $event['currency'] . ' ' . $event['refund_amount'];
        $payment = $this->createTransaction($orderObject, $event);

        $orderObject->addStatusHistoryComment(__('Payment Gateway Reference '.$event['chargeCode'].' and tracker id '. $event['tracker']));
        $orderObject->setGrandTotal($event['refund_amount']);
        $orderObject->setBaseGrandTotal($event['refund_amount']);
        $orderObject->setTotalPaid($event['refund_amount']);
        $orderObject->setBaseTotalPaid($event['refund_amount']);
        $orderObject->save();

        
    }

     /**
     * @param OrderInterface $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function refund($event)
    {
        $orderObject = $this->_safepayHelper->getOrderById($event['orderId']);
        $invoices = $orderObject->getInvoiceCollection();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var RefundInvoiceInterface $refundInvoice */
        $refundInvoice = $objectManager->get("\Magento\Sales\Api\RefundInvoiceInterface");
        foreach ($invoices as $invoice) {
            // If you don't want to refund shipping, or to make adjustment refunds, use the CreationArguments (otherwise, you can leave these next two lines out)
            // $creditMemoCreationArguments = new CreationArguments();
            // $creditMemoCreationArguments->setShippingAmount(0);
            $comment = 'Order has been Refunded Successfully';
    
            $refund = $refundInvoice->execute($invoice->getId(), [] , false);
            $this->logger->critical('Safepay refund'.$refund);
        }
    }

    public function createTransaction($order = null, $event = array())
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($event['tracker']);
            $payment->setTransactionId($event['tracker']);
            // $payment->setAdditionalInformation(
            //     [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $event]
            // );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            /** @var TransactionBuilder $transactionBuilder */
            $trans = $objectManager->create(TransactionBuilder::class);
            // $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($event['tracker'])
            ->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $event]
            )
            ->setFailSafe(true)
            //build method creates the transaction and returns the object
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return  $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            //log errors here
        }
    }

}
