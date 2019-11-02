<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use venveo\compress\Compress as Plugin;
use venveo\compress\errors\CompressException;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;

/**
 * Class CompressController
 * @package venveo\compress\controllers
 */
class CompressController extends Controller
{
    public $allowAnonymous = ['get-link'];

    /**
     * Gets a direct link to the asset
     * @param $uid
     * @return \craft\web\Response|string|\yii\console\Response
     * @throws CompressException
     */
    public function actionGetLink($uid)
    {
        $record = ArchiveRecord::find()->where(['=', 'uid', $uid])->one();
        if (!$record instanceof ArchiveRecord) {
            return \Craft::$app->response->setStatusCode(404, 'Archive could not be found');
        }

        // If the asset is ready, redirect to its URL
        if ($record->assetId) {
            $archiveModel = ArchiveModel::hydrateFromRecord($record);
            // It's possible for an asset ID to exist, but getAsset to return false on soft-deleted assets
            if ($archiveModel->getAsset()) {
                $record->dateLastAccessed = DateTimeHelper::currentUTCDateTime();
                $record->save();
                return \Craft::$app->response->redirect($archiveModel->getAsset()->getUrl());
            }
        }

        // We need to generate the asset NOW!
        try {
            $archiveModel = Plugin::$plugin->compress->createArchiveAsset($record);

            // Now that we have the asset, let's make sure the queue doesn't run
            $cacheKey = 'Compress:InQueue:' . $record->uid;
            if (\Craft::$app->cache->get('Compress:InQueue:' . $record->uid)) {
                $jobId = \Craft::$app->cache->get($cacheKey . ':jobId');
                if ($jobId) {
                    \Craft::$app->queue->release($jobId);
                    \Craft::$app->cache->delete($cacheKey);
                    \Craft::$app->cache->delete($cacheKey . ':jobId');
                }
            }
            return \Craft::$app->response->redirect($archiveModel->asset->getUrl());
        } catch (\Exception $e) {
            \Craft::error('Archive could not be generated: ' . $e->getMessage(), __METHOD__);
            \Craft::error($e->getTraceAsString(), __METHOD__);
            throw new CompressException('Archive could not be generated: '. $e->getMessage());
        }
    }
}
