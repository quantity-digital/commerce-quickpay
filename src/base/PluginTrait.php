<?php

namespace QD\commerce\quickpay\base;

use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\services\Api;
use QD\commerce\quickpay\services\Payments;
use QD\commerce\quickpay\services\Plans;
use QD\commerce\quickpay\services\PlanTypes;
use QD\commerce\quickpay\services\Subscriptions;
use QD\commerce\quickpay\services\Orders;

trait PluginTrait
{
	public function initComponents()
	{
		$this->setComponents([
			'payments' 		=> Payments::class,
			'api'      		=> Api::class,
			'gateway'  		=> Gateway::class,
			'orders'  		=> Orders::class,
			'subscriptions' => Subscriptions::class,
			'planTypes' 	=> PlanTypes::class,
			'plans' 		=> Plans::class
		]);
	}

	/**
	 * @return Payments The Payments service
	 */
	public function getPayments()
	{
		return $this->get('payments');
	}

	/**
	 * @return Api The Api service
	 */
	public function getApi()
	{
		return $this->get('api', 'test');
	}

	/**
	 * @return Gateway The Gateway class
	 */
	public function getGetway()
	{
		return $this->get('gateway');
	}

	/**
	 * @return Orders The Order class
	 */
	public function getOrders()
	{
		return $this->get('orders');
	}

	/**
	 * @return Subscriptions The Subscriptions class
	 */
	public function getSubscriptions()
	{
		return $this->get('subscriptions');
	}

	public function getPlanTypes()
	{
		return $this->get('planTypes');
	}

	public function getPlans()
	{
		return $this->get('plans');
	}
}
