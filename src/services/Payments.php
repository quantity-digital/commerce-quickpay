<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\models\Transaction;
use craft\commerce\services\Gateways;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\models\PaymentRequestModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\responses\CaptureResponse;
use QD\commerce\quickpay\responses\PaymentResponse;
use craft\commerce\records\Transaction as TransactionRecord;
use QD\commerce\quickpay\models\PaymentResponseModel;
use QD\commerce\quickpay\responses\RefundResponse;
use stdClass;
use yii\base\Exception;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\commerce\helpers\Currency;
use QD\commerce\quickpay\events\PaymentCaptureAmount;
use QD\commerce\quickpay\events\ShippingTotal;

class Payments extends Component
{
	//* Events
	const EVENT_BEFORE_SHIPPING_TOTAL = 'beforeShippingTotal';
	const EVENT_BEFORE_PAYMENT_CAPTURE_AMOUNT = 'beforePaymentCaptureAmount';

	public Api $api;

	/**
	 * Inits the service
	 *
	 * @return void
	 */
	public function init(): void
	{
		$this->api = Plugin::$plugin->getApi();
	}

	/**
	 * Initiates payment
	 *
	 * @param PaymentRequestModel $paymentRequest
	 * @return stdClass
	 */
	public function initiatePayment(PaymentRequestModel $paymentRequest): stdClass
	{
		$payload = $paymentRequest->getPayload();

		//Create payment
		$request = $this->api->post('/payments', $payload);

		return $request;
	}

	/**
	 * Creates a payment link
	 *
	 * @param PaymentRequestModel $paymentRequest
	 * @param stdClass $request
	 * @return stdClass
	 */
	public function getPaymentLink(PaymentRequestModel $paymentRequest, stdClass $request): stdClass
	{
		//Create link to payment (redirect to it)
		$payload = $paymentRequest->getLinkPayload();
		$link = $this->api->put('/payments/' . $request->id . '/link', $payload);

		return $link;
	}


	/**
	 * Initiates payment from Gateway
	 *
	 * @param Transaction $transaction
	 * @return PaymentResponse|boolean
	 */
	public function intiatePaymentFromGateway(Transaction $transaction): PaymentResponse|bool
	{
		//Set gateway for API
		$this->api->setGateway($transaction->getGateway());

		$order = $transaction->getOrder();
		$paymentRequest = new PaymentRequestModel([
			'order'       => $order,
			'transaction' => $transaction,
		]);

		//Create payment at quickpay
		$request = $this->initiatePayment($paymentRequest);

		$response = new PaymentResponse($request);
		//If payment wasn't created return with error message
		if (!$response->isSuccessful()) {
			return $response;
		}

		//Get redirect url
		$request = $this->getPaymentLink($paymentRequest, $request);

		if (!$request) {
			return false;
		}

		//Set redirect url inside response
		$url = $request->url ?? null;
		if ($url) {
			$response->setRedirectUrl($url);
		}

		return $response;
	}

	/**
	 * Captures a transaction from gateway
	 *
	 * @param Transaction $transaction
	 * @return CaptureResponse
	 */
	public function captureFromGateway(Transaction $transaction): CaptureResponse
	{
		//* Define
		$order = $transaction->getOrder();
		$gateway = $order->getGateway();
		$authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
		$authorizedAmount = (float)$transaction->paymentAmount;

		// Get outstanding amount
		//? Get and convert the outstanding balance for an order, in case of order adjustments / partial captures
		$amount = (float) Currency::formatAsCurrency($order->getOutstandingBalance(), $order->paymentCurrency, false, false, true);

		// Trigger event to modify the amount
		$event = new PaymentCaptureAmount([
			'order' => $order,
			'amount' => $amount,

		]);
		$this->trigger(self::EVENT_BEFORE_PAYMENT_CAPTURE_AMOUNT, $event);

		// Outstanding amount is larger than the authorized value, set amount to be equal to authorized value
		//? We can only capure a maximum of the authorized amount
		if ($authorizedAmount <= $event->amount) {
			$event->amount = $authorizedAmount;
		}

		// Authorized amount is larger than the outstanding amount, update the transaction to have correct amounts
		if ($authorizedAmount > $event->amount) {
			//? Amount is always defined in the default currency, therefore no convertion should be made
			$transaction->amount = $order->getOutstandingBalance();

			// Set the payment amount to the events amount data
			$transaction->paymentAmount = $event->amount;
		}

		// Convert to cents
		//? Quickpay expects the amount to be in cents
		$cents = $event->amount * 100;

		//* Capture request
		// Set payload
		$payload = [
			'amount' => (float) $cents,
		];

		// Set custom headers
		//? This is the only way, other than having to adjust quickpay itself to define a callback url 
		$headers = [
			'QuickPay-Callback-Url: ' . UrlHelper::siteUrl('quickpay/callbacks/payments/notify')
		];

		// Set gateway for the API request
		$this->api->setGateway($gateway);

		// make request to capture payment
		$response = $this->api->setHeaders($headers)->post("/payments/{$authorizedTransation->reference}/capture", $payload);

		// Return capture response
		//? Will return Either "Pending" for awaiting callback or "Processed" for capture completed
		return new CaptureResponse($response);
	}

