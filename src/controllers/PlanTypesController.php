<?php
namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\web\Controller;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\models\PlanTypeModel;
use QD\commerce\quickpay\models\PlanTypeSiteModel;
use QD\commerce\quickpay\Plugin;
use yii\web\HttpException;
use yii\web\Response;

class PlanTypesController extends Controller
{
    // Public Methods
    // =========================================================================

    public function init()
    {
		//TODO - Setup permisions
        // $this->requirePermission('commerce-plan-manageTypes');

        parent::init();
    }

    public function actionEdit(int $planTypeId = null, PlanTypeModel $planType = null): Response
    {
        $variables = [
            'planTypeId' => $planTypeId,
            'planType' => $planType,
            'brandNewPlanType' => false,
        ];

        if (empty($variables['planType'])) {
            if (!empty($variables['planTypeId'])) {
                $planTypeId = $variables['planTypeId'];
                $variables['planType'] = Plugin::getInstance()->getPlanTypes()->getPlanTypeById($planTypeId);

                if (!$variables['planType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['planType'] = new PlanTypeModel();
                $variables['brandNewPlanType'] = true;
            }
        }

        if (!empty($variables['planTypeId'])) {
            $variables['title'] = $variables['planType']->name;
        } else {
            $variables['title'] = Craft::t('commerce-quickpay', 'Create a Plan Type');
        }

        return $this->renderTemplate('commerce-quickpay/plan-types/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $planType = new PlanTypeModel();

        $request = Craft::$app->getRequest();

        $planType->id = $request->getBodyParam('planTypeId');
        $planType->name = $request->getBodyParam('name');
        $planType->handle = $request->getBodyParam('handle');
        $planType->skuFormat = $request->getBodyParam('skuFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);

            $siteSettings = new PlanTypeSiteModel();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $planType->setSiteSettings($allSiteSettings);

        // Set the plan type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Plan::class;
        $planType->setFieldLayout($fieldLayout);

        // Save it
        if (Plugin::getInstance()->getPlanTypes()->savePlanType($planType)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-quickpay', 'Plan type saved.'));

            return $this->redirectToPostedUrl($planType);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce-quickpay', 'Couldn’t save plan type.'));

        // Send the planType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'planType' => $planType
        ]);

        return null;
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $planTypeId = Craft::$app->getRequest()->getRequiredParam('id');
        Plugin::getInstance()->getPlanTypes()->deletePlanTypeById($planTypeId);

        return $this->asJson(['success' => true]);
    }

}
