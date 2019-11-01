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

use venveo\compress\Compress;

use Craft;
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
     * @var string
     */
    public $defaultVolumeHandle = '';

    public $autoRegenerate = true;

    public $maxFileSize = 0;

    public $maxFileCount = 0;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['defaultVolumeHandle', 'string'],
            ['autoRegenerate', 'boolean'],
            [['maxFileSize', 'maxFileCount'], 'integer'],
        ];
    }
}
