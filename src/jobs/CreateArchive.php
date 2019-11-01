<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\jobs;

use venveo\compress\Compress;

use Craft;
use craft\queue\BaseJob;
use venveo\compress\records\Archive as ArchiveRecord;

/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class CreateArchive extends BaseJob
{
    // Public Properties
    // =========================================================================

    public $archiveUid;
    public $filename = '';
    public $cacheKey = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        // If it's not in the cache, we'll assume it got completed on-demand
        if (!\Craft::$app->cache->get($this->cacheKey)) {
            Craft::info('Archive already completed');
            return true;
        }

        $archiveRecord = ArchiveRecord::find()->where(['=', 'uid', $this->archiveUid])->one();
        if (!$archiveRecord instanceof ArchiveRecord) {
            Craft::error('Archive was deleted before it could be created');
            return false;
        }

        try {
            Compress::$plugin->compress->createArchiveAsset($archiveRecord, $this->filename);
        } catch (\Exception $e) {
            Craft::error('Failed to create archive', 'craft-compress');
            Craft::error($e->getMessage(), 'craft-compress');
            Craft::error($e->getTraceAsString(), 'craft-compress');

            \Craft::$app->cache->delete($this->cacheKey);
            \Craft::$app->cache->delete($this->cacheKey.':jobId');
            // Go ahead and blow up
            return false;
        }
        \Craft::$app->cache->delete($this->cacheKey);
        \Craft::$app->cache->delete($this->cacheKey.':jobId');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('craft-compress', 'Creating Archive '. $this->filename);
    }
}
