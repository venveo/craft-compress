<?php
/**
 * JustTesting plugin for Craft CMS 3.x
 *
 * Test
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress\utilities;

use Craft;
use craft\base\Utility;

/**
 * JustTesting Utility
 *
 * @author    Venveo
 * @package   JustTesting
 * @since     1.0.0
 */
class CompressUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('compress', 'Compress Cache');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'compress-utility';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@venveo/compress/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'compress/_components/utilities/CompressUtility_content',
            [
            ]
        );
    }
}
