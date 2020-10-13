<?php
namespace QD\commerce\quickpay\fields;

use Craft;
use craft\fields\BaseRelationField;
use QD\commerce\quickpay\elements\Plan;

class Plans extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('commerce-quickpay', 'Plans');
    }

    protected static function elementType(): string
    {
        return Plan::class;
    }

    public static function defaultSelectionLabel(): string
    {
        return Craft::t('commerce-quickpay', 'Add a plan');
    }
}
