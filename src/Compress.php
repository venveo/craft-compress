<?php
/**
 * Compress plugin for Craft CMS 3.x
 *
 * Create files
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\compress;

use Craft;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Gc;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use venveo\compress\models\Settings;
use venveo\compress\services\Compress as CompressService;
use venveo\compress\utilities\CompressUtility;
use venveo\compress\variables\CompressVariable;
use yii\base\Event;

/**
 * Class Compress
 *
 * @author    Venveo
 * @package   Compress
 * @since     1.0.0
 *
 * @property  CompressService $compress
 */
class Compress extends Plugin
{
    /**
     * @var Compress
     */
    public static $plugin;

    /**
     * @var string
     */
    public string $schemaVersion = '4.0.1';

    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('compress', CompressVariable::class);
            }
        );

        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DELETE,
            function (ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                $this->compress->handleAssetUpdated($asset);
            }
        );

        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                if ($asset->scenario === Asset::SCENARIO_REPLACE || $asset->scenario === Asset::SCENARIO_FILEOPS) {
                    $this->compress->handleAssetUpdated($asset);
                }
            }
        );

        Event::on(Gc::class, Gc::EVENT_RUN, function () {
            if ($this->getSettings()->deleteStaleArchivesHours) {
                $this->compress->deleteStaleArchives(25);
            }
        });


        // Register our utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CompressUtility::class;
            }
        );

    }

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'compress' => ['class' => CompressService::class]
            ],
        ];
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            'compress/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
