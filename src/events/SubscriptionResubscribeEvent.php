<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class SubscriptionResubscribeEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscription;
}
