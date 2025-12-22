<?php
declare(strict_types=1);

namespace Blackbird\CleanCloudflareImageCache\Observer;

use Blackbird\CleanCloudflareImageCache\Model\Config as CleanCloudflareConfig;
use Cloudflare\API\Adapter\GuzzleFactory as GuzzleAdapterFactory;
use Cloudflare\API\Auth\APIKeyFactory as CloudflareAPIKeyFactory;
use Cloudflare\API\Endpoints\EndpointException;
use Cloudflare\API\Endpoints\ZonesFactory as CloudflareZonesFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     * @param GuzzleAdapterFactory $guzzleAdapterFactory
     * @param CloudflareZonesFactory $cloudflareZonesFactory
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected DirectoryList $directoryList,
        protected CleanCloudflareConfig $cleanCloudflareConfig,
        protected CloudflareAPIKeyFactory $cloudflareAPIKeyFactory,
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

        $paths = $observer->getPaths();
        $key = $this->cloudflareAPIKeyFactory->create(
            [
                'email' => $this->cleanCloudflareConfig->getEmail(),
                'apiKey' => $this->cleanCloudflareConfig->getApiKey()
            ]
        );
        $adapter = $this->guzzleAdapterFactory->create(['auth' => $key]);
        $zone = $this->cloudflareZonesFactory->create(['adapter' => $adapter]);
        $pubPath = $this->directoryList->getPath(DirectoryList::PUB) . '/';
        $mediaBaseUrl = $this->storeManager->getDefaultStoreView()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $urlToClean = [];

        foreach ($paths as $path) {
            $path = \str_replace($pubPath, '', $path);
            $urlToClean[] = $mediaBaseUrl . $path;
        }

        try {
            foreach (\array_chunk($urlToClean, self::API_PURGE_LIMIT) as $urlChunk) {
                $zone->cachePurge($this->cleanCloudflareConfig->getZoneId(), $urlChunk);
            }
        } catch (EndpointException $e) {
            //Do nothing, it only throw if there is no url to clean
        } catch (\Exception $e) {
            $this->logger->error($e);
        }
    }
}