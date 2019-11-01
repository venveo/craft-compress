<?php

namespace venveo\compress\migrations;

use Craft;
use craft\db\Migration;

/**
 * The Install Migration covers all install migrations
 *
 * @since 1.0
 */
class Install extends Migration
{
    public $driver;

    /*
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /*
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
        return true;
    }

    /**
     * Creates all necessary tables for this plugin
     *
     * @return bool
     */
    protected function createTables()
    {
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%compress_archives}}');
        if ($tableSchema === null) {
            $this->createTable(
                '{{%compress_archives}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'assetId' => $this->integer(),
                    'hash' => $this->string()->notNull(),
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%compress_files}}');
        if ($tableSchema === null) {
            $this->createTable(
                '{{%compress_files}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'archiveId' => $this->integer()->notNull(),
                    'assetId' => $this->integer()->notNull(),
                ]
            );
        }

        return true;
    }

    protected function createIndexes()
    {
        $this->createIndex(null, '{{%compress_archives}}', ['hash'], true);
    }

    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%compress_archives}}', ['assetId'], '{{%assets}}', ['id'], 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, '{{%compress_files}}', ['archiveId'], '{{%compress_archives}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%compress_files}}', ['assetId'], '{{%assets}}', ['id'], 'CASCADE', 'CASCADE');
    }

    protected function removeTables()
    {
        $this->dropTableIfExists('{{%compress_files}}');
        $this->dropTableIfExists('{{%compress_archives}}');
    }
}
