<?php

namespace QD\commerce\quickpay\services;

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\models\Transaction;
use craft\elements\User;
use craft\helpers\Db;
use DateTime;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\models\SubscriptionRequestModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\responses\SubscriptionResponse;
use craft\commerce\Plugin as CommercePlugin;
use QD\commerce\quickpay\responses\CaptureResponse;
use craft\commerce\records\Transaction as TransactionRecord;
use Exception;
use QD\commerce\quickpay\events\SubscriptionCaptureEvent;
use QD\commerce\quickpay\events\SubscriptionUnsubscribeEvent;
use Throwable;

class Subscriptions extends Component
{
	/**
	 * Events
	 */
	const EVENT_BEFORE_SAVE_SUBSCRIPTION = 'beforeSaveSubscription';
	const EVENT_AFTER_COMPLETED_SUBSCRIPTION_CAPTURE = 'afterCompletedeSubscriptionCapture';
	const EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE = 'afterFailedSubscriptionCapture';
	const EVENT_BEFORE_SUBSCRIPTION_CAPTURE = 'afterBeforeSubscriptionCapture';
	const EVENT_AFTER_SUBSCRIPTION_UNSUBSCRIBE = 'afterAfterSubscriptionUnsubscribe';

	public $api;

	public function init()
	{
		$this->api = Plugin::$plugin->getApi();
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
		$subscription = new Subscription();
		$subscription->userId = $user->id;
		$subscription->planId = $plan->id;
		$subscription->orderId = $order->id;
		$subscription->trialDays = $plan->trialDays;
		$subscription->subscriptionData = $subscriptionData;
		$subscription->isCanceled = false;
		$subscription->hasStarted = true;
		$subscription->isSuspended = false;

		$subscription->setFieldValues($fieldValues);

		$this->trigger(self::EVENT_BEFORE_SAVE_SUBSCRIPTION, new SubscriptionEvent([
			'subscription' => $subscription
		]));

		Craft::$app->getElements()->saveElement($subscription, false);

		return $subscription;
	}

	public function calculateNextPaymentDate($subscription)
	{
		$dayCreated = $subscription->dateCreated->format('d');

		$monthLength = $subscription->dateCreated->format('t');
		$shift = $monthLength - $dayCreated;

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

				if ($dayCreated > 28) {
					$dateObject = Carbon::instance($subscription->nextPaymentDate)->addYearNoOverflow($intervals[$planInterval])->lastOfMonth()->subDay($shift);
					break;
				}

				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addYearNoOverflow($intervals[$planInterval]);
				break;

			default:

				if ($dayCreated > 28) {
					$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($intervals[$planInterval])->lastOfMonth()->subDay($shift);
					break;
				}

				$dateObject = Carbon::instance($subscription->nextPaymentDate)->addMonthNoOverflow($intervals[$planInterval]);
				break;
		}

		return $dateObject->setTime($subscription->dateCreated->format('H'), $subscription->dateCreated->format('i'));
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

	public function captureSubscription(Subscription $subscription)
	{
		$subscriptionData = $subscription->subscriptionData;
		$subscriptionQtys = isset($subscriptionData['qty']) ? $subscriptionData['qty'] : [];

		$plan = $subscription->plan;
		$purchasables = $plan->getPurchasables();
		$qtys = $plan->getQtys();
		$amount = 0;

		foreach ($purchasables as $purchasable) {
			if (isset($subscriptionQtys[$purchasable->id])) {
				$qty = $subscriptionQtys[$purchasable->id];
			} else {
				$qty = $qtys[$purchasable->id];
			}
			$amount += $qty * $purchasable->price;
		}

		$eventData = new SubscriptionCaptureEvent([
			'subscription' => $subscription,
			'amount' => $amount
		]);

		if ($this->hasEventHandlers(self::EVENT_BEFORE_SUBSCRIPTION_CAPTURE)) {
			$this->trigger(self::EVENT_BEFORE_SUBSCRIPTION_CAPTURE, $eventData);
		}

		try {
			$order = $eventData->subscription->order;
			$transaction = Plugin::getInstance()->getOrders()->getSuccessfulTransactionForOrder($order);
			$captureResponse = $this->captureFromGateway($transaction, $eventData->amount);

			if ($captureResponse->isSuccessful()) {
				$eventData->subscription->nextPaymentDate = $this->calculateNextPaymentDate($eventData->subscription);
				$eventData->subscription->isSuspended = false;

				// Allow plugins to be triggered on successfull capture
				if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETED_SUBSCRIPTION_CAPTURE)) {
					$this->trigger(self::EVENT_AFTER_COMPLETED_SUBSCRIPTION_CAPTURE, $eventData);
				}
			}

			if (!$captureResponse->isSuccessful()) {
				$eventData->subscription->isSuspended = true;

				// Allow plugins to be triggered on failed capture
				if ($this->hasEventHandlers(self::EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE)) {
					$this->trigger(self::EVENT_AFTER_FAILED_SUBSCRIPTION_CAPTURE, $eventData);
				}
			}

			Craft::$app->getElements()->saveElement($eventData->subscription, false);
		} catch (Exception $e) {
			//TODO
		}
	}

	public function captureFromGateway(Transaction $authorizedTransation, $amount)
	{
		$order 		= $authorizedTransation->getOrder();
		$parentId = $authorizedTransation->parentId;
		$this->api->setGateway($authorizedTransation->getGateway());

		$response = $this->api->post("/subscriptions/{$authorizedTransation->reference}/recurring", [
			'amount' => $amount * 100,
			'order_id' => time(),
			'auto_capture' => 'true'
		]);

		$captureResponse = new CaptureResponse($response);
		$transaction = new TransactionRecord();

		if ($captureResponse->isSuccessful()) {
			$transaction->status = TransactionRecord::STATUS_SUCCESS;
		} elseif ($captureResponse->isProcessing()) {
			$transaction->status = TransactionRecord::STATUS_PROCESSING;
		} elseif ($captureResponse->isRedirect()) {
			$transaction->status = TransactionRecord::STATUS_REDIRECT;
		} else {
			$transaction->status = TransactionRecord::STATUS_FAILED;
		}

		$transaction->paymentAmount = $amount;
		$transaction->amount = $amount;
		$transaction->type = 'capture';
		$transaction->parentId = $parentId;
		$transaction->response = $captureResponse->getData();
		$transaction->code = $captureResponse->getCode();
		$transaction->reference = $captureResponse->getTransactionReference();
		$transaction->message = $captureResponse->getMessage();
		$transaction->orderId = $order->id;
		$transaction->gatewayId = $order->gatewayId;
		$transaction->paymentCurrency = $order->paymentCurrency;
		$transaction->currency = $order->paymentCurrency;
		$transaction->save(false);

		return $captureResponse;
	}
}
