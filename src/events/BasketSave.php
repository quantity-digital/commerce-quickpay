<?php

/**
 * Modify basket lineitem total price event
 * 
 * @package Quickpay
 */

namespace QD\commerce\quickpay\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class BasketSave extends Event
{
  /**
   * Craft Commerce order
   *
   * @var Order
   */
  public Order $order;

  /**
   * Basket
   *
   * @var array
   */
  public $basket;

  /**
   * Shipping
   *
   * @var array
   */
  public $shipping;
}
