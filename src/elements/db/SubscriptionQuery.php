<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace QD\commerce\quickpay\elements\db;

use Craft;
use craft\db\Query;
use craft\db\Table as DbTable;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use DateTime;
use QD\commerce\quickpay\base\Table;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\elements\Subscription;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Schema;

/**
 * SubscriptionQuery represents a SELECT SQL statement for subscriptions in a way that is independent of DBMS.
 *
 * @method Subscription[]|array all($db = null)
 * @method Subscription|array|false one($db = null)
 * @method Subscription|array|false nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @doc-path dev/element-queries/subscription-queries.md
 * @replace {element} subscription
 * @replace {elements} subscriptions
 * @replace {twig-method} craft.subscriptions()
 * @replace {myElement} mySubscription
 * @replace {element-class} \craft\commerce\elements\Subscription
 * @supports-status-param
 */
class SubscriptionQuery extends ElementQuery
{
    /**
     * @var int|int[] The user id of the subscriber
     */
    public $userId;

    /**
     * @var int|int[] The subscription plan id
     */
    public $planId;

    /**
     * @var int|int[] The id of the order that the license must be a part of.
     */
    public $orderId;

    /**
     * @var int|int[] Number of trial days for the subscription
     */
    public $trialDays;

    /**
     * @var bool Whether the subscription is currently on trial.
     */
    public $onTrial;

    /**
     * @var DateTime Time of next payment for the subscription
     */
    public $nextPaymentDate;

    /**
     * @var bool Whether the subscription is canceled
     */
    public $isCanceled;

    /**
     * @var bool Whether the subscription is suspended
     */
    public $isSuspended;

    /**
     * @var DateTime The date the subscription ceased to be active
     */
    public $dateSuspended;

    /**
     * @var bool Whether the subscription has started
     */
    public $hasStarted;

    /**
     * @var DateTime The time the subscription was canceled
     */
    public $dateCanceled;

    /**
     * @var DateTime The date the subscription ceased to be active
     */
    public $dateExpired;

