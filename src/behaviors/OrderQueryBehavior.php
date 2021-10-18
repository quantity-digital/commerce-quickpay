<?php

namespace QD\commerce\quickpay\behaviors;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\base\Behavior;

class OrderQueryBehavior extends Behavior
{
	/**
	 * @var mixed Value
	 */
	public $subscriptionId;

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			ElementQuery::EVENT_BEFORE_PREPARE => [$this, 'beforePrepare'],
		];
	}

	/**
	 * Applies the `subscriptionId param to the query. Accepts anything that can eventually be passed to `Db::parseParam(…)`.
	 *
	 * @param mixed $value
	 */
	public function subscriptionId($value)
	{
		$this->subscriptionId = $value;
		return $this->owner;
	}

	/**
	 * Prepares the user query.
	 */
	public function afterPrepare()
	{
		if ($this->owner->select === ['COUNT(*)']) {
			return;
		}

		// Join our `orderextras` table:
		$this->owner->query->leftJoin('quickpay_orderinfo quickpay', '`quickpay`.`id` = `commerce_orders`.`id`');

		// Select custom columns:
		$this->owner->query->addSelect([
			'quickpay.subscriptionId',
		]);

		if ($this->subscriptionId) {
			$this->owner->query->andWhere(Db::parseParam('quickpay.subscriptionId', $this->subscriptionId));
		}
	}
}
