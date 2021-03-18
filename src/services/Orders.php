<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\records\Transaction as TransactionRecord;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\queue\CapturePayment;

class Orders extends Component
{
    public function addAutoCaptureJob($event)
    {
        $order = $event->order;
        $orderstatus = $order->getOrderStatus();
        $gateway = $order->getGateway();

        if ($gateway instanceof Gateway && $gateway->autoCapture && $gateway->autoCaptureStatus === $orderstatus->handle) {
            $transaction = $this->getSuccessfulTransactionForOrder($order);

            if ($transaction && $transaction->canCapture()) {
                Craft::$app->getQueue()->delay(10)->push(new CapturePayment(
                    [
                        'transaction' => $transaction,
                    ]
                ));
            }
        }
    }

    public function getOrderById($id)
    {
        return CommercePlugin::getInstance()->getOrders()->getOrderById($id);
    }

    public function getSuccessfulTransactionForOrder(Order $order)
    {
        $transactions = $order->getTransactions();
        usort($transactions, array($this, 'dateCompare'));

        foreach ($transactions as $transaction) {

            if (
                $transaction->status === TransactionRecord::STATUS_SUCCESS
                && $transaction->type === TransactionRecord::TYPE_AUTHORIZE
            ) {
                return $transaction;
            }
        }

        return false;
    }

    private static function dateCompare($element1, $element2)
    {
        $datetime1 = date_timestamp_get($element1['dateCreated']);
        $datetime2 = date_timestamp_get($element2['dateCreated']);
        return $datetime2 - $datetime1;
    }
}
