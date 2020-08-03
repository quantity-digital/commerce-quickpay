<?php

namespace QD\commerce\quickpay\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use DateTime;

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
		$orderId = $this->order->id;
		$currency = $this->order->currency;

		//Todo Handle orderId already ben sent to quickpay
		$payload = [
			'order_id' => (string)time(),
			'currency' => $currency
		];

		return $payload;
	}

	public function getLinkPayload()
	{
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

		//For testing only
		$payload['callback_url'] = str_replace('localhost:8002', 'b547dc285bba.ngrok.io', $payload['callback_url']);

		return $payload;
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
