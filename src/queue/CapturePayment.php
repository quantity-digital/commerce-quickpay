<?php

namespace QD\commerce\quickpay\queue;

use Craft;
use craft\commerce\elements\Order;
use craft\queue\BaseJob;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\App;
use craft\mail\Message;
use Exception;
use QD\commerce\quickpay\Plugin as Quickpay;
use \yii\queue\Queue;
use \yii\queue\QueueInterface;
use yii\queue\RetryableJobInterface;

class CapturePayment extends BaseJob implements RetryableJobInterface
{
	/**
	 * Transction to capture
	 * @deprecated Deprecated since version 4.0.3. Use $orderId instead.
	 */
	public $transaction;

	/**
	 * Id of the order to capture
	 */
	public $orderId;

	/**
	 * Encapsulates the Ttr
	 *
	 * @return integer
	 */
	public function getTtr(): int
	{
		return 5;
	}

	public function canRetry($attempt, $error)
	{
		if ($attempt == 5) {
			$message = new Message();

			if ($this->transaction) {
				$transaction = $this->transaction;
				$order = Order::find()->id($transaction->orderId)->one();
			}

			if ($this->orderId) {
				$order = Order::find()->id($this->orderId)->one();
			}

			$gateway = $order->getGateway();
			$notificationEmails = \str_replace(' ', '', App::parseEnv($gateway->notificationEmails));

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
		//? I dont know why this is deprecated, since its still the only option in use.
		if ($this->transaction) {
			$transaction = $this->transaction;
			$order = Order::find()->id($transaction->orderId)->one();
		}

		if ($this->orderId) {
			$order = Order::find()->id($this->orderId)->one();
			$transaction = Quickpay::getInstance()->getOrders()->getSuccessfulTransactionForOrder($order);
		}

		//No successful transaction found
		if (!$transaction) {
			throw new Exception('Could not find successfull transaction');
		}

		//Get gateway
		$gateway = $order->getGateway();

		//Order is already paid, so we can just update the status
		if ($order->isPaid) {
			Quickpay::getInstance()->getOrders()->setAfterCaptureStatus($order, $gateway);
			$this->setProgress($queue, 1);
			return;
		}

		//Order is not paid, so we need to capture the transaction
		if (!$order->isPaid) {

			// Run the default CraftCMS capture function
			//? This will create either a Successful or Processing transaction, depending on the callback from quickpay
			$child = Commerce::getInstance()->getPayments()->captureTransaction($transaction);

			// Get the order for the child transaction
			$order = $child->order;

			$this->setProgress($queue, .5);

			switch ($child->status) {
				case TransactionRecord::STATUS_SUCCESS:
					// The capture was imidietly successfull, no callback needed, therefore we just update the craft order
					$order->updateOrderPaidInformation();
					Quickpay::getInstance()->getOrders()->setAfterCaptureStatus($order, $gateway);
					break;
				case TransactionRecord::STATUS_PROCESSING:
					// If the capture is still processing, we need to wait for the callback from quickpay
					break;
				case TransactionRecord::STATUS_PENDING:
					// If the capture is pending, we need to wait for the callback from quickpay
					break;
				default:
					throw new Exception('Could not capture payment');
					break;
			}
		}

		$this->setProgress($queue, 1);
	}

	/**
	 * Queuejob description
	 *
	 * @return string
	 */
	protected function defaultDescription(): string
	{
		return 'Capture quickpay payment';
	}
}
