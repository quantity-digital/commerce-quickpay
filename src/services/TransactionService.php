<?php

namespace QD\commerce\quickpay\services;

use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\models\Transaction as TransactionModel;
use QD\commerce\quickpay\models\PaymentResponseModel;
use craft\commerce\Plugin as Commerce;
use craft\commerce\db\Table;
use craft\base\Component;
use craft\db\Query;
use Exception;

class TransactionService extends Component
{
  //* Create
  /**
   * Create a child transaction of the type Authorize
   *
   * @param integer $reference The Quickpay id
   * @param PaymentResponseModel $response
   * @param string $status
   * @param string $message
   * @return TransactionModel
   */
  public function createAuthorize(int $reference, PaymentResponseModel $response, string $status, string $message = ''): TransactionModel
  {
    // If data is an array, convert it to an object
    if (is_array($response)) {
      $response = (object) $response;
    }

    if ($reference) {
      throw new Exception("No Quickpay reference supplied", 1);
    }

    // Get the parent transaction
    $parent = Commerce::getInstance()->getTransactions()->getTransactionByReference($reference);

    // Get the order belonging to the transaction
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    // Create a new child transaction
    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);

    // Set the transaction type to authorize
    $transaction->type = TransactionRecord::TYPE_AUTHORIZE;

    // Set the transaction status
    $transaction->status = $status;

    // Set the transaction reference to the Quickpay id
    $transaction->reference = $reference;

    // Set the transaction response to the response from quickpay
    $transaction->response = $response;

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
   * @param integer $reference The Quickpay id
   * @param PaymentResponseModel $response
   * @param string $status
   * @param string $message
   * @return TransactionModel
   */
  public function createCapture(int $reference, PaymentResponseModel $response, string $status, string $message = ''): TransactionModel
  {
    // If data is an array, convert it to an object
    if (is_array($response)) {
      $response = (object) $response;
    }

    if ($reference) {
      throw new Exception("No Quickpay reference supplied", 1);
    }

    // Get the parent transaction
    $parent = Commerce::getInstance()->getTransactions()->getTransactionByReference($reference);

    // Get the order belonging to the transaction
    $order = Commerce::getInstance()->getOrders()->getOrderById($parent->orderId);

    // Create a new child transaction
    $transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);

    // Set the transaction type to authorize
    $transaction->type = TransactionRecord::TYPE_CAPTURE;

    // Set the transaction status
    $transaction->status = $status;

    // Set the transaction reference to the Quickpay id
    $transaction->reference = $reference;

    // Set the transaction response to the response from quickpay
    $transaction->response = $response;

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


  /**
   * Get the type of the latest child transaction
   *
   * @param TransactionModel $transaction
   * @return object
   */
  public function getLastTransaction(TransactionModel $transaction): object
  {
    $last = $this->_createTransactionQuery()
      ->where([
        'reference' => $transaction->reference,
        'orderId' => $transaction->orderId,
      ])
      ->orderBy(['id' => SORT_DESC])
      ->one();

    return (object)
    [
      'type' => $last ? $last['type'] : '',
      'status' => $last ? $last['status'] : '',
    ];
  }

  /**
   * Returnes whether or not the transaction has been refunded
   *
   * @param TransactionModel $transaction
   * @return boolean
   */
  public function isRefunded(TransactionModel $transaction): bool
  {
    return $this->_createTransactionQuery()
      ->where([
        'reference' => $transaction->reference,
        'orderId' => $transaction->orderId,
        'type' => TransactionRecord::TYPE_REFUND,
      ])
      ->exists();
  }


  /**
   * Returns a Query object prepped for retrieving Transactions.
   *
   * @return Query The query object.
   */
  private function _createTransactionQuery(): Query
  {
    return (new Query())
      ->select([
        'amount',
        'code',
        'currency',
        'dateCreated',
        'dateUpdated',
        'gatewayId',
        'hash',
        'id',
        'message',
        'note',
        'orderId',
        'parentId',
        'paymentAmount',
        'paymentCurrency',
        'paymentRate',
        'reference',
        'response',
        'status',
        'type',
        'userId',
      ])
      ->from([Table::TRANSACTIONS])
      ->orderBy(['id' => SORT_ASC]);
  }
}
