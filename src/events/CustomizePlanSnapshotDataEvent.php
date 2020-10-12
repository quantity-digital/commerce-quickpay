<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class CustomizePlanSnapshotDataEvent extends Event
{
    // Properties
    // =========================================================================

    public $plan;
    public $fieldData;
}