	/**
	 * Refunds from gateway
	 *
	 * @param Transaction $transaction
	 * @return RefundResponse
	 */
	public function refundFromGateway(Transaction $transaction): RefundResponse
	{
		$order = $transaction->getOrder();
		$gateway = $order->getGateway();
		$this->api->setGateway($gateway);

		// Get the amount to refund
		$amount     = $transaction->paymentAmount;

		$response = $this->api->post("/payments/{$transaction->reference}/refund", [
			'amount' => $amount * 100
		]);

		return new RefundResponse($response);
	}

	/**
	 * Get cancel link from gateway
	 *
	 * @param Transaction $authTransaction
	 * @return void
	 */
	public function cancelLinkFromGateway(Transaction $authTransaction): void
	{
		$order = $authTransaction->getOrder();

		$this->api->setGateway($order->getGateway());
		$this->api->delete("/payments/{$authTransaction->reference}/link");

		$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order, $authTransaction);
		$transaction->status = TransactionRecord::STATUS_FAILED;
		$transaction->type = TransactionRecord::TYPE_AUTHORIZE;
		$transaction->reference = $transaction->reference;
		$transaction->response = '';
		$transaction->message = 'Transaction canceled.';

		CommercePlugin::getInstance()->transactions->saveTransaction($transaction);
	}

	/**
	 * @param Order $order
	 *
	 * @return Transaction|null
	 */
	public function getSuccessfulTransactionForOrder(Order $order): Transaction|null
	{
		foreach ($order->getTransactions() as $transaction) {
			if ($transaction->isSuccessful()) {
				return $transaction;
			}
		}

		return null;
	}

	/**
	 * Returns the gatway of the customer
	 *
	 * @return Gateway
	 * @throws Exception when quickpay is not setup correctly
	 */
	public function getGateway(): Gateway
	{
		$gateways = new Gateways();

		foreach ($gateways->getAllCustomerEnabledGateways() as $gateway) {
			if ($gateway instanceof Gateway) {
				return $gateway;
			}
		}
		throw new Exception('The Quickpay gateway is not setup correctly.');
	}

	//* Basket
	/**
	 * Get the quickpay basket payload
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getBasketPayload(Order $order): array
	{
		$lines = [];

		// Items
		$items = $order->getLineItems();
		if (!$items) return $lines;
		$taxrate = Plugin::getInstance()->getTaxes()->getProductTaxRate($order);

		foreach ($items as $item) {
			$purchasable = $item->getPurchasable();
			if (!$purchasable) continue;

			$unitPrice = $item->taxIncluded ? ($item->total / $item->qty) : ($item->total + $item->taxIncluded) / $item->qty;
			$lines[] = [
				'qty' => $item->qty,
				'item_no' => $item->sku,
				'item_name' => $item->description,
				'item_price' => (float) $unitPrice * 100,
				'vat_rate' => $taxrate
			];
		}

		// Adjustments
		$adjustments = $order->getAdjustments();
		if (!$adjustments) return $lines;

		foreach ($adjustments as $adjustment) {
			if ($adjustment->lineItemId) continue;
			if ($adjustment->type === 'shipping') continue;
			if ($adjustment->type === 'tax') continue;

			$lines[] = [
				'qty' => 1,
				'item_no' => $adjustment->id,
				'item_name' => $adjustment->name,
				'item_price' => (float) $adjustment->amount * 100,
				'vat_rate' => 0
			];
		}

		return $lines;
	}

	//* Shipping
	/**
	 * Get the payload for the selected shipping method
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getShippingPayload(Order $order): array
	{
		//Get taxrate
		$taxrate = Plugin::getInstance()->getTaxes()->getShippingTaxRate($order);

		$event = new ShippingTotal([
			'order' => $order,
			'total' => Currency::formatAsCurrency($order->totalShippingCost, $order->paymentCurrency, false, false, true),

		]);
		$this->trigger(self::EVENT_BEFORE_SHIPPING_TOTAL, $event);
		$amount = $event->total;


		return [
			'amount' => (float) $amount * 100,
			'vat_rate' => $taxrate
		];
	}

	/**
	 * Return the model for a Quickpay PaymentResponse
	 *
	 * @param string $body
	 * @return PaymentResponseModel
	 */
	public function getResponseModel(string $body): PaymentResponseModel
	{
		$data = Json::decode($body);

		return new PaymentResponseModel(
			id: $data['id'] ?? 0,
			ulid: $data['ulid'] ?? '',
			merchant_id: $data['merchant_id'] ?? 0,
			order_id: $data['order_id'] ?? 0,
			accepted: $data['accepted'] ?? false,
			type: $data['type'] ?? '',
			text_on_statement: $data['text_on_statement'] ?? '',
			branding_id: $data['branding_id'] ?? null,
			variables: $data['variables'] ?? [],
			currency: $data['currency'] ?? '',
			state: $data['state'] ?? '',
			metadata: $data['metadata'] ?? [],
			link: $data['link'] ?? [],
			shipping_address: $data['shipping_address'] ?? null,
			invoice_address: $data['invoice_address'] ?? null,
			basket: $data['basket'] ?? [],
			shipping: $data['shipping'] ?? null,
			operations: $data['operations'] ?? [],
			test_mode: $data['test_mode'] ?? false,
			acquirer: $data['acquirer'] ?? '',
			facilitator: $data['facilitator'] ?? null,
			created_at: $data['created_at'] ?? '',
			updated_at: $data['updated_at'] ?? '',
			retented_at: $data['retented_at'] ?? '',
			balance: $data['balance'] ?? 0,
			fee: $data['fee'] ?? null,
			deadline_at: $data['deadline_at'] ?? null,
		);
	}
}
