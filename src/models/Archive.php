<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\models;

use craft\base\Model;
use craft\db\ActiveRecord;
use craft\elements\Asset;
use craft\helpers\UrlHelper;
use DateTime;
use venveo\compress\Compress as Plugin;

/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class Archive extends Model
{
    public $id;
    public $uid;
    public $assetId;
    public $hash;

    public ?DateTime $dateUpdated;
    public ?DateTime $dateCreated;
    public ?DateTime $dateLastAccessed = null;

    public $asset;


    /**
     * @param ActiveRecord $record
     * @param Asset|null $asset
     * @return Archive
     */
    public static function hydrateFromRecord(ActiveRecord $record, Asset $asset = null): Archive
    {
        $instance = new self($record->toArray());
        if ($asset instanceof Asset) {
            $instance->asset = $asset;
        }
        return $instance;
    }

    /**
     * @return Asset|null
     */
    public function getAsset()
    {
        if (!$this->assetId) {
            return null;
        }
        if ($this->asset instanceof Asset) {
            return $this->asset;
        }

        $this->asset = \Craft::$app->assets->getAssetById($this->assetId);
        return $this->asset;
    }

    /**
     * @return string|null
     */
    public function getLazyLink()
    {
        if ($this->asset instanceof Asset) {
            return $this->asset->getUrl();
        }
        return UrlHelper::actionUrl('compress/compress/get-link', ['uid' => $this->uid]);
    }

    public function getContents()
    {
        return Plugin::$plugin->compress->getArchiveContents($this);
    }

    /**
     * Check if the asset has been generated
     * @return bool
     */
    public function isReady(): bool
    {
        if (!$this->assetId) {
            return false;
        }
        if ($this->getAsset() instanceof Asset) {
            return true;
        }

        return false;
    }
}
