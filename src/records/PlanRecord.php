<?php

namespace QD\commerce\quickpay\records;

use craft\db\ActiveRecord;
use craft\records\Element;

use craft\commerce\records\TaxCategory;
use craft\commerce\records\ShippingCategory;
use QD\commerce\quickpay\base\Table;
use yii\db\ActiveQueryInterface;

class PlanRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Table::PLANS;
    }

    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(PlanTypeRecord::class, ['id' => 'typeId']);
    }

    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }

    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
