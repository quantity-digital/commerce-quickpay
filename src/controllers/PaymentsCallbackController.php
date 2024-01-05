<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\Plugin as Commerce;
use craft\helpers\App;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\Plugin as Quickpay;
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
		//TODO: Update to use same logic as notify
		if (!$transactionReference) {
			throw new ForbiddenHttpException('Missing transaction reference.');
		}

		//Get transaction and order
		$parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionReference);

		// Set the child transaction reference
		//? The continue requests contains no body data, therefore we fetch the reference from the parent transaction
		$data = (object) [
			'id' => $parentTransaction->reference,
		];

		return Quickpay::getInstance()->getPaymentsCallbackService()->continue($data, $parentTransaction);
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
		$parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionReference);

		return Quickpay::getInstance()->getPaymentsCallbackService()->cancel($parentTransaction);
	}

	/**
	 * Update an orders transation and status on Quickpay callback
	 * ? This will usually be called imidiatly after the request, but can be delayed up to 24 hours
	 * 
	 * @param string|null $transactionReference
	 * @return void
	 */
	public function actionNotify(): void
	{
		// Request
		//? This is the response from quickpay, and is suppling the updated status of the parent transaction
		$body = Craft::$app->request->getRawBody();
		$data = Quickpay::getInstance()->getPayments()->getResponseModel($body);

		// Get the craft transaction
		$parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionByReference($data->id);

		// Get the gateway of the transaction
		$gateway = $parentTransaction->getGateway();

		// Validate the checksum
		$this->validateSha256Checksum($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"], $gateway);

		// Handle notify
		Quickpay::getInstance()->getPaymentsCallbackService()->notify($data, $parentTransaction);
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
		if (!$checksum) {
			throw new ForbiddenHttpException('Missing Checksum.');
		}

		$base = file_get_contents("php://input");
		$privateKey = App::parseEnv($gateway->private_key);

		$valid = (hash_hmac("sha256", $base, $privateKey) === $checksum);

		if (!$valid) {
			throw new ForbiddenHttpException('Invalid Checksum.');
		}

		return $valid;
	}
}
