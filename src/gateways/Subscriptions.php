<?php

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

class Subscriptions extends BaseGateway
{

	const SUPPORTS = [
		'Authorize' => true,
		'Capture' => true,
		'CompleteAuthorize' => false,
		'CompletePurchase' => false,
		'PaymentSources' => false,
		'Purchase' => false,
		'Refund' => true,
		'PartialRefund' => true,
		'Void' => false,
		'Webhooks' => false,
	];

	const PAYMENT_TYPES = [
		'authorize' => 'Authorize Only',
	];

	use GatewayTrait;

	//Settings options
	public $api_key;
	public $private_key;
	public $analyticsId;
	public $brandingId;
	public $autoCapture = 1;
	public $autoCaptureStatus;
	public $enableAutoStatus;
	public $afterCaptureStatus;
	public $paymentMethods;
	public $enabled3ds;
	public $paymentOrderStatus;

	// Settings
	// =========================================================================

	/**
	 * Returns the display name of this class.
	 *
	 * @return string The display name of this class.
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Quickpay Subscription');
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
	public function getSettingsHtml()
	{
		//craft.commerce.orderStatuses.allOrderStatuses

		foreach (CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
			$statusOptions[] = [
				'value' => $status->id,
				'label' => $status->displayName
			];
		}

		//Allowed payment methods on Quickpay
		$paymentMethods = [
			'creditcard' => 'Creditcard',
			'american-express' => 'American Express credit card',
			'dankort' => 'Dankort credit card',
			'diners' => 'Diners Club credit card',
			'fbg1886' => 'Forbrugsforeningen af 1886',
			'jcb' => 'JCB credit card',
			'maestro' => 'Maestro debit card',
			'mastercard' => 'Mastercard credit card',
			'mastercard-debet' => 'Mastercard debet card',
			'visa' => 'Visa credit card',
			'visa-electron' => 'Visa debet (former Visa Electron) card',
		];

		return Craft::$app->getView()->renderTemplate('commerce-quickpay/gateways/subscriptions', ['gateway' => $this, 'statusOptions' => $statusOptions, 'paymentMethods' => $paymentMethods]);
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
		$response = Plugin::$plugin->getSubscriptions()->intiateSubscriptionFromGateway($transaction);
		if (!$response) {
			throw new ServiceUnavailableHttpException(Craft::t('commerce', 'An error occured when communicatiing with Quickpay. Please try again.'));
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
		$response = Plugin::$plugin->getSubscriptions()->captureFromGateway($transaction);

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
}
