<?php


namespace venveo\compress\records;

use craft\db\ActiveRecord;
use craft\records\Asset;
use craft\records\Site;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * @property Site $site
 * @property User $owner
 * @property Asset $asset
 * @property Archive $archive
 * @property integer id
 * @property integer archiveId
 */
class File extends ActiveRecord
{
    /*
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%compress_files}}';
    }

    /**
     * @return ActiveQueryInterface The relational query object.
     */
    public function getArchive(): ActiveQueryInterface
    {
        return $this->hasOne(Archive::class, ['id' => 'archiveId']);
    }

    /**
     * @return ActiveQueryInterface The relational query object.
     */
    public function getAsset(): ActiveQueryInterface
    {
        return $this->hasOne(Asset::class, ['id' => 'assetId']);
    }
}
