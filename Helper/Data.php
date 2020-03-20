<?php

namespace Safepay\Checkout\Helper;

use Exception;
use Mageplaza\Core\Helper\AbstractData as CoreHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Safepay\Checkout\Model\EnvVars;
use Magento\Sales\Model\OrderFactory;

class Data extends CoreHelper
{
    const STORE_SCOPE = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
    const SANDBOX = EnvVars::SANDBOX;
    const PRODUCTION = EnvVars::PRODUCTION;

    protected $_orderManagement;
    protected $_orderFactory;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        OrderManagementInterface $orderManagement,
        OrderFactory $orderFactory,
        UrlInterface $urlInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_orderManagement = $orderManagement;
        $this->_orderFactory = $orderFactory;
        $this->_urlInterface = $urlInterface;
        parent::__construct($context, $objectManager, $storeManager);
    }

    public function getEnvironment()
    {
        return $this->getStoreConfigValue('sandbox') ? self::SANDBOX : self::PRODUCTION;
    }

    public function getStoreConfigValue($fieldId)
    {
        return $this->_scopeConfig->getValue(
                    "payment/safepay/".$fieldId, 
                    self::STORE_SCOPE
                );
    }

    /**
     * int $orderId
     * Order cancel by order id $orderId 
     */
    public function cancelOrder($orderId) {
        $this->_orderManagement->cancel($orderId);
    }

    public function getUrl($urlKey = null, $paramArray = [])
    {
        if (!empty($urlKey)) {
            return $this->_urlInterface->getUrl($urlKey, $paramArray);
        } else {
            return $this->_urlInterface->getBaseUrl();
        }
    }

    public function constructUrl($order, $tracker="")
    {
        $baseURL = $this->getStoreConfigValue('sandbox') ? EnvVars::SANDBOX_CHECKOUT_URL : EnvVars::PRODUCTION_CHECKOUT_URL;
        $order_id = $order->getId();
        $params = array(
            "env"            => $this->getStoreConfigValue('sandbox') ? self::SANDBOX : self::PRODUCTION,
            "beacon"         => $tracker,
            "source"         => 'magento',
            "order_id"       => $order_id,
            "nonce"          => 'magento_order_id'. $order_id,
            "redirect_url"   => $this->getUrl('safepay/payment/response'),
            "cancel_url"     => $this->getUrl('safepay/payment/cancel')
        );

        $baseURL = $baseURL.'/?'.http_build_query($params);

        return $baseURL;
    }

    public function getSharedSecret()
    {
        $key = $this->getStoreConfigValue('sandbox') ? $this->getStoreConfigValue('sandbox_webhook_secret') : $this->getStoreConfigValue('production_webhook_secret');
        return $key;
    }

    public function getOrderById($orderId)
    {
        $order = $this->_orderFactory->create()->load($orderId);
        return $order;
    }

    public function validateSignature($tracker, $signature)
    {
        $secret = $this->getSharedSecret();
        $signature_2 = hash_hmac('sha256', $tracker, $secret);
        
        if ($signature_2 === $signature) {
            return true;
        }

        return false;
    }
}