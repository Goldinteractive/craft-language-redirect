<?php

namespace goldinteractive\languageredirect;

use Craft;
use craft\base\Plugin as BasePlugin;
use goldinteractive\languageredirect\services\LanguageRedirect;
use goldinteractive\languageredirect\services\StaticRedirect;

/**
 * Language Redirect plugin
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'staticRedirect'   => StaticRedirect::class,
                'languageRedirect' => LanguageRedirect::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            self::getInstance()->staticRedirect->handleUrlRedirects();

            // if we are still here then we didn't redirect anything
            // handle language redirect
            self::getInstance()->languageRedirect->handleLanguageRedirect();
        });
    }
}
