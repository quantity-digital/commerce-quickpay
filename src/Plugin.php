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
use craft\commerce\services\Purchasables;
use craft\events\DefineBehaviorsEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Sites;
use craft\web\twig\variables\CraftVariable;
use QD\commerce\quickpay\base\PluginTrait;
use QD\commerce\quickpay\behaviors\OrderBehavior;
use QD\commerce\quickpay\behaviors\OrderQueryBehavior;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\elements\Subscription;
use QD\commerce\quickpay\fields\Plans;
use QD\commerce\quickpay\gateways\Subscriptions;
use QD\commerce\quickpay\variables\PlansVariable;

class Plugin extends \craft\base\Plugin
{
    use PluginTrait;

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
    public $schemaVersion = '2.2';
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
        $this->registerElementTypes();

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Plans::class;
            }
        );
    }

    private function registerElementTypes()
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Plan::class;
                $event->types[] = Subscription::class;
            }
        );

        Event::on(
            Purchasables::class,
            Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Plan::class;
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
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
                $event->types[] = Subscriptions::class;
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

                Event::on(
                    CraftVariable::class,
                    CraftVariable::EVENT_INIT,
                    function (Event $event) {
                        /** @var CraftVariable $variable */
                        $variable = $event->sender;
                        $variable->attachBehavior('plans', PlansVariable::class);
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

                    'quickpay/callbacks/subscriptions/continue/<transactionReference>' => 'commerce-quickpay/subscriptions-callback/continue',
                    'quickpay/callbacks/subscriptions/notify/<transactionReference>' => 'commerce-quickpay/subscriptions-callback/notify',

                    'quickpay/callbacks/recurring/notify/<transactionReference>' => 'commerce-quickpay/recurring-callback/notify',

                    'quickpay/cron/subscriptions/capture' => 'commerce-quickpay/subscriptions-cron/capture',
                    'quickpay/cron/subscriptions/create-order' => 'commerce-quickpay/subscriptions-cron/create-order',
                    'quickpay/cron/subscriptions/authorize' => 'commerce-quickpay/subscriptions-cron/authorize'
                ]);
            }
        );
    }

    protected function installCpEventListeners()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'commerce-quickpay/plan-types/new' => 'commerce-quickpay/plan-types/edit',
                    'commerce-quickpay/plan-types/<planTypeId:\d+>' => 'commerce-quickpay/plan-types/edit',

                    'commerce-quickpay/plans/<planTypeHandle:{handle}>' => 'commerce-quickpay/plans/index',
                    'commerce-quickpay/plans/<planTypeHandle:{handle}>/new' => 'commerce-quickpay/plans/edit',
                    'commerce-quickpay/plans/<planTypeHandle:{handle}>/new/<siteHandle:\w+>' => 'commerce-quickpay/plans/edit',
                    'commerce-quickpay/plans/<planTypeHandle:{handle}>/<planId:\d+>' => 'commerce-quickpay/plans/edit',
                    'commerce-quickpay/plans/<planTypeHandle:{handle}>/<planId:\d+>/<siteHandle:\w+>' => 'commerce-quickpay/plans/edit',

                    'commerce-quickpay/subscriptions/new' =>                 'commerce-quickpay/subscriptions/index',
                    'commerce-quickpay/subscriptions/<subscriptionId:\d+>' =>     'commerce-quickpay/subscriptions/edit',
                ]);
            }
        );

        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getPlanTypes(), 'afterSaveSiteHandler']);
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getPlans(), 'afterSaveSiteHandler']);
    }

    public function getCpNavItem(): array
    {
        $navItems = parent::getCpNavItem();

        $navItems['label'] = Craft::t('commerce-quickpay', 'Quickpay');

        $navItems['subnav']['subscriptions'] = [
            'label' => Craft::t('commerce-quickpay', 'Subscriptions'),
            'url' => 'commerce-quickpay/subscriptions',
        ];

        $navItems['subnav']['plans'] = [
            'label' => Craft::t('commerce-quickpay', 'Plans'),
            'url' => 'commerce-quickpay/plans',
        ];

        $navItems['subnav']['planTypes'] = [
            'label' => Craft::t('commerce-quickpay', 'Plan Types'),
            'url' => 'commerce-quickpay/plan-types',
        ];

        return $navItems;
    }
}
