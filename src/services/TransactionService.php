<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\base\Component;
use craft\commerce\models\Transaction as TransactionModel;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use QD\commerce\quickpay\plugin\Data;
use craft\helpers\App;
use Exception;

class TransactionService extends Component
{
  //* Create


  /**
   * Create a child transaction of the type Authorize
   *
   * @param mixed $data
   * @param TransactionModel $parent
   * @param string $status
   * @param string $message
   * @return TransactionModel
   */
  public function createAuthorize(mixed $data, string $status, string $message = ''): TransactionModel
  {
    // If data is an array, convert it to an object
    if (is_array($data)) {
      $data = (object) $data;
    }

    if (!isset($data->id) || !$data->id) {
      throw new Exception("No Quickpay reference supplied", 1);
    }

    // Get the parent transaction
    $parent = Commerce::getInstance()->getTransactions()->getTransactionByReference($data->id);

    // Get the order belonging to the transaction
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    // Create a new child transaction
    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);

    // Set the transaction type to authorize
    $transaction->type = TransactionRecord::TYPE_AUTHORIZE;

    // Set the transaction status
    $transaction->status = $status;

    // Set the transaction reference to the Quickpay id
    $transaction->reference = $data->id;

    // Set the transaction response to the response from quickpay
    $transaction->response = $data;

    // Define a message for the transaction
    $transaction->message = $message;

    // Save the child transaction
    if (!Commerce::getInstance()->getTransactions()->saveTransaction($transaction)) {
      //TODO: Setup logging
      // throw new Exception("Could not save transaction", 1);
    }

    return $transaction;
  }

  /**
   * Create a child transaction of the type Capture
   *
   * @param mixed $data
   * @param TransactionModel $parent
   * @param string $status
   * @param string $message
   * @return TransactionModel
   */
  public function createCapture(mixed $data, string $status, string $message = ''): TransactionModel
  {
    // If data is an array, convert it to an object
    if (is_array($data)) {
      $data = (object) $data;
    }

    if (!isset($data->id) || !$data->id) {
      throw new Exception("No Quickpay reference supplied", 1);
    }

    // Get the parent transaction
    $parent = Commerce::getInstance()->getTransactions()->getTransactionByReference($data->id);

    // Get the order belonging to the transaction
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    // Create a new child transaction
    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);

    // Set the transaction type to authorize
    $transaction->type = TransactionRecord::TYPE_CAPTURE;

    // Set the transaction status
    $transaction->status = $status;

    // Set the transaction reference to the Quickpay id
    $transaction->reference = $data->id;

    // Set the transaction response to the response from quickpay
    $transaction->response = $data;

    // Define a message for the transaction
    $transaction->message = $message;

    // Save the child transaction
    if (!Commerce::getInstance()->getTransactions()->saveTransaction($transaction)) {
      // TODO: Setup logging
      // throw new Exception("Could not save transaction", 1);
    }

    return $transaction;
  }

  public function createRefund()
  {
    //TODO: Create refund
  }
}
