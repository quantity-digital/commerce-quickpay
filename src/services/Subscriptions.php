<?php

namespace QD\commerce\quickpay\services;

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\User;
use craft\helpers\Db;
use DateTime;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\models\SubscriptionRequestModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\responses\SubscriptionResponse;
use QD\commerce\quickpay\responses\CaptureResponse;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\services\Gateways;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use Exception;
use QD\commerce\quickpay\events\SubscriptionAuthorizeEvent;
use QD\commerce\quickpay\events\SubscriptionCaptureEvent;
use QD\commerce\quickpay\events\SubscriptionRecurringEvent;
use QD\commerce\quickpay\events\SubscriptionResubscribeEvent;
use QD\commerce\quickpay\events\SubscriptionUnsubscribeEvent;
use QD\commerce\quickpay\gateways\Subscriptions as GatewaysSubscriptions;
use QD\commerce\quickpay\responses\PaymentResponse;
use QD\commerce\quickpay\responses\RefundResponse;
use QD\commerce\quickpay\responses\SubscriptionCaptureResponse;
use Throwable;

class Subscriptions extends Component
{
	/**
	 * Events
	 */
	const EVENT_BEFORE_SAVE_SUBSCRIPTION = 'beforeSaveSubscription';
	const EVENT_AFTER_COMPLETED_SUBSCRIPTION_CAPTURE = 'afterCompletedeSubscriptionCapture';
	const EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE = 'afterFailedSubscriptionCapture';
	const EVENT_AFTER_SUBSCRIPTION_UNSUBSCRIBE = 'afterSubscriptionUnsubscribe';
	const EVENT_AFTER_SUBSCRIPTION_RESUBSCRIBE = 'afterSubscriptionResubscribe';

	const EVENT_BEFORE_SUBSCRIPTION_CAPTURE = 'afterBeforeSubscriptionCapture';
	const EVENT_BEFORE_RECURRING_AUTHORIZE = 'beforeRecurringAuthorize';

	public $api;

	public function init()
	{
		$this->api = Plugin::$plugin->getApi();
	}

	public function getSubscriptionById($orderId)
	{
		return Subscription::find()->anyStatus()->orderId($orderId)->one();
	}

	/**
	 * Initiate the subscription on Quickpay
	 *
	 * @return object
	 */
	public function initiateSubscription(SubscriptionRequestModel $subscriptionRequest)
	{
		$payload = $subscriptionRequest->getPayload();

		$request = $this->api->post('/subscriptions', $payload);

		return $request;
	}

	public function getSubscriptionLink(SubscriptionRequestModel $subscriptionRequest, $request)
	{

		//Create link to subscription (redirect to it)
		$payload = $subscriptionRequest->getLinkPayload();
		$link = $this->api->put('/subscriptions/' . $request->id . '/link', $payload);

		return $link;
	}

