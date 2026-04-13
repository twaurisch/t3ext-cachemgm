<?php

declare(strict_types=1);

namespace Aoe\Cachemgm\EventListener;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Frontend\Event\ShouldUseCachedPageDataIfAvailableEvent;

class AvoidCacheLoading
{
    public function __invoke(ShouldUseCachedPageDataIfAvailableEvent $event): void
    {
        if ($this->isCrawlerRunningAndRecachingPage($event)) {
            // Disables a look-up for cached page data - thus resulting in re-generation of the page even if cached.
            $event->setShouldUseCachedPageData(false);
        }
    }

    /**
     * Check if crawler is loaded, a crawler session is running and re-caching is requested as processing instruction.
     *
     * In TYPO3 v12, crawler data is stored in TSFE->applicationData.
     * In TYPO3 v13, applicationData was removed (Breaking-102600), so we check
     * the request attribute as fallback.
     */
    private function isCrawlerRunningAndRecachingPage(ShouldUseCachedPageDataIfAvailableEvent $event): bool
    {
        if (!ExtensionManagementUtility::isLoaded('crawler')) {
            return false;
        }

        $crawlerData = $this->getCrawlerData($event);
        if ($crawlerData === null) {
            return false;
        }

        return !empty($crawlerData['running'])
            && in_array(
                'tx_cachemgm_recache',
                $crawlerData['parameters']['procInstructions'] ?? [],
                true
            );
    }

    private function getCrawlerData(ShouldUseCachedPageDataIfAvailableEvent $event): ?array
    {
        // TYPO3 v12: check applicationData on TSFE
        $tsfe = $event->getController();
        if (property_exists($tsfe, 'applicationData') && isset($tsfe->applicationData['tx_crawler'])) {
            return $tsfe->applicationData['tx_crawler'];
        }

        // TYPO3 v13+: check request attribute
        $request = $event->getRequest();
        $crawlerData = $request->getAttribute('tx_crawler');
        if (is_array($crawlerData)) {
            return $crawlerData;
        }

        return null;
    }
}
