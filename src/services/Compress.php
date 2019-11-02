<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\services;

use Craft;
use craft\base\Component;
use craft\base\Volume;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\StringHelper;
use venveo\compress\Compress as Plugin;
use venveo\compress\errors\CompressException;
use venveo\compress\events\CompressEvent;
use venveo\compress\jobs\CreateArchive;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;
use venveo\compress\records\File as FileRecord;
use ZipArchive;


/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class Compress extends Component
{
    /**
     * Called right before saving the archive records. You may take this
     * opportunity to modify the list of files being stored in the archive
     */
    public const EVENT_BEFORE_CONFIGURE_ARCHIVE = 'EVENT_BEFORE_CONFIGURE_ARCHIVE';

    /**
     * Called right after successfully saving the Archive record and its File
     * records.
     */
    public const EVENT_AFTER_CONFIGURE_ARCHIVE = 'EVENT_AFTER_CONFIGURE_ARCHIVE';

    /**
     * @param AssetQuery $query
     * @param bool $lazy
     * @param null $filename
     * @return ArchiveModel|null
     */
    public function getArchiveModelForQuery($query, $lazy = false, $filename = null)
    {
        // Get the assets and create a unique hash to represent them
        if ($query instanceof AssetQuery) {
            $assets = $query->all();
        } elseif (is_array($query)) {
            $assets = $query;
        } else {
            Craft::error('Unexpected input provided for asset query', __METHOD__);
            return null;
        }

        $hash = $this->getHashForAssets($assets);

        // Make sure we haven't already hashed these assets. If so, return the
        // archive.
        $record = $this->getArchiveRecordByHash($hash);
        if ($record instanceof ArchiveRecord && isset($record->assetId)) {
            $asset = \Craft::$app->assets->getAssetById($record->assetId);
            return ArchiveModel::hydrateFromRecord($record, $asset);
        }

        // No existing record, let's create a new one
        if (!$record instanceof ArchiveRecord) {
            $record = $this->createArchiveRecord($assets, null);
        }

        // We'll use the cache to keep track of the status of the archive to
        // avoid a race condition between the queue and the web server
        $cacheKey = 'Compress:InQueue:' . $record->uid;

        // If we're lazy, we'll just check to make sure it's not in the queue
        // already and then add it to the queue and set a cache key for the job
        if ($lazy === true) {
            // Make sure we don't run more than one job for the archive
            if (!\Craft::$app->cache->get($cacheKey)) {
                $job = new CreateArchive([
                    'cacheKey' => $cacheKey,
                    'archiveUid' => $record->uid,
                    'filename' => $filename
                ]);
                $jobId = \Craft::$app->queue->push($job);
                \Craft::$app->cache->set($cacheKey, true);
                \Craft::$app->cache->set($cacheKey . ':' . 'jobId', $jobId);
            }
            return ArchiveModel::hydrateFromRecord($record, null);
        }

        // We'll do it live!
        try {
            return $this->createArchiveAsset($record, $filename);
        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return null;
        }
    }

    public function createArchiveRecord($assets, $archiveAsset = null): ArchiveRecord
    {
        $archive = $this->createArchiveRecords($assets, $archiveAsset);
        return $archive;
    }

    /**
     * @param ArchiveRecord $archiveRecord
     * @param AssetQuery $query
     * @param string $assetName
     * @return ArchiveModel
     * @throws \craft\errors\VolumeException
     * @throws \craft\errors\VolumeObjectExistsException
     * @throws \craft\errors\VolumeObjectNotFoundException
     * @throws \Exception
     */
    public function createArchiveAsset(ArchiveRecord $archiveRecord)
    {
        $uuid = StringHelper::UUID();
        $fileAssetRecords = $archiveRecord->fileAssets;
        $assetIds = [];
        /** @var File $fileAssetRecord */
        foreach ($fileAssetRecords as $fileAssetRecord) {
            $assetIds[] = $fileAssetRecord->assetId;
        }
        $assetQuery = new AssetQuery(Asset::class);
        $assetQuery->id($assetIds);
        $assets = $assetQuery->all();
        $assetName = $uuid . '.zip';
        $filename = $uuid . '.zip';
        // Create the SupportAttachment zip
        $zipPath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $filename;

        try {
            // Create the zip
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new CompressException('Cannot create zip file at: ' . $zipPath);
            }

            $maxFileCount = Plugin::$plugin->getSettings()->maxFileCount;
            if ($maxFileCount > 0 && count($assets) > $maxFileCount) {
                throw new CompressException('Cannot create zip; maxFileCount exceeded.');
            }

            $totalFileSize = 0;
            $maxFileSize = Plugin::$plugin->getSettings()->maxFileSize;
            foreach ($assets as $asset) {
                $totalFileSize += $asset->size;
                if ($maxFileSize > 0 && $totalFileSize > $maxFileSize) {
                    throw new CompressException('Cannot create zip; maxFileSize exceeded.');
                }
                $zip->addFile($asset->getCopyOfFile(), $asset->filename);
            }

            $zip->close();
        } catch (\Exception $e) {
            Craft::error('Failed to create zip file', __METHOD__);
            Craft::error($e->getMessage(), __METHOD__);
            Craft::error($e->getTraceAsString(), __METHOD__);
            return null;
        }
        $stream = fopen($zipPath, 'rb');

        $volumeHandle = Plugin::$plugin->getSettings()->defaultVolumeHandle;
        /** @var Volume $volume */
        $volume = Craft::$app->volumes->getVolumeByHandle($volumeHandle);
        if (!$volume instanceof Volume) {
            throw new CompressException('Default volume not set.');
        }
        $finalFilePath = $assetName;
        $volume->createFileByStream($finalFilePath, $stream, []);
        unlink($zipPath);
        $asset = Craft::$app->getAssetIndexer()->indexFile($volume, $finalFilePath);
        $archiveRecord->assetId = $asset->id;
        $archiveRecord->save();
        return ArchiveModel::hydrateFromRecord($archiveRecord, $asset);
    }

    /**
     * @param $zippedAssets
     * @param $asset
     * @param null $archiveRecord
     * @return ArchiveRecord
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    private function createArchiveRecords($zippedAssets, $asset, $archiveRecord = null): ArchiveRecord
    {
        if (!$archiveRecord instanceof ArchiveRecord) {
            $archiveRecord = new ArchiveRecord();
            $archiveRecord->assetId = $asset->id ?? null;
            $archiveRecord->hash = $this->getHashForAssets($zippedAssets);
            $archiveRecord->save();
        }

        $event = new CompressEvent([
            'archiveRecord' => $archiveRecord,
            'assets' => $zippedAssets
        ]);
        $this->trigger(self::EVENT_BEFORE_CONFIGURE_ARCHIVE, $event);

        $zippedAssets = $event->assets;
        $archiveRecord = $event->archiveRecord;

        $rows = [];
        /** @var Asset $zippedAsset */
        foreach ($zippedAssets as $zippedAsset) {
            $rows[] = [
                $archiveRecord->id,
                $zippedAsset->id
            ];
        }
        $cols = ['archiveId', 'assetId'];
        \Craft::$app->db->createCommand()->batchInsert(FileRecord::tableName(), $cols, $rows)->execute();

        $event = new CompressEvent([
            'archiveRecord' => $archiveRecord,
            'assets' => $zippedAssets
        ]);
        $this->trigger(self::EVENT_AFTER_CONFIGURE_ARCHIVE, $event);

        return $archiveRecord;
    }

    /**
     * Creates a hash
     *
     * @param $assets Asset[]
     * @return string
     */
    private function getHashForAssets($assets): string
    {
        $ids = [];
        foreach ($assets as $asset) {
            $ids[] = [$asset->id];
        }
        return md5(\GuzzleHttp\json_encode($ids));
    }

    /**
     * Get an archive record from a hash generated based on its contents
     *
     * @param $hash
     * @return ArchiveRecord|null
     */
    private function getArchiveRecordByHash($hash): ?ArchiveRecord
    {
        return ArchiveRecord::findOne(['hash' => $hash]);
    }

    /**
     * Deletes archives associated with deleted files to force them to be regenerated
     *
     * @param Asset $asset
     */
    public function handleAssetUpdated(Asset $asset): void
    {
        // Get the files this affects and the archives. We're just going to
        // delete the asset for the archive to prompt it to regenerate.
        // the file records will be deleted when its asset is deleted
        $fileRecords = FileRecord::find()->where(['=', 'assetId', $asset->id])->with('archive')->all();

        $archiveAssets = [];
        $archiveRecordUids = [];
        /** @var FileRecord $fileRecord */
        foreach ($fileRecords as $fileRecord) {
            $archiveAssets[] = $fileRecord->archive->assetId;
            $archiveRecordUids[] = $fileRecord->archive->uid;
        }
        $archiveAssets = array_unique($archiveAssets);
        $archiveRecordUids = array_unique($archiveRecordUids);
        foreach ($archiveAssets as $archiveAsset) {
            try {
                \Craft::$app->elements->deleteElementById($archiveAsset);
            } catch (\Throwable $e) {
                Craft::error('Failed to delete an archive asset after a dependent file was deleted: ' . $e->getMessage(), __METHOD__);
                Craft::error($e->getTraceAsString(), __METHOD__);
            }
        }

        if (Plugin::$plugin->getSettings()->autoRegenerate) {
            foreach ($archiveRecordUids as $recordUid) {
                $cacheKey = 'Compress:InQueue:' . $recordUid;
                // Make sure we don't run more than one job for the archive
                if (!\Craft::$app->cache->get($cacheKey)) {
                    $job = new CreateArchive([
                        'cacheKey' => $cacheKey,
                        'archiveUid' => $recordUid
                    ]);
                    $jobId = \Craft::$app->queue->push($job);
                    \Craft::$app->cache->set($cacheKey, true);
                    \Craft::$app->cache->set($cacheKey . ':' . 'jobId', $jobId);
                    \Craft::info('Regenerating archive after a file was deleted.', __METHOD__);
                }
            }
        }

    }

    /**
     * @param ArchiveModel $archive
     * @return AssetQuery
     */
    public function getArchiveContents(ArchiveModel $archive): AssetQuery
    {
        $records = FileRecord::find()->where(['=', 'archiveId', $archive->id])->select(['assetId'])->asArray()->all();
        // There has to be a better way to do this...
        $ids = [];
        /** FileRecord $record */
        foreach ($records as $record) {
            $ids[] = $record['assetId'];
        }
        return (new AssetQuery(Asset::class))->id($ids);
    }

    /**
     * @param int $offset
     * @param null $limit
     * @return array
     */
    public function getArchives($offset = 0, $limit = null): array
    {
        $records = ArchiveRecord::find();
        if ($offset) {
            $records->offset($offset);
        }
        if ($limit) {
            $records->limit($limit);
        }
        $records = $records->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = ArchiveModel::hydrateFromRecord($record);
        }
        return $models;
    }

    /**
     * Get an archive model from it's record's UID
     *
     * @param $uid
     * @return ArchiveModel|null
     */
    public function getArchiveModelByUID($uid)
    {
        $record = ArchiveRecord::find()->where(['=', 'uid', $uid])->one();
        if (!$record instanceof ArchiveRecord) {
            return null;
        }
        return ArchiveModel::hydrateFromRecord($record);
    }

}
