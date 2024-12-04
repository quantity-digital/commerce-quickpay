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
		$currency = $this->order->paymentCurrency;
		$reference = $this->order->reference ?? null;

		// Set id for quickpay
		$reference = $this->_getSetReference($this->order);
		$quickpayOrderId = $this->_getSetQuickpayReference($reference, $this->order);

		return [
			'order_id' => $quickpayOrderId,
			'currency' => $currency,
			'basket' => Plugin::getInstance()->getPayments()->getBasketPayload($this->order),
			'shipping' => Plugin::getInstance()->getPayments()->getShippingPayload($this->order),
		];
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

	/**
	 * Update craft order reference
	 *
	 * @param Order $order
	 * @return string
	 */
	private function _getSetReference(Order $order): string
	{
		// Get current order reference
		//? If active cart, reference is null
		$reference = $order->reference ?? null;

		// If reference exists, return it
		if ($reference) {
			return (string) $reference;
		}

		// Generate reference from template
		// Fetch template from store settings
		$referenceTemplate = CommercePlugin::getInstance()->getStores()->getStoreBySiteId($order->orderSiteId)->getOrderReferenceFormat();
		// Render template
		$orderId = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $order);

		// Save to order
		try {
			$order->reference = $orderId;
			Craft::$app->getElements()->saveElement($order, false);
		} catch (Throwable $exception) {
			throw $exception;
		}

		// Return
		return (string) $order->reference;
	}

	/**
	 * Update quickpay Order ID
	 *
	 * @param string $reference
	 * @param Order $order
	 * @return string
	 */
	private function _getSetQuickpayReference(string $reference, Order $order): string
	{
		//Update length of reference to minimum 4
		//? Quickpay requires minimum 4 characters
		$length = strlen($reference);
		if ($length < 4) {
			while ($length < 4) {
				$reference = '0' . $reference;
				$length++;
			}
		}

		// Set transaction try count
		//? Reference cant be the same for the transactions, if there are multiple transactions add transaction count
		$count = count($order->transactions) ?? 0;
		if (!$count) {
			return (string) $reference;
		}

		return (string) $reference . '-' . $count;
	}
}
