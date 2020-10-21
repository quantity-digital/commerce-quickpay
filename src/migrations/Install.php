<?php

namespace QD\commerce\quickpay\migrations;

use Craft;
use craft\commerce\db\Table as DbTable;
use craft\db\Migration;
use craft\db\Table as CraftDbTable;
use craft\commerce\db\Table as CommerceDbTable;
use QD\commerce\quickpay\base\Table;

class Install extends Migration
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{

		$this->createTables();
		$this->createIndexes();
		$this->addForeignKeys();

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool
	{
		$this->dropForeignKeys();
		$this->dropTables();
		$this->dropProjectConfig();
		return true;
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Deletes the project config entry.
	 */
	protected function dropProjectConfig()
	{
		Craft::$app->projectConfig->remove('commerce-quickpay');
	}

	protected function createTables()
	{
		$this->createTable(Table::SUBSCRIPTIONS, [
			'id' => $this->primaryKey(),
			'userId' => $this->integer()->notNull(),
			'planId' => $this->integer(),
			'orderId' => $this->integer(),
			'subscriptionData' => $this->text(),
			'trialDays' => $this->integer()->notNull(),
			'nextPaymentDate' => $this->dateTime(),
			'hasStarted' => $this->boolean()->notNull()->defaultValue(false),
			'isCanceled' => $this->boolean()->notNull()->defaultValue(false),
			'isSuspended' => $this->boolean()->notNull()->defaultValue(false),
			'dateCanceled' => $this->dateTime(),
			'dateExpired' => $this->dateTime(),
			'dateSuspended' => $this->dateTime(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'dateStarted' => $this->dateTime()->notNull(),
			'cardExpireYear' => $this->integer(),
			'cardExpireMonth' => $this->integer(),
			'cardLast4' => $this->string(),
			'cardBrand' => $this->string(),
			'quickpayReference' => $this->string(),
			'uid' => $this->uid(),
		]);

		$this->createTable(Table::PLANS, [
			'id' => $this->primaryKey(),
			'title' => $this->string()->notNull(),
			'slug' => $this->string()->notNull(),
			'planInterval' => $this->string()->notNull(),
			'trialDays' => $this->integer()->defaultValue(0),
			'expiryDate' => $this->dateTime(),
			'postDate' => $this->dateTime()->notNull(),
			'price' => $this->decimal(12, 2)->notNull(),
			'typeId' => $this->integer(),
			'taxCategoryId' => $this->integer()->notNull(),
			'shippingCategoryId' => $this->integer()->notNull(),
			'sku' => $this->string()->notNull(),
			'sku' => $this->integer(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable(Table::PLANTYPES, [
			'id' => $this->primaryKey(),
			'fieldLayoutId' => $this->integer(),
			'name' => $this->string()->notNull(),
			'handle' => $this->string()->notNull(),
			'skuFormat' => $this->string(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable(Table::PLANTYPES_SITES, [
			'id' => $this->primaryKey(),
			'planTypeId' => $this->integer()->notNull(),
			'siteId' => $this->integer()->notNull(),
			'uriFormat' => $this->text(),
			'template' => $this->string(500),
			'hasUrls' => $this->boolean(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable(Table::PURCHASABLES, [
			'id' => $this->primaryKey(),
			'planId' => $this->integer()->notNull(),
			'purchasableId' => $this->integer()->notNull(),
			'purchasableType' => $this->string()->notNull(),
			'qty' => $this->integer(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable(Table::ORDERINFO, [
            'id' => $this->integer()->notNull(),
            'subscriptionId' => $this->integer()->null(),
            'PRIMARY KEY([[id]])',
        ]);
	}

	protected function createIndexes()
	{
		//Subscriptions indexes
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'userId');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'planId');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'nextPaymentDate');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'isCanceled');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'isSuspended');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'dateCreated');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'dateStarted');
		$this->createIndex(null, Table::SUBSCRIPTIONS, 'dateExpired');

		//Plans indexes
		$this->createIndex(null, Table::PLANS, 'typeId', false);
		$this->createIndex(null, Table::PLANS, 'taxCategoryId', false);
		$this->createIndex(null, Table::PLANS, 'shippingCategoryId', false);

		//Purchables indexes
		$this->createIndex(null, Table::PURCHASABLES, 'planId', false);
		$this->createIndex(null, Table::PURCHASABLES, 'purchasableId', false);

		//Plantypes indexes
		$this->createIndex(null, Table::PLANTYPES, 'handle', true);
		$this->createIndex(null, Table::PLANTYPES, 'fieldLayoutId', false);

		//Sites indexes
		$this->createIndex(null, Table::PLANTYPES_SITES, ['planTypeId', 'siteId'], true);
		$this->createIndex(null, Table::PLANTYPES_SITES, 'siteId', false);
	}

	protected function addForeignKeys()
	{
		//Subscriptions foreignkeys
		$this->addForeignKey(null, Table::SUBSCRIPTIONS, 'userId', CraftDbTable::USERS, 'id', 'RESTRICT');
		$this->addForeignKey(null, Table::SUBSCRIPTIONS, 'planId', Table::PLANS, 'id', 'RESTRICT');
		$this->addForeignKey(null, Table::SUBSCRIPTIONS, 'orderId', DbTable::ORDERS, 'id', 'SET NULL');

		//Plans
		$this->addForeignKey(null, Table::PLANS, 'id', CraftDbTable::ELEMENTS, 'id', 'CASCADE', null);
		$this->addForeignKey(null, Table::PLANS, 'shippingCategoryId', DbTable::SHIPPINGCATEGORIES, 'id', null, null);
		$this->addForeignKey(null, Table::PLANS, 'taxCategoryId', DbTable::TAXCATEGORIES, 'id', null, null);
		$this->addForeignKey(null, Table::PLANS, 'typeId', Table::PLANTYPES, 'id', 'CASCADE', null);

		//Purchables
		$this->addForeignKey(null, Table::PURCHASABLES, 'planId', Table::PLANS, 'id', 'CASCADE', null);
		$this->addForeignKey(null, Table::PURCHASABLES, 'purchasableId', DbTable::PURCHASABLES, 'id', 'CASCADE', null);

		//Plantypes
		$this->addForeignKey(null, Table::PLANTYPES, 'fieldLayoutId', CraftDbTable::FIELDLAYOUTS, 'id', 'SET NULL', null);

		//Sites
		$this->addForeignKey(null, Table::PLANTYPES_SITES, 'siteId', CraftDbTable::SITES, 'id', 'CASCADE', 'CASCADE');
		$this->addForeignKey(null, Table::PLANTYPES_SITES, 'planTypeId', Table::PLANTYPES, 'id', 'CASCADE', null);

		//Orderinfo
		$this->addForeignKey(null, Table::ORDERINFO, ['id'], CommerceDbTable::ORDERS, ['id'], 'CASCADE', 'CASCADE');
		$this->addForeignKey(null, Table::ORDERINFO, ['subscriptionId'], Table::SUBSCRIPTIONS, ['id'], 'CASCADE', 'CASCADE');
	}

	protected function dropForeignKeys()
	{
		$this->dropForeignKey('quickpay_subscriptions_userId_fk',Table::SUBSCRIPTIONS);
		$this->dropForeignKey('quickpay_subscriptions_planId_fk',Table::SUBSCRIPTIONS);
		$this->dropForeignKey('quickpay_subscriptions_orderId_fk',Table::SUBSCRIPTIONS);

		$this->dropForeignKey('quickpay_plans_id_fk',Table::PLANS);
		$this->dropForeignKey('quickpay_plans_shippingCategoryId_fk',Table::PLANS);
		$this->dropForeignKey('quickpay_plans_taxCategoryId_fk',Table::PLANS);
		$this->dropForeignKey('quickpay_plans_typeId_fk',Table::PLANS);

		$this->dropForeignKey('quickpay_purchasables_planId_fk',Table::PURCHASABLES);
		$this->dropForeignKey('quickpay_purchasables_purchasableId_fk',Table::PURCHASABLES);

		$this->dropForeignKey('quickpay_plantypes_fieldLayoutId_fk',Table::PLANTYPES);

		$this->dropForeignKey('quickpay_plantypes_sites_planTypeId_fk',Table::PLANTYPES_SITES);
		$this->dropForeignKey('quickpay_plantypes_sites_siteId_fk',Table::PLANTYPES_SITES);

		$this->dropForeignKey('quickpay_orderinfo_id_fk',Table::ORDERINFO);
		$this->dropForeignKey('quickpay_orderinfo_subscriptionId_fk',Table::ORDERINFO);
	}

	protected function dropTables()
	{
		$this->dropTableIfExists(Table::PLANS);
		$this->dropTableIfExists(Table::PLANTYPES_SITES);
		$this->dropTableIfExists(Table::PLANTYPES);
		$this->dropTableIfExists(Table::PURCHASABLES);
		$this->dropTableIfExists(Table::SUBSCRIPTIONS);
		$this->dropTableIfExists(Table::ORDERINFO);
	}
}
