<?php

namespace QD\commerce\quickpay\migrations;

use Craft;
use craft\db\Migration;
use QD\commerce\quickpay\base\Table;
use craft\commerce\db\Table as CommerceDbTable;

/**
 * m201018_181407_carddata migration.
 */
class m201020_120200_orderinfo extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::ORDERINFO, [
            'id' => $this->integer()->notNull(),
            'subscriptionId' => $this->integer()->null(),
            'PRIMARY KEY([[id]])',
        ]);

		//Orderinfo
		$this->addForeignKey(null, Table::ORDERINFO, ['id'], CommerceDbTable::ORDERS, ['id'], 'CASCADE', 'CASCADE');
		$this->addForeignKey(null, Table::ORDERINFO, ['subscriptionId'], Table::SUBSCRIPTIONS, ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201020_120200_orderinfo cannot be reverted.\n";
        return false;
    }
}
