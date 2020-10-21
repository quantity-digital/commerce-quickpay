<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionFailedCaptureEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
	public $order;
}
