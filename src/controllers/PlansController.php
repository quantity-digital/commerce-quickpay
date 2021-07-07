<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\base\Element;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\models\PlanPurchasableModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\records\PlanPurchasableRecord;
use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class PlansController extends Controller
{
	// Properties
	// =========================================================================

	protected $allowAnonymous = [];


	// Public Methods
	// =========================================================================

	public function init()
	{
		parent::init();
	}

	public function actionIndex(): Response
	{
		return $this->renderTemplate('commerce-quickpay/plans/index');
	}

	public function actionEdit(string $planTypeHandle, int $planId = null, string $siteHandle = null, Plan $plan = null): Response
	{
		$planType = null;

		$variables = [
			'planTypeHandle' => $planTypeHandle,
			'planId' => $planId,
			'plan' => $plan
		];

		// Make sure a correct plan type handle was passed so we can check permissions
		if ($planTypeHandle) {
			$planType = Plugin::$plugin->getPlanTypes()->getPlanTypeByHandle($planTypeHandle);
		}

		if (!$planType) {
			throw new Exception('The plan type was not found.');
		}

		$variables['planType'] = $planType;

		if ($siteHandle !== null) {
			$variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

			if (!$variables['site']) {
				throw new Exception('Invalid site handle: ' . $siteHandle);
			}
		}

		$this->_prepareVariableArray($variables);

		if (!empty($variables['plan']->id)) {
			$variables['title'] = $variables['plan']->title;
		} else {
			$variables['title'] = Craft::t('commerce-quickpay', 'Create a New Plan');
		}

		// Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
		$variables['baseCpEditUrl'] = 'commerce-quickpay/plans/' . $variables['planTypeHandle'] . '/{id}';

		// Set the "Continue Editing" URL
		$variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

		$this->_livePreview($variables);

		$variables['tabs'] = [];

		foreach ($variables['planType']->getFieldLayout()->getTabs() as $index => $tab) {
			// Do any of the fields on this tab have errors?
			$hasErrors = false;

			if ($variables['plan']->hasErrors()) {
				foreach ($tab->getFields() as $field) {
					if ($hasErrors = $variables['plan']->hasErrors($field->handle . '.*')) {
						break;
					}
				}
			}

			$variables['tabs'][] = [
				'label' => Craft::t('site', $tab->name),
				'url' => '#' . $tab->getHtmlId(),
				'class' => $hasErrors ? 'error' : null,

			];
		}

		$variables['intervalOptions'] = [
			1 => 'Monthly',
			3 => 'Every 3 months',
			6 => 'Every 6 months',
			9 => 'Every 9 months',
			12 => 'Yearly'
		];

		return $this->renderTemplate('commerce-quickpay/plans/_edit', $variables);
	}

	public function actionDeletePlan()
	{
		$this->requirePostRequest();

		$planId = Craft::$app->getRequest()->getRequiredParam('planId');
		$plan = Plan::findOne($planId);

		if (!$plan) {
			throw new Exception(Craft::t('commerce-quickpay', 'No plan exists with the ID “{id}”.', ['id' => $planId]));
		}

		$this->enforcePlanPermissions($plan);

		if (!Craft::$app->getElements()->deleteElement($plan)) {
			if (Craft::$app->getRequest()->getAcceptsJson()) {
				$this->asJson(['success' => false]);
			}

			Craft::$app->getSession()->setError(Craft::t('commerce-quickpay', 'Couldn’t delete plan.'));
			Craft::$app->getUrlManager()->setRouteParams([
				'plan' => $plan
			]);

			return null;
		}

		if (Craft::$app->getRequest()->getAcceptsJson()) {
			$this->asJson(['success' => true]);
		}

		Craft::$app->getSession()->setNotice(Craft::t('commerce-quickpay', 'Plan deleted.'));

		return $this->redirectToPostedUrl($plan);
	}

	public function actionSave()
	{
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();

		$plan = $this->_setPlanFromPost();

		if ($plan->enabled && $plan->enabledForSite) {
			$plan->setScenario(Element::SCENARIO_LIVE);
		}

		if (!Craft::$app->getElements()->saveElement($plan)) {
			if ($request->getAcceptsJson()) {
				return $this->asJson([
					'success' => false,
					'errors' => $plan->getErrors(),
				]);
			}

			Craft::$app->getSession()->setError(Craft::t('commerce-quickpay', 'Couldn’t save plan.'));

			$variables = ['plan' => $plan];
			$this->_prepareVariableArray($variables);
			// Send the category back to the template
			Craft::$app->getUrlManager()->setRouteParams($variables);

			return null;
		}

		$this->deleteAllPurchasablesByPlanId($plan->id);

		foreach ($plan->getPurchasableIds() as $id) {

			$purchasable = Craft::$app->getElements()->getElementById($id);

			$planPurchasable = new PlanPurchasableModel();
			$planPurchasable->planId = $plan->id;
			$planPurchasable->purchasableId = $id;
			$planPurchasable->purchasableType = get_class($purchasable);
			$planPurchasable->qty = $plan->qtys[$id];

			$this->savePlanPurchasables($planPurchasable);
		}


		if ($request->getAcceptsJson()) {
			return $this->asJson([
				'success' => true,
				'id' => $plan->id,
				'title' => $plan->title,
				'status' => $plan->getStatus(),
				'url' => $plan->getUrl(),
				'cpEditUrl' => $plan->getCpEditUrl()
			]);
		}

		Craft::$app->getSession()->setNotice(Craft::t('app', 'Plan saved.'));

		return $this->redirectToPostedUrl($plan);
	}

	public function actionPreviewPlan(): Response
	{

		$this->requirePostRequest();

		$plan = $this->_setPlanFromPost();

		$this->enforcePlanPermissions($plan);

		return $this->_showPlan($plan);
	}

	public function actionSharePlan($planId, $siteId): Response
	{
		$plan = Plugin::getInstance()->plans->getPlanById($planId, $siteId);

		if (!$plan) {
			throw new HttpException(404);
		}

		$this->enforcePlanPermissions($plan);

		// Create the token and redirect to the plan URL with the token in place
		$token = Craft::$app->getTokens()->createToken([
			'commerce-quickpay/plans/view-shared-plan', ['planId' => $plan->id, 'siteId' => $siteId]
		]);

		$url = UrlHelper::urlWithToken($plan->getUrl(), $token);

		return $this->redirect($url);
	}

	public function actionViewSharedPlan($planId, $site = null)
	{
		$this->requireToken();

		$plan = Plugin::getInstance()->plans->getPlanById($planId, $site);

		if (!$plan) {
			throw new HttpException(404);
		}

		$this->_showPlan($plan);

		return null;
	}

	public function savePlanPurchasables(PlanPurchasableModel $planPurchasable)
	{

		$planPurchasableRecord = new PlanPurchasableRecord();
		$planPurchasableRecord->planId = $planPurchasable->planId;
		$planPurchasableRecord->purchasableId = $planPurchasable->purchasableId;
		$planPurchasableRecord->purchasableType = $planPurchasable->purchasableType;
		$planPurchasableRecord->qty = $planPurchasable->qty;

		if (!$planPurchasable->hasErrors()) {

			$db = Craft::$app->getDb();
			$transaction = $db->beginTransaction();

			try {
				$success = $planPurchasableRecord->save(false);

				if ($success) {
					$planPurchasable->id = $planPurchasableRecord->id;

					$transaction->commit();
				}
			} catch (\Throwable $e) {
				$transaction->rollBack();
				throw $e;
			}

			return $success;
		}

		return false;
	}

	public function deleteAllPurchasablesByPlanId(int $planId): bool
	{
		return (bool)PlanPurchasableRecord::deleteAll(['planId' => $planId]);
	}


	// Protected Methods
	// =========================================================================

	protected function enforcePlanPermissions(Plan $plan)
	{
		if (!$plan->getType()) {
			Craft::error('Attempting to access a plan that doesn’t have a type', __METHOD__);
			throw new HttpException(404);
		}
	}


	// Private Methods
	// =========================================================================

	private function _showPlan(Plan $plan): Response
	{

		$planType = $plan->getType();

		if (!$planType) {
			throw new ServerErrorHttpException('Plan type not found.');
		}

		$siteSettings = $planType->getSiteSettings();

		if (!isset($siteSettings[$plan->siteId]) || !$siteSettings[$plan->siteId]->hasUrls) {
			throw new ServerErrorHttpException('The plan ' . $plan->id . ' doesn\'t have a URL for the site ' . $plan->siteId . '.');
		}

		$site = Craft::$app->getSites()->getSiteById($plan->siteId);

		if (!$site) {
			throw new ServerErrorHttpException('Invalid site ID: ' . $plan->siteId);
		}

		Craft::$app->language = $site->language;

		// Have this plan override any freshly queried plans with the same ID/site
		Craft::$app->getElements()->setPlaceholderElement($plan);

		$this->getView()->getTwig()->disableStrictVariables();

		return $this->renderTemplate($siteSettings[$plan->siteId]->template, [
			'plan' => $plan
		]);
	}

	private function _prepareVariableArray(&$variables)
	{
		// Locale related checks
		if (Craft::$app->getIsMultiSite()) {
			// Only use the sites that the user has access to
			$variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
		} else {
			$variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
		}

		if (!$variables['siteIds']) {
			throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this section');
		}

		if (empty($variables['site'])) {
			$site = $variables['site'] = Craft::$app->getSites()->currentSite;

			if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
				$site = $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
			}
		} else {
			// Make sure they were requesting a valid site
			/** @var Site $site */
			$site = $variables['site'];
			if (!in_array($site->id, $variables['siteIds'], false)) {
				throw new ForbiddenHttpException('User not permitted to edit content in this site');
			}
		}

		// Plan related checks
		if (empty($variables['plan'])) {
			if (!empty($variables['planId'])) {
				$variables['plan'] = Craft::$app->getElements()->getElementById($variables['planId'], Plan::class, $site->id);

				if (!$variables['plan']) {
					throw new Exception('Missing plan data.');
				}
			} else {
				$variables['plan'] = new Plan();
				$variables['plan']->typeId = $variables['planType']->id;

				if (!empty($variables['siteId'])) {
					$variables['plan']->site = $variables['siteId'];
				}
			}
		}

		// Enable locales
		if ($variables['plan']->id) {
			$variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['plan']->id);
		} else {
			$variables['enabledSiteIds'] = [];

			foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
				$variables['enabledSiteIds'][] = $site;
			}
		}


		$variables['purchasables'] = null;
		$purchasables = [];
		foreach ($variables['plan']->getPurchasableIds() as $purchasableId) {
			$purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
			if ($purchasable) {
				$class = get_class($purchasable);
				$purchasables[$class] = $purchasables[$class] ?? [];
				$purchasables[$class][] = $purchasable;
			}
		}
		$variables['purchasables'] = $purchasables;

		$variables['purchasableTypes'] = [];
		$purchasableTypes = Commerce::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

		/** @var Purchasable $purchasableType */
		foreach ($purchasableTypes as $purchasableType) {
			if ($purchasableType != get_class($variables['plan'])) {
				$variables['purchasableTypes'][] = [
					'name' => $purchasableType::displayName(),
					'elementType' => $purchasableType
				];
			}
		}
	}

	private function _livePreview(array &$variables)
	{
		if (!Craft::$app->getRequest()->isMobileBrowser(true)) {
			$this->getView()->registerJs('Craft.LivePreview.init(' . Json::encode([
				'fields' => '#title-field, #fields > div > div > .field',
				'extraFields' => '#meta-pane',
				'previewUrl' => $variables['plan']->getUrl(),
				'previewAction' => Craft::$app->getSecurity()->hashData('commerce-quickpay/plans/preview-plan'),
				'previewParams' => [
					'typeId' => $variables['planType']->id,
					'planId' => $variables['plan']->id,
					'siteId' => $variables['plan']->siteId,
				]
			]) . ');');

			$variables['showPreviewBtn'] = true;

			// Should we show the Share button too?
			if ($variables['plan']->id) {
				// If the plan is enabled, use its main URL as its share URL.
				if ($variables['plan']->getStatus() === Plan::STATUS_LIVE) {
					$variables['shareUrl'] = $variables['plan']->getUrl();
				} else {
					$variables['shareUrl'] = UrlHelper::actionUrl('commerce-quickpay/plans/share-plan', [
						'planId' => $variables['plan']->id,
						'siteId' => $variables['plan']->siteId
					]);
				}
			}
		} else {
			$variables['showPreviewBtn'] = false;
		}
	}

	private function _setPlanFromPost(): Plan
	{
		$request = Craft::$app->getRequest();
		$planId = $request->getBodyParam('planId');
		$siteId = $request->getBodyParam('siteId');

		if ($planId) {
			$plan = Plugin::getInstance()->getPlans()->getPlanById($planId, $siteId);

			if (!$plan) {
				throw new Exception(Craft::t('commerce-quickpay', 'No plan with the ID “{id}”', ['id' => $planId]));
			}
		} else {
			$plan = new Plan();
		}

		$plan->typeId = $request->getBodyParam('typeId');
		$plan->siteId = $siteId ?? $plan->siteId;
		$plan->enabled = (bool)$request->getBodyParam('enabled');

		$plan->price = Localization::normalizeNumber($request->getBodyParam('price')); //TODO make it not required on editpage
		$plan->sku = $request->getBodyParam('sku');

		$purchasables = [];
		$purchasableGroups = $request->getBodyParam('purchasables') ?: [];
		foreach ($purchasableGroups as $group) {
			if (is_array($group)) {
				array_push($purchasables, ...$group);
			}
		}
		$purchasables = array_unique($purchasables);
		$plan->setPurchasableIds($purchasables);

		$plan->qtys = $request->getBodyParam('qty');

		if (($postDate = Craft::$app->getRequest()->getBodyParam('postDate')) !== null) {
			$plan->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
		}

		if (($expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate')) !== null) {
			$plan->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
		}

		// $plan->promotable = (bool)$request->getBodyParam('promotable');
		$plan->taxCategoryId = $request->getBodyParam('taxCategoryId');
		$plan->shippingCategoryId = $request->getBodyParam('shippingCategoryId');
		$plan->slug = $request->getBodyParam('slug');

		$plan->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $plan->enabledForSite);
		$plan->title = $request->getBodyParam('title', $plan->title);
		$plan->planInterval = $request->getBodyParam('planInterval');
		$plan->subscriptionInterval = $request->getBodyParam('subscriptionInterval');
		$plan->trialDays = $request->getBodyParam('trialDays');

		$plan->setFieldValuesFromRequest('fields');

		// Last checks
		if (empty($plan->sku)) {
			$planType = $plan->getType();
			$plan->sku = Craft::$app->getView()->renderObjectTemplate($planType->skuFormat, $plan);
		}

		return $plan;
	}
}
