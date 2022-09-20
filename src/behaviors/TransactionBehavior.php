<?php

namespace QD\commerce\quickpay\behaviors;

use craft\commerce\records\Transaction as TransactionRecord;
use yii\base\Behavior;

class TransactionBehavior extends Behavior
{

    // Public Methods
    // =========================================================================

    public function isSuccessful(): bool
    {
        return  $this->status === TransactionRecord::STATUS_SUCCESS
            && $this->type === TransactionRecord::TYPE_AUTHORIZE;
    }
}
