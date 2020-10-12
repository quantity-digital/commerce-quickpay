<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use Exception;
use QD\commerce\quickpay\base\Table;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\models\PlanTypeModel;
use QD\commerce\quickpay\models\PlanTypeSiteModel;
use QD\commerce\quickpay\records\PlanTypeRecord;
use QD\commerce\quickpay\records\PlanTypeSiteRecord;
use yii\base\Component;

class PlanTypes extends Component
{
	// Properties
	// =========================================================================

	private $fetchedAllPlanTypes = false;
	private $planTypesById;
	private $planTypesByHandle;
	private $allPlanTypeIds;
	private $editablePlanTypeIds;
	private $siteSettingsByPlanId = [];


	// Public Methods
	// =========================================================================
	public function getAllplanTypes(): array
	{
		if (!$this->fetchedAllPlanTypes) {
			$results = $this->createPlanTypeQuery()->all();

			foreach ($results as $result) {
				$this->memoizePlanType(new PlanTypeModel($result));
			}

			$this->fetchedAllPlanTypes = true;
		}

		return $this->planTypesById ?: [];
	}

	public function getEditablePlanTypeIds(): array
	{
		return $this->getAllPlanTypeIds();
	}

	public function getAllPlanTypeIds(): array
	{
		if (null === $this->allPlanTypeIds) {
			$this->allPlanTypeIds = [];
			$planTypes = $this->getAllplanTypes();

			foreach ($planTypes as $planType) {
				$this->allPlanTypeIds[] = $planType->id;
			}
		}

		return $this->allPlanTypeIds;
	}

	public function getPlanTypeById(int $planTypeId)
	{
		if (isset($this->planTypesById[$planTypeId])) {
			return $this->planTypesById[$planTypeId];
		}

		if ($this->fetchedAllPlanTypes) {
			return null;
		}

		$result = $this->createPlanTypeQuery()
			->where(['id' => $planTypeId])
			->one();

		if (!$result) {
			return null;
		}

		$this->memoizePlanType(new PlanTypeModel($result));

		return $this->planTypesById[$planTypeId];
	}

	public function getPlanTypeByHandle($handle)
	{
		if (isset($this->planTypesByHandle[$handle])) {
			return $this->planTypesByHandle[$handle];
		}

		if ($this->fetchedAllPlanTypes) {
			return null;
		}

		$result = $this->createPlanTypeQuery()
			->where(['handle' => $handle])
			->one();

		if (!$result) {
			return null;
		}

		$this->memoizePlanType(new PlanTypeModel($result));

		return $this->planTypesByHandle[$handle];
	}

	public function getPlanTypeSites($planTypeId): array
	{
		if (!isset($this->siteSettingsByPlanId[$planTypeId])) {
			$rows = (new Query())
				->select([
					'id',
					'planTypeId',
					'siteId',
					'uriFormat',
					'hasUrls',
					'template'
				])
				->from(Table::PLANTYPES_SITES)
				->where(['planTypeId' => $planTypeId])
				->all();

			$this->siteSettingsByPlanId[$planTypeId] = [];

			foreach ($rows as $row) {
				$this->siteSettingsByPlanId[$planTypeId][] = new PlanTypeSiteModel($row);
			}
		}

		return $this->siteSettingsByPlanId[$planTypeId];
	}

	public function savePlanType(PlanTypeModel $planType, bool $runValidation = true): bool
	{
		$isNewPlanType = !$planType->id;

		if ($runValidation && !$planType->validate()) {
			Craft::info('Plan type not saved due to validation error.', __METHOD__);

			return false;
		}

		if (!$isNewPlanType) {
			$planTypeRecord = PlanTypeRecord::findOne($planType->id);

			if (!$planTypeRecord) {
				throw new Exception("No plan type exists with the ID '{$planType->id}'");
			}
		} else {
			$planTypeRecord = new PlanTypeRecord();
		}

		$planTypeRecord->name = $planType->name;
		$planTypeRecord->handle = $planType->handle;
		$planTypeRecord->skuFormat = $planType->skuFormat;

		// Get the site settings
		$allSiteSettings = $planType->getSiteSettings();

		// Make sure they're all there
		foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
			if (!isset($allSiteSettings[$siteId])) {
				throw new Exception('Tried to save a plan type that is missing site settings');
			}
		}

		$db = Craft::$app->getDb();
		$transaction = $db->beginTransaction();

