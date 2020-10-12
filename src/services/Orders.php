<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\records\Transaction as TransactionRecord;

class Orders extends Component
{

	// public function addAutoStatusQueue($event)
	// {
	// 	$transaction = $event->transaction;
	// }

	// public function addAutoCaptureQueue($event)
	// {
	// 	$order = $event->order;
	// 	$orderstatus = $order->getOrderStatus();
	// 	$gateway = $order->getGateway();

	// 	if ($gateway instanceof Gateway && $gateway->autoCapture && $gateway->autoCaptureStatus === $orderstatus->handle) {
	// 		$transaction = $this->getPayments()->getSuccessfulTransactionForOrder($order);

	// 		if ($transaction && $transaction->canCapture()) {
	// 			Craft::$app->getQueue()->delay(10)->push(new CapturePayment(
	// 				[
	// 					'transaction' => $transaction,
	// 				]
	// 			));
	// 		}
	// 	}
	// }

	public function getOrderById($id)
	{
		return CommercePlugin::getInstance()->getOrders()->getOrderById($id);
	}

	public function getSuccessfulTransactionForOrder(Order $order)
	{
		foreach ($order->getTransactions() as $transaction) {

			if (
				$transaction->status === TransactionRecord::STATUS_SUCCESS
				&& $transaction->type === TransactionRecord::TYPE_AUTHORIZE
			) {

				return $transaction;
			}
		}
	}
}
