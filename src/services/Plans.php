<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\db\Query;
use QD\commerce\quickpay\base\Table;
use QD\commerce\quickpay\elements\Plan;
use yii\base\Component;

class Plans extends Component
{
	public function getPlanById(int $id, $siteId = null)
	{
		return Craft::$app->getElements()->getElementById($id, Plan::class, $siteId);
	}

	public function getAllPlans(): array
	{
		$results = $this->_createPlansQuery()
			->all();

		return $results;
	}

	private function _createPlansQuery(): Query
	{
		return (new Query())
			->select([
				'id',
				'quickpay_plans.title',
				'uid',
				'slug'
			])
			->from([Table::PLANS]);
	}
}
