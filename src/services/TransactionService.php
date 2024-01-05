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

use craft\commerce\base\Gateway;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

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
