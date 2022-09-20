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
use QD\commerce\quickpay\responses\RefundResponse;
use stdClass;
use yii\base\Exception;

class Payments extends Component
{

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
		$order                = $transaction->getOrder();
		$authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
		$authorizedAmount     = (float)$transaction->amount;
		$amount               = $order->getOutstandingBalance();

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
		$order                = $transaction->getOrder();
		$this->api->setGateway($order->getGateway());

		//multiplied by 100 because industry standard is to save the amount in "cents"
		$amount     = (float)$transaction->amount * 100;
		$response = $this->api->post("/payments/{$transaction->reference}/refund", [
			'amount' => $amount
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

		//TODO: shouldn't this be returned?
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
	 * TODO: how does it find the correct Gateway?
	 * Returns the gatway of the customer?
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
}
