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
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%compress_archives}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%compress_archives}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'siteId' => $this->integer()->notNull(),
                    'assetId' => $this->integer(),
                    'hash' => $this->string()->notNull(),
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%compress_files}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%compress_files}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'siteId' => $this->integer()->notNull(),
                    'archiveId' => $this->integer()->notNull(),
                    'assetId' => $this->integer()->notNull(),
                ]
            );
        }

        return $tablesCreated;
    }


    protected function createIndexes()
    {
        $this->createIndex(null, '{{%compress_archives}}', ['siteId'], false);
        $this->createIndex(null, '{{%compress_archives}}', ['assetId'], true);
        $this->createIndex(null, '{{%compress_archives}}', ['hash'], true);

        $this->createIndex(null, '{{%compress_files}}', ['archiveId'], false);
        $this->createIndex(null, '{{%compress_files}}', ['siteId'], false);
        $this->createIndex(null, '{{%compress_files}}', ['assetId'], false);
    }

    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%compress_archives}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%compress_archives}}', ['assetId'], '{{%assets}}', ['id'], 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, '{{%compress_files}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%compress_files}}', ['archiveId'], '{{%compress_archives}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%compress_files}}', ['assetId'], '{{%assets}}', ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * Remove all tables created by this plugin
     *
     * @return bool
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%compress_files}}');
        $this->dropTableIfExists('{{%compress_archives}}');
    }
}
