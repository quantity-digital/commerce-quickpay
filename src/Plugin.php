<?php

namespace QD\commerce\quickpay;

use Craft;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use QD\commerce\quickpay\gateways\Gateway;
use craft\commerce\Plugin as CommercePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins;
use craft\web\UrlManager;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;
use craft\events\DefineBehaviorsEvent;
use QD\commerce\quickpay\plugin\Services;
use QD\commerce\quickpay\behaviors\OrderBehavior;
use QD\commerce\quickpay\behaviors\OrderQueryBehavior;

class Plugin extends \craft\base\Plugin
{
	use Services;

	// Static Properties
	// =========================================================================

	public static $plugin;

	/**
	 * @var bool
	 */
	public static $commerceInstalled = false;

	// Public Properties
	// =========================================================================

	/**
	 * @inheritDoc
	 */
	public $schemaVersion = '2.2.2';
	public $hasCpSettings = false;
	public $hasCpSection = true;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		self::$plugin = $this;

		$this->initComponents();

		self::$commerceInstalled = class_exists(CommercePlugin::class);

		// Install event listeners
		$this->installEventListeners();

	}

	protected function installEventListeners()
	{

		$this->installGlobalEventListeners();
	}

	public function installGlobalEventListeners()
	{
		Event::on(
			Gateways::class,
			Gateways::EVENT_REGISTER_GATEWAY_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = Gateway::class;
			}
		);

		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_LOAD_PLUGINS,
			function () {
				// Install these only after all other plugins have loaded
				$request = Craft::$app->getRequest();

				/**
				 * Order element behaviours
				 */
				Event::on(
					Order::class,
					Order::EVENT_DEFINE_BEHAVIORS,
					function (DefineBehaviorsEvent $e) {
						$e->behaviors['commerce-quickpay.attributes'] = OrderBehavior::class;
					}
				);

				Event::on(
					OrderQuery::class,
					OrderQuery::EVENT_DEFINE_BEHAVIORS,
					function (DefineBehaviorsEvent $e) {
						$e->behaviors['commerce-quickpay.queryparams'] = OrderQueryBehavior::class;
					}
				);

				Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, [$this->getOrders(), 'addAutoCaptureJob']);

				if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
					$this->installSiteEventListeners();
				}

				if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
					$this->installCpEventListeners();
				}
			}
		);
	}

	protected function installSiteEventListeners()
	{
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules = array_merge($event->rules, [
					'quickpay/callbacks/payments/continue/<transactionReference>' => 'commerce-quickpay/payments-callback/continue',
					'quickpay/callbacks/payments/notify/<transactionReference>' => 'commerce-quickpay/payments-callback/notify',
					'quickpay/callbacks/payments/cancel/<transactionReference>' => 'commerce-quickpay/payments-callback/cancel',
				]);
			}
		);
	}

	public function getCpNavItem(): array
	{
		$navItems = parent::getCpNavItem();

		$navItems['label'] = Craft::t('commerce-quickpay', 'Quickpay');

		return $navItems;
	}
}
