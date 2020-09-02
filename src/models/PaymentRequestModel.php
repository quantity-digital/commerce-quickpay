<?php

namespace QD\commerce\quickpay\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use DateTime;
use QD\commerce\quickpay\Plugin;

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
		$orderId = $this->order->shortNumber;
		$currency = $this->order->currency;

		if($count = count($this->order->transactions)){
			$orderId = $orderId.'-'.$count;
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
		$gateway = Plugin::$plugin->getPayments()->getGateway();

		// Settings
		$storedTotal = $this->order->storedTotalPrice;
		$cents = $storedTotal * 100;

		$payload = [
			'language'     => Craft::$app->getLocale()->getLanguageID(),
			'amount'       => $cents,
			'continue_url' => UrlHelper::siteUrl('quickpay/callbacks/continue/' . $this->getTransactionReference()),
			'cancel_url'   => UrlHelper::siteUrl($this->order->cancelUrl),
			'callback_url' => UrlHelper::siteUrl('quickpay/callbacks/notify/' . $this->getTransactionReference()),
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
		$payload['callback_url'] = str_replace('localhost:8002', 'b547dc285bba.ngrok.io', $payload['callback_url']);

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

		foreach($paymentMethods as $key => $paymentMethod){
			if(\in_array($paymentMethod,$allowed3ds)){
				$paymentMethods[$key] = '3d-'.$paymentMethod;
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
