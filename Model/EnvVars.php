<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Safepay\Checkout\Model;

class EnvVars
{
    const SANDBOX = "sandbox";
    const PRODUCTION = "production";
    const PRODUCTION_CHECKOUT_URL = "https://www.getsafepay.com/components";
    const SANDBOX_CHECKOUT_URL = "https://sandbox.api.getsafepay.com/components";
    const SANDBOX_API_URL = 'https://sandbox.api.getsafepay.com/';
    const PRODUCTION_API_URL = 'https://api.getsafepay.com/';
    const INIT_TRANSACTION_ENDPOINT = "order/v1/init";
}
