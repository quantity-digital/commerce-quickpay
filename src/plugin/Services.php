<?php

namespace QD\commerce\quickpay\plugin;

use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\services\Api;
use QD\commerce\quickpay\services\Payments;
use QD\commerce\quickpay\services\Subscriptions;
use QD\commerce\quickpay\services\Orders;

trait Services
{
	public function initComponents()
	{
		$this->setComponents([
			'payments' 		=> Payments::class,
			'api'      		=> Api::class,
			'gateway'  		=> Gateway::class,
			'orders'  		=> Orders::class,
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
}

