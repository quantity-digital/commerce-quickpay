<?php

/**
 * Modify the shipping cost total
 * 
 * @package Quickpay
 */

namespace QD\commerce\quickpay\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class ShippingTotal extends Event
{
  /**
   * Craft Commerce order
   *
   * @var Order
   */
  public Order $order;

  /**
   * Total
   *
   * @var float
   */
  public $total;
}
