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
 *
 * @property-read mixed $contents
 * @property-read null|string $lazyLink
 */
class Archive extends Model
{
    public ?int $id = null;
    public ?string $uid = null;
    public ?int $assetId = null;
    public ?string $hash = null;

    public ?DateTime $dateUpdated;
    public ?DateTime $dateCreated;
    public ?DateTime $dateLastAccessed = null;

    public ?Asset $asset = null;


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
    public function getAsset($siteId = null): ?Asset
    {
        if (!$this->assetId) {
            return null;
        }
        if ($this->asset instanceof Asset) {
            return $this->asset;
        }
        if (!$siteId) {
            $siteId = \Craft::$app->sites?->currentSite?->id;
        }

        $this->asset = \Craft::$app->assets->getAssetById($this->assetId, $siteId);
        return $this->asset;
    }

    /**
     * @return string|null
     */
    public function getLazyLink(): ?string
    {
        if ($this->asset instanceof Asset) {
            // Ensure we _can_ get a url for the asset
            $assetUrl = $this->asset->getUrl();
            if($assetUrl) {
                return $assetUrl;
            }
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
