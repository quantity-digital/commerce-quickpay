<?php

namespace QD\commerce\quickpay\queue;

use Craft;
use craft\commerce\base\GatewayInterface as BaseGatewayInterface;
use craft\queue\BaseJob;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\records\Transaction as TransactionRecord;
use \craft\Commerce\models\Transaction;
use \craft\Commerce\elements\Order;
use craft\helpers\App;
use \yii\queue\Queue;
use \yii\queue\QueueInterface;

class CapturePayment extends BaseJob
{
    public Transaction $transaction;

    /**
     * Encapsulates the Ttr
     *
     * @return integer
     */
    public function getTtr(): int
    {
        return 300;
    }

    /**
     * TODO: why can I not type this $queue
     * Executes the transaction
     *
     * @param Queue|QueueInterface $queue
     * @return void
     */
    public function execute($queue): void
    {
        $order = $this->transaction->order;
        $gateway = $order->getGateway();

        if ($order->isPaid && App::parseBooleanEnv($gateway->enableAutoStatus)) {
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
                if (App::parseBooleanEnv($gateway->enableAutoStatus)) {
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

    /**
     * Encapsulates the default description
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return 'Capture quickpay payment';
    }

    /**
     * Adds the transaction to the queue, after a 300 ms delay
     *
     * @return void
     */
    protected function reAddToQueue(): void
    {
        Craft::$app->getQueue()->delay(300)->push(new CapturePayment(
            [
                'transaction' => $this->transaction,
            ]
        ));
    }

    /**
     * Updates order status
     *
     * @param Order $order to update
     * @param Gateway $gateway
     * @return void
     * @throws Throwable
     */
    protected function updateOrderStatus(Order $order, BaseGatewayInterface $gateway): void
    {
        $orderStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle(App::parseEnv($gateway->afterCaptureStatus));
        $order->orderStatusId = $orderStatus->id;

        Craft::$app->getElements()->saveElement($order);
    }
}
