<?php

/**
 * Modify the amount to capture be captured in quickpay
 * 
 * @package Quickpay
 */

namespace QD\commerce\quickpay\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class PaymentCaptureAmount extends Event
{
  /**
   * Craft Commerce order
   *
   * @var Order
   */
  public Order $order;

  /**
   * Amount
   *
   * @var float
   */
  public $amount;
}
