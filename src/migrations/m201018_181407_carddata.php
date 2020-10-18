<?php

namespace QD\commerce\quickpay\migrations;

use Craft;
use craft\db\Migration;
use QD\commerce\quickpay\base\Table;

/**
 * m201018_181407_carddata migration.
 */
class m201018_181407_carddata extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::SUBSCRIPTIONS,'cardLast4',$this->string());
        $this->addColumn(Table::SUBSCRIPTIONS,'cardExpireMonth',$this->integer());
        $this->addColumn(Table::SUBSCRIPTIONS,'cardExpireYear',$this->integer());
        $this->addColumn(Table::SUBSCRIPTIONS,'cardBrand',$this->string());
		$this->addColumn(Table::SUBSCRIPTIONS,'dateStarted', $this->dateTime()->notNull());

		$this->createIndex(null, Table::SUBSCRIPTIONS, 'dateStarted');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201018_181407_carddata cannot be reverted.\n";
        return false;
    }
}
