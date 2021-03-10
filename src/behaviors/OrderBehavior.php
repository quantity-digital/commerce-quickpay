<?php

namespace QD\commerce\quickpay\behaviors;

use Craft;
use craft\commerce\elements\Order;
use QD\commerce\quickpay\base\Table;
use yii\base\Behavior;

class OrderBehavior extends Behavior
{
	/**
	 * @var string|null
	 */
	public $subscriptionId;


	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			Order::EVENT_BEFORE_SAVE => [$this, 'setOrderInfo'],
			Order::EVENT_AFTER_SAVE => [$this, 'saveOrderInfo'],
		];
	}

	public function setOrderInfo()
	{
		// If droppointId is set, store it on the order
		$request = Craft::$app->getRequest();

		if (!$request->getIsConsoleRequest() && \method_exists($request, 'getParam')) {
			$subscriptionId = $request->getParam('subscriptionId');

			if ($subscriptionId !== NULL) {
				$this->subscriptionId = $subscriptionId;
			}
		}
	}

	/**
	 * Saves extra attributes that the Behavior injects.
	 *
	 * @return void
	 */
	public function saveOrderInfo()
	{
		$data = [];

		if ($this->subscriptionId !== null) {
			$data['subscriptionId'] = $this->subscriptionId;
		}

		if ($data) {
			Craft::$app->getDb()->createCommand()
				->upsert(Table::ORDERINFO, [
					'id' => $this->owner->id,
				], $data, [], false)
				->execute();
		}
	}
}
