<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\models\Transaction;
use craft\commerce\services\Gateways;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\helpers\Currency;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\models\PaymentRequestModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\responses\CaptureResponse;
use QD\commerce\quickpay\responses\PaymentResponse;
use craft\commerce\records\Transaction as TransactionRecord;
use QD\commerce\quickpay\events\BasketAdjustmentAmount;
use QD\commerce\quickpay\events\BasketLineTotal;
use QD\commerce\quickpay\events\ShippingTotal;
use QD\commerce\quickpay\responses\RefundResponse;
use stdClass;
use yii\base\Exception;

class Payments extends Component
{

	const EVENT_BEFORE_BASKET_ADJUSTMENT_AMOUNT = 'beforeBasketAdjustmentAmount';
	const EVENT_BEFORE_BASKET_LINE_TOTAL = 'beforeBasketLineTotal';
	const EVENT_BEFORE_SHIPPING_TOTAL = 'beforeShippingTotal';

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
		$amount = $paymentRequest->getLinkPayload();
		$link = $this->api->put('/payments/' . $request->id . '/link', $amount);

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
		$order = $transaction->getOrder();
		$gateway = $transaction->getGateway();
		$authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
		$authorizedAmount     = (float)$transaction->paymentAmount;

		// Get the amount to capture
		$amount = $transaction->paymentAmount;

		// Set gateway for API
		$this->api->setGateway($order->getGateway());

		//Outstanding amount is larger than the authorized value - set amount to be equal to authorized value
		if ($authorizedAmount < $amount) {
			$amount = $authorizedAmount;
		}

		if ($authorizedAmount > $amount) {
			$transaction->amount = $amount;
			$transaction->paymentAmount = $amount;
		}

		//multiplied by 100 because industry standard is to save the amount in "cents"
		$response = $this->api->post("/payments/{$authorizedTransation->reference}/capture", [
			'amount' => $amount * 100
		]);

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

		$response = $this->api->delete("/payments/{$authTransaction->reference}/link");

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
	public function getSuccessfulTransactionForOrder(Order $order): Transaction
	{
		foreach ($order->getTransactions() as $transaction) {
			if ($transaction->isSuccessful()) {
				return $transaction;
			}
		}
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
		$quickpayGateway = null;

		foreach ($gateways->getAllCustomerEnabledGateways() as $gateway) {

			// If it's an instance of QuickPay Gateway, we return it
			if ($gateway instanceof Gateway) {
				$quickpayGateway = $gateway;
				break;
			}
		}

		if (!$quickpayGateway) {
			throw new Exception('The Quickpay gateway is not setup correctly.');
		}

		return $gateway;
	}

	/**
	 * Get the quickpay basket payload
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getBasketPayload(Order $order): array
	{
		// Get the payment methods enabled in Quickpay gateway
		$methods = $order->getGateway()->paymentMethods;

		// If methods array is empty or contains klarna-payments
		//? If empty all available payments methods are enabled, therefore we need to assume that klarna is a possible payment method
		if (!$methods || in_array('klarna-payments', $methods)) {
			return $this->_getKlarnaBasketPayload($order);
		}

		return $this->_getDefaultBasketPayload($order);
	}

	/**
	 * Get the basket payload including negative adjustments (Discounts)
	 * 
	 * @param Order $order
	 * @return array
	 */
	private function _getDefaultBasketPayload(Order $order): array
	{
		//Start empty lines array
		$lines = [];

		//Get taxrate for product lineitems
		$taxrate = Plugin::getInstance()->getTaxes()->getProductTaxRate($order);

		//Get gateway
		$gateway = $order->getGateway();

		//* Lineitems
		//Loop through all lineitems and add them. We multiply with 100 to convert to cents
		foreach ($order->getLineItems() as $lineItem) {

			$event = new BasketLineTotal([
				'order' => $order,
				'lineItem' => $lineItem,
				'total' => Currency::formatAsCurrency($lineItem->total, $order->paymentCurrency, $gateway->convertAmount, false, true),

			]);
			$this->trigger(self::EVENT_BEFORE_BASKET_LINE_TOTAL, $event);

			// Get total amount
			$amount = $event->total;

			// Get the unit price
			$unit = $amount / $lineItem->qty;

			$lines[] = [
				'qty' => $lineItem->qty,
				'item_no' => $lineItem->sku,
				'item_name' => $lineItem->description,
				'item_price' => $unit * 100,
				'vat_rate' => $taxrate
			];
		}

		//* Adjustments
		//Loop through all adjustments and add items like discounts and taxes thats not included in the lineitems
		foreach ($order->adjustments as $adjustment) {

			//If included in the price or shipping (as its added in the shippingPayload), skip
			if ($adjustment->included || $adjustment->type == 'shipping') {
				continue;
			}

			$event = new BasketAdjustmentAmount([
				'order' => $order,
				'adjustment' => $adjustment,
				'amount' => Currency::formatAsCurrency($adjustment->amount, $order->paymentCurrency, $gateway->convertAmount, false, true),

			]);
			$this->trigger(self::EVENT_BEFORE_BASKET_ADJUSTMENT_AMOUNT, $event);
			$amount = $event->amount;

			$lines[] = [
				'qty' => 1,
				'item_no' => $adjustment->id,
				'item_name' => $adjustment->name,
				'item_price' => $amount * 100,
				'vat_rate' => $taxrate
			];
		}

		return $lines;
	}

