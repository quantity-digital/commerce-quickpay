<?php

namespace QD\commerce\quickpay\elements\db;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use DateTime;
use QD\commerce\quickpay\base\Table;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\models\PlanTypeModel;
use QD\commerce\quickpay\Plugin;

class PlanQuery extends ElementQuery
{
	// Properties
	// =========================================================================

	public $editable = false;
	public $typeId;
	public $postDate;
	public $expiryDate;


	// Public Methods
	// =========================================================================

	public function __construct(string $elementType, array $config = [])
	{
		// Default status
		if (!isset($config['status'])) {
			$config['status'] = Plan::STATUS_LIVE;
		}

		parent::__construct($elementType, $config);
	}

	public function __set($name, $value)
	{
		switch ($name) {
			case 'type':
				$this->type($value);
				break;
			case 'before':
				$this->before($value);
				break;
			case 'after':
				$this->after($value);
				break;
			default:
				parent::__set($name, $value);
		}
	}

	public function type($value)
	{
		if ($value instanceof PlanTypeModel) {
			$this->typeId = $value->id;
		} else if ($value !== null) {
			$this->typeId = (new Query())
				->select(['id'])
				->from([Table::PLANS])
				->where(Db::parseParam('handle', $value))
				->column();
		} else {
			$this->typeId = null;
		}

		return $this;
	}

	public function before($value)
	{
		if ($value instanceof DateTime) {
			$value = $value->format(DateTime::W3C);
		}

		$this->postDate = ArrayHelper::toArray($this->postDate);
		$this->postDate[] = '<' . $value;

		return $this;
	}

	public function after($value)
	{
		if ($value instanceof DateTime) {
			$value = $value->format(DateTime::W3C);
		}

		$this->postDate = ArrayHelper::toArray($this->postDate);
		$this->postDate[] = '>=' . $value;

		return $this;
	}

	public function editable(bool $value = true)
	{
		$this->editable = $value;

		return $this;
	}

	public function typeId($value)
	{
		$this->typeId = $value;

		return $this;
	}

	public function postDate($value)
	{
		$this->postDate = $value;

		return $this;
	}

	public function expiryDate($value)
	{
		$this->expiryDate = $value;

		return $this;
	}

	// Protected Methods
	// =========================================================================

	protected function beforePrepare(): bool
	{
		// See if 'type' were set to invalid handles
		if ($this->typeId === []) {
			return false;
		}

		$this->joinElementTable('quickpay_plans');

		$this->query->select([
			'quickpay_plans.id',
			'quickpay_plans.typeId',
			'quickpay_plans.taxCategoryId',
			'quickpay_plans.shippingCategoryId',
			'quickpay_plans.postDate',
			'quickpay_plans.expiryDate',
			'quickpay_plans.planInterval',
			'quickpay_plans.subscriptionInterval',
			'quickpay_plans.trialDays',
			'quickpay_plans.title',
			'quickpay_plans.price',
			'quickpay_plans.sku',
		]);

		if ($this->postDate) {
			$this->subQuery->andWhere(Db::parseDateParam('quickpay_plans.postDate', $this->postDate));
		}

		if ($this->expiryDate) {
			$this->subQuery->andWhere(Db::parseDateParam('quickpay_plans.expiryDate', $this->expiryDate));
		}

		if ($this->typeId) {
			$this->subQuery->andWhere(Db::parseParam('quickpay_plans.typeId', $this->typeId));
		}

		$this->_applyEditableParam();

		return parent::beforePrepare();
	}

	protected function statusCondition(string $status)
	{
		$currentTimeDb = Db::prepareDateForDb(new \DateTime());

		switch ($status) {
			case Plan::STATUS_LIVE:
				return [
					'and',
					[
						'elements.enabled' => true,
						'elements_sites.enabled' => true
					],
					['<=', 'quickpay_plans.postDate', $currentTimeDb],
					[
						'or',
						['quickpay_plans.expiryDate' => null],
						['>', 'quickpay_plans.expiryDate', $currentTimeDb]
					]
				];
			case Plan::STATUS_PENDING:
				return [
					'and',
					[
						'elements.enabled' => true,
						'elements_sites.enabled' => true,
					],
					['>', 'quickpay_plans.postDate', $currentTimeDb]
				];
			case Plan::STATUS_EXPIRED:
				return [
					'and',
					[
						'elements.enabled' => true,
						'elements_sites.enabled' => true
					],
					['not', ['quickpay_plans.expiryDate' => null]],
					['<=', 'quickpay_plans.expiryDate', $currentTimeDb]
				];
			default:
				return parent::statusCondition($status);
		}
	}

	// Private Methods
	// =========================================================================

	private function _applyEditableParam()
	{
		if (!$this->editable) {
			return;
		}

		$user = Craft::$app->getUser()->getIdentity();

		if (!$user) {
			throw new QueryAbortedException();
		}

		// Limit the query to only the sections the user has permission to edit
		$this->subQuery->andWhere([
			'quickpay_plans.typeId' => Plugin::$plugin->getPlanTypes()->getEditablePlanTypeIds()
		]);
	}
}
