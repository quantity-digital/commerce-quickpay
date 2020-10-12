<?php

namespace QD\commerce\quickpay\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\Plugin;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response;

class SubscriptionsController extends BaseController
{

	/**
	 * @inheritdoc
	 */
	public $allowAnonymous = [
		'capture' => self::ALLOW_ANONYMOUS_LIVE,
	];


	/**
	 * @param int|null $subscriptionId
	 * @param Subscription|null $subscription
	 * @return Response
	 * @throws HttpException
	 * @throws InvalidConfigException
	 */
	public function actionEdit(int $subscriptionId = null, Subscription $subscription = null): Response
	{
		$variables = [];
		$this->requirePermission('commerce-manageSubscriptions');
		$this->getView()->registerAssetBundle(CommerceCpAsset::class);

		if ($subscription === null && $subscriptionId) {
			$subscription = Subscription::find()->anyStatus()->id($subscriptionId)->one();
		}

		$fieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

		$variables['tabs'] = [];

		$variables['tabs'][] = [
			'label' => Craft::t('commerce', 'Manage'),
			'url' => '#subscriptionManageTab',
			'class' => null
		];

		foreach ($fieldLayout->getTabs() as $index => $tab) {
			// Do any of the fields on this tab have errors?
			$hasErrors = false;

			if ($subscription->hasErrors()) {
				foreach ($tab->getFields() as $field) {
					if ($subscription->getErrors($field->handle)) {
						$hasErrors = true;
						break;
					}
				}
			}

			$variables['tabs'][] = [
				'label' => Craft::t('commerce', $tab->name),
				'url' => '#tab' . ($index + 1),
				'class' => $hasErrors ? 'error' : null
			];
		}

		$variables['continueEditingUrl'] = $subscription->cpEditUrl;
		$variables['subscriptionId'] = $subscriptionId;
		$variables['subscription'] = $subscription;
		$variables['fieldLayout'] = $fieldLayout;

		return $this->renderTemplate('commerce-quickpay/subscriptions/edit', $variables);
	}

	public function actionUnsubscribe()
	{
		$this->requireLogin();
		$this->requirePostRequest();

		$session = Craft::$app->getSession();
		$request = Craft::$app->getRequest();

		$error = false;
		$subscription = null;

		try {
			$subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');

			$subscription = Subscription::find()->anyStatus()->uid($subscriptionUid)->one();

			if (!Plugin::getInstance()->getSubscriptions()->cancelSubscription($subscription)) {
				$error = Craft::t('commerce', 'Unable to cancel subscription at this time.');
			}
		} catch (SubscriptionException $exception) {
			$error = $exception->getMessage();
		}

		if ($error) {
			if ($request->getAcceptsJson()) {
				return $this->asErrorJson($error);
			}

			$session->setError($error);

			return null;
		}

		if ($request->getAcceptsJson()) {
			return $this->asJson([
				'success' => true,
				'subscription' => $subscription
			]);
		}

		return $this->redirectToPostedUrl();
	}

	public function actionCapture()
	{
		//Should find all subscriptins thats not cancelled, and where next payment is older than current time
		$subscriptions = Subscription::find()->isCanceled(false)->isSuspended(false)->nextPaymentDate(time())->all();

		foreach ($subscriptions as $subscription) {
			$capture = Plugin::getInstance()->getSubscriptions()->captureSubscription($subscription);
		}
	}
}
