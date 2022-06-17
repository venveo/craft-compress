<?php

namespace venveo\compress\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220617_145938_add_filename_support migration.
 */
class m220617_145938_add_filename_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%compress_archives}}', 'filename', $this->string()->after('uid'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220617_145938_add_filename_support cannot be reverted.\n";
        return false;
    }
}