	public function intiateSubscriptionFromGateway(Transaction $transaction)
	{
		//Set gateway for API
		$this->api->setGateway($transaction->getGateway());

		$order = $transaction->getOrder();
		$paymentRequest = new SubscriptionRequestModel([
			'order'       => $order,
			'transaction' => $transaction,
		]);

		//Create payment at quickpay
		$request = $this->initiateSubscription($paymentRequest);
		$response = new SubscriptionResponse($request);

		//If subscription wasn't created return with error message
		if (!$response->isSuccessful()) {
			return $response;
		}

		//Get redirect url
		$request = $this->getSubscriptionLink($paymentRequest, $request);

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

	public function createSubscription(User $user, Plan $plan, Order $order, array $subscriptionData = [], array $fieldValues = []): Subscription
	{
		$gateway = $order->getGateway();
		$paymentOrderStatus = Craft::parseEnv($gateway->paymentOrderStatus);

		//This is a payment order, so ignore the create subscription
		if ($order->orderStatusId == $paymentOrderStatus) {
			return false;
		}

		//Create new subscription
		$subscription = new Subscription();
		$subscription->userId = $user->id;
		$subscription->planId = $plan->id;
		$subscription->orderId = $order->id;
		$subscription->trialDays = $plan->trialDays;
		$subscription->subscriptionData = $subscriptionData;
		$subscription->isCanceled = false;
		$subscription->hasStarted = true;
		$subscription->dateStarted = new DateTime('now');
		$subscription->isSuspended = false;

		$transaction = Plugin::getInstance()->getOrders()->getSuccessfulTransactionForOrder($order);

		//If authorized subscription exists, store the data
		if ($transaction) {
			$response = Json::decode($transaction->response, false);
			$metdata = $response->metadata;

			$subscription->quickpayReference = $transaction->reference;
			$subscription->cardExpireYear = $metdata->exp_year;
			$subscription->cardExpireMonth = $metdata->exp_month;
			$subscription->cardLast4 = $metdata->last4;
			$subscription->cardBrand = $metdata->brand;
		}

		$subscription->setFieldValues($fieldValues);
		$event =  new SubscriptionEvent([
			'subscription' => $subscription
		]);
		$this->trigger(self::EVENT_BEFORE_SAVE_SUBSCRIPTION, $event);

		Craft::$app->getElements()->saveElement($event->subscription, false);

		return $subscription;
	}

	public function calculateNextPaymentDate($subscription)
	{
		$subscription->dateStarted = DateTime::createFromFormat('Y-m-j H:i:s', $subscription->dateStarted);
		$dateStarted = $subscription->dateStarted->format('d');

		$monthLength = $subscription->dateStarted->format('t');
		$shift = $monthLength - $dateStarted;

		$intervals = [
			'daily' => '+1 day',
			'weekly' => '+1 week',
			'monthly' => 1,
			'3_months' => 3,
			'6_months' => 6,
			'9_months' => 9,
			'yearly' => 1
		];

		$planInterval = $subscription->plan->planInterval;

		Carbon::useMonthsOverflow(false);
		switch ($planInterval) {
			case 'daily':
				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addDay(1);
				break;

			case 'weekly':
				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addDay(7);
				break;

			case 'yearly':

				if ($dateStarted > 28) {
					$dateObject = Carbon::instance($subscription->nextPaymentDate)->addYearNoOverflow($intervals[$planInterval])->lastOfMonth()->subDay($shift);
					break;
				}

				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addYearNoOverflow($intervals[$planInterval]);
				break;

			default:

				if ($dateStarted > 28) {
					$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($intervals[$planInterval])->lastOfMonth()->subDay($shift);
					break;
				}

				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($intervals[$planInterval]);
				break;
		}

		return $dateObject->setTime($subscription->dateStarted->format('H'), $subscription->dateStarted->format('i'));
	}

	public function cancelSubscription(Subscription $subscription): bool
	{
		$subscription->isCanceled = true;
		$subscription->dateCanceled = Db::prepareDateForDb(new DateTime());
		$subscription->dateExpired = Db::prepareDateForDb($subscription->nextPaymentDate);

		try {
			Craft::$app->getElements()->saveElement($subscription, false);
		} catch (Throwable $exception) {
			Craft::warning('Failed to cancel subscription ' . $subscription->reference . ': ' . $exception->getMessage());

			throw new SubscriptionException(Craft::t('commerce', 'Unable to cancel subscription at this time.'));
		}

		$eventData = new SubscriptionUnsubscribeEvent([
			'subscription' => $subscription,
		]);

		if ($this->hasEventHandlers(self::EVENT_AFTER_SUBSCRIPTION_UNSUBSCRIBE)) {
			$this->trigger(self::EVENT_AFTER_SUBSCRIPTION_UNSUBSCRIBE, $eventData);
		}

		return true;
	}

	public function reactivateSubscription(Subscription $subscription): bool
	{
		$subscription->isCanceled = false;
		$subscription->dateCanceled = null;
		$subscription->dateExpired = null;

		$now = new DateTime();

		if ($now > $subscription->nextPaymentDate) {
			$subscription->dateStarted = $now;
		}

		try {
			Craft::$app->getElements()->saveElement($subscription, false);
		} catch (Throwable $exception) {
			Craft::warning('Failed to reactivate subscription ' . $subscription->reference . ': ' . $exception->getMessage());

			throw new SubscriptionException(Craft::t('commerce', 'Unable to reactivate subscription at this time.'));
		}

		$eventData = new SubscriptionResubscribeEvent([
			'subscription' => $subscription,
		]);

		if ($this->hasEventHandlers(self::EVENT_AFTER_SUBSCRIPTION_RESUBSCRIBE)) {
			$this->trigger(self::EVENT_AFTER_SUBSCRIPTION_RESUBSCRIBE, $eventData);
		}

		return true;
	}

	//Recurring transaction functions
	public function createRecurring($order, $subscription)
	{
		$gateway = $subscription->order->getGateway();
		$this->api->setGateway($gateway);

		$eventData = new SubscriptionRecurringEvent([
			'subscription' => $subscription,
			'amount' => $order->totalPrice,
			'order' => $order
		]);

		if ($this->hasEventHandlers(self::EVENT_BEFORE_RECURRING_AUTHORIZE)) {
			$this->trigger(self::EVENT_BEFORE_RECURRING_AUTHORIZE, $eventData);
		}

		$orderId = $order->reference;
		if (strlen($orderId) < 4) {
			while (strlen($orderId) < 4) {
				$orderId = '0' . $orderId;
			}
		}

		$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order);
		$transaction->status = \craft\commerce\records\Transaction::STATUS_PENDING;
		$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
		$transaction->message = 'Authorizing recurring payment';
		$transaction->amount = $eventData->amount;
,
		$headers = [
			'QuickPay-Callback-Url: ' . UrlHelper::siteUrl('quickpay/callbacks/payments/notify/' . $transaction->hash)
		];

		$response = $this->api->setHeaders($headers)->post("/subscriptions/{$subscription->quickpayReference}/recurring", [
			'amount' => $eventData->amount * 100,
			'order_id' => $orderId,
			'auto_capture' => Craft::parseEnv($gateway->autoCapture)
		]);

		$recurringResponse = new SubscriptionResponse($response);
		$transaction->reference = $recurringResponse->getTransactionReference();
		$transaction->response = $recurringResponse->getData();
		CommercePlugin::getInstance()->transactions->saveTransaction($transaction);

		return $transaction;
	}

