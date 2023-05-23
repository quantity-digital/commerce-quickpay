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
use craft\mail\Message;
use Exception;
use \yii\queue\Queue;
use \yii\queue\QueueInterface;
use yii\queue\RetryableJobInterface;

class CapturePayment extends BaseJob implements RetryableJobInterface
{
	public Transaction $transaction;

	/**
	 * Encapsulates the Ttr
	 *
	 * @return integer
	 */
	public function getTtr(): int
	{
		return 60;
	}

	public function canRetry($attempt, $error)
	{
		if ($attempt == 5) {
			$message = new Message();
			$order = $this->transaction->order;
			$gateway = $order->getGateway();
			$notificationEmails = \str_replace(' ', '', $gateway->notificationEmails);

			// If no notification mail is set, or notifications are disabled, we throw an exception
			if (!$notificationEmails || !$gateway->sendNotfifications) {
				throw new Exception($error, 503);
			}

			$message->setTo($notificationEmails);
			$message->setSubject('Capture payment failed');
			$message->setTextBody("Capture payment for order {$order->reference} failed.");

			// Swallow exceptions from the mailer:
			try {
				Craft::$app->getMailer()->send($message);
			} catch (\Throwable $e) {
				Craft::warning("Something went wrong: {$e->getMessage()}", __METHOD__);
			}

			// Throw an exception to retry the job later
			throw new Exception($error, 503);
		}

		return ($attempt < 5);
	}

	/**
	 * Executes the transaction
	 *
	 * @param Queue|QueueInterface $queue
	 * @return void
	 */
	public function execute($queue): void
	{
		$order = $this->transaction->order;
		$gateway = $order->getGateway();

		//Order is already paid, so we can just update the status
		if ($order->isPaid && App::parseBooleanEnv($gateway->enableAutoStatus)) {
			$this->updateOrderStatus($order, $gateway);
			$this->setProgress($queue, 1);
			return;
		}

		//Order is not paid, so we need to capture the transaction
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
				throw new Exception('Could not capture payment');
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
