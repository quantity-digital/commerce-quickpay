<?php

namespace QD\commerce\quickpay\records;

use craft\db\ActiveRecord;
use QD\commerce\quickpay\base\Table;

class PlanPurchasableRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Table::PURCHASABLES;
    }
}
