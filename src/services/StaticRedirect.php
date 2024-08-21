<?php

namespace goldinteractive\languageredirect\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use nystudio107\retour\helpers\UrlHelper;
use nystudio107\retour\Retour;
use nystudio107\retour\services\Redirects;
use yii\base\InvalidConfigException;

class StaticRedirect extends Component
{
    /**
     * Handle url redirects that need to be done before we call the language redirect
     * Useful for short-urls
     */
    public function handleUrlRedirects()
    {
        $request = Craft::$app->getRequest();

        // check if retour is installed & enabled
        if (Craft::$app->plugins->isPluginEnabled('retour') && $request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
            try {
                $fullUrl = urldecode($request->getAbsoluteUrl());
                $pathOnly = urldecode($request->getUrl());

                if (Retour::$settings->alwaysStripQueryString) {
                    $fullUrl = UrlHelper::stripQueryString($fullUrl);
                    $pathOnly = UrlHelper::stripQueryString($pathOnly);
                }

                $redirect = Retour::getInstance()->redirects->getStaticRedirect($fullUrl, $pathOnly, null);

                if ($redirect) {
                    Retour::getInstance()->redirects->incrementRedirectHitCount($redirect);
                    Retour::getInstance()->redirects->doRedirect($fullUrl, $pathOnly, $redirect);
                }
            } catch (InvalidConfigException $e) {
                Craft::error(
                    $e->getMessage(),
                    __METHOD__
                );
            }
        }
    }
}
