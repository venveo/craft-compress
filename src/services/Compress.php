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
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\models\Volume;
use Illuminate\Support\Collection;
use venveo\compress\Compress as Plugin;
use venveo\compress\errors\CompressException;
use venveo\compress\events\CompressEvent;
use venveo\compress\jobs\CreateArchive;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;
use venveo\compress\records\File as FileRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;
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
    const EVENT_BEFORE_CONFIGURE_ARCHIVE = 'EVENT_BEFORE_CONFIGURE_ARCHIVE';

    /**
     * Called right after successfully saving the Archive record and its File
     * records.
     */
    const EVENT_AFTER_CONFIGURE_ARCHIVE = 'EVENT_AFTER_CONFIGURE_ARCHIVE';

    public function getArchiveModelForQuery(
        AssetQuery|Collection|array $query,
        bool $lazy = false,
        null|string $filename = null,
    ): null|ArchiveModel
    {
        // Get the assets and create a unique hash to represent them
        if ($query instanceof AssetQuery) {
            $assets = $query->all();
        } elseif ($query instanceof Collection) {
            $assets = $query->toArray();
        } else {
            $assets = $query;
        }

        $hash = $this->getHashForAssets($assets, $filename);

        // Make sure we haven't already hashed these assets. If so, return the
        // archive.
        $record = $this->getArchiveRecordByHash($hash);
        if ($record && $record->assetId && $asset = Craft::$app->assets->getAssetById($record->assetId)) {
            return ArchiveModel::hydrateFromRecord($record, $asset);
        }

        // No existing record, let's create a new one
        if (!$record instanceof ArchiveRecord) {
            $record = $this->createArchiveRecord($assets, null, $filename);
        }

        // We'll use the cache to keep track of the status of the archive to
        // avoid a race condition between the queue and the web server
        $cacheKey = 'Compress:InQueue:' . $record->uid;

        // If we're lazy, we'll just check to make sure it's not in the queue
        // already and then add it to the queue and set a cache key for the job
        if ($lazy === true) {
            // Make sure we don't run more than one job for the archive
            if (!Craft::$app->cache->get($cacheKey)) {
                $job = new CreateArchive([
                    'cacheKey' => $cacheKey,
                    'archiveUid' => $record->uid
                ]);
                $jobId = Craft::$app->queue->push($job);
                Craft::$app->cache->set($cacheKey, true);
                Craft::$app->cache->set($cacheKey . ':' . 'jobId', $jobId);
            }
            return ArchiveModel::hydrateFromRecord($record, null);
        }

        // We'll do it live!
        try {
            return $this->createArchiveAsset($record);
        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return null;
        }
    }

    public function createArchiveRecord($assets, $archiveAsset = null, ?string $filename = null): ArchiveRecord
    {
        $archive = $this->createArchiveRecords($assets, $archiveAsset, null, $filename);
        return $archive;
    }

    /**
     * @param ArchiveRecord $archiveRecord
     * @param AssetQuery $query
     * @param string $assetName
     * @return ArchiveModel
     * @throws \craft\errors\VolumeException
     * @throws \Exception
     */
    public function createArchiveAsset(ArchiveRecord $archiveRecord): ?ArchiveModel
    {
        $uuid = StringHelper::UUID();
        $fileAssetRecords = $archiveRecord->fileAssets;
        $assetIds = [];
        /** @var FileRecord $fileAssetRecord */
        foreach ($fileAssetRecords as $fileAssetRecord) {
            $assetIds[] = $fileAssetRecord->assetId;
        }
        $assetQuery = new AssetQuery(Asset::class);
        $assetQuery->id($assetIds);
        $assets = $assetQuery->all();
        $assetName = $uuid . '.zip';
        if ($archiveRecord->filename) {
            $assetName = $archiveRecord->filename . '.zip';
        }
        $tempFileName = $uuid . '.zip';
        $tempFileName = \craft\helpers\FileHelper::sanitizeFilename($tempFileName, ['separator' => null]);

        $tempDirectory = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . 'compress';
        FileHelper::createDirectory($tempDirectory);
        $zipPath = $tempDirectory . DIRECTORY_SEPARATOR . $tempFileName;
        try {
            // Create the zip
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new CompressException('Cannot create zip file at: ' . $zipPath);
            }

            $maxFileCount = Plugin::getInstance()->getSettings()->maxFileCount;
            if ($maxFileCount > 0 && count($assets) > $maxFileCount) {
                throw new CompressException('Cannot create zip; maxFileCount exceeded.');
            }

            $totalFileSize = array_reduce($assets, static fn($carry, $asset) => $carry + $asset->size, 0);
            $maxFileSize = Plugin::getInstance()->getSettings()->maxFileSize;
            if ($maxFileSize > 0 && $totalFileSize > $maxFileSize) {
                throw new CompressException('Cannot create zip; maxFileSize exceeded.');
            }
            App::maxPowerCaptain();

            foreach ($assets as $asset) {
                $zip->addFromString($asset->filename, file_get_contents($asset->url));
            }

            $zip->close();
        } catch (\Exception $e) {
            Craft::error('Failed to create zip file', __METHOD__);
            Craft::error($e->getMessage(), __METHOD__);
            Craft::error($e->getTraceAsString(), __METHOD__);
            return null;
        }
        $stream = fopen($zipPath, 'rb');

        $volumeHandle = Plugin::getInstance()->getSettings()->defaultVolumeHandle;
        $volumeSubdirectory = Plugin::getInstance()->getSettings()->defaultVolumeSubdirectory;
        /** @var Volume $volume */
        $volume = Craft::$app->volumes->getVolumeByHandle($volumeHandle);
        if (!$volume instanceof Volume) {
            throw new CompressException('Default volume not set.');
        }
        $finalFilePath = $assetName;
        if ($volumeSubdirectory) {
            $finalFilePath = $volumeSubdirectory . DIRECTORY_SEPARATOR . $finalFilePath;
        }
        $finalFilePath = FileHelper::normalizePath($finalFilePath);
        $fs = $volume->getFs();
        $fs->writeFileFromStream($finalFilePath, $stream, []);
        unlink($zipPath);
        $session = Craft::$app->getAssetIndexer()->createIndexingSession([$volume]);
        $asset = Craft::$app->getAssetIndexer()->indexFile($volume, $finalFilePath, $session->id);
        $archiveRecord->assetId = $asset->id;
        $archiveRecord->dateLastAccessed = DateTimeHelper::currentUTCDateTime();
        $archiveRecord->save();
        return ArchiveModel::hydrateFromRecord($archiveRecord, $asset);
    }

    /**
     * @param array $zippedAssets
     * @param Asset $asset
     * @param ArchiveRecord|null $archiveRecord
     * @param string|null $filename
     * @return ArchiveRecord
     * @throws Exception
     */
    private function createArchiveRecords(array $zippedAssets, ?Asset $asset = null, ?ArchiveRecord $archiveRecord = null, ?string $filename = null): ArchiveRecord
    {
        if (!$archiveRecord) {
            $archiveRecord = new ArchiveRecord();
            $archiveRecord->dateLastAccessed = DateTimeHelper::currentUTCDateTime();
            $archiveRecord->assetId = $asset->id ?? null;
            $archiveRecord->hash = $this->getHashForAssets($zippedAssets, $filename);
            if ($filename) {
                $archiveRecord->filename = FileHelper::sanitizeFilename($filename, ['separator' => null]);
            }
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
        foreach ($zippedAssets as $zippedAsset) {
            $rows[] = [
                $archiveRecord->id,
                $zippedAsset->id
            ];
        }
        $cols = ['archiveId', 'assetId'];
        Craft::$app->db->createCommand()->batchInsert(FileRecord::tableName(), $cols, $rows)->execute();

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
     * @param array $assets Asset
     * @param string|null $filename
     * @return string
     */
    private function getHashForAssets(array $assets, ?string $filename = null): string
    {
        $ids = [];
        foreach ($assets as $asset) {
            $updatedAt = $asset->dateUpdated->getTimestamp();
            $key = $asset->id . ':' . $updatedAt;
            $ids[] = $key;
        }
        sort($ids);
        $hashKey = implode('', $ids);

        if ($filename) {
            $hashKey = $filename . $hashKey;
        }
        return md5($hashKey);
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
                Craft::$app->elements->deleteElementById($archiveAsset);
            } catch (\Throwable $e) {
                Craft::error('Failed to delete an archive asset after a dependent file was deleted: ' . $e->getMessage(),
                    __METHOD__);
                Craft::error($e->getTraceAsString(), __METHOD__);
            }
        }

        if (Plugin::getInstance()->getSettings()->autoRegenerate) {
            foreach ($archiveRecordUids as $recordUid) {
                $cacheKey = 'Compress:InQueue:' . $recordUid;
                // Make sure we don't run more than one job for the archive
                if (!Craft::$app->cache->get($cacheKey)) {
                    $job = new CreateArchive([
                        'cacheKey' => $cacheKey,
                        'archiveUid' => $recordUid
                    ]);
                    $jobId = Craft::$app->queue->push($job);
                    Craft::$app->cache->set($cacheKey, true);
                    Craft::$app->cache->set($cacheKey . ':' . 'jobId', $jobId);
                    Craft::info('Regenerating archive after a file was deleted.', __METHOD__);
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
        $ids = ArrayHelper::getColumn($records, 'assetId');
        return Asset::find()->id($ids);
    }

    /**
     * @param int|null $offset
     * @param int|null $limit
     * @return array
     */
    public function getArchives(?int $offset = 0, ?int $limit = null): array
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
     * Get an archive model from its record's UID
     *
     * @param $uid
     * @return ArchiveModel|null
     */
    public function getArchiveModelByUID($uid): ?ArchiveModel
    {
        $record = ArchiveRecord::find()->where(['=', 'uid', $uid])->one();
        if (!$record instanceof ArchiveRecord) {
            return null;
        }
        return ArchiveModel::hydrateFromRecord($record);
    }


    /**
     * Deletes registered 404s that haven't been hit in a while
     * @param null $limit
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteStaleArchives($limit = null): void
    {
        $hours = Plugin::getInstance()->getSettings()->deleteStaleArchivesHours;

        $interval = DateTimeHelper::secondsToInterval($hours * 60 * 60);
        $expire = DateTimeHelper::currentUTCDateTime();
        $pastTime = $expire->sub($interval);

        $query = ArchiveRecord::find()
            ->andWhere(['<', 'dateLastAccessed', Db::prepareDateForDb($pastTime)]);

        if ($limit) {
            $query->limit($limit);
        }
        $archives = $query->all();
        foreach($archives as $archive) {
            $assetId = $archive->assetId;
            if ($assetId) {
                Craft::$app->elements->deleteElementById($assetId, null, null, true);
            }
            $archive->delete();
        }
    }


}
