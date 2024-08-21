<?php

namespace goldinteractive\languageredirect\services;

use Craft;
use craft\base\Component;
use craft\models\Site;
use Exception;

class LanguageRedirect extends Component
{
    /**
     * Checks if we need to do a language redirect & runs it
     *
     * @throws Exception
     */
    public function handleLanguageRedirect()
    {

        if (!Craft::$app->request->getIsCpRequest() && defined('LANGUAGE_REDIRECT') && LANGUAGE_REDIRECT) {
            if ($this->needsLanguageRedirect()) {
                $this->redirectToLocale();
            }
        }
    }

    protected function needsLanguageRedirect(): bool
    {
        $request = Craft::$app->getRequest();

        if (($parsedUrl = parse_url($request->getAbsoluteUrl())) === false || !is_array($parsedUrl) || !isset($parsedUrl['path'])) {
            // we cannot parse the url for whatever reason
            return false;
        }

        $possibleSites = \Craft::$app->sites->getAllSites();

        $currentUrl = rtrim($request->getAbsoluteUrl(), '/');

        foreach ($possibleSites as $possibleSite) {
            $tmpBaseUrl = rtrim($possibleSite->baseUrl, '/');

            if ($tmpBaseUrl === $currentUrl ||
                str_starts_with($currentUrl . '/', $tmpBaseUrl . '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Redirects the browser to the correct locale
     * based on the browser accepted language configuration
     *
     * @throws \yii\base\InvalidConfigException
     */
    protected function redirectToLocale()
    {
        $response = Craft::$app->getResponse();
        $groupId = $this->figureOutGroupId();
        $sites = $this->getSitesFromGroup($groupId);

        if (empty($sites)) {
            throw new \Exception('No sites found in group id: ' . $groupId);
        }

        $locales = $this->getLocalesFromSites($sites);
        $fallback = $this->getFallbackLocale($sites);

        if ($fallback === null) {
            throw new \Exception('no locales found and so no fallback locale could be determined');
        }

        $locale = $this->negotiateLanguage($locales, $fallback);
        $i = array_search($locale, $locales);
        $site = $sites[$i];

        $url = rtrim(\Craft::getAlias($site->baseUrl), '/');
        $requestPath = '/' . \Craft::$app->request->getPathInfo(true);
        $queryString = Craft::$app->request->getQueryString();

        if (!empty($queryString)) {
            $requestPath .= '?' . Craft::$app->request->getQueryString();
        }

        $response->redirect($url . $requestPath, 302)->send();
        exit;
    }

    /**
     * @param int $groupId
     * @return array
     */
    protected function getSitesFromGroup(int $groupId): array
    {
        return \Craft::$app->sites->getSitesByGroupId($groupId, false);
    }

    protected function figureOutGroupId(): int
    {
        $request = Craft::$app->getRequest();
        $parsedUrl = parse_url($request->getAbsoluteUrl());
        $url = $request->getAbsoluteUrl();
        if (mb_strlen($parsedUrl['path']) > 1) {
            $url = str_replace($parsedUrl['path'], '', $url);
        }

        $sites = \Craft::$app->sites->getAllSites();
        $possibleGroupIds = [];
        $primaryGroup = null;

        foreach ($sites as $site) {
            if (!in_array($site->groupId, $possibleGroupIds) && str_starts_with($site->baseUrl, $url)) {
                $possibleGroupIds[] = $site->groupId;
            }

            if ($site->primary) {
                $primaryGroup = $site->groupId;
            }
        }

        if (in_array($primaryGroup, $possibleGroupIds) || empty($possibleGroupIds)) {
            return $primaryGroup;
        }else {
            return $possibleGroupIds[0];
        }
    }

    /**
     * @param array $sites
     * @return array
     */
    protected function getLocalesFromSites(array $sites): array
    {
        $locales = [];

        foreach ($sites as $site) {
            $locales[] = mb_strtolower($site->language);
        }

        return $locales;
    }

    /**
     * @param array $sites
     * @return string|null
     */
    protected function getFallbackLocale(array $sites): string
    {
        $fallback = null;

        /** @var Site $site */
        foreach ($sites as $site) {
            if ($site->primary) {
                $fallback = mb_strtolower($site->language);
            }
        }

        /*
         * We did not find the primary site.
         * This can happen if we use multiple
         * site groups (happens rarely) so we just
         * take the first one.
         */
        if ($fallback === null && count($sites) > 0) {
            $fallback = $sites[0]->language;
        }

        return $fallback;
    }

    /**
     * @param array  $supported
     * @param string $fallback
     * @return string
     */
    protected function negotiateLanguage(array $supported, string $fallback): string
    {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ret = $fallback;

        $locales = $this->parseAcceptLanguage($accept);

        foreach ($locales as $locale => $q) {
            if (!empty($locale) && strpos($locale, '-') === false) {
                foreach ($supported as $supportedLocale) {
                    if (strpos($supportedLocale, $locale) === 0) {
                        return $supportedLocale;
                    }
                }
            }

            if (in_array($locale, $supported)) {
                return $locale;
            }
        }

        return $ret;
    }

    /**
     * Returns an array of accepted locales as keys ordered by q
     *
     * @param string $accept
     * @return array
     */
    protected function parseAcceptLanguage(string $accept): array
    {
        $locales = [];
        $langs = [];
        $x = explode(',', $accept);
        foreach ($x as $val) {
            $val = mb_strtolower($val);
            // Check for q-value. No q-value equals to 1.0
            if (preg_match("/(.*);q=([0-1]{0,1}.\d{0,4})/i", $val, $matches)) {
                $locale = $matches[1];
                $q = $matches[2];
                $locales[$locale] = (float)$q;

                [$lang] = explode('-', $locale);
                if (!isset($langs[$lang])) {
                    $langs[$lang] = $q - 0.1;
                }
            } else {
                $locales[$val] = 1.0;
            }
        }

        arsort($locales);

        return $locales;
    }
}
