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
use craft\events\RegisterUrlRulesEvent;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
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
    // Static Properties
    // =========================================================================

    /**
     * @var Compress
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

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
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('compress', CompressVariable::class);
            }
        );

        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DELETE,
            function(ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                $this->compress->handleAssetDeleted($asset);
            }
        );


        // Register site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['system/compress/<uid:.+>'] = 'compress/compress/get-link';
            }
        );


        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CompressUtility::class;
            }
        );

    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'craft-compress/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
