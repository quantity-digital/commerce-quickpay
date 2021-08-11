<?php

namespace QD\commerce\quickpay\models;

use Craft;
use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\helpers\UrlHelper;
use craft\commerce\Plugin as CommercePlugin;
use Throwable;

/**
 *
 * @property array $payload
 */
class PaymentRequestModel extends Model
{
	public $amount;
	public $orderId;
	public $order;
	public $transaction;
	public $paymentId;
	private $transactionReference;

	public function init()
	{
		parent::init();
		$this->transactionReference = $this->transaction->hash;
	}

	public function getPayload()
	{
		// Order info
		$orderId = $this->order->reference;
		$currency = $this->order->paymentCurrency;

		if ($orderId == null) {
			$referenceTemplate = CommercePlugin::getInstance()->getSettings()->orderReferenceFormat;

			try {
				$orderId = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $this);
				$this->order->reference = $orderId;
				$originalRecalculationMode = $this->order->getRecalculationMode();
				$this->order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
				Craft::$app->getElements()->saveElement($this->order, false);
				$this->order->setRecalculationMode($originalRecalculationMode);
			} catch (Throwable $exception) {
				Craft::error('Unable to generate order completion reference for order ID: ' . $this->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
				throw $exception;
			}
		}


		if ($count = count($this->order->transactions)) {
			$orderId = $orderId . '-' . $count;
		}

		if (strlen($orderId) < 4) {
			while (strlen($orderId) < 4) {
				$orderId = '0' . $orderId;
			}
		}

		$payload = [
			'order_id' => $orderId,
			'currency' => $currency
		];
		return $payload;
	}

	public function getLinkPayload()
	{
		//Get gateway
		$gateway = $this->transaction->getGateway();

		// Settings
		$currency = new Currency;
		$storedTotal = $currency->formatAsCurrency($this->order->storedTotalPrice, $this->order->paymentCurrency, $gateway->convertAmount, false, true);
		$cents = $storedTotal * 100;

		$payload = [
			'language'     => Craft::$app->getLocale()->getLanguageID(),
			'amount'       => $cents,
			'continue_url' => UrlHelper::siteUrl('quickpay/callbacks/payments/continue/' . $this->getTransactionReference()),
			'cancel_url'   => UrlHelper::siteUrl('quickpay/callbacks/payments/cancel/' . $this->getTransactionReference()),
			'callback_url' => UrlHelper::siteUrl('quickpay/callbacks/payments/notify/' . $this->getTransactionReference()),
		];

		//Is paymentmethods defined in settings
		$paymentMethods = $gateway->paymentMethods;
		if ($paymentMethods) {
			$paymentMethods = $this->apply3ds($paymentMethods);
			$paymentMethods = implode(', ', $paymentMethods);
			$payload['payment_methods'] = $paymentMethods;
		}

		//Is analyticsId defined in settings
		$analyticsId = Craft::parseEnv($gateway->analyticsId);
		if ($analyticsId) {
			$payload['google_analytics_tracking_id'] = $analyticsId;
		}

		//Is brandingId defined in settings
		$brandingId = Craft::parseEnv($gateway->brandingId);
		if ($brandingId) {
			$payload['branding_id'] = $brandingId;
		}

		//For testing only
		$payload['callback_url'] = str_replace('localhost:8002', 'qddev.eu.ngrok.io', $payload['callback_url']);

		return $payload;
	}

	private function apply3ds($paymentMethods)
	{
		//Which payment types is allowed to have 3D-secure
		$allowed3ds = [
			'creditcard',
			'dankort',
			'jcb',
			'maestro',
			'mastercard',
			'mastercard-debet',
			'visa',
			'visa-electron',
		];

		foreach ($paymentMethods as $key => $paymentMethod) {
			if (\in_array($paymentMethod, $allowed3ds)) {
				$paymentMethods[$key] = '3d-' . $paymentMethod;
			}
		}

		return $paymentMethods;
	}

	public function getTransactionReference(): string
	{
		return $this->transactionReference;
	}

	public function setPaymentID($id)
	{
		$this->paymentId = $id;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['order'], 'required'],
		];
	}
}
