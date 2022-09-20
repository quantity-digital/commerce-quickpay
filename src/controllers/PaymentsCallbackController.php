<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\Plugin;
use craft\helpers\Json;
use craft\helpers\App;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\Plugin as QuickpayPlugin;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class PaymentsCallbackController extends BaseController
{
	/**
	 * @inheritdoc
	 */
	public array $allowAnonymous = [
		'continue' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
		'notify' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
		'cancel' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE
	];

	/**
	 * @inheritdoc
	 */
	public bool $enableCsrfValidation = false;

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
	 * TODO: Figure out what this does
	 *
	 * @param string|null $transactionReference
	 * @return Response
	 */
	public function actionContinue(string $transactionReference = null): Response
	{
		//Get transaction and order
		$authTransaction = Plugin::getInstance()->transactions->getTransactionByHash($transactionReference);
		$order = Plugin::getInstance()->orders->getOrderById($authTransaction->orderId);
		//Order is already paid
		if ($order->getIsPaid()) {
			// $reponse  = Craft::$app->getResponse()->redirect($order->returnUrl);
			// $reponse->send();
			return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
		}

		// If it's successful already, we're good.
		if (Plugin::getInstance()->getTransactions()->isTransactionSuccessful($authTransaction)) {
			return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
		}

		//Create a new transaction with processing
		$transaction = Plugin::getInstance()->transactions->createTransaction($order, $authTransaction);
		$transaction->status = \craft\commerce\records\Transaction::STATUS_PROCESSING;
		$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
		$transaction->message = 'Authorize request completed. Waiting for final confirmation from Quickpay.';
		Plugin::getInstance()->transactions->saveTransaction($transaction);

		return Craft::$app->getResponse()->redirect($order->returnUrl)->send();
	}

	/**
	 * TODO: figure out what this does 
	 *
	 * @param string|null $transactionReference
	 * @return Response
	 */
	public function actionCancel(string $transactionReference = null): Response
	{
		//Get transaction and order
		$authTransaction = Plugin::getInstance()->transactions->getTransactionByHash($transactionReference);
		$order = Plugin::getInstance()->orders->getOrderById($authTransaction->orderId);

		//Enable recalculation for order
		QuickpayPlugin::getInstance()->orders->enableCalculation($order);
		QuickpayPlugin::getInstance()->payments->cancelLinkFromGateway($authTransaction);

		return Craft::$app->getResponse()->redirect($order->cancelUrl)->send();
	}

	/**
	 * TODO: Figure out what this does
	 *
	 * @param string|null $transactionReference
	 * @return Response
	 */
	public function actionNotify(string $transactionReference = null): Response
	{
		if (!$transactionReference) {
			throw new ForbiddenHttpException('Missing transaction reference.');
		}

		if (!isset($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"])) {
			throw new ForbiddenHttpException('Missing Checksum.');
		}

		$authTransaction = Plugin::getInstance()->transactions->getTransactionByHash($transactionReference);

		//Validate checksum
		if (!$this->validateSha256Checksum($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"], $authTransaction->getGateway())) {
			throw new ForbiddenHttpException('Wrong Checksum.');
		}

		//Get requesy body
		$body = Craft::$app->request->getRawBody();
		$data = Json::decode($body);

		$isTransactionSuccessful = Plugin::getInstance()->getTransactions()->isTransactionSuccessful($authTransaction);
		$order = Plugin::getInstance()->orders->getOrderById($authTransaction->orderId);

		if (!$isTransactionSuccessful && $data['accepted'] && $data['state'] === 'new') {
			//Create a new transaction with processing
			$transaction = Plugin::getInstance()->transactions->createTransaction($order, $authTransaction);
			$transaction->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
			$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
			$transaction->reference = $data['id'];
			$transaction->response = $body;
			$transaction->message = 'Transaction authorized.';
			Plugin::getInstance()->transactions->saveTransaction($transaction);
			return;
		}

		if (!$isTransactionSuccessful && $data['state'] === 'rejected') {
			//Create a new transaction with failed
			$transaction = Plugin::getInstance()->transactions->createTransaction($order, $authTransaction);
			$transaction->status = \craft\commerce\records\Transaction::STATUS_FAILED;
			$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
			$transaction->reference = $data['id'];
			$transaction->response = $body;
			$transaction->message = 'Transaction rejected.';
			Plugin::getInstance()->transactions->saveTransaction($transaction);
			return;
		}

		if (!$order->getIsPaid() && $data['state'] === 'processed') {
			//Create a new transaction with success
			$transaction = Plugin::getInstance()->transactions->createTransaction($order, $authTransaction);
			$transaction->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
			$transaction->type = \craft\commerce\records\Transaction::TYPE_CAPTURE;
			$transaction->reference = $data['id'];
			$transaction->response = $body;
			$transaction->message = 'Transaction captured offsite.';
			Plugin::getInstance()->transactions->saveTransaction($transaction);
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
}
