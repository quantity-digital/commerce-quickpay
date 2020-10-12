<?php
namespace QD\commerce\quickpay\records;

use craft\db\ActiveRecord;
use craft\records\User;
use QD\commerce\quickpay\base\Table;
use yii\db\ActiveQueryInterface;

class SubscriptionRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SUBSCRIPTIONS;
    }

    /**
     * Return the subscription's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->order->gatewayId;
    }

    /**
     * Return the subscription's user
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['userId' => 'id']);
    }

    /**
     * Return the subscription's plan
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getPlan(): ActiveQueryInterface
    {
        return $this->hasOne(PlanRecord::class, ['planId' => 'id']);
    }
}
