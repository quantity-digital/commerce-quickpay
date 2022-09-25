<?php

namespace QD\commerce\quickpay\plugin;

use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\services\Api;
use QD\commerce\quickpay\services\Payments;
use QD\commerce\quickpay\services\Orders;

trait Services
{
	/**
	 * Init components
	 *
	 * @return void
	 */
	public function initComponents(): void
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
	public function getPayments(): Payments
	{
		return $this->get('payments');
	}

	/**
	 * @return Api The Api service
	 */
	public function getApi(): Api
	{
		return $this->get('api');
	}

	/**
	 * @return Gateway The Gateway class
	 */
	public function getGetway(): Gateway
	{
		return $this->get('gateway');
	}

	/**
	 * @return Orders The Order class
	 */
	public function getOrders(): Orders
	{
		return $this->get('orders');
	}
}