	/**
	 * Get the basket payload required for klarna to work
	 *
	 * @param Order $order
	 * @return array
	 */
	private function _getKlarnaBasketPayload(Order $order): array
	{
		$lines = [];
		$adjustments = [];
		$taxrate = Plugin::getInstance()->getTaxes()->getProductTaxRate($order);
		$subtract = 0;
		$gateway = $order->getGateway();


		//* Adjustments
		foreach ($order->adjustments as $adjustment) {
			// If adjustment is included, or shipping, skip
			//? Included adjustments are already included in the line items
			//? Shipping is added in the shipping payload
			if ($adjustment->included || $adjustment->type == 'shipping') {
				continue;
			}

			$event = new BasketAdjustmentAmount([
				'order' => $order,
				'adjustment' => $adjustment,
				'amount' => Currency::formatAsCurrency($adjustment->amount, $order->paymentCurrency, $gateway->convertAmount, false, true),

			]);
			$this->trigger(self::EVENT_BEFORE_BASKET_ADJUSTMENT_AMOUNT, $event);
			$amount = $event->amount;

			// If adjustment is negative, add up
			//? Klarna cannot handle negative values, therefore we need to subtract all negative values from the lineitems
			if ($adjustment->amount < 0) {
				$subtract = abs($subtract) + abs($amount);
				continue;
			}

			// Add to adjustments array
			$adjustments[] = [
				'qty' => 1,
				'item_no' => $adjustment->id,
				'item_name' => $adjustment->name,
				'item_price' => $amount * 100,
				'vat_rate' => $taxrate
			];
		}

		//* Lineitems
		foreach ($order->getLineItems() as $lineItem) {

			$event = new BasketLineTotal([
				'order' => $order,
				'lineItem' => $lineItem,
				'total' => Currency::formatAsCurrency($lineItem->total, $order->paymentCurrency, $gateway->convertAmount, false, true),

			]);
			$this->trigger(self::EVENT_BEFORE_BASKET_LINE_TOTAL, $event);
			$amount = $event->total;

			// Subtract negative values from lineitems
			//? We will subtract as much as posible from each item, not going below 0
			if ($subtract > 0) {
				if ($amount >= $subtract) {
					$amount = $amount - $subtract;
					$subtract = 0;
				} else {
					$subtract -= $amount;
					$amount = 0;
				}
			}

			// Get the unit price
			$unit = $amount / $lineItem->qty;

			// Format line item
			$lines[] = [
				'qty' => $lineItem->qty,
				'item_no' => $lineItem->sku,
				'item_name' => $lineItem->description,
				'item_price' => $unit * 100,
				'vat_rate' => $taxrate
			];
		}

		// Merge lines and adjustments
		//? We want the adjustments to be added after the line items
		return array_merge($lines, $adjustments);
	}

	/**
	 * Get the payload for the selected shipping method
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getShippingPayload(Order $order): array
	{
		//Get gateway
		$gateway = $order->getGateway();

		//Get taxrate
		$taxrate = Plugin::getInstance()->getTaxes()->getShippingTaxRate($order);

		$event = new ShippingTotal([
			'order' => $order,
			'total' => Currency::formatAsCurrency($order->totalShippingCost, $order->paymentCurrency, $gateway->convertAmount, false, true),

		]);
		$this->trigger(self::EVENT_BEFORE_SHIPPING_TOTAL, $event);
		$amount = $event->total;


		return [
			'amount' => $amount * 100,
			'vat_rate' => $taxrate
		];
	}
}
