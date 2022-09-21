<?php

namespace QD\commerce\quickpay\behaviors;

use craft\commerce\records\Transaction as TransactionRecord;
use yii\base\Behavior;

class TransactionBehavior extends Behavior
{

    /** @var Transaction */
    public $owner;

    // Public Methods
    // =========================================================================

    public function isSuccessful(): bool
    {
        return  $this->owner->status === TransactionRecord::STATUS_SUCCESS
            && $this->owner->type === TransactionRecord::TYPE_AUTHORIZE;
    }
}
