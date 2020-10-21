<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\console\Controller;
use craft\db\Query;
use DateTime;
use Exception;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\Plugin;
use Throwable;

class SubscriptionsCronController extends Controller
{
	protected $allowAnonymous = array('capture');

	public function actionCreateOrder()
	{
		//Should find all subscriptins thats not cancelled, and where next payment is older than current time
		$subscriptions = Subscription::find()->isCanceled(false)->isSuspended(false)->nextPaymentDate(time())->all();

		foreach ($subscriptions as $subscription) {
			$gateway = $subscription->order->getGateway();
			$orderStatus = Craft::parseEnv($gateway->paymentOrderStatus);

			$order = new Order();

			$order->isCompleted = false;
			$order->orderStatusId = $orderStatus;
			$order->email = $subscription->order->email;
			$order->customerId = $subscription->order->customerId;
			$order->billingAddressId = $subscription->order->billingAddressId;
			$order->shippingAddressId = $subscription->order->shippingAddressId;
			$order->gatewayId = $subscription->order->gatewayId;
			$order->currency = $subscription->order->currency;
			$order->paymentCurrency = $subscription->order->paymentCurrency;
			$order->recalculationMode = $subscription->order->recalculationMode;
			$order->shippingMethodHandle = $subscription->order->shippingMethodHandle;
			$order->shippingMethodName = $subscription->order->shippingMethodName;
			$order->subscriptionId = $subscription->id;

			if (!Craft::$app->getElements()->saveElement($order)) {
				var_dump($order->errors);
			}

			$purchasable = $subscription->plan;

			$lineItem = CommercePlugin::getInstance()->getLineItems()->resolveLineItem($order->id, $purchasable->purchasableId, $subscription->subscriptionData);
			$order->addLineItem($lineItem);

			if($this->markAsComplete($order)){
				$subscription->nextPaymentDate = Plugin::getInstance()->getSubscriptions()->calculateNextPaymentDate($subscription);
				Craft::$app->getElements()->saveElement($subscription);
			}
		}
	}

	public function actionAuthorize()
	{
		$gateway = Plugin::getInstance()->getSubscriptions()->getGateway();
		$orderStatusId = Craft::parseEnv($gateway->paymentOrderStatus);
		$orders = Order::find()->isUnpaid()->orderStatusId($orderStatusId)->all();

		foreach($orders as $order){
			$subscription = Subscription::find()->id($order->subscriptionId)->one();

			//If order has been suspended because if failed payment, skip until payment details are updated
			if($subscription->isSuspended){
				continue;
			}

			Plugin::getInstance()->getSubscriptions()->createRecurring($order, $subscription);
		}
	}

	private function markAsComplete($order): bool
    {
        $order->isCompleted = true;
        $order->dateOrdered = new DateTime();

        // Reset estimated address relations
        $order->estimatedShippingAddressId = null;
        $order->estimatedBillingAddressId = null;

        if ($order->reference == null) {
            $referenceTemplate = CommercePlugin::getInstance()->getSettings()->orderReferenceFormat;

            try {
                $order->reference = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $this);
            } catch (Throwable $exception) {
                Craft::error('Unable to generate order completion reference for order ID: ' . $order->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
                throw $exception;
            }
        }

        // Completed orders should no longer recalculate anything by default
        $order->setRecalculationMode('all');

        $success = Craft::$app->getElements()->saveElement($order, false);

        if (!$success) {
            Craft::error(Craft::t('commerce', 'Could not mark order {number} as complete. Order save failed during order completion with errors: {order}',
                ['number' => $order->number, 'order' => json_encode($order->errors)]), __METHOD__);

            return false;
        }

        return true;
    }
}