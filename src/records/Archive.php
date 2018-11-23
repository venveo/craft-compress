<?php


namespace venveo\compress\records;

use craft\db\ActiveRecord;
use craft\records\Asset;
use craft\records\Site;
use Ramsey\Uuid\Uuid;
use yii\db\ActiveQueryInterface;

/**
 * @property int|null ownerId
 * @property integer siteId
 * @property \yii\db\ActiveQueryInterface $site
 * @property \yii\db\ActiveQueryInterface $asset
 * @property mixed $fileAssets
 * @property integer id
 * @property Uuid uid
 * @property integer assetId
 * @property string hash
 */
class Archive extends ActiveRecord
{
    /*
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%compress_archives}}';
    }

    /**
     * @return ActiveQueryInterface The relational query object.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }

    /**
     * @return ActiveQueryInterface The relational query object.
     */
    public function getAsset(): ActiveQueryInterface
    {
        return $this->hasOne(Asset::class, ['id' => 'assetId']);
    }

    public function getFileAssets()
    {
        return $this->hasMany(File::class, ['archiveId' => 'id']);
    }
}
