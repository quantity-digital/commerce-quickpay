<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\Json;
use QD\commerce\quickpay\Plugin as QuickpayPlugin;
use yii\web\ForbiddenHttpException;

class SubscriptionsCallbackController extends BaseController
{
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

	public function actionContinue($transactionReference = null)
	{
		//Get transaction and order
		$authTransaction = CommercePlugin::getInstance()->transactions->getTransactionByHash($transactionReference);
		$order = CommercePlugin::getInstance()->orders->getOrderById($authTransaction->orderId);


		//Order is already paid
		if ($order->getIsPaid()) {
			return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
		}

		// If it's successful already, we're good.
		if (CommercePlugin::getInstance()->getTransactions()->isTransactionSuccessful($authTransaction)) {
			return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
		}

		//Create a new transaction with processing
		$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order, $authTransaction);
		$transaction->status = \craft\commerce\records\Transaction::STATUS_PROCESSING;
		$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
		$transaction->message = 'Authorize request completed. Waiting for final confirmation from Quickpay.';
		CommercePlugin::getInstance()->transactions->saveTransaction($transaction);

		return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
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

		//Get requesy body
		$body = Craft::$app->request->getRawBody();
		$data = Json::decode($body);

		$authTransaction = CommercePlugin::getInstance()->transactions->getTransactionByHash($transactionReference);
		$isTransactionSuccessful = CommercePlugin::getInstance()->getTransactions()->isTransactionSuccessful($authTransaction);
		$order = CommercePlugin::getInstance()->orders->getOrderById($authTransaction->orderId);

		if (!$isTransactionSuccessful && $data['accepted'] && $data['state'] === 'active') {
			//Create a new transaction with processing
			$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order, $authTransaction);
			$transaction->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
			$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
			$transaction->reference = $data['id'];
			$transaction->response = $body;
			$transaction->message = 'Transaction authorized.';
			CommercePlugin::getInstance()->transactions->saveTransaction($transaction);
			return;
		}
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
