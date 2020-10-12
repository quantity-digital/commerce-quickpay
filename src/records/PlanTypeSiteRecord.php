<?php
namespace QD\commerce\quickpay\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use QD\commerce\quickpay\base\Table;
use yii\db\ActiveQueryInterface;

class PlanTypeSiteRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return Table::PLANTYPES_SITES;
    }

    public function getPlanType(): ActiveQueryInterface
    {
        return $this->hasOne(PlanTypeRecord::class, ['id', 'planTypeId']);
    }

    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
