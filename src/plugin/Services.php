<?php

namespace QD\commerce\quickpay\plugin;

use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\services\Api;
use QD\commerce\quickpay\services\Payments;
use QD\commerce\quickpay\services\Orders;
use QD\commerce\quickpay\services\PaymentsCallbackService;
use QD\commerce\quickpay\services\Taxes;
use QD\commerce\quickpay\services\TransactionService;

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
			'payments' 						=> Payments::class,
			'paymentsCallback' 		=> PaymentsCallbackService::class,
			'api'      						=> Api::class,
			'gateway'  						=> Gateway::class,
			'orders'  						=> Orders::class,
			'taxes'  							=> Taxes::class,
			'transaction' 				=> TransactionService::class,
		]);
	}

	/**
	 * @return Payments
	 */
	public function getPayments(): Payments
	{
		return $this->get('payments');
	}

	/**
	 * @return PaymentsCallbackService
	 */
	public function getPaymentsCallbackService(): PaymentsCallbackService
	{
		return $this->get('paymentsCallback');
	}

	/**
	 * @return Api
	 */
	public function getApi(): Api
	{
		return $this->get('api');
	}

	/**
	 * @return Gateway
	 */
	public function getGetway(): Gateway
	{
		return $this->get('gateway');
	}

	/**
	 * @return Orders
	 */
	public function getOrders(): Orders
	{
		return $this->get('orders');
	}

	/**
	 * @return Taxes
	 */
	public function getTaxes(): Taxes
	{
		return $this->get('taxes');
	}

	/**
	 * @return TransactionService
	 */
	public function getTransactionService(): TransactionService
	{
		return $this->get('transaction');
	}
}
