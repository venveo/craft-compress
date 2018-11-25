<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

/**
 * Compress config.php
 *
 * This file exists only as a template for the Compress settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'compress.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    /**
     * Volume to store generated assets
     *
     * Default: null
     */
    'defaultVolumeHandle' => null,

    /**
     * If set to true, queue jobs will be dispatched to regenerate an archive
     * if you delete one of its dependent files. Otherwise, this will occur
     * lazily.
     *
     * Default: true
     */
    'autoRegenerate' => true,
];
