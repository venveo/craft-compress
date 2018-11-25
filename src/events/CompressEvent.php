<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\events;

use craft\elements\Asset;
use venveo\compress\models\Archive as ArchiveModel;
use venveo\compress\records\Archive as ArchiveRecord;
use venveo\compress\records\File;
use yii\base\Event;

class CompressEvent extends Event
{
    /** @var ArchiveRecord $archiveRecord */
    public $archiveRecord;

    /** @var Asset[] $assets */
    public $assets = [];
}
