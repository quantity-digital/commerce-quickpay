<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionAuthorizeEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
	public $order;
}
