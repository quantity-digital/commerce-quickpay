<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\elements\Order;
use craft\commerce\models\Transaction as ModelsTransaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction;
use craft\helpers\App;
use craft\helpers\Json;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\Plugin as Quickpay;
use QD\commerce\quickpay\plugin\Data;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class PaymentsCallbackController extends BaseController
{
	/**
	 * @inheritdoc
	 */
	public array|int|bool $allowAnonymous = [
		'continue' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
		'notify' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
		'cancel' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE
	];

	// Disable CSRF validation for the controller
	public $enableCsrfValidation = false;

	/**
	 * Init the Controller
	 *
	 * @return void
	 */
	public function init(): void
	{
		parent::init();
	}

	/**
	 * Before action
	 * ? Not sure what this does, but I dont dare to remove it
	 * 
	 * @param \yii\base\Action $action
	 * @return bool
	 * @throws \craft\web\ServiceUnavailableHttpException
	 * @throws \yii\web\BadRequestHttpException
	 * @throws \yii\web\ForbiddenHttpException
	 */
	public function beforeAction($action): bool
	{
		if (!parent::beforeAction($action)) {
			return false;
		}

		return true;
	}

	/**
	 * Function to be run if quickpay calls the continue_url
	 * ? This occours when Quickpay can't authorize the transaction right away, we are therefore awaiting the callback from quickpay
	 * 
	 * @param string|null $transactionReference
	 * @return Response
	 */
	public function actionContinue(string $transactionReference = null): Response
	{
		// Check if transaction reference is set
		if (!$transactionReference) {
			throw new ForbiddenHttpException('Missing transaction reference.');
		}

		//Get transaction and order
		$parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionReference);
		$order = Commerce::getInstance()->getOrders()->getOrderById($parentTransaction->orderId);

		//Order is already paid
		if ($order->getIsPaid()) {
			return Craft::$app->getResponse()->redirect($order->returnUrl);
		}

		// If it's successful already, we're good.
		//? This will return true if either the current transaction or its parent transaction is successful
		$isTransactionSuccessful = Commerce::getInstance()->getTransactions()->isTransactionSuccessful($parentTransaction);
		if ($isTransactionSuccessful) {
			return Craft::$app->getResponse()->redirect($order->returnUrl);
		}

		// Create Processing transaction
		$this->_createChildTransaction($order, $parentTransaction, [], Transaction::STATUS_PROCESSING, Transaction::TYPE_AUTHORIZE, 'Transaction pending final confirmation from Quickpay.');

		// Redirect to return url
		return Craft::$app->getResponse()->redirect($order->returnUrl);
	}

	/**
	 * Cancel the transaction
	 * 
	 * @param string|null $transactionReference
	 * @return Response
	 */
	public function actionCancel(string $transactionReference = null): Response
	{
		//Get transaction and order
		$authTransaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionReference);
		$order = Commerce::getInstance()->getOrders()->getOrderById($authTransaction->orderId);

		//Enable recalculation for order
		Quickpay::getInstance()->getOrders()->enableCalculation($order);

		// Cancel the transaction
		Quickpay::getInstance()->getPayments()->cancelLinkFromGateway($authTransaction);

		// Redirect to cancel url
		return Craft::$app->getResponse()->redirect($order->cancelUrl);
	}

	/**
	 * Update an orders transation and status on Quickpay callback
	 * ? This will usually be called imidiatly after the request, but can be delayed up to 24 hours
	 * 
	 * @param string|null $transactionReference
	 * @return void
	 */
	public function actionNotify(string $transactionReference = null): void
	{
		// Check if transaction reference is set
		if (!$transactionReference) {
			throw new ForbiddenHttpException('Missing transaction reference.');
		}

		// Check if checksum is set
		$checksum = $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"] ?? null;
		if (!$checksum) {
			throw new ForbiddenHttpException('Missing Checksum.');
		}

		// Get the craft transaction from the quickpay transaction reference
		//? The transaction being referenced here is the parent transaction for the order (The transaction created when the user is redirected to quickpay)
		$parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionReference);

		// Validate checksum
		if (!$this->validateSha256Checksum($checksum, $parentTransaction->getGateway())) {
			throw new ForbiddenHttpException('Invalid Checksum.');
		}

		//Get requesy body
		//? This is the response from quickpay, and is suppling the updated status of the parent transaction
		$body = Craft::$app->request->getRawBody();
		$data = Quickpay::getInstance()->getPayments()->getResponseModel($body);
		// $data = Json::decode($body);

		// Check if transaction is successful
		//? This will return true if either the current transaction or its parent transaction is successful
		$isTransactionSuccessful = Commerce::getInstance()->getTransactions()->isTransactionSuccessful($parentTransaction);

		// Get the order belonging to the transaction
		//? This is used to define what order any child transactions should be attached to
		$order = Commerce::getInstance()->getOrders()->getOrderById($parentTransaction->orderId);

		//* Authorize
		//? If the parent transaction is not successful (Initial / Redirect), and the update State is "New" and Accepted, Quickpay has authorized the transaction
		if (!$isTransactionSuccessful && $data->accepted && $data->state === Data::STATE_NEW) {
			$this->_createChildTransaction($order, $parentTransaction, $data, Transaction::STATUS_SUCCESS, Transaction::TYPE_AUTHORIZE, 'Transaction authorized.');
			return;
		}

		//? If the parent transaction is not successful (Initial / Redirect), and the update State is "Rejected" quickpay has rejected the transaction
		if (!$isTransactionSuccessful && $data->state === Data::STATE_REJECTED) {
			$this->_createChildTransaction($order, $parentTransaction, $data, Transaction::STATUS_FAILED, Transaction::TYPE_AUTHORIZE, 'Transaction rejected.');
			return;
		}

		//* Capture
		//? If the order is not yet defined as paid, and the quickpay status is "Processed", the transaction has been captured
		if (!$order->getIsPaid() && $data->state === Data::STATE_PROCESSED) {
			// TODO: Add capture logic: Update order status, set order paid status etc.
			$this->_createChildTransaction($order, $parentTransaction, $data, Transaction::STATUS_SUCCESS, Transaction::TYPE_CAPTURE, 'Transaction captured.');
			return;
		}
	}

	/**
	 * Validates the checksum
	 *
	 * @param string $checksum
	 * @param Gateway $gateway
	 * @return boolean
	 */
	protected function validateSha256Checksum(string $checksum, Gateway $gateway): bool
	{
		$base = file_get_contents("php://input");
		$privateKey = App::parseEnv($gateway->private_key);
		return (hash_hmac("sha256", $base, $privateKey) === $checksum);
	}


	/**
	 * Creates a new child transaction for the parent
	 *
	 * @param Order $order
	 * @param ModelsTransaction $parent
	 * @param mixed $data
	 * @param string $status
	 * @param string $type
	 * @param string $message
	 * @return void
	 */
	private function _createChildTransaction(Order $order, ModelsTransaction $parent, mixed $data, string $status, string $type, string $message): void
	{
		if (is_array($data)) {
			$data = (object) $data;
		}

		// Create a new child transaction
		$transaction = Commerce::getInstance()->getTransactions()->createTransaction($order, $parent);

		// Set the transaction status to failed
		$transaction->status = $status;
		$transaction->type = $type;

		// Set the transaction reference to match the one given by quickpay
		if (isset($data->id) && $data->id) {
			$transaction->reference = $data->id;
		}

		// Set the transaction response to the response from quickpay
		//? This can be used for debugging
		if ($data) {
			$transaction->response = $data;
		}

		// Define a message for the transaction
		$transaction->message = $message;

		// Save the child transaction
		Commerce::getInstance()->getTransactions()->saveTransaction($transaction);
		return;
	}
}
