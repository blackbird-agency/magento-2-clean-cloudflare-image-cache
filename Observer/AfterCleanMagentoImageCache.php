<?php
declare(strict_types=1);

namespace Blackbird\CleanCloudflareImageCache\Observer;

use Blackbird\CleanCloudflareImageCache\Model\Config as CleanCloudflareConfig;
use Cloudflare\API\Adapter\GuzzleFactory as GuzzleAdapterFactory;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Auth\APIKeyFactory as CloudflareAPIKeyFactory;
use Cloudflare\API\Auth\APITokenFactory as CloudflareAPITokenFactory;
use Cloudflare\API\Endpoints\EndpointException;
use Cloudflare\API\Endpoints\ZonesFactory as CloudflareZonesFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * Class AfterCleanMagentoImageCache
 *
 * @package Blackbird\CleanCloudflareImageCache\Observer
 **/
class AfterCleanMagentoImageCache implements ObserverInterface
{
    // Maximum 30 prefixes per API call.
    public const API_PURGE_LIMIT = 30;

    /**
     * @param DirectoryList $directoryList
     * @param CleanCloudflareConfig $cleanCloudflareConfig
     * @param CloudflareAPIKeyFactory $cloudflareAPIKeyFactory
     * @param CloudflareAPITokenFactory $cloudflareAPITokenFactory
     * @param GuzzleAdapterFactory $guzzleAdapterFactory
     * @param CloudflareZonesFactory $cloudflareZonesFactory
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected DirectoryList $directoryList,
        protected CleanCloudflareConfig $cleanCloudflareConfig,
        protected CloudflareAPIKeyFactory $cloudflareAPIKeyFactory,
        protected CloudflareAPITokenFactory $cloudflareAPITokenFactory,
        protected GuzzleAdapterFactory $guzzleAdapterFactory,
        protected CloudflareZonesFactory $cloudflareZonesFactory,
        protected LoggerInterface $logger,
        protected StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->cleanCloudflareConfig->isUsed()) {
            return;
        }

        /** @var Product $product */
        $product = $observer->getProduct();
        $paths = $observer->getPaths();

        $mediaPath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/';
        $urlToClean = [];

        foreach ($product->getWebsiteIds() as $websiteId) {
            $websiteId = (int) $websiteId;
            $website = $this->storeManager->getWebsite($websiteId);
            $baseUrl = $website->getDefaultStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $zoneId = $this->cleanCloudflareConfig->getZoneId();
            $adapter = $this->guzzleAdapterFactory->create(['auth' => $this->getAuthKey($websiteId)]);
            $zone = $this->cloudflareZonesFactory->create(['adapter' => $adapter]);

            foreach ($paths as $path) {
                $path = \str_replace($mediaPath, '', $path);
                $urlToClean[$zoneId][] = $baseUrl . $path;
            }
        }

        try {
            foreach (\array_chunk($urlToClean, self::API_PURGE_LIMIT, true) as $urlChunk) {
                if ($this->cleanCloudflareConfig->isDebugEnabled()) {
                    foreach ($urlChunk as $url) {
                        $this->logger->info(sprintf('Cloudflare clean URL: %s', $url));
                    }
                }
                $zoneId = array_key_first($urlChunk);

                $zone->cachePurge($zoneId, $urlChunk[$zoneId]);
            }
        } catch (EndpointException $e) {
            //Do nothing, it only throw if there is no url to clean
        } catch (\Exception $e) {
            $this->logger->error($e);
        }
    }

    private function getAuthKey(int $websiteId)
    {
        if ($this->cleanCloudflareConfig->getApiToken($websiteId)) {
            return $this->cloudflareAPITokenFactory->create([
                'apiToken' => (string)$this->cleanCloudflareConfig->getApiToken($websiteId)
            ]);

        }

        return $this->cloudflareAPIKeyFactory->create(
            [
                'email' => (string)$this->cleanCloudflareConfig->getEmail($websiteId),
                'apiKey' => (string)$this->cleanCloudflareConfig->getApiKey($websiteId)
            ]
        );
    }
}
