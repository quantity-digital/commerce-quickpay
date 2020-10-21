<?php

namespace QD\commerce\quickpay\elements;

use Carbon\Carbon;
use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\records\Transaction;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\gql\TypeManager;
use craft\gql\types\DateTime as TypesDateTime;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use DateTime;
use Exception;
use QD\commerce\quickpay\base\Table;
use QD\commerce\quickpay\elements\db\SubscriptionQuery;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\records\SubscriptionRecord;
use yii\base\InvalidConfigException;

class Subscription extends Element
{
	/**
	 * @var string
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * @var string
	 */
	const STATUS_EXPIRED = 'expired';

	/**
	 * @var string
	 */
	const STATUS_SUSPENDED = 'suspended';


	/**
	 * @var int User id
	 */
	public $userId;

	/**
	 * @var int Plan id
	 */
	public $planId;

	/**
	 * @var int|null Order id
	 */
	public $orderId;

	/**
	 * @var int Trial days granted
	 */
	public $trialDays;

	/**
	 * @var DateTime Date of next payment
	 */
	public $nextPaymentDate;

	/**
	 * @var bool Whether the subscription is canceled
	 */
	public $isCanceled;

	/**
	 * @var DateTime Time when subscription was canceled
	 */
	public $dateCanceled;

	/**
	 * @var DateTime Time when subscription expired
	 */
	public $dateExpired;

	/**
	 * @var bool Whether the subscription has started
	 */
	public $hasStarted;

	/**
	 * @var bool Whether the subscription is on hold due to payment issues
	 */
	public $isSuspended;

	/**
	 * @var DateTime Time when subscription was put on hold
	 */
	public $dateSuspended;

	/**
	 * @var Plan
	 */
	private $_plan;

	/**
	 * @var User
	 */
	private $_user;

	/**
	 * @var Order
	 */
	private $_order;

	/**
	 * @var array The subscription data from gateway
	 */
	public $_subscriptionData;

	public $cardExpireYear;

	public $cardExpireMonth;

	public $cardLast4;

	public $cardBrand;

	public $quickpayReference;

