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

use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use venveo\compress\Compress as Plugin;
use venveo\compress\errors\CompressException;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;
use yii\base\InvalidConfigException;
use yii\web\RangeNotSatisfiableHttpException;

/**
 * Class CompressController
 * @package venveo\compress\controllers
 */
class CompressController extends Controller
{
    public int|bool|array $allowAnonymous = ['get-link'];

    /**
     * Gets a direct link to the asset
     * @param $uid
     * @return \yii\web\Response
     * @throws CompressException
     * @throws InvalidConfigException
     * @throws RangeNotSatisfiableHttpException
     */
    public function actionGetLink($uid)
    {
        /** @var ArchiveRecord $record */
        $record = ArchiveRecord::find()->where(['=', 'uid', $uid])->one();
        if (!$record) {
            return \Craft::$app->response->setStatusCode(404, 'Archive could not be found');
        }

        // If the asset is ready, send it over
        if ($record->assetId) {
            $archiveModel = ArchiveModel::hydrateFromRecord($record);
            // It's possible for an asset ID to exist, but getAsset to return false on soft-deleted assets
            $archiveAsset = $archiveModel->getAsset();
            if ($archiveAsset) {
                $record->dateLastAccessed = DateTimeHelper::currentUTCDateTime();
                $record->save();
                return $this->getAssetResponse($archiveModel->getAsset());
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
            return $this->getAssetResponse($archiveModel->getAsset());
        } catch (\Exception $e) {
            \Craft::error('Archive could not be generated: ' . $e->getMessage(), __METHOD__);
            \Craft::error($e->getTraceAsString(), __METHOD__);
            return \Craft::$app->response->setStatusCode(404, 'Could not produce zip file URL.');
        }
    }

    /**
     * @param $archiveAsset
     * @return \craft\web\Response|\yii\console\Response|\yii\web\Response
     * @throws RangeNotSatisfiableHttpException
     */
    protected function getAssetResponse($archiveAsset) {
        if (!$archiveAsset) {
            return \Craft::$app->response->setStatusCode(404, 'Could not produce zip file URL.');
        }
        $assetUrl = $archiveAsset->getUrl();

        // If we have a public URL for the asset, we'll just 302 redirect to it
        if ($assetUrl) {
            return \Craft::$app->response->redirect($archiveAsset->getUrl());
        }
        App::maxPowerCaptain();
        // No public URLs, we'll need to stream the response.
        return $this->response
            ->sendStreamAsFile($archiveAsset->getStream(), $archiveAsset->getFilename(), [
                'fileSize' => $archiveAsset->size,
                'mimeType' => $archiveAsset->getMimeType(),
            ]);
    }
}
