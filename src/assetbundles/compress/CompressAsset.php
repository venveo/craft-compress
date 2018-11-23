<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\assetbundles\Compress;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class CompressAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@venveo/compress/assetbundles/compress/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Compress.js',
        ];

        $this->css = [
            'css/Compress.css',
        ];

        parent::init();
    }
}