	/**
	 * @var DateTime Time when subscription expired
	 */
	public $dateStarted;



	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Subscription');
	}

	/**
	 * @inheritdoc
	 */
	public static function lowerDisplayName(): string
	{
		return Craft::t('commerce', 'subscription');
	}

	/**
	 * @inheritdoc
	 */
	public static function pluralDisplayName(): string
	{
		return Craft::t('commerce', 'Subscriptions');
	}

	/**
	 * @inheritdoc
	 */
	public static function pluralLowerDisplayName(): string
	{
		return Craft::t('commerce', 'subscriptions');
	}

	/**
	 * @return null|string
	 */
	public function __toString()
	{
		return (string)$this->getPlan();
	}

	/**
	 * Returns whether this subscription can be reactivated.
	 *
	 * @return bool
	 * @throws InvalidConfigException if gateway misconfigured
	 */
	public function canReactivate()
	{
		return $this->isCanceled;
	}

	/**
	 * @inheritdoc
	 */
	public function getFieldLayout()
	{
		return Craft::$app->getFields()->getLayoutByType(static::class);
	}

	/**
	 * Returns the subscription plan for this subscription
	 *
	 * @return PlanInterface
	 */
	public function getPlan()
	{
		if (null === $this->_plan) {
			$this->_plan = Plugin::getInstance()->getPlans()->getPlanById($this->planId);
		}

		return $this->_plan;
	}

	/**
	 * Returns the User that is subscribed.
	 *
	 * @return User
	 */
	public function getSubscriber(): User
	{
		if (null === $this->_user) {
			$this->_user = Craft::$app->getUsers()->getUserById($this->userId);
		}

		return $this->_user;
	}

	/**
	 * @return array
	 */
	public function getSubscriptionData(): array
	{
		return $this->_subscriptionData;
	}

	/**
	 *
	 * @param string|array $data
	 */
	public function setSubscriptionData($data)
	{
		$data = Json::decodeIfJson($data);

		$this->_subscriptionData = $data;
	}

	/**
	 * Returns the datetime of trial expiry.
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	public function getTrialExpires()
	{

		return Carbon::parse($this->dateStarted)->addDay($this->trialDays)->format('Y-m-d H:i');
	}

	/**
	 * Returns the next payment amount with currency code as a string.
	 *
	 * @return string
	 * @throws InvalidConfigException
	 */
	public function getNextPaymentAmount(): string
	{
		return $this->getGateway()->getNextPaymentAmount($this);
	}

	/**
	 * Returns the order that included this subscription, if any.
	 *
	 * @return null|Order
	 */
	public function getOrder()
	{
		if ($this->_order) {
			return $this->_order;
		}

		if ($this->orderId) {
			return $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
		}

		return null;
	}

	/**
	 * @return string
	 */
	public function getPlanName(): string
	{
		return (string)$this->getPlan();
	}

	/**
	 * @inheritdoc
	 */
	public function getCpEditUrl(): string
	{
		return UrlHelper::cpUrl('commerce-quickpay/subscriptions/' . $this->id);
	}

	/**
	 * Returns the link for editing the order that purchased this license.
	 *
	 * @return string
	 */
	public function getOrderEditUrl(): string
	{
		if ($this->orderId) {
			return UrlHelper::cpUrl('commerce/orders/' . $this->orderId);
		}

		return '';
	}

	/**
	 * Returns an array of all payments for this subscription.
	 *
	 * @return SubscriptionPayment[]
	 * @throws InvalidConfigException
	 */
	public function getAllPayments()
	{
		$orders = Order::find()->subscriptionId(287)->all();

		$payments = [];

		foreach ($orders as $order) {
			$transactions = $order->getTransactions();
			foreach ($transactions as $transaction) {
				if ($transaction->type === Transaction::TYPE_CAPTURE) {
					$payments[] = $transaction;
				}
			}
		}

		return $payments;
	}

	/**
	 * @return null|string
	 */
	public function getName()
	{
		return Craft::t('commerce', 'Subscription to “{plan}”', ['plan' => $this->getPlanName()]);
	}

	/**
	 * @inheritdoc
	 */
	public static function hasStatuses(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getStatus()
	{
		if ($this->isCanceled) {
			return self::STATUS_EXPIRED;
		}

		if ($this->isSuspended) {
			return self::STATUS_SUSPENDED;
		}

		return self::STATUS_ACTIVE;
	}


	/**
	 * @inheritdoc
	 */
	public static function defineSources(string $context = null): array
	{
		$plans = Plugin::getInstance()->getPlans()->getAllPlans();

		$planIds = [];

		foreach ($plans as $plan) {
			$planIds[] = $plan['id'];
		}


		$sources = [
			'*' => [
				'key' => '*',
				'label' => Craft::t('commerce', 'All active subscriptions'),
				'criteria' => ['planId' => $planIds],
				'defaultSort' => ['dateStarted', 'desc']
			]
		];

		$sources[] = ['heading' => Craft::t('commerce', 'Subscription plans')];

		foreach ($plans as $plan) {
			$key = 'plan:' . $plan['id'];

			$sources[$key] = [
				'key' => $key,
				'label' => $plan['title'],
				'data' => [
					'handle' => $plan['slug']
				],
				'criteria' => ['planId' => $plan['id']]
			];
		}

		$sources[] = ['heading' => Craft::t('commerce', 'Subscriptions on hold')];

		$criteriaPaymentIssue = ['isSuspended' => true, 'hasStarted' => true];
		$sources[] = [
			'key' => 'carts:payment-issue',
			'label' => Craft::t('commerce', 'Payment issues'),
			'criteria' => $criteriaPaymentIssue,
			'defaultSort' => ['quickpay_subscriptions.dateUpdated', 'desc'],
		];

		$criteriaUnsubscribed = ['isCanceled' => true, 'hasStarted' => true];
		$sources[] = [
			'key' => 'carts:unsubscribed',
			'label' => Craft::t('commerce', 'Unsubscribed'),
			'criteria' => $criteriaUnsubscribed,
			'defaultSort' => ['quickpay_subscriptions.dateUpdated', 'desc'],
		];

		return $sources;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasContent(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public static function eagerLoadingMap(array $sourceElements, string $handle)
	{
		$sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

		if ($handle === 'subscriber') {
			$map = (new Query())
				->select('id as source, userId as target')
				->from(Table::SUBSCRIPTIONS)
				->where(['in', 'id', $sourceElementIds])
				->all();

			return [
				'elementType' => User::class,
				'map' => $map
			];
		}

		return parent::eagerLoadingMap($sourceElements, $handle);
	}

	/**
	 * @inheritdoc
	 */
	public function setEagerLoadedElements(string $handle, array $elements)
	{
		if ($handle === 'order') {
			$this->_order = $elements[0] ?? null;

			return;
		}

		if ($handle === 'subscriber') {
			$this->_user = $elements[0] ?? null;

			return;
		}

		parent::setEagerLoadedElements($handle, $elements);
	}

	/**
	 * @inheritdoc
	 */
	public function defineRules(): array
	{
		$rules = parent::defineRules();

		$rules[] = [['userId', 'planId', 'orderId', 'subscriptionData'], 'required'];

		return $rules;
	}

	/**
	 * @inheritdocs
	 */
	public static function statuses(): array
	{
		return [
			self::STATUS_ACTIVE => Craft::t('commerce', 'Active'),
			self::STATUS_EXPIRED => Craft::t('commerce', 'Canceled'),
			self::STATUS_SUSPENDED => Craft::t('commerce-quickpay', 'Payment issues')
		];
	}

	/**
	 * @inheritdoc
	 */
	public function datetimeAttributes(): array
	{
		$attributes = parent::datetimeAttributes();
		$attributes[] = 'nextPaymentDate';
		$attributes[] = 'dateExpired';
		$attributes[] = 'dateCanceled';
		$attributes[] = 'dateSuspended';
		return $attributes;
	}

	/**
	 * @inheritdoc
	 * @return SubscriptionQuery The newly created [[SubscriptionQuery]] instance.
	 */
	public static function find(): ElementQueryInterface
	{
		return new SubscriptionQuery(static::class);
	}

	/**
	 * @inheritdoc
	 */
	public function afterSave(bool $isNew)
	{
		if (!$isNew) {
			$subscriptionRecord = SubscriptionRecord::findOne($this->id);

			if (!$subscriptionRecord) {
				throw new InvalidConfigException('Invalid subscription id: ' . $this->id);
			}
		} else {
			$subscriptionRecord = new SubscriptionRecord();
			$subscriptionRecord->id = $this->id;
		}

		$subscriptionRecord->planId = $this->planId;
		$subscriptionRecord->subscriptionData = $this->subscriptionData;
		$subscriptionRecord->isCanceled = $this->isCanceled;
		$subscriptionRecord->dateCanceled = $this->dateCanceled;
		$subscriptionRecord->dateExpired = $this->dateExpired;
		$subscriptionRecord->hasStarted = $this->hasStarted;
		$subscriptionRecord->dateStarted = $this->dateStarted;
		$subscriptionRecord->isSuspended = $this->isSuspended;
		$subscriptionRecord->dateSuspended = $this->dateSuspended;
		$subscriptionRecord->quickpayReference = $this->quickpayReference;
		$subscriptionRecord->cardExpireYear = $this->cardExpireYear;
		$subscriptionRecord->cardExpireMonth = $this->cardExpireMonth;
		$subscriptionRecord->cardLast4 = $this->cardLast4;
		$subscriptionRecord->cardBrand = $this->cardBrand;

		// We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
		$subscriptionRecord->dateUpdated = $this->dateUpdated;
		$subscriptionRecord->dateCreated = $this->dateCreated;

		// Some properties of the subscription are immutable
		if ($isNew) {
			$subscriptionRecord->orderId = $this->orderId;
			$subscriptionRecord->trialDays = $this->trialDays;
			$subscriptionRecord->userId = $this->userId;
			$subscriptionRecord->nextPaymentDate = $this->calculateFirstPaymentDate($this->trialDays);
		}

		if (!$isNew) {
			$subscriptionRecord->nextPaymentDate = $this->nextPaymentDate;
		}

		$subscriptionRecord->save(false);

		parent::afterSave($isNew);
	}

	public function calculateFirstPaymentDate()
	{
		return Carbon::parse($this->dateStarted)->addDay($this->trialDays);
	}

	public static function getFieldDefinitions(): array
	{
		return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
			'dateStarted' => [
				'name' => 'dateUpdated',
				'type' => TypesDateTime::getType(),
				'description' => 'The date the element was last updated.'
			],
		]), self::getName());
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineTableAttributes(): array
	{
		return [
			'title' => ['label' => Craft::t('commerce', 'Subscription plan')],
			'subscriber' => ['label' => Craft::t('commerce', 'Subscribing user')],
			'dateCanceled' => ['label' => Craft::t('commerce', 'Cancellation date')],
			'dateStarted' => ['label' => Craft::t('commerce', 'Subscription date')],
			'nextPaymentDate' => ['label' => Craft::t('commerce', 'Next paymentdate')],
			'dateExpired' => ['label' => Craft::t('commerce', 'Expiry date')],
			'trialExpires' => ['label' => Craft::t('commerce', 'Trial expiry date')],
			'orderLink' => ['label' => Craft::t('commerce', 'Order')]
		];
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineDefaultTableAttributes(string $source): array
	{
		$attributes = [];

		$attributes[] = 'subscriber';
		$attributes[] = 'orderLink';
		$attributes[] = 'dateStarted';

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSearchableAttributes(): array
	{
		return [
			'subscriber',
			'plan',
		];
	}

	/**
	 * @inheritdoc
	 */
	protected function tableAttributeHtml(string $attribute): string
	{
		switch ($attribute) {
			case 'plan':
				return $this->getPlanName();

			case 'subscriber':
				$subscriber = $this->getSubscriber();
				$url = $subscriber->getCpEditUrl();

				return '<a href="' . $url . '">' . $subscriber . '</a>';

			case 'orderLink':
				$url = $this->getOrderEditUrl();

				return $url ? '<a href="' . $url . '">' . Craft::t('commerce', 'View order') . '</a>' : '';

			default: {
					return parent::tableAttributeHtml($attribute);
				}
		}
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSortOptions(): array
	{
		return [
			[
				'label' => Craft::t('commerce', 'Subscription date'),
				'orderBy' => 'quickpay_subscriptions.dateStarted',
				'attribute' => 'dateStarted',
				'defaultDir' => 'desc',
			],
			[
				'label' => Craft::t('commerce', 'Subscription date'),
				'orderBy' => 'quickpay_subscriptions.nextPaymentDate',
				'attribute' => 'nextPaymentDate',
				'defaultDir' => 'desc',
			],
			[
				'label' => Craft::t('app', 'ID'),
				'orderBy' => 'elements.id',
				'attribute' => 'id',
			],
		];
	}


	/**
	 * @inheritdoc
	 */
	protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute)
	{
		switch ($attribute) {
			case 'subscriber':
				$elementQuery->andWith('subscriber');
				break;
			case 'orderLink':
				$elementQuery->andWith('order');
				break;
			default:
				parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
		}
	}
}
