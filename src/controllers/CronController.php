<?php

namespace QD\commerce\quickpay\controllers;

use craft\console\Controller;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\Plugin;

class CronController extends Controller
{
	protected $allowAnonymous = array('capture');

	public function actionCapture()
	{
		//Should find all subscriptins thats not cancelled, and where next payment is older than current time
		$subscriptions = Subscription::find()->isCanceled(false)->isSuspended(false)->nextPaymentDate(time())->all();

		foreach ($subscriptions as $subscription) {
			$capture = Plugin::getInstance()->getSubscriptions()->captureSubscription($subscription);
		}
	}
}
