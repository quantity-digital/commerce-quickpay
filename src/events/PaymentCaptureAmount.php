<?php

/**
 * Event to modify transaction amount / order a capture is made
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
   * @var float Outstanding balance
   */
  public $amount;
}
