<?php

namespace QD\commerce\quickpay\models;

use craft\base\Model;

class PaymentModel extends Model
{

	// Public Properties
	// =========================================================================

	/**
	 * @var int
	 */
	public $orderId;

	/**
	 * @var int
	 */
	public $transactionReference;

	/**
	 * @var string
	 */
	public $paymentId;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['orderId', 'transactionReference'], 'required'],
		];
	}

}
