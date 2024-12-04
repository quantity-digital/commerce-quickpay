<?php

namespace QD\commerce\quickpay\models;

use Craft;
use craft\helpers\App;
use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\helpers\UrlHelper;
use craft\commerce\Plugin as CommercePlugin;
use QD\commerce\quickpay\Plugin;
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

	/**
	 * Inits the payment request
	 *
	 * @return void
	 */
	public function init(): void
	{
		parent::init();
		$this->transactionReference = $this->transaction->hash;
	}

	/**
	 * Get the payload
	 *
	 * @return mixed
	 */
	public function getPayload(): mixed
	{
		// Order info
		$orderId = $this->order->reference;
		$currency = $this->order->paymentCurrency;

		if ($orderId == null) {
			$referenceTemplate = CommercePlugin::getInstance()->getStores()->getStoreBySiteId($this->order->orderSiteId)->getOrderReferenceFormat();

			try {
				$orderId = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $this);
				$this->order->reference = $orderId;
				$originalRecalculationMode = $this->order->getRecalculationMode();
				$this->order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
				Craft::$app->getElements()->saveElement($this->order, false);
				$this->order->setRecalculationMode($originalRecalculationMode);
			} catch (Throwable $exception) {
				Craft::error('Unable to generate order completion reference for order ID: ' . $this->order->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
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
			'currency' => $currency,
			'basket' => Plugin::getInstance()->getPayments()->getBasketPayload($this->order),
			'shipping' => Plugin::getInstance()->getPayments()->getShippingPayload($this->order),
		];

		return $payload;
	}

	/**
	 * Get payload link
	 *
	 * @return mixed
	 */
	public function getLinkPayload(): mixed
	{
		//Get gateway
		$gateway = $this->transaction->getGateway();

		// Settings
		$paymentAmount = $this->transaction->paymentAmount;
		$cents = $paymentAmount * 100;

		$payload = [
			'language'     => Craft::$app->getLocale()->getLanguageID(),
			'amount'       => $cents,
			'continue_url' => UrlHelper::siteUrl('quickpay/callbacks/payments/continue/' . $this->getTransactionReference()),
			'cancel_url'   => UrlHelper::siteUrl('quickpay/callbacks/payments/cancel/' . $this->getTransactionReference()),
			'callback_url' => UrlHelper::siteUrl('quickpay/callbacks/payments/notify'),
		];

		//Is paymentmethods defined in settings
		$paymentMethods = $gateway->paymentMethods;
		if ($paymentMethods) {
			$paymentMethods = $this->apply3ds($paymentMethods);
			$paymentMethods = implode(', ', $paymentMethods);
			$payload['payment_methods'] = $paymentMethods;
		}

		//Is analyticsId defined in settings
		$analyticsId = App::parseEnv($gateway->analyticsId);
		if ($analyticsId) {
			$payload['google_analytics_tracking_id'] = $analyticsId;
		}

		//Is brandingId defined in settings
		$brandingId = App::parseEnv($gateway->brandingId);
		if ($brandingId) {
			$payload['branding_id'] = $brandingId;
		}

		return $payload;
	}

	/**
	 * Applies 3d to the front of all the payment methods
	 *
	 * @param array $paymentMethods
	 * @return array
	 */
	private function apply3ds(array $paymentMethods): array
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

	/**
	 * Gets the transaction reference
	 *
	 * @return string
	 */
	public function getTransactionReference(): string
	{
		return $this->transactionReference;
	}

	/**
	 * Sets the paymentID
	 *
	 * @param string $id
	 * @return void
	 */
	public function setPaymentID(string $id): void
	{
		$this->paymentId = $id;
	}

	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['order'], 'required'],
		];
	}
}
