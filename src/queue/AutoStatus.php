<?php

namespace QD\commerce\quickpay\queue;

use craft\queue\BaseJob;

class AutoStatus extends BaseJob
{
	/**
	 * @var int Order ID
	 */
	public $orderId;

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

	}

	// Protected Methods
	// =========================================================================

	protected function defaultDescription(): string
	{
		return 'Set order status';
	}
}
