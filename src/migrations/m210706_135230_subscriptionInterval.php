<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use QD\commerce\quickpay\base\Table;

/**
 * m210706_135230_subscriptionInterval migration.
 */
class m210706_135230_subscriptionInterval extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		$this->addColumn(Table::PLANS, 'subscriptionInterval', $this->string()->notNull());
		$this->addColumn(Table::SUBSCRIPTIONS, 'subscriptionEndDate', $this->dateTime());
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		echo "m210706_135230_subscriptionInterval cannot be reverted.\n";
		return false;
	}
}
