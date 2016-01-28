<?php
/**
 * Ffm_Orderoptionindicator extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the OSL 3.0 License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * @category       Ffm
 * @package        Ffm_Orderoptionindicator
 * @copyright      Copyright (c) 2015
 * @license        OSL 3.0 http://opensource.org/licenses/OSL-3.0
 */
/**
 * Adminhtml indicator block
 *
 * @category    Ffm
 * @package     Ffm_Orderoptionindicator
 * @author      Sander Mangel <sander@sandermangel.nl>
 */
class Ffm_Orderoptionindicator_Block_Adminhtml_Indicator extends Mage_Adminhtml_Block_Template
{
    protected $_order;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieve order model object
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('sales_order');
    }

    protected function _isAllowedAction($action)
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/' . $action);
    }

    protected function _defaultChecks($order)
    {
        $unallowedOptions = [];
        if ($order->canUnhold()) {
            $unallowedOptions[] = $this->__("Order is on hold");
        }
        if ($order->isPaymentReview()) {
            $unallowedOptions[] = $this->__("Payment is in review");
        }

        return $unallowedOptions;
    }



    public function getCanEdit()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('edit') && $order->canEdit()) return true; // no blocking issues

        if (!$this->_isAllowedAction('edit')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        if ($order->isCanceled()) {
            $unallowedOptions[] = $this->__('Is cancelled');
        }
        $state = $order->getState();
        if ($state === $order::STATE_COMPLETE || $state === $order::STATE_CLOSED) {
            $unallowedOptions[] = $this->__('State is complete or closed');
        }
        if (!$order->getPayment()->getMethodInstance()->canEdit()) {
            $unallowedOptions[] = $this->__("Can't edit payment data");
        }
        if ($order->getActionFlag($order::ACTION_FLAG_EDIT) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        // see if order has non-editable products as items
        $nonEditableTypes = array_keys($order->getResource()->aggregateProductsByTypes(
            $order->getId(),
            array_keys(Mage::getConfig()
                ->getNode('adminhtml/sales/order/create/available_product_types')
                ->asArray()
            ),
            false
        ));
        if ($nonEditableTypes) {
            $unallowedOptions[] = $this->__('Order contains non-editable product types: %s', implode(', ', $nonEditableTypes));
        }

        return $unallowedOptions;
    }

    public function getCanCancel()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('cancel') && $order->canEdit()) return true; // no blocking issues


        if (!$this->_isAllowedAction('cancel')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        $allInvoiced = true;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice()) {
                $allInvoiced = false; break;
            }
        }
        if ($allInvoiced) {
            $unallowedOptions[] = $this->__("All items are invoiced, create Credit Memo");
        }

        $state = $order->getState();
        if ($state === $order::STATE_COMPLETE || $state === $order::STATE_CLOSED) {
            $unallowedOptions[] = $this->__("State is complete or closed");
        }
        if ($order->getActionFlag($order::ACTION_FLAG_CANCEL) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        return $unallowedOptions;
    }

    public function getCanEmail()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('emails') && !$order->isCanceled()) return true; // no blocking issues

        if (!$this->_isAllowedAction('emails')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        return $unallowedOptions;
    }

    public function getCanCredit()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('creditmemo') && ($order->canCreditmemo() || $order->hasForcedCanCreditmemo())) return true; // no blocking issues

        if (!$this->_isAllowedAction('creditmemo')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }


        if (!$order->hasForcedCanCreditmemo()) {
            $unallowedOptions[] = $this->__("Is forced prevented");
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        if ($order->isCanceled()) {
            $unallowedOptions[] = $this->__("Order is canceled");
        }

        if ($order->getState() == $order::STATE_CLOSED) {
            $unallowedOptions[] = $this->__("Order is closed");
        }

        /**
         * We can have problem with float in php (on some server $a=762.73;$b=762.73; $a-$b!=0)
         * for this we have additional diapason for 0
         * TotalPaid - contains amount, that were not rounded.
         */
        if (abs($order->getStore()->roundPrice($order->getTotalPaid()) - $order->getTotalRefunded()) < .0001) {
            return true;
        }

        if ($order->getActionFlag($order::ACTION_FLAG_EDIT) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        return $unallowedOptions;
    }

    public function getCanVoidPayment()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('invoice') && $order->canVoidPayment()) return true; // no blocking issues

        if (!$this->_isAllowedAction('invoice')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        if (!$order->canVoidPayment()) {
            $unallowedOptions[] = $this->__("Can't void the payment");
        }

        return $unallowedOptions;
    }

    public function getCanHold()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('hold') && $order->canHold()) return true; // no blocking issues

        if (!$this->_isAllowedAction('hold')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        $state = $order->getState();
        if ($state === $order::STATE_COMPLETE) {
            $unallowedOptions[] = $this->__('State is complete');
        }
        if ($state === $order::STATE_CLOSED) {
            $unallowedOptions[] = $this->__('State is closed');
        }

        if ($order->getActionFlag($order::ACTION_FLAG_HOLD) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        return $unallowedOptions;
    }

    public function getCanUnhold()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('unhold') && $order->canUnhold()) return true; // no blocking issues

        if (!$this->_isAllowedAction('unhold')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        if ($order->isPaymentReview()) {
            $unallowedOptions[] = $this->__('Payment can be reviewed');
        }
        if ($order->getState() !== $order::STATE_HOLDED) {
            $unallowedOptions[] = $this->__('Is not on hold');
        }

        return $unallowedOptions;
    }

    public function getCanReviewPayment()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('review_payment') && $order->canReviewPayment() && $order->canFetchPaymentReviewUpdate()) return true; // no blocking issues

        if (!$this->_isAllowedAction('review_payment')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        if (!$order->canReviewPayment()) {
            if ($order->getState() === $order::STATE_PAYMENT_REVIEW) {
                $unallowedOptions[] = $this->__('State is not payment review');
            }
            if ($order->getPayment()->canReviewPayment()) {
                $unallowedOptions[] = $this->__('Review not allowed by payment method');
            }
        }

        if (!$order->canFetchPaymentReviewUpdate()) {
            if ($order->getPayment()->canFetchTransactionInfo()) {
                $unallowedOptions[] = $this->__("Can't fetch transaction info");
            }
        }

        return $unallowedOptions;
    }

    public function getCanInvoice()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('invoice') && $order->canInvoice()) return true; // no blocking issues

        if (!$this->_isAllowedAction('invoice')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        if ($order->canUnhold()) {
            $unallowedOptions[] = $this->__("Is on hold");
        }
        if ($order->isPaymentReview()) {
            $unallowedOptions[] = $this->__("Payment is in review");
        }
        if ($order->isCanceled()) {
            $unallowedOptions[] = $this->__("Order is cancelled");
        }

        $state = $order->getState();
        if ($state === $order::STATE_COMPLETE || $state === $order::STATE_CLOSED) {
            $unallowedOptions[] = $this->__("State is closed or complete");
        }

        if ($order->getActionFlag($order::ACTION_FLAG_INVOICE) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        $invoiced = false;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice()>0 && !$item->getLockedDoInvoice()) {
                $invoiced = true; break;
            }
        }

        if (!$invoiced) {
            $unallowedOptions[] = $this->__("Can't invoice items");
        }

        return $unallowedOptions;
    }


    public function getCanShip()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('ship') && $order->canShip()) return true; // no blocking issues

        if (!$this->_isAllowedAction('ship')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        if ($order->getIsVirtual()) {
            $unallowedOptions[] = $this->__('Order is virtual');
        }

        if ($order->isCanceled()) {
            $unallowedOptions[] = $this->__('Order is canceled');
        }

        if ($order->getActionFlag($order::ACTION_FLAG_SHIP) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        $ship = false;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToShip()>0 && !$item->getIsVirtual() && !$item->getLockedDoShip()) {
                $ship = true;break;
            }
        }

        if (!$ship) {
            $unallowedOptions[] = $this->__("Can't ship items");
        }

        return $unallowedOptions;
    }


    public function getCanReorder()
    {
        $order = $this->getOrder();
        $unallowedOptions = [];

        if ($this->_isAllowedAction('reorder') && $this->helper('sales/reorder')->isAllowed($order->getStore()) && $order->canReorderIgnoreSalable()) return true; // no blocking issues

        if (!$this->_isAllowedAction('reorder')) {
            $unallowedOptions[] = $this->__('Insufficient rights');
        }

        if (!$this->helper('sales/reorder')->isAllowed($order->getStore())) {
            $unallowedOptions[] = $this->__('Reorder not allowed on store');
        }

        $unallowedOptions = array_merge($unallowedOptions, $this->_defaultChecks($order));

        if (!$order->getCustomerId()) {
            $unallowedOptions[] = $this->__('No registered customer');
        }

        if ($order->getActionFlag($order::ACTION_FLAG_REORDER) === false) {
            $unallowedOptions[] = $this->__('Misc reason, flag raised');
        }

        return $unallowedOptions;
    }
}