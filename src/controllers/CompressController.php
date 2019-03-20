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

use craft\web\Controller;
use venveo\compress\Compress as Plugin;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;

class CompressController extends Controller
{
    public $allowAnonymous = ['getLink'];

    /**
     * Gets a direct link to the asset
     */
    public function actionGetLink($uid)
    {
        $record = ArchiveRecord::find()->where(['=', 'uid', $uid])->one();
        if (!$record instanceof ArchiveRecord) {
            \Craft::$app->response->setStatusCode(404);
            return 'Archive could not be found';
        }

        // If the asset is ready, redirect to its URL
        if ($record->assetId) {
            $archiveModel = ArchiveModel::hydrateFromRecord($record);
            return \Craft::$app->response->redirect($archiveModel->getAsset()->getUrl());
        }

        // We need to generate the asset NOW!
        try {
            $asset = Plugin::$plugin->compress->createArchiveAsset($record);

            // Now that we have the asset, let's make sure the queue doesn't run
            $cacheKey = 'Compress:InQueue:'.$record->uid;
            if (\Craft::$app->cache->get('Compress:InQueue:'.$record->uid)) {
                $jobId = \Craft::$app->cache->get($cacheKey . ':jobId');
                if ($jobId) {
                    \Craft::$app->queue->release($jobId);
                    \Craft::$app->cache->delete($cacheKey);
                    \Craft::$app->cache->delete($cacheKey . ':jobId');
                }
            }
            return \Craft::$app->response->redirect($asset->getUrl());
        } catch (\Exception $e) {
            \Craft::$app->response->setStatusCode(500);
            \Craft::error('Archive could not be generated: '.$e->getMessage(), 'craft-compress');
            \Craft::error($e->getTraceAsString(), 'craft-compress');
            return 'Archive could not be generated';
        }
    }
}
