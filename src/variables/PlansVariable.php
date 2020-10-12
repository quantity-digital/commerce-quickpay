<?php

namespace QD\commerce\quickpay\variables;

use Craft;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\Plugin;
use yii\base\Behavior;

class PlansVariable extends Behavior
{
    // Public Methods
    // =========================================================================


	 /**
     * @var Plugin
     */
	public $commercePlans;

    public function init()
    {
        parent::init();

        // Point `craft.commercePlans` to the plugin instance
		$this->commercePlans = Plugin::$plugin;

	}

    public function plans($criteria = null)
    {
        $query = Plan::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
	}

}
