<?php

namespace QD\commerce\quickpay\base;

use craft\commerce\base\Plan as BasePlan;
use craft\commerce\stripe\models\Plan;

trait SubscriptionTrait
{
	// Public Methods
    // =========================================================================

	/**
     * @inheritdoc
     */
    public function supportsPlanSwitch(): bool
    {
        return true;
    }

	/**
     * @inheritdoc
     */
    public function supportsReactivation(): bool
    {
        return true;
    }
}
