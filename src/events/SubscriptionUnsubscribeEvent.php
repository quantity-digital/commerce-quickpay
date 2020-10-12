<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionUnsubscribeEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
}
