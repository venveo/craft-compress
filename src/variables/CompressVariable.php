<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\variables;

use craft\elements\db\AssetQuery;
use venveo\compress\Compress;
use venveo\compress\models\Archive as ArchiveModel;

/**
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 */
class CompressVariable
{
    /**
     * @param AssetQuery|array $query
     * @param $filename
     * @param bool $lazy
     * @return ArchiveModel|null
     */
    public function zip($query, $lazy = false, $filename = null): ?ArchiveModel
    {
        return Compress::$plugin->compress->getArchiveModelForQuery($query, $lazy, $filename);
    }

    /**
     * Get archive models
     *
     * @param int|null $offset
     * @param int|null $limit
     * @return array
     */
    public function archives(?int $offset = 0, ?int $limit = 100): array
    {
        return Compress::$plugin->compress->getArchives($offset, $limit);
    }
}
