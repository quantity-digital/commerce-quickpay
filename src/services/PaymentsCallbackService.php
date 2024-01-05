<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\base\Component;
use craft\commerce\models\Transaction as TransactionModel;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction;
use QD\commerce\quickpay\plugin\Data;
use QD\commerce\quickpay\Plugin as Quickpay;
use yii\web\Response;

class PaymentsCallbackService extends Component
{
  /**
   * Function to be run if quickpay calls the continue_url
   *
   * @param mixed $data
   * @param TransactionModel $parent
   * @return Response
   */
  public function continue(mixed $data, TransactionModel $parent): Response
  {
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    //Order is already paid
    if ($order->getIsPaid()) {
      return Craft::$app->getResponse()->redirect($order->returnUrl);
    }

    // If it's successful already, we're good.
    //? This will return true if either the current transaction or its parent transaction is successful
    $isTransactionSuccessful = Commerce::getInstance()->getTransactions()->isTransactionSuccessful($parent);
    if ($isTransactionSuccessful) {
      return Craft::$app->getResponse()->redirect($order->returnUrl);
    }

    // Create Processing transaction
    Quickpay::getInstance()->getTransactionService()->createAuthorize($data, Transaction::STATUS_PROCESSING, 'Transaction pending final confirmation from Quickpay.');

    // Redirect to return url
    return Craft::$app->getResponse()->redirect($order->returnUrl);
  }

  /**
   * Function to be run if quickpay calls the cancel_url
   *
   * @param TransactionModel $parent
   * @return Response
   */
  public function cancel($parent): Response
  {
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    //Enable recalculation for order
    Quickpay::getInstance()->getOrders()->enableCalculation($order);

    // Cancel the transaction
    Quickpay::getInstance()->getPayments()->cancelLinkFromGateway($parent);

    // Redirect to cancel url
    return Craft::$app->getResponse()->redirect($order->cancelUrl);
  }

  /**
   * Function to be run when quickpay calls the callback_url
   *
   * @param mixed $data
   * @param TransactionModel $parent
   * @return void
   */
  public function notify(mixed $data, TransactionModel $parent): void
  {
    // Check if transaction is successful
    //? This will return true if either the current transaction or its parent transaction is successful
    $isTransactionSuccessful = Commerce::getInstance()->getTransactions()->isTransactionSuccessful($parent);

    // Get the order belonging to the transaction
    //? This is used to define what order any child transactions should be attached to
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);
    $gateway = $order->getGateway();

    //* Authorize
    if (!$isTransactionSuccessful) {
      switch ($data->state) {
        case Data::STATE_NEW:
          //? If the parent transaction is not successful (Initial / Redirect), and the update State is "New" and Accepted, Quickpay has authorized the transaction
          Quickpay::getInstance()->getTransactionService()->createAuthorize($data, Transaction::STATUS_SUCCESS, 'Transaction authorized.');
          break;

        case Data::STATE_REJECTED:
          //? If the parent transaction is not successful (Initial / Redirect), and the update State is "Rejected" quickpay has rejected the transaction
          Quickpay::getInstance()->getTransactionService()->createAuthorize($data, Transaction::STATUS_FAILED, 'Transaction rejected.');
          break;
      }
    }

    // Get the last transaction state
    $isRefunded = Quickpay::getInstance()->getTransactionService()->isRefunded($parent);

    // If the transaction has been refunded, return
    //? Callback have not yet been implemented for refunds, 
    if ($isRefunded) {
      return;
    }

    //* Capture
    //? If the Quickpay state is "Processed" and the order is not yet set as paid in Craft Commerce, update the order
    if (!$order->getIsPaid() && $data->state === Data::STATE_PROCESSED) {
      // Create the capture transaction
      Quickpay::getInstance()->getTransactionService()->createCapture($data, Transaction::STATUS_SUCCESS, 'Transaction captured.');

      // Update Craft order paid information
      $order->updateOrderPaidInformation();

      // Auto update the order status
      Quickpay::getInstance()->getOrders()->updateOrderStatus($order, $gateway);
      return;
    }

    return;
  }
}
