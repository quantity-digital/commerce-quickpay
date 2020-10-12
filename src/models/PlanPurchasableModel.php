<?php

namespace QD\commerce\quickpay\models;


use craft\base\Model;

class PlanPurchasableModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $id;
    public $planId;
	public $purchasableId;
	public $purchasableType;
    public $qty;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
     /**
     * @return array
     */
    public function rules()
    {
        $rules = [
            [
                [
                    'pland',
					'purchasableId',
					'purchasableType',
                ], 'required'
            ],
            [['qty'], 'integer', 'min' => 1],
		];

	}
}

?>
