<?php

namespace QD\commerce\quickpay\queue;

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
		return 3600;
	}

	public function execute($queue)
	{
		$child = CommercePlugin::getInstance()->getPayments()->captureTransaction($this->transaction);
		$this->setProgress($queue, .5);

		if ($child->status == TransactionRecord::STATUS_SUCCESS) {
			$child->order->updateOrderPaidInformation();
		}
		$this->setProgress($queue, 1);
	}

	// Protected Methods
	// =========================================================================

	protected function defaultDescription(): string
	{
		return 'Capture quickpay payment';
	}
}
