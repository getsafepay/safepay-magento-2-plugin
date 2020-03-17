<?php

namespace Safepay\Checkout\Helper;

use Exception;
use Mageplaza\Core\Helper\AbstractData as CoreHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\UrlInterface;

class Data extends CoreHelper
{
    const STORE_SCOPE = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;

    /**
     * @var CurlFactory
     */
    protected $_curlFactory;

    /**
     * @var DefaultFormsPaths
     */
    protected $_formPaths;

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
        UrlInterface $urlInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_urlInterface = $urlInterface;
        parent::__construct($context, $objectManager, $storeManager);
    }

    public function getStoreConfigValue($fieldId)
    {
        return $this->_scopeConfig->getValue(
                    "payment/safepay/".$fieldId, 
                    self::STORE_SCOPE
                );
    }
}