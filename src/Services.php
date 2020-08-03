<?php

namespace QD\commerce\quickpay;

use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\services\Api;
use QD\commerce\quickpay\services\Payments;

trait Services
{
	public function initComponents()
	{
		$this->setComponents([
			'payments' => Payments::class,
			'api'      => Api::class,
			'gateway'  => Gateway::class
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
		return $this->get('api');
	}
}
