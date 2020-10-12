<?php
namespace QD\commerce\quickpay\events;

use yii\base\Event;

class CustomizePlanSnapshotFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    public $plan;
    public $fields;
}
