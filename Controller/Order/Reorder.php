<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Safepay\Checkout\Controller\Order;

use Magento\Framework\App\Action;
use Magento\Sales\Model\OrderFactory;

class Reorder extends Action\Action
{
    protected $_orderFactory;

    /**
     * @param Action\Context $context
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Action\Context $context,
        OrderFactory $orderFactory
    ) {
        $this->_orderFactory = $orderFactory;
        parent::__construct($context);
    }

    /**
     * Action for reorder
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (empty($this->getRequest()->getParam('order_id'))) {
            return $resultRedirect->setPath('checkout/cart');
        }

        if (empty($this->getRequest()->getParam('hide_error')) || !($this->getRequest()->getParam('hide_error'))) {
            /* As the cart is repopulated in case of error from gateway, so showing this error message */
            $this->messageManager->addError(
                __('Something went wrong while processing your payment, please try again.')
            );
        }

        $order = $this->_orderFactory->create()->load($this->getRequest()->getParam('order_id'));
        
        /* @var $cart \Magento\Checkout\Model\Cart */
        $cart = $this->_objectManager->get(\Magento\Checkout\Model\Cart::class);
        $items = $order->getItemsCollection();
        foreach ($items as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                if ($this->_objectManager->get(\Magento\Checkout\Model\Session::class)->getUseNotice(true)) {
                    $this->messageManager->addNoticeMessage($e->getMessage());
                } else {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
                return $resultRedirect->setPath('checkout/cart');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('We can\'t add this item to your shopping cart right now.')
                );
                return $resultRedirect->setPath('checkout/cart');
            }
        }

        $cart->save();
        return $resultRedirect->setPath('checkout/cart');
    }
}
