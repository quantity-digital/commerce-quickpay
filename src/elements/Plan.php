<?php

namespace QD\commerce\quickpay\elements;

use Craft;
use Exception;
use yii\base\InvalidConfigException;
use craft\commerce\Plugin as Commerce;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\elements\db\ElementQueryInterface;
use craft\elements\actions\Delete;
use craft\events\CancelableEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;
use QD\commerce\quickpay\elements\db\PlanQuery;
use QD\commerce\quickpay\events\CustomizePlanSnapshotDataEvent;
use QD\commerce\quickpay\events\CustomizePlanSnapshotFieldsEvent;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\records\PlanRecord;
use QD\commerce\quickpay\records\PlanPurchasableRecord;

class Plan extends Purchasable
{
	// Constants
	// =========================================================================
	const STATUS_LIVE = 'live';
	const STATUS_PENDING = 'pending';
	const STATUS_EXPIRED = 'expired';

	const EVENT_BEFORE_CAPTURE_PLAN_SNAPSHOT = 'beforeCapturePlanSnapshot';
	const EVENT_AFTER_CAPTURE_PLAN_SNAPSHOT = 'afterCapturePlanSnapshot';
	const EVENT_BEFORE_SUBSCRIPTION_CREATE = 'beforeLineItemSubscriptionCreate';

	// Public Properties
	// =========================================================================
	public $id;
	public $typeId;
	public $taxCategoryId;
	public $shippingCategoryId;
	public $postDate;
	public $expiryDate;
	public $sku;
	public $price;
	public $planInterval;
	public $trialDays;

	// Private Properties
	// =========================================================================
	private $_planType;
	private $_purchasables;
	private $_purchasableIds;
	private $_qtys;

