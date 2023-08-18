<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Quantity Digital
 * @license MIT
 */

namespace QD\commerce\quickpay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\ServiceUnavailableHttpException;
use QD\commerce\quickpay\base\GatewayTrait;
use QD\commerce\quickpay\Plugin;

class Gateway extends BaseGateway
{
	const SUPPORTS = [
		'Authorize' => true,
		'Capture' => true,
		'CompleteAuthorize' => false,
		'CompletePurchase' => false,
		'PaymentSources' => false,
		'Purchase' => true,
		'Refund' => true,
		'PartialRefund' => true,
		'Void' => true,
		'Webhooks' => false,
	];

	const PAYMENT_TYPES = [
		'authorize' => 'Authorize Only',
	];

	use GatewayTrait;

	//Settings options
	public string $api_key = '';
	public string $private_key = '';
	public string $analyticsId = '';
	public string $brandingId = '';
	public bool $autoCapture = false;
	public string $autoCaptureStatus = '';
	public bool $enableAutoStatus = false;
	public string $afterCaptureStatus = '';
	public bool $sendNotfifications = false;
	public string $notificationEmails = '';
	public array $paymentMethods = [];
	public bool $enabled3ds = false;
	public bool $convertAmount = false;

	// Settings
	// =========================================================================

	/**
	 * Returns the display name of this class.
	 *
	 * @return string The display name of this class.
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Quickpay Payment');
	}

	/**
	 * Returns the component’s settings HTML.
	 *
	 * @return string|null
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\Exception
	 */
	public function getSettingsHtml(): string
	{
		//craft.commerce.orderStatuses.allOrderStatuses
		foreach (CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
			$statusOptions[] = [
				'value' => $status->handle,
				'label' => $status->displayName
			];
		}

		//Allowed payment methods on Quickpay
		$paymentMethods = [
			'creditcard' => 'Creditcard',
			'american-express' => 'American Express credit card',
			'mobilepay' => 'MobilePay Online',
			'dankort' => 'Dankort credit card',
			'diners' => 'Diners Club credit card',
			'fbg1886' => 'Forbrugsforeningen af 1886',
			'jcb' => 'JCB credit card',
			'maestro' => 'Maestro debit card',
			'mastercard' => 'Mastercard credit card',
			'mastercard-debet' => 'Mastercard debet card',
			'visa' => 'Visa credit card',
			'visa-electron' => 'Visa debet (former Visa Electron) card',
			// 'paypal' => 'PayPal',
			// 'sofort' => 'Sofort',
			'viabill' => 'ViaBill',
			// 'resurs' => 'Resurs Bank',
			'klarna-payments' => 'Klarna Payments',
			// 'klarna' => 'Klarna',
			'bitcoin' => 'Bitcoin through Coinify',
			// 'swish' => 'Swish',
			'trustly' => 'Trustly',
			'ideal' => 'iDEAL',
			'vipps' => 'Vipps',
			'paysafecard' => 'Paysafecard'
		];

		return Craft::$app->getView()->renderTemplate('commerce-quickpay/gateways/payments', ['gateway' => $this, 'statusOptions' => $statusOptions, 'paymentMethods' => $paymentMethods]);
	}

	/**
	 * Returns the payment type options.
	 *
	 * @return array
	 */
	public function getPaymentTypeOptions(): array
	{
		return self::PAYMENT_TYPES;
	}

	/**
	 * Makes an authorize request.
	 *
	 * @param Transaction $transaction The authorize transaction
	 * @param BasePaymentForm $form A form filled with payment info
	 * @return RequestResponseInterface
	 */
	public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		//TODO : Implement paymentlink to be sent to customer for manual orders
		$response = Plugin::$plugin->getPayments()->intiatePaymentFromGateway($transaction);

		//If no response, throw error
		if (!$response) {
			$form->addErrors(['Failed to communicate with Quickpay']);
			throw new ServiceUnavailableHttpException('Failed to communicate with Quickpay. Please try again.');
		}

		if (!$response->isSuccessful()) {
			$form->addErrors($response->errors);
			throw new ServiceUnavailableHttpException($response->message, $response->_code);
		}

		return $response;
	}

	/**
	 * Makes a capture request.
	 *
	 * @param Transaction $transaction The capture transaction
	 * @param string $reference Reference for the transaction being captured.
	 * @return RequestResponseInterface
	 */
	public function capture(Transaction $transaction, string $reference): RequestResponseInterface
	{
		$response = Plugin::$plugin->getPayments()->captureFromGateway($transaction);

		return $response;
	}

	/**
	 * Makes an refund request.
	 *
	 * @param Transaction $transaction The refund transaction
	 * @return RequestResponseInterface
	 */
	public function refund(Transaction $transaction): RequestResponseInterface
	{
		$response = Plugin::$plugin->getPayments()->refundFromGateway($transaction);

		return $response;
	}

	/**
	 * TODO: Implement cancel functionality
	 * Cancels a transaction
	 *
	 * @param Transaction $transaction
	 * @return RequestResponseInterface
	 */
	// public function cancel(Transaction $transaction): RequestResponseInterface
	// {
	// 	Plugin::$plugin->getPayments()->cancelFromGateway($transaction);
	// }
}