	public function captureFromGateway($transaction)
	{
		$order = $transaction->getOrder();

		$gateway = $order->getGateway();
		$this->api->setGateway($gateway);

		$authorizedTransation = Plugin::getInstance()->getOrders()->getSuccessfulTransactionForOrder($order);
		$authorizedAmount     = (int)$transaction->amount;
		$amount               = $order->getOutstandingBalance();

		//Outstanding amount is larger than the authorized value - set amount to be equal to authorized value
		if ($authorizedAmount < $amount) {
			$amount = $authorizedAmount;
		}

		if ($authorizedAmount > $amount) {
			$transaction->amount = $amount;
			$transaction->paymentAmount = $amount;
		}

		$response = Plugin::$plugin->api->post("/payments/{$authorizedTransation->reference}/capture", [
			'amount' => $amount * 100
		]);

		return new CaptureResponse($response);
	}

	public function refundFromGateway(Transaction $transaction): RefundResponse
	{
		$amount     = (int)$transaction->amount * 100;
		$response = Plugin::$plugin->api->post("/payments/{$transaction->reference}/refund", [
			'amount' => $amount
		]);

		return new RefundResponse($response);
	}

	public function getGateway(): GatewaysSubscriptions
	{
		$gateways = new Gateways();
		$quickpayGateway = null;

		foreach ($gateways->getAllCustomerEnabledGateways() as $gateway) {

			if ($gateway instanceof GatewaysSubscriptions) {
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
