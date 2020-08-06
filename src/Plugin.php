<?php

namespace QD\commerce\quickpay;

use Craft;
use craft\commerce\events\OrderStatusEvent;
use QD\commerce\quickpay\gateways\Gateway;
use craft\commerce\Plugin as CommercePlugin;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins;
use craft\web\UrlManager;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;
use craft\commerce\records\Transaction as TransactionRecord;

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
	public $schemaVersion = '1.0';

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

		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_INSTALL_PLUGIN,
			function (PluginEvent $event){
				if ($event->plugin === $this) {
				}
			}
		);
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
			function (RegisterComponentTypesEvent $event){
				$event->types[] = Gateway::class;
			}
		);

		// Handler: Plugins::EVENT_AFTER_LOAD_PLUGINS
		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_LOAD_PLUGINS,
			function (){
				// Install these only after all other plugins have loaded
				$request = Craft::$app->getRequest();

				if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
					$this->installSiteEventListeners();
				}

				if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
					$this->installCpEventListeners();
				}
			}
		);

		//Autocapture on statuschange
		Event::on(
			OrderHistories::class,
			OrderHistories::EVENT_ORDER_STATUS_CHANGE,
			function(OrderStatusEvent $event) {
				$order = $event->order;
				$orderstatus = $order->getOrderStatus();
				$gateway = $order->getGateway();

				//if gateway is quickpay and autocapture on statuschange is active
				if($gateway instanceof Gateway && $gateway->autoCapture && $gateway->autoCaptureStatus === $orderstatus->handle){
					$transaction = $this->getPayments()->getSuccessfulTransactionForOrder($order);

					if ($transaction && $transaction->canCapture()) {
						// capture transaction and display result
						$child = CommercePlugin::getInstance()->getPayments()->captureTransaction($transaction);

						$message = $child->message ? ' (' . $child->message . ')' : '';

						if ($child->status == TransactionRecord::STATUS_SUCCESS) {
							$child->order->updateOrderPaidInformation();
						}
					}
				}
			}
		);
	}

	protected function installSiteEventListeners()
	{
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			function (RegisterUrlRulesEvent $event){
				$event->rules = array_merge($event->rules, [
					'quickpay/callbacks/continue/<transactionReference>' => 'commerce-quickpay/callback/continue',
					'quickpay/callbacks/notify/<transactionReference>' => 'commerce-quickpay/callback/notify',
				]);
			}
		);
	}

	protected function installCpEventListeners()
	{
	}

}
