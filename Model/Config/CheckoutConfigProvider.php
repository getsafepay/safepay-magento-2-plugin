<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Safepay\Checkout\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Safepay\Checkout\Helper\Data as SafepayHelper;
use Safepay\Checkout\Model\EnvVars;

/**
 * Class CheckoutConfigProvider
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    protected $_safepayHelper;
    /**
     * @param SafepayHelper $safepayHelper
     */
    public function __construct(
        SafepayHelper $safepayHelper
    ) {
        $this->_safepayHelper = $safepayHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $safepaycheckoutConfig = array();
        $safepaycheckoutConfig['sandbox'] = (bool)$this->getStoreConfigValue("sandbox");
        $safepaycheckoutConfig['environment'] = (bool)$this->getStoreConfigValue("sandbox") ? 'sandbox' : 'production';
        $tokenApiBaseUrl = $safepaycheckoutConfig['sandbox'] ? EnvVars::SANDBOX_API_URL : EnvVars::PRODUCTION_API_URL;
        $safepaycheckoutConfig['api_key'] = $safepaycheckoutConfig['sandbox'] ? $this->getStoreConfigValue("sandbox_key") : $this->getStoreConfigValue("production_key");
        $safepaycheckoutConfig['webhook_secret'] = $safepaycheckoutConfig['sandbox'] ? $this->getStoreConfigValue("sandbox_webhook_secret") : $this->getStoreConfigValue("production_webhook_secret");
        $safepaycheckoutConfig['token_api_url'] = $tokenApiBaseUrl . EnvVars::INIT_TRANSACTION_ENDPOINT;
        $safepaycheckoutConfig['order_success_message'] = $this->getStoreConfigValue("order_success_message");
        $config = [
            'payment' => [
                'safepay' => $safepaycheckoutConfig
            ]
        ];
        return $config;
    }

    public function getStoreConfigValue($fieldId)
    {
        return $this->_safepayHelper->getStoreConfigValue($fieldId);
    }
}
