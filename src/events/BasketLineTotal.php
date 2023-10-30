<?php

/**
 * Modify basket lineitem total price event
 * 
 * @package Quickpay
 */

namespace QD\commerce\quickpay\events;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use yii\base\Event;

class BasketLineTotal extends Event
{
  /**
   * Craft Commerce order
   *
   * @var Order
   */
  public Order $order;

  /**
   * Craft Commerce lineitem
   *
   * @var LineItem
   */
  public LineItem $lineItem;

  /**
   * Total
   *
   * @var float
   */
  public $total;
}
