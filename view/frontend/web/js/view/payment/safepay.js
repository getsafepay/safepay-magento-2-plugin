define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'safepay',
                component: 'Safepay_Checkout/js/view/payment/method-renderer/safepay-method'
            }
        );
        return Component.extend({});
    }
);