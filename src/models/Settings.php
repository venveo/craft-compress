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

/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * The handle for the volume where archives are stored
     */
    public string $defaultVolumeHandle = '';

    /**
     * An optional subdirectory to put zipped files in
     */
    public ?string $defaultVolumeSubdirectory = '';

    /**
     * Should we automatically regenerate
     */
    public bool $autoRegenerate = true;

    /**
     * How many hours do we wait before an archive is considered stale?
     */
    public int $deleteStaleArchivesHours = 0;

    /**
     * The maximum sum of input files we can compress. Set to 0 for no limit
     */
    public int $maxFileSize = 0;

    /**
     * The maximum number of input files we can compress. Set to 0 for no limit
     */
    public int $maxFileCount = 0;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['defaultVolumeHandle', 'defaultVolumeSubdirectory'], 'string'],
            [['autoRegenerate'], 'boolean'],
            [['maxFileSize', 'maxFileCount', 'deleteStaleArchivesHours'], 'integer'],
        ];
    }
}
