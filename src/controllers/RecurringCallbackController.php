<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\Json;
use DateTime;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\events\SubscriptionAuthorizeEvent;
use QD\commerce\quickpay\events\SubscriptionCaptureEvent;
use QD\commerce\quickpay\events\SubscriptionFailedAuthorizationEvent;
use QD\commerce\quickpay\events\SubscriptionFailedCaptureEvent;
use yii\web\ForbiddenHttpException;

class RecurringCallbackController extends BaseController
{

	const EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE = 'afterFailedSubscriptionCapture';
	const EVENT_AFTER_FAILED_SUBSCRIPTION_AUTHORIZE = 'afterFailedSubscriptionAuthorize';
	const EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_CAPTURE = 'afterSuccessfulSubscriptionCapture';
	const EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_AUTHORIZE = 'afterSuccessfulSubscriptionAuthorize';

	/**
	 * @inheritdoc
	 */
	public $allowAnonymous = [
		'continue' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
		'notify' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE
	];

	/**
	 * @inheritdoc
	 */
	public $enableCsrfValidation = false;

	public function init()
	{
		parent::init();
	}

	/**
	 * @param \yii\base\Action $action
	 * @return bool
	 * @throws \craft\web\ServiceUnavailableHttpException
	 * @throws \yii\web\BadRequestHttpException
	 * @throws \yii\web\ForbiddenHttpException
	 */
	public function beforeAction($action)
	{
		if (!parent::beforeAction($action)) {
			return false;
		}

		return true;
	}

	public function actionNotify($transactionReference = null)
	{
		if (!$transactionReference) {
			throw new ForbiddenHttpException('Missing transaction reference.');
		}

		if (!isset($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"])) {
			throw new ForbiddenHttpException('Missing Checksum.');
		}

		$authTransaction = CommercePlugin::getInstance()->transactions->getTransactionByHash($transactionReference);

		//Validate checksum
		if (!$this->validateSha256Checksum($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"], $authTransaction->getGateway())) {
			throw new ForbiddenHttpException('Wrong Checksum.');
		}

		//Get request body
		$body = Craft::$app->request->getRawBody();
		$data = Json::decode($body, false);
		$isTransactionSuccessful = CommercePlugin::getInstance()->getTransactions()->isTransactionSuccessful($authTransaction);
		$order = CommercePlugin::getInstance()->orders->getOrderById($authTransaction->orderId);
		$operations = $data->operations;
		$operation = end($operations);

		switch ($operation->type) {
			case 'recurring':
				$type = TransactionRecord::TYPE_AUTHORIZE;
				$status = ($operation->qp_status_code === '20000') ? TransactionRecord::STATUS_SUCCESS : TransactionRecord::STATUS_FAILED;
				$message = $operation->aq_status_msg;
				break;

			case 'capture':
				$type = TransactionRecord::TYPE_CAPTURE;
				$status = ($operation->qp_status_code === '20000') ? TransactionRecord::STATUS_SUCCESS : TransactionRecord::STATUS_FAILED;
				$message = $operation->aq_status_msg;
				sleep(1);
				$childTransactions = CommercePlugin::getInstance()->transactions->getChildrenByTransactionId($authTransaction->id);

				foreach ($childTransactions as $childTransaction) {
					if ($childTransaction->type === TransactionRecord::TYPE_AUTHORIZE && $childTransaction->status === TransactionRecord::STATUS_SUCCESS) {
						$authTransaction = $childTransaction;
					}
				}
				break;
		}

		if ($isTransactionSuccessful && $type === TransactionRecord::TYPE_AUTHORIZE) {
			return;
		}

		$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order, $authTransaction);
		$transaction->status = $status;
		$transaction->type = $type;
		$transaction->reference = $data->id;
		$transaction->response = $body;
		$transaction->message = $message;
		CommercePlugin::getInstance()->transactions->saveTransaction($transaction);

		$subscription = Subscription::find()->id($order->subscriptionId)->one();

		//If authorization failed
		if ($type === TransactionRecord::TYPE_AUTHORIZE && $status === TransactionRecord::STATUS_FAILED) {

			$subscription->isSuspended = true;
			$subscription->dateSuspended = new DateTime('now');

			$eventData = new SubscriptionFailedAuthorizationEvent([
				'order' => $order,
				'subscription' => $subscription
			]);

			if ($this->hasEventHandlers(self::EVENT_AFTER_FAILED_SUBSCRIPTION_AUTHORIZE)) {
				$this->trigger(self::EVENT_AFTER_FAILED_SUBSCRIPTION_AUTHORIZE, $eventData);
			}

		}

		//If capture failed
		if ($type === TransactionRecord::TYPE_CAPTURE && $status === TransactionRecord::STATUS_FAILED) {

			$subscription->isSuspended = true;
			$subscription->dateSuspended = new DateTime('now');

			$eventData = new SubscriptionFailedCaptureEvent([
				'order' => $order,
				'subscription' => $subscription
			]);


			if ($this->hasEventHandlers(self::EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE)) {
				$this->trigger(self::EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE, $eventData);
			}

		}

		//If authorize was success
		if($type === TransactionRecord::TYPE_AUTHORIZE && $status === TransactionRecord::STATUS_SUCCESS){
			$subscription->isSuspended = false;
			$subscription->dateSuspended = null;

			$eventData = new SubscriptionAuthorizeEvent([
				'order' => $order,
				'subscription' => $subscription,
			]);

			if ($this->hasEventHandlers(self::EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_AUTHORIZE)) {
				$this->trigger(self::EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_AUTHORIZE, $eventData);
			}
		}


		//If capture was success
		if($type === TransactionRecord::TYPE_CAPTURE && $status === TransactionRecord::STATUS_SUCCESS){
			$subscription->isSuspended = false;
			$subscription->dateSuspended = null;

			$eventData = new SubscriptionCaptureEvent([
				'order' => $order,
				'subscription' => $subscription,
			]);

			if ($this->hasEventHandlers(self::EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_CAPTURE)) {
				$this->trigger(self::EVENT_AFTER_SUCCESSFUL_SUBSCRIPTION_CAPTURE, $eventData);
			}
		}

		Craft::$app->getElements()->saveElement($eventData->subscription);

		return;
	}

	/**
	 * Validate quickpay checksum
	 *
	 * @param [type] $checksum
	 *
	 * @return void
	 */
	protected function validateSha256Checksum($checksum, $gateway)
	{
		$base = file_get_contents("php://input");
		$privateKey = Craft::parseEnv($gateway->private_key);
		return (hash_hmac("sha256", $base, $privateKey) === $checksum);
	}
}
