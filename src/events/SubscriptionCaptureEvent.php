<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionCaptureEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
	public $amount;
}
