<?php

namespace QD\commerce\quickpay\migrations;

use Craft;
use craft\db\Migration;

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
	}

	protected function createIndexes()
	{
	}

	protected function addForeignKeys()
	{
	}

	protected function dropForeignKeys()
	{
	}

	protected function dropTables()
	{
	}
}
