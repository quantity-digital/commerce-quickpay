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
use QD\commerce\quickpay\events\BasketAdjustmentAmount;
use QD\commerce\quickpay\events\BasketLineTotal;
use QD\commerce\quickpay\events\BasketSave;
use QD\commerce\quickpay\events\ShippingTotal;

class Payments extends Component
{
	//* Events
	const EVENT_BEFORE_BASKET_ADJUSTMENT_AMOUNT = 'beforeBasketAdjustmentAmount';
	const EVENT_BEFORE_BASKET_LINE_TOTAL = 'beforeBasketLineTotal';
	const EVENT_BEFORE_BASKET_SAVE = 'beforeBasketSave';
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
		// $authorizedAmount     = (float)$transaction->paymentAmount;

		//* Set
		// Set gateway for API
		$this->api->setGateway($gateway);

		//* Amount
		// Get amount to be captured
		// TODO: Update to use calculated order total + Add event to allow for custom amount
		//? multiplied by 100 because industry standard is to save the amount in "cents"
		$amount = $transaction->paymentAmount * 100;


		// TODO: Redundant so far, will be used when above is updated
		//Outstanding amount is larger than the authorized value - set amount to be equal to authorized value
		// if ($authorizedAmount < $amount) {
		// 	$amount = $authorizedAmount;
		// }

		// if ($authorizedAmount > $amount) {
		// 	$transaction->amount = $amount;
		// 	$transaction->paymentAmount = $amount;
		// }

		//* Capture request
		// Set payload
		$payload = [
			'amount' => $amount,
		];

		// make request to capture payment
		$response = $this->api->post("/payments/{$authorizedTransation->reference}/capture", $payload);

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

	//* Basket
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
			$lineItemTotal = $event->total;

			// Get the unit price
			//? Quickpay expects to get the unit price, as they add up themselves
			$lineItemUnitPrice = $lineItemTotal / $lineItem->qty;

			$lines[] = [
				'qty' => $lineItem->qty,
				'item_no' => $lineItem->sku,
				'item_name' => $lineItem->description,
				'item_price' => (int) $lineItemUnitPrice * 100,
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
			$adjustmentAmount = $event->amount;

			$lines[] = [
				'qty' => 1,
				'item_no' => $adjustment->id,
				'item_name' => $adjustment->name,
				'item_price' => (int) $adjustmentAmount * 100,
				'vat_rate' => $taxrate
			];
		}

		return $lines;
	}

	// Klarna
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
		$gateway = $order->getGateway();

		// The total value of negative adjustments that should be subtracted from the line items
		//? Klarna cannot deal with negative adjustment values, therefor we need to subtract the value from the lineitems
		$subtract = 0;


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
			$adjustmentAmount = $event->amount;

			// If adjustment is negative, add up
			//? Klarna cannot handle negative values, therefore we need to subtract all negative values from the lineitems
			if ($adjustmentAmount < 0) {
				$subtract = abs($subtract) + abs($adjustmentAmount);
				continue;
			}

			// Add to adjustments array
			$adjustments[] = [
				'qty' => 1,
				'item_no' => $adjustment->id,
				'item_name' => $adjustment->name,
				'item_price' => (int) $adjustmentAmount * 100,
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
			$lineItemTotal = $event->total;

			// Subtract negative values from lineitems
			//? We will subtract as much as posible from each item, not going below 0
			['newItemAmount' => $lineItemTotal, 'amountLeftToSubtract' => $subtract] = $this->_subtractAdjustmentAmount($lineItemTotal, $subtract);

			// Get the unit price
			//? Quickpay expects to get the unit price, as they add up themselves
			$lineItemUnitPrice = $lineItemTotal / $lineItem->qty;

			// Format line item
			$lines[] = [
				'qty' => $lineItem->qty,
				'item_no' => $lineItem->sku,
				'item_name' => $lineItem->description,
				'item_price' => (int) $lineItemUnitPrice * 100,
				'vat_rate' => $taxrate
			];
		}

		// Event to handle any additional adjustments before save
		$event = new BasketSave([
			'order' => $order,
			'basket' => array_merge($lines, $adjustments),
			'shipping' => $this->getShippingPayload($order)

		]);
		$this->trigger(self::EVENT_BEFORE_BASKET_SAVE, $event);

		return $event->basket;
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


	/**
	 * Handle subtracting negative adjustment amounts from line items, not going below 0
	 * ? Used in the generation of a Klarna basket payload
	 * 
	 * @param float $itemAmount
	 * @param float $amountToSubtract
	 * @return array
	 */
	private function _subtractAdjustmentAmount(float $itemAmount, float $amountToSubtract): array
	{
		if ($amountToSubtract <= 0) {
			// We don't need to subtract a negative amount
			return [
				'newItemAmount' => $itemAmount,
				'amountLeftToSubtract' => $amountToSubtract,
			];
		}

		if ($itemAmount >= $amountToSubtract) {
			// If the line item can contain the full adjustment, apply in full and set adjustment to 0
			return [
				'newItemAmount' => $itemAmount - $amountToSubtract,
				'amountLeftToSubtract' => (float)0,
			];
		} else {
			// If the line item can't contain the full adjustment, apply until 0, and return the remaining amount to subtract
			return [
				'newItemAmount' => (float)0,
				'amountLeftToSubtract' => $amountToSubtract - $itemAmount,
			];
		}
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
