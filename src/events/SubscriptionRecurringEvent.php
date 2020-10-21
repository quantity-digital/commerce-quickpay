<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionRecurringEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
	public $amount;
	public $order;
}
