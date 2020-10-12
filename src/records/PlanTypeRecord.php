<?php
namespace QD\commerce\quickpay\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use QD\commerce\quickpay\base\Table;
use yii\db\ActiveQueryInterface;

class PlanTypeRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return Table::PLANTYPES;
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