	// Static Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return Craft::t('commerce-quickpay', 'Plan');
	}

	public static function lowerDisplayName(): string
	{
		return Craft::t('commerce-quickpay', 'plan');
	}

	public static function pluralDisplayName(): string
	{
		return Craft::t('commerce-quickpay', 'Plans');
	}

	public static function pluralLowerDisplayName(): string
	{
		return Craft::t('commerce-quickpay', 'plans');
	}

	public function __toString(): string
	{
		return (string)$this->title;
	}

	public function getName()
	{
		return $this->title;
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
	public static function hasTitles(): bool
	{
		return true;
	}

	public static function hasUris(): bool
	{
		return true;
	}

	public static function hasStatuses(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public static function isLocalized(): bool
	{
		return true;
	}

	public static function defineSources(string $context = null): array
	{
		if ($context === 'index') {
			$planTypes = Plugin::$plugin->getPlanTypes()->getAllPlanTypes();
			$editable = true;
		} else {
			$planTypes = Plugin::$plugin->getPlanTypes()->getAllPlanTypes();
			$editable = false;
		}

		$planTypeIds = [];

		foreach ($planTypes as $planType) {
			$planTypeIds[] = $planType->id;
		}

		$sources = [
			[
				'key' => '*',
				'label' => Craft::t('commerce-quickpay', 'All plans'),
				'criteria' => [
					'typeId' => $planTypeIds,
					'editable' => $editable
				],
				'defaultSort' => ['postDate', 'desc']
			]
		];

		$sources[] = ['heading' => Craft::t('commerce-quickpay', 'Plan Types')];

		foreach ($planTypes as $planType) {
			$key = 'planType:' . $planType->id;
			$canEditPlans = Craft::$app->getUser()->checkPermission('commerce-quickpay-managePlanType:' . $planType->id);

			$sources[$key] = [
				'key' => $key,
				'label' => $planType->name,
				'data' => [
					'handle' => $planType->handle,
					'editable' => $canEditPlans
				],
				'criteria' => ['typeId' => $planType->id, 'editable' => $editable]
			];
		}

		return $sources;
	}

	protected static function defineActions(string $source = null): array
	{
		$actions = [];

		$actions[] = Craft::$app->getElements()->createAction([
			'type' => Delete::class,
			'confirmationMessage' => Craft::t('commerce-quickpay', 'Are you sure you want to delete the selected plans?'),
			'successMessage' => Craft::t('commerce-quickpay', 'Plans deleted.'),
		]);

		return $actions;
	}

	public function getStatuses(): array
	{
		return [
			self::STATUS_LIVE => Craft::t('commerce-quickpay', 'Live'),
			self::STATUS_PENDING => Craft::t('commerce-quickpay', 'Pending'),
			self::STATUS_EXPIRED => Craft::t('commerce-quickpay', 'Expired'),
			self::STATUS_DISABLED => Craft::t('commerce-quickpay', 'Disabled')
		];
	}

	public function getIsAvailable(): bool
	{
		return $this->getStatus() === static::STATUS_LIVE;
	}

	public function getStatus()
	{
		$status = parent::getStatus();

		if ($status === self::STATUS_ENABLED && $this->postDate) {
			$currentTime = DateTimeHelper::currentTimeStamp();
			$postDate = $this->postDate->getTimestamp();
			$expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

			if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
				return self::STATUS_LIVE;
			}

			if ($postDate > $currentTime) {
				return self::STATUS_PENDING;
			}

			return self::STATUS_EXPIRED;
		}

		return $status;
	}

	public function rules(): array
	{
		$rules = parent::rules();

		$rules[] = [['typeId', 'purchasableIds', 'qtys'], 'required'];
		$rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

		return $rules;
	}

	public static function find(): ElementQueryInterface
	{
		return new PlanQuery(static::class);
	}

	public function datetimeAttributes(): array
	{
		$attributes = parent::datetimeAttributes();
		$attributes[] = 'postDate';
		$attributes[] = 'expiryDate';

		return $attributes;
	}

	public function getIsEditable(): bool
	{
		if ($this->getType()) {
			$id = $this->getType()->id;

			return Craft::$app->getUser()->checkPermission('commerce-quickpay-managePlanType:' . $id);
		}

		return false;
	}

	public function getCpEditUrl()
	{
		$planType = $this->getType();

		if ($planType) {
			return UrlHelper::cpUrl('commerce-quickpay/plans/' . $planType->handle . '/' . $this->id);
		}

		return null;
	}

	public function getProduct()
	{
		return $this;
	}

	public function getFieldLayout()
	{
		$planType = $this->getType();

		return $planType ? $planType->getPlanFieldLayout() : null;
	}

	public function getUriFormat()
	{
		$planTypeSiteSettings = $this->getType()->getSiteSettings();

		if (!isset($planTypeSiteSettings[$this->siteId])) {
			throw new InvalidConfigException('Plan’s type (' . $this->getType()->id . ') is not enabled for site ' . $this->siteId);
		}

		return $planTypeSiteSettings[$this->siteId]->uriFormat;
	}

	public function getType()
	{
		if ($this->_planType) {
			return $this->_planType;
		}



		return $this->typeId ? $this->_planType = Plugin::$plugin->getPlanTypes()->getPlanTypeById($this->typeId) : null;
	}

	public function getTaxCategory()
	{
		if ($this->taxCategoryId) {
			return Commerce::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
		}

		return null;
	}

	public function getShippingCategory()
	{
		if ($this->shippingCategoryId) {
			return Commerce::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
		}

		return null;
	}

	// Events
	// -------------------------------------------------------------------------

	public function beforeSave(bool $isNew): bool
	{
		if ($this->enabled && !$this->postDate) {
			// Default the post date to the current date/time
			$this->postDate = DateTimeHelper::currentUTCDateTime();
		}

		return parent::beforeSave($isNew);
	}

	public function afterSave(bool $isNew)
	{
		if (!$isNew) {
			$planRecord = PlanRecord::findOne($this->id);

			if (!$planRecord) {
				throw new Exception('Invalid plan id: ' . $this->id);
			}
		} else {
			$planRecord = new PlanRecord();
			$planRecord->id = $this->id;
		}

		$planRecord->postDate = $this->postDate;
		$planRecord->expiryDate = $this->expiryDate;
		$planRecord->typeId = $this->typeId;
		$planRecord->taxCategoryId = $this->taxCategoryId;
		$planRecord->shippingCategoryId = $this->shippingCategoryId;
		$planRecord->price = $this->price;
		$planRecord->title = $this->title;
		$planRecord->slug = $this->slug;
		$planRecord->planInterval = $this->planInterval;
		$planRecord->trialDays = $this->trialDays;

		// Generate SKU if empty
		if (empty($this->sku)) {
			try {
				$planType = Plugin::$plugin->planTypes->getPlanTypeById($this->typeId);
				$this->sku = Craft::$app->getView()->renderObjectTemplate($planType->skuFormat, $this);
			} catch (\Exception $e) {
				$this->sku = '';
			}
		}

		$planRecord->sku = $this->sku;

		$planRecord->save(false);

		return parent::afterSave($isNew);
	}

	// Implement Purchasable
	// =========================================================================

	public function getPurchasableId(): int
	{
		return $this->id;
	}

	public function getSnapshot(): array
	{
		$data = [];

		$data['type'] = self::class;

		// Default Plan custom field handles
		$planFields = [];
		$planFieldsEvent = new CustomizePlanSnapshotFieldsEvent([
			'plan' => $this,
			'fields' => $planFields,
		]);

		// Allow plugins to modify fields to be fetched
		if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_PLAN_SNAPSHOT)) {
			$this->trigger(self::EVENT_BEFORE_CAPTURE_PLAN_SNAPSHOT, $planFieldsEvent);
		}

		// Capture specified Plan field data
		$planFieldData = $this->getSerializedFieldValues($planFieldsEvent->fields);
		$planDataEvent = new CustomizePlanSnapshotDataEvent([
			'plan' => $this,
			'fieldData' => $planFieldData,
		]);

		// Allow plugins to modify captured Plan data
		if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_PLAN_SNAPSHOT)) {
			$this->trigger(self::EVENT_AFTER_CAPTURE_PLAN_SNAPSHOT, $planDataEvent);
		}

		$data['fields'] = $planDataEvent->fieldData;

		//$data['productId'] = $this->id;

		return array_merge($this->getAttributes(), $data);
	}

	public function getPrice(): float
	{
		//If override price is set
		if ($this->price) {
			return $this->price;
		}

		//If no override price is set, calculate from purchasables
		$price = 0;
		$qtys = $this->getQtys();
		foreach ($this->getPurchasables() as $purchable) {
			$price += $purchable->price * $qtys[$purchable->id];
		}

		return $price;
	}

	public function getSku(): string
	{
		return $this->sku;
	}

	public function getDescription(): string
	{
		return $this->title;
	}

	public function getTaxCategoryId(): int
	{
		return $this->taxCategoryId;
	}

	public function getShippingCategoryId(): int
	{
		return $this->shippingCategoryId;
	}

	public function hasFreeShipping(): bool
	{
		return true;
	}

	public function getIsPromotable(): bool
	{
		return true;
	}

	public function hasStock(): bool
	{
		return $this->getStock() > 0;
	}

	public function getStock()
	{
		$stock = [];

		$qtys = $this->getQtys();

		foreach ($this->getPurchasables() as $purchasable) {
			$qty = $qtys[$purchasable->id];

			if (method_exists($purchasable, 'availableQuantity')) {
				$stock[] = floor($purchasable->availableQuantity() / $qty);
			} elseif (property_exists($purchasable, 'stock')) {
				$stock[] = $purchasable->hasUnlimitedStock ? PHP_INT_MAX : floor($purchasable->stock / $qty);
			} else {
				// assume not stock or quantity means unlimited
				$stock[] = PHP_INT_MAX;
			}
		}
		if (count($stock)) {
			return min($stock);
		}

		return 0;
	}

	public function getPurchasables()
	{
		if (null === $this->_purchasables) {
			foreach ($this->getPurchasableIds() as $id) {
				$this->_purchasables[] = Craft::$app->getElements()->getElementById($id);
			}
		}

		return $this->_purchasables;
	}

	public function getPurchasableIds(): array
	{
		if (null === $this->_purchasableIds) {

			$purchasableIds = [];

			foreach ($this->_getPlanPurchasables() as $row) {
				$purchasableIds[] = $row['purchasableId'];
			}

			$this->_purchasableIds = $purchasableIds;
		}

		return $this->_purchasableIds;
	}

	public function setPurchasableIds(array $purchasableIds)
	{
		$this->_purchasableIds = array_unique($purchasableIds);
	}

	public function getQtys()
	{
		if (null === $this->_qtys) {

			$this->_qtys = [];

			foreach ($this->_getPlanPurchasables() as $row) {
				$this->_qtys[$row['purchasableId']] = $row['qty'];
			}
		}

		return $this->_qtys;
	}

	public function setQtys($qtys)
	{
		$this->_qtys = is_array($qtys) ? $qtys : [];
	}

	public function populateLineItem(LineItem $lineItem)
	{
		$errors = [];

		if ($lineItem->purchasable === $this) {

			if (isset($lineItem->snapshot['options']['qty'])) {
				$price = 0;
				$qtys = $lineItem->snapshot['options']['qty'];

				foreach ($this->getPurchasables() as $purchable) {
					$price += $purchable->price * $qtys[$purchable->id];
				}

				$lineItem->price = $price;
				$lineItem->salePrice = $price;
			}
		}

		if ($errors) {
			$cart = Commerce::getInstance()->getCarts()->getCart();
			$cart->addErrors($errors);
			Craft::$app->getSession()->setError(implode(',', $errors));
		}
	}

	/**
	 * Create a subscription with the plans in the cart
	 *
	 * @inheritdoc
	 */
	public function afterOrderComplete(Order $order, LineItem $lineItem)
	{
		$event = new CancelableEvent();

		if ($this->hasEventHandlers(self::EVENT_BEFORE_SUBSCRIPTION_CREATE)) {
			$this->trigger(self::EVENT_BEFORE_SUBSCRIPTION_CREATE, $event);
		}

		if (!$event->isValid) {
			return;
		}

		Plugin::getInstance()->getSubscriptions()->createSubscription($order->getUser(), $this, $order, $lineItem->snapshot['options']);
	}

	// Protected methods
	// =========================================================================

	protected function route()
	{
		// Make sure the plan type is set to have URLs for this site
		$siteId = Craft::$app->getSites()->currentSite->id;
		$planTypeSiteSettings = $this->getType()->getSiteSettings();

		if (!isset($planTypeSiteSettings[$siteId]) || !$planTypeSiteSettings[$siteId]->hasUrls) {
			return null;
		}

		return [
			'templates/render', [
				'template' => $planTypeSiteSettings[$siteId]->template,
				'variables' => [
					'plan' => $this,
				]
			]
		];
	}

	protected static function defineTableAttributes(): array
	{
		return [
			'quickpay_plans.title' => ['label' => Craft::t('commerce-quickpay', 'Title')],
			'type' => ['label' => Craft::t('commerce-quickpay', 'Type')],
			'slug' => ['label' => Craft::t('commerce-quickpay', 'Slug')],
			'sku' => ['label' => Craft::t('commerce-quickpay', 'SKU')],
			'price' => ['label' => Craft::t('commerce-quickpay', 'Price')],
			'postDate' => ['label' => Craft::t('commerce-quickpay', 'Post Date')],
			'expiryDate' => ['label' => Craft::t('commerce-quickpay', 'Expiry Date')],
		];
	}

	protected static function defineDefaultTableAttributes(string $source): array
	{
		$attributes = [];

		if ($source === '*') {
			$attributes[] = 'type';
		}

		$attributes[] = 'postDate';
		$attributes[] = 'expiryDate';

		return $attributes;
	}

	protected static function defineSearchableAttributes(): array
	{
		return ['title'];
	}

	protected function tableAttributeHtml(string $attribute): string
	{
		/* @var $planType planType */
		$planType = $this->getType();

		switch ($attribute) {
			case 'type':
				return ($planType ? Craft::t('site', $planType->name) : '');

			case 'taxCategory':
				$taxCategory = $this->getTaxCategory();

				return ($taxCategory ? Craft::t('site', $taxCategory->name) : '');

			case 'shippingCategory':
				$shippingCategory = $this->getShippingCategory();

				return ($shippingCategory ? Craft::t('site', $shippingCategory->name) : '');

			case 'defaultPrice':
				$code = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

				return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));

			case 'promotable':
				return ($this->$attribute ? '<span data-icon="check" title="' . Craft::t('commerce-quickpay', 'Yes') . '"></span>' : '');

			default:
				return parent::tableAttributeHtml($attribute);
		}
	}

	protected static function defineSortOptions(): array
	{
		return [
			'quickpay_plans.title' => Craft::t('commerce-quickpay', 'Title'),
			'postDate' => Craft::t('commerce-quickpay', 'Post Date'),
			'expiryDate' => Craft::t('commerce-quickpay', 'Expiry Date'),
			'price' => Craft::t('commerce-quickpay', 'Price'),
		];
	}

	private function _getPlanPurchasables()
	{
		return $purchasables = PlanPurchasableRecord::find()
			->where(['planId' => $this->id])
			->all();
	}
}
