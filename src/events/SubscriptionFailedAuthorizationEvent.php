<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionFailedAuthorizationEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
	public $order;
}
