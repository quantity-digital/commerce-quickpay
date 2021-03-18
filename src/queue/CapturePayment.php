<?php

namespace QD\commerce\quickpay\queue;

use Craft;
use craft\queue\BaseJob;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\records\Transaction as TransactionRecord;

class CapturePayment extends BaseJob
{
    /**
     * @var \craft\Commerce\models\Transaction Transaction
     */
    public $transaction;

    public function canRetry($attempt, $error)
    {
        $attempts = 5;
        return $attempt < $attempts;
    }

    public function getTtr()
    {
        return 300;
    }

    public function execute($queue)
    {
        $order = $this->transaction->order;
        $gateway = $order->getGateway();

        if ($order->isPaid && $gateway->enableAutoStatus) {
            $this->updateOrderStatus($order, $gateway);
            $this->setProgress($queue, 1);
            return;
        }

        if (!$order->isPaid) {
            $child = CommercePlugin::getInstance()->getPayments()->captureTransaction($this->transaction);
            $order = $child->order;
            $this->setProgress($queue, .5);

            if ($child->status === TransactionRecord::STATUS_SUCCESS) {
                $order->updateOrderPaidInformation();
                if ($gateway->enableAutoStatus) {
                    $this->updateOrderStatus($order, $gateway);
                }
            } else {
                $this->reAddToQueue();
            }
        }

        $this->setProgress($queue, 1);
    }

    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return 'Capture quickpay payment';
    }

    protected function reAddToQueue()
    {
        Craft::$app->getQueue()->delay(300)->push(new CapturePayment(
            [
                'transaction' => $this->transaction,
            ]
        ));
    }

    protected function updateOrderStatus($order, $gateway)
    {
        $orderStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($gateway->afterCaptureStatus);
        $order->orderStatusId = $orderStatus->id;
        try {
            Craft::$app->getElements()->saveElement($order);
        } catch (\Throwable $th) {
            throw $th;
        }
        return;
    }
}