    /**
     * @var array
     */
    protected $defaultOrderBy = ['quickpay_subscriptions.dateStarted' => SORT_DESC];

    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Subscription::STATUS_ACTIVE;
            $config['hasStarted'] = true;
            $config['isCanceled'] = false;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'user':
                $this->user($value);
                break;
            case 'plan':
                $this->plan($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function user($value)
    {
        if ($value instanceof User) {
            $this->userId = $value->id;
        } else if ($value !== null) {
            $this->userId = (new Query())
                ->select(['id'])
                ->from([DbTable::USERS])
                ->where(Db::parseParam('username', $value))
                ->column();
        } else {
            $this->userId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscription plan.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function plan($value)
    {
        if ($value instanceof Plan) {
            $this->planId = $value->id;
        } else if ($value !== null) {
            $this->planId = (new Query())
                ->select(['id'])
                ->from([Table::PLANS])
                ->where(Db::parseParam('slug', $value))
                ->column();
        } else {
            $this->planId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts’ IDs.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function userId($value)
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscription plans’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a plan with an ID of 1.
     * | `[1, 2]` | for plans with an ID of 1 or 2.
     * | `['not', 1, 2]` | for plans not with an ID of 1 or 2.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function planId($value)
    {
        $this->planId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order, per its ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with an order with an ID of 1.
     * | `'not 1'` | not with an order with an ID of 1.
     * | `[1, 2]` | with an order with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with an order with an ID of 1 or 2.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function orderId($value)
    {
        $this->orderId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the number of trial days.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function trialDays($value)
    {
        $this->trialDays = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are on trial.
     *
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function onTrial(bool $value = true)
    {
        $this->onTrial = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ next payment dates.
     *
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function nextPaymentDate($value)
    {
        $this->nextPaymentDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are canceled.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isCanceled(bool $value = true)
    {
        $this->isCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ cancellation date.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateCanceled($value)
    {
        $this->dateCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that have started.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function hasStarted(bool $value = true)
    {
        $this->hasStarted = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are suspended.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isSuspended(bool $value = true)
    {
        $this->isSuspended = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ suspension date.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateSuspended($value)
    {
        $this->dateSuspended = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ expiration date.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateExpired($value)
    {
        $this->dateExpired = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ statuses.
     *
     */
    public function status($value)
    {
        return parent::status($value);
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'plan' were set to invalid handles
        if ($this->planId === []) {
            return false;
        }

        $this->joinElementTable('quickpay_subscriptions');
        $this->subQuery->innerJoin(DbTable::USERS . ' users', '[[quickpay_subscriptions.userId]] = [[users.id]]');

        $this->query->select([
            'quickpay_subscriptions.id',
            'quickpay_subscriptions.userId',
            'quickpay_subscriptions.planId',
            'quickpay_subscriptions.orderId',
            'quickpay_subscriptions.subscriptionData',
            'quickpay_subscriptions.trialDays',
            'quickpay_subscriptions.nextPaymentDate',
            'quickpay_subscriptions.isCanceled',
            'quickpay_subscriptions.dateCanceled',
            'quickpay_subscriptions.dateExpired',
            'quickpay_subscriptions.dateStarted',
            'quickpay_subscriptions.hasStarted',
            'quickpay_subscriptions.isSuspended',
            'quickpay_subscriptions.dateSuspended',
            'quickpay_subscriptions.cardLast4',
            'quickpay_subscriptions.cardExpireMonth',
            'quickpay_subscriptions.cardExpireYear',
            'quickpay_subscriptions.cardBrand',
        ]);

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.userId', $this->userId));
        }

        if ($this->planId) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.planId', $this->planId));
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.orderId', $this->orderId));
        }

        if ($this->trialDays) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.trialDays', $this->trialDays));
        }

        if ($this->nextPaymentDate) {
            $this->subQuery->andWhere(Db::parseDateParam('quickpay_subscriptions.nextPaymentDate', $this->nextPaymentDate, '<='));
        }

        if ($this->isCanceled !== null) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.isCanceled', $this->isCanceled, '=', false, Schema::TYPE_BOOLEAN));
        }

        if ($this->dateCanceled) {
            $this->subQuery->andWhere(Db::parseDateParam('quickpay_subscriptions.dateCanceled', $this->dateCanceled));
        }

        if ($this->hasStarted !== null) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.hasStarted', $this->hasStarted, '=', false, Schema::TYPE_BOOLEAN));
        }

        if ($this->isSuspended !== null) {
            $this->subQuery->andWhere(Db::parseParam('quickpay_subscriptions.isSuspended', $this->isSuspended, '=', false, Schema::TYPE_BOOLEAN));
        }

        if ($this->dateSuspended) {
            $this->subQuery->andWhere(Db::parseDateParam('quickpay_subscriptions.dateSuspended', $this->dateSuspended));
        }

        if ($this->dateExpired) {
            $this->subQuery->andWhere(Db::parseDateParam('quickpay_subscriptions.dateExpired', $this->dateExpired));
        }

        if ($this->onTrial === true) {
            $this->subQuery->andWhere($this->_getTrialCondition(true));
        } else if ($this->onTrial === false) {
            $this->subQuery->andWhere($this->_getTrialCondition(false));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        switch ($status) {
            case Subscription::STATUS_ACTIVE:
                return [
                    'quickpay_subscriptions.isCanceled' => '0',
                ];
            case Subscription::STATUS_EXPIRED:
                return [
                    'quickpay_subscriptions.isCanceled' => '1',
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    /**
     * @inheritdoc
     */

    public function anyStatus()
    {
        $this->isSuspended = null;
        $this->hasStarted = null;
        return parent::anyStatus();
    }

    /**
     * Returns the SQL condition to use for trial status.
     *
     * @param bool $onTrial
     * @return mixed
     */
    private function _getTrialCondition(bool $onTrial)
    {
        if ($onTrial) {
            if (Craft::$app->getDb()->getIsPgsql()) {
                return new Expression("NOW() <= [[quickpay_subscriptions.dateStarted]] + [[quickpay_subscriptions.trialDays]] * INTERVAL '1 day'");
            }

            return new Expression('NOW() <= ADDDATE([[quickpay_subscriptions.dateStarted]], [[quickpay_subscriptions.trialDays]])');
        }

        if (Craft::$app->getDb()->getIsPgsql()) {
            return new Expression("NOW() > [[quickpay_subscriptions.dateStarted]] + [[quickpay_subscriptions.trialDays]] * INTERVAL '1 day'");
        }

        return new Expression('NOW() > ADDDATE([[quickpay_subscriptions.dateStarted]], [[quickpay_subscriptions.trialDays]])');
    }
}
