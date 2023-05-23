<?php

namespace QD\commerce\quickpay;

use Craft;
use craft\commerce\models\Transaction;
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
use QD\commerce\quickpay\behaviors\TransactionBehavior;

class Plugin extends \craft\base\Plugin
{
	use Services;

	// Static Properties
	// =========================================================================

	/**
	 * @var Plugin
	 */
	public static Plugin $plugin;

	/**
	 * @var bool
	 */
	public static bool $commerceInstalled = false;

	// Public Properties
	// =========================================================================

	/**
	 * @inheritDoc
	 */
	public string $schemaVersion = '2.2.3';
	public bool $hasCpSettings = false;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init(): void
	{
		parent::init();

		self::$plugin = $this;

		$this->initComponents();

		self::$commerceInstalled = class_exists(CommercePlugin::class);

		// Install event listeners
		$this->installEventListeners();
	}

	/**
	 * Install eventlisteners
	 *
	 * @return void
	 */
	protected function installEventListeners(): void
	{
		$this->installGlobalEventListeners();
	}


	/**
	 * Install global eventlistners
	 *
	 * @return void
	 */
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
				 * Transaction element behaviours
				 */
				Event::on(
					Transaction::class,
					Transaction::EVENT_DEFINE_BEHAVIORS,
					function (DefineBehaviorsEvent $event) {
						$event->behaviors['commerce-quickpay.attributes'] = TransactionBehavior::class;
					}
				);

				Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, [$this->getOrders(), 'addAutoCaptureJob']);

				if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
					$this->installSiteEventListeners();
				}
			}
		);
	}

	/**
	 * Install site eventlisteners
	 *
	 * @return void
	 */
	protected function installSiteEventListeners(): void
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
}
