<?php

namespace QD\commerce\quickpay\services;

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\events\TransactionEvent;
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
use craft\commerce\services\Gateways;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use Exception;
use QD\commerce\quickpay\events\SubscriptionRecurringEvent;
use QD\commerce\quickpay\events\SubscriptionResubscribeEvent;
use QD\commerce\quickpay\events\SubscriptionUnsubscribeEvent;
use QD\commerce\quickpay\gateways\Subscriptions as GatewaysSubscriptions;
use QD\commerce\quickpay\responses\RefundResponse;
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

	const EVENT_AFTER_CAPTURE_TRANSACTION = 'afterRecurringCapture';

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
		$orderStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($gateway->paymentOrderStatus);
		$paymentOrderStatus = $orderStatus->id;

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
		$subscription->nextPaymentDate = new DateTime('now');
		//Adjust next paymentdate
		$subscription->nextPaymentDate = $this->calculateFirstPaymentDate($subscription);

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

	public function calculateNextSubscriptionEndDate($subscription)
	{
		$dateStarted = DateTime::createFromFormat('Y-m-j H:i:s', $subscription->dateStarted);
		$dayStarted = $dateStarted->format('d');

		$monthLength = $dateStarted->format('t');
		$shift = $monthLength - $dayStarted;

		$subscriptionInterval = $subscription->plan->subscriptionInterval;

		Carbon::useMonthsOverflow(false);
		if ($dayStarted > 28) {
			$dateObject = Carbon::instance($subscription->subscriptionEndDate)->addMonthNoOverflow($subscriptionInterval)->lastOfMonth()->subDay($shift);
		}

		if ($dayStarted <= 28) {
			$dateObject = Carbon::instance($subscription->subscriptionEndDate)->addMonthNoOverflow($subscriptionInterval);
		}

		return $dateObject->setTime($dateStarted->format('H'), $dateStarted->format('i'));
	}

	public function calculateFirstPaymentDate($subscription)
	{
		if ($subscription->trialDays) {
			return Carbon::instance($subscription->dateStarted)->addDays($subscription->trialDays);
		}

		if (!$subscription->trialDays) {
			return $this->calculateNextPaymentDate($subscription);
		}
	}

	public function calculateNextPaymentDate($subscription)
	{
		$dateStarted = DateTime::createFromFormat('Y-m-j H:i:s', $subscription->dateStarted);

		$dayStarted = $dateStarted->format('d');

		$monthLength = $dateStarted->format('t');
		$shift = $monthLength - $dayStarted;

		$planInterval = $subscription->plan->planInterval;

		Carbon::useMonthsOverflow(false);
		if ($dayStarted > 28) {
			$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($planInterval)->lastOfMonth()->subDay($shift);
		}

		if ($dayStarted <= 28) {
			$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($planInterval);
		}

		return $dateObject->setTime($dateStarted->format('H'), $dateStarted->format('i'));
	}

	public function cancelSubscription(Subscription $subscription): bool
	{
		$subscription->isCanceled = true;
		$subscription->dateCanceled = Db::prepareDateForDb(new DateTime());
		$subscription->dateExpired = Db::prepareDateForDb($subscription->subscriptionEndDate);

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

		$pending = $this->_checkForPendingTransactions($order);
		if ($pending) {
			return;
		}

		$transaction = CommercePlugin::getInstance()->transactions->createTransaction($order);
		$transaction->status = \craft\commerce\records\Transaction::STATUS_PENDING;
		$transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
		$transaction->message = 'Authorizing recurring payment';
		$transaction->amount = $eventData->amount;

		$headers = [
			'QuickPay-Callback-Url: ' . UrlHelper::siteUrl('quickpay/callbacks/recurring/notify/' . $transaction->hash)
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
		$authorizedAmount     = (float)$transaction->amount;
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

		// Raise 'afterCaptureTransaction' event
		if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
			$this->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, new TransactionEvent([
				'transaction' => $transaction
			]));
		}

		return new CaptureResponse($response);
	}

	public function refundFromGateway(Transaction $transaction): RefundResponse
	{
		$amount     = (float)$transaction->amount * 100;
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

	private function _checkForPendingTransactions(Order $order): bool
	{
		// Get transactions
		$transactions = $order->transactions;

		// If no transactions, return false
		if (!$transactions) {
			return false;
		}

		// Filter array to only authorized
		$authorized = array_filter($transactions, function ($transaction) {
			return $transaction['type'] == 'authorize';
		});

		// Get last authorized
		$lastAuthorized = end($authorized);

		// If last authorized is pending, return true
		if ($lastAuthorized['status'] == 'pending') {
			return true;
		}

		return false;
	}
}
