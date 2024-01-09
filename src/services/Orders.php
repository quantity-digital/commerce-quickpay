<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\helpers\App;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as CommercePlugin;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\queue\CapturePayment;
use craft\commerce\base\GatewayInterface as BaseGatewayInterface;
use craft\commerce\Plugin as Commerce;
use Exception;

class Orders extends Component
{
	/**
	 * Adds an autocapture job
	 *
	 * @param mixed $event
	 * @return void
	 */
	public function addAutoCaptureJob(mixed $event): void
	{
		$order = $event->order;
		$orderstatus = $order->getOrderStatus();
		$gateway = $order->getGateway();

		if ($gateway instanceof Gateway && App::parseBooleanEnv($gateway->autoCapture) && App::parseEnv($gateway->autoCaptureStatus) === $orderstatus->handle) {
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

	/**
	 * Queries database for order by id
	 *
	 * @param string $id
	 * @return Order
	 */
	public function getOrderById(string $id): Order
	{
		return CommercePlugin::getInstance()->getOrders()->getOrderById($id);
	}

	/**
	 * Gets the first successful transaction on an order
	 *
	 * @param Order $order
	 * @return Transaction|boolean
	 */
	public function getSuccessfulTransactionForOrder(Order $order): Transaction|bool
	{
		$transactions = $order->getTransactions();
		usort($transactions, array($this, 'dateCompare'));

		foreach ($transactions as $transaction) {
			if ($transaction->isSuccessful()) {
				return $transaction;
			}
		}

		return false;
	}

	/**
	 * Update the order status
	 *
	 * @param Order $order
	 * @param BaseGatewayInterface $gateway
	 * @return void
	 */
	public function setAfterCaptureStatus(Order $order, BaseGatewayInterface $gateway): void
	{
		// If auto status is disabled, return
		if (!App::parseBooleanEnv($gateway->enableAutoStatus)) {
			return;
		}

		// Get the "afterCaptureStatus" status.
		$orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusByHandle(App::parseEnv($gateway->afterCaptureStatus));

		if (!$orderStatus) {
			throw new Exception("After capture status not found", 1);
		}

		// Update the order status
		$order->orderStatusId = $orderStatus->id;

		// Save the updated status.
		Craft::$app->getElements()->saveElement($order);
	}


	/**
	 * Set order to recalculation mode, and updates cart search indexes
	 *
	 * @param Order $order
	 * @return void
	 */
	public function enableCalculation(Order $order): void
	{
		$order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
		$updateCartSearchIndexes = CommercePlugin::getInstance()->getSettings()->updateCartSearchIndexes;
		Craft::$app->getElements()->saveElement($order, false, false, $updateCartSearchIndexes);
	}

	/**
	 * Compare: compares the creation date of two Transactions
	 *
	 * @param Transaction $element1
	 * @param Transaction $element2
	 * @return integer
	 */
	private static function dateCompare(Transaction $element1, Transaction $element2): int
	{
		$datetime1 = date_timestamp_get($element1['dateCreated']);
		$datetime2 = date_timestamp_get($element2['dateCreated']);
		return $datetime2 - $datetime1;
	}
}