		try {
			// Plan Field Layout
			$fieldLayout = $planType->getPlanFieldLayout();
			Craft::$app->getFields()->saveLayout($fieldLayout);
			$planType->fieldLayoutId = $fieldLayout->id;
			$planTypeRecord->fieldLayoutId = $fieldLayout->id;

			// Save the voplanucher type
			$planTypeRecord->save(false);

			// Now that we have a plan type ID, save it on the model
			if (!$planType->id) {
				$planType->id = $planTypeRecord->id;
			}

			// Might as well update our cache of the plan type while we have it.
			$this->planTypesById[$planType->id] = $planType;

			// Update the site settings
			// -----------------------------------------------------------------

			$sitesNowWithoutUrls = [];
			$sitesWithNewUriFormats = [];
			$allOldSiteSettingsRecords = [];

			if (!$isNewPlanType) {
				// Get the old plan type site settings
				$allOldSiteSettingsRecords = PlanTypeSiteRecord::find()
					->where(['planTypeId' => $planType->id])
					->indexBy('siteId')
					->all();
			}

			foreach ($allSiteSettings as $siteId => $siteSettings) {
				// Was this already selected?
				if (!$isNewPlanType && isset($allOldSiteSettingsRecords[$siteId])) {
					$siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
				} else {
					$siteSettingsRecord = new PlanTypeSiteRecord();
					$siteSettingsRecord->planTypeId = $planType->id;
					$siteSettingsRecord->siteId = $siteId;
				}

				if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
					$siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
					$siteSettingsRecord->template = $siteSettings['template'];
				} else {
					$siteSettingsRecord->uriFormat = null;
					$siteSettingsRecord->template = null;
				}

				if (!$siteSettingsRecord->getIsNewRecord()) {
					// Did it used to have URLs, but not anymore?
					if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings['hasUrls']) {
						$sitesNowWithoutUrls[] = $siteId;
					}

					// Does it have URLs, and has its URI format changed?
					if ($siteSettings['hasUrls'] && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
						$sitesWithNewUriFormats[] = $siteId;
					}
				}

				$siteSettingsRecord->save(false);

				// Set the ID on the model
				$siteSettings->id = $siteSettingsRecord->id;
			}

			if (!$isNewPlanType) {
				// Drop any site settings that are no longer being used, as well as the associated plan/element
				// site rows
				$affectedSiteUids = array_keys($allSiteSettings);

				foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
					if (!in_array($siteId, $affectedSiteUids, false)) {
						$siteSettingsRecord->delete();
					}
				}
			}

			if (!$isNewPlanType) {
				foreach ($allSiteSettings as $siteId => $siteSettings) {
					Craft::$app->getQueue()->push(new ResaveElements([
						'description' => Craft::t('app', 'Resaving {type} plans ({site})', [
							'type' => $planType->name,
							'site' => $siteSettings->getSite()->name,
						]),
						'elementType' => Plan::class,
						'criteria' => [
							'siteId' => $siteId,
							'typeId' => $planType->id,
							'status' => null,
							'enabledForSite' => false,
						]
					]));
				}
			}

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();

			throw $e;
		}

		return true;
	}

	public function deletePlanTypeById(int $id): bool
	{
		$db = Craft::$app->getDb();
		$transaction = $db->beginTransaction();

		try {
			$planType = $this->getPlanTypeById($id);

			$criteria = Plan::find();
			$criteria->typeId = $planType->id;
			$criteria->status = null;
			$criteria->limit = null;
			$plans = $criteria->all();

			foreach ($plans as $plan) {
				Craft::$app->getElements()->deleteElement($plan);
			}

			$fieldLayoutId = $planType->getPlanFieldLayout()->id;
			Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);

			$planTypeRecord = PlanTypeRecord::findOne($planType->id);
			$affectedRows = $planTypeRecord->delete();

			if ($affectedRows) {
				$transaction->commit();
			}

			return (bool)$affectedRows;
		} catch (\Throwable $e) {
			$transaction->rollBack();

			throw $e;
		}
	}

	public function afterSaveSiteHandler(SiteEvent $event)
	{
		if ($event->isNew) {
			$primarySiteSettings = (new Query())
				->select(['planTypeId', 'uriFormat', 'template', 'hasUrls'])
				->from([Table::PLANTYPES_SITES])
				->where(['siteId' => $event->oldPrimarySiteId])
				->one();

			if ($primarySiteSettings) {
				$newSiteSettings = [];

				$newSiteSettings[] = [
					$primarySiteSettings['planTypeId'],
					$event->site->id,
					$primarySiteSettings['uriFormat'],
					$primarySiteSettings['template'],
					$primarySiteSettings['hasUrls']
				];

				Craft::$app->getDb()->createCommand()
					->batchInsert(
						Table::PLANTYPES_SITES,
						['planTypeId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
						$newSiteSettings
					)
					->execute();
			}
		}
	}

	// Private methods
	// =========================================================================
	private function memoizePlanType(PlanTypeModel $planType)
	{
		$this->planTypesById[$planType->id] = $planType;
		$this->planTypesByHandle[$planType->handle] = $planType;
	}

	private function createPlanTypeQuery(): Query
	{
		return (new Query())
			->select([
				'id',
				'fieldLayoutId',
				'name',
				'handle',
				'skuFormat'
			])
			->from([Table::PLANTYPES]);
	}
}
