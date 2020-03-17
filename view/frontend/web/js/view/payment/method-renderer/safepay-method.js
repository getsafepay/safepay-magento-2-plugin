define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/url'
    ],
    function ($, ko, Component, redirectOnSuccessAction, additionalValidators, alert, quote, fullScreenLoader, url) {
        'use strict';

        var tokenApiResponse = ko.observableArray();

        return Component.extend({
            redirectAfterPlaceOrder: true,
            defaults: {
                template: 'Safepay_Checkout/payment/safepay'
            },

            /**
             * @returns {exports.initialize}
             */
            initialize: function () {
                this._super();
                this.tokenRequest();

                return this;
            },

            tokenRequest: function() {
                let tokenApiUrl = window.checkoutConfig.payment.safepay.token_api_url;
                $.ajax( tokenApiUrl,
                {
                    type: 'POST',
                    dataType: 'json',
                    showLoader: true,
                    data: JSON.stringify({
                        environment : window.checkoutConfig.payment.safepay.environment,
                        client : window.checkoutConfig.payment.safepay.api_key,
                        amount : parseInt(quote.totals().base_grand_total),
                        currency : quote.totals().quote_currency_code
                    }),
                    success: function (apiResponse, status, xhr) {
                        if(apiResponse.status.message === "success"){
                            if (typeof apiResponse.data.token !== 'undefined' && apiResponse.data.token != null) {
                                tokenApiResponse(apiResponse.data);
                                return true;
                            } else {
                                alert({
                                    title: $.mage.__('Error'),
                                    content: $.mage.__('Something went wrong. Please try again Test.'),
                                    actions: {
                                        always: function(){}
                                    }
                                });
                                return false;
                            }
                        }else{
                            alert({
                                title: $.mage.__('Error'),
                                content: $.mage.__(data["message"]),
                                actions: {
                                    always: function(){}
                                }
                            });
                            return false;
                        }
                    },
                    error: function (jqXhr, textStatus, errorMessage) {
                        alert({
                            title: $.mage.__('Error'),
                            content: $.mage.__('Something went wrong. Please try again Test.'),
                            actions: {
                                always: function(){}
                            }
                        });
                        return false;
                    }
                });
            },

            afterPlaceOrder: function () {
                redirectOnSuccessAction.redirectUrl = url.build('test/test/test');
                this.redirectAfterPlaceOrder = true;
            },
        });
    }
);
