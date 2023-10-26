<?php

/**
 * Modify basket adjustment amount event
 * 
 * @package Quickpay
 */

namespace QD\commerce\quickpay\events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use yii\base\Event;

class BasketAdjustmentAmount extends Event
{
	/**
	 * Craft Commerce order
	 *
	 * @var Order
	 */
	public Order $order;

	/**
	 * Craft Commerce order adjustment
	 *
	 * @var OrderAdjustment
	 */
	public OrderAdjustment $adjustment;

	/**
	 * Amount
	 *
	 * @var float
	 */
	public $amount;
}
