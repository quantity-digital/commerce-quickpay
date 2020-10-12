<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
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
		$results = $this->_createPlansQuery()->all();

		return $results;
	}

	public function afterSaveSiteHandler(SiteEvent $event)
	{
		$queue = Craft::$app->getQueue();
		$siteId = $event->oldPrimarySiteId;
		$elementTypes = [
			Plan::class,
		];

		foreach ($elementTypes as $elementType) {
			$queue->push(new ResaveElements([
				'elementType' => $elementType,
				'criteria' => [
					'siteId' => $siteId,
					'status' => null,
					'enabledForSite' => false
				]
			]));
		}
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
