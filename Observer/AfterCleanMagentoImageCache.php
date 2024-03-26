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
use Psr\Log\LoggerInterface;

/**
 * Class AfterCleanMagentoImageCache
 * @package Blackbird\CleanCloudflareImageCache\Observer
 **/
class AfterCleanMagentoImageCache implements ObserverInterface
{
    // Maximum 30 prefixes per API call.
    public const API_PURGE_LIMIT = 30;
    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected DirectoryList $directoryList;

    /**
     * @var \Blackbird\CleanCloudflareImageCache\Model\Config
     */
    protected CleanCloudflareConfig $cleanCloudflareConfig;

    /**
     * @var \Cloudflare\API\Auth\APIKeyFactory
     */
    protected CloudflareAPIKeyFactory $cloudflareAPIKeyFactory;

    /**
     * @var \Cloudflare\API\Adapter\GuzzleFactory
     */
    protected GuzzleAdapterFactory $guzzleAdapterFactory;

    /**
     * @var \Cloudflare\API\Endpoints\ZonesFactory
     */
    protected CloudflareZonesFactory $cloudflareZonesFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected UrlInterface $url;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Blackbird\CleanCloudflareImageCache\Model\Config $cleanCloudflareConfig
     * @param \Cloudflare\API\Auth\APIKeyFactory $cloudflareAPIKeyFactory
     * @param \Cloudflare\API\Adapter\GuzzleFactory $guzzleAdapterFactory
     * @param \Cloudflare\API\Endpoints\ZonesFactory $cloudflareZonesFactory
     * @param \Magento\Framework\UrlInterface $url
     */
    public function __construct(
        DirectoryList           $directoryList,
        CleanCloudflareConfig   $cleanCloudflareConfig,
        CloudflareAPIKeyFactory $cloudflareAPIKeyFactory,
        GuzzleAdapterFactory    $guzzleAdapterFactory,
        CloudflareZonesFactory  $cloudflareZonesFactory,
        UrlInterface            $url,
        LoggerInterface         $logger
    )
    {
        $this->directoryList = $directoryList;
        $this->cleanCloudflareConfig = $cleanCloudflareConfig;
        $this->cloudflareAPIKeyFactory = $cloudflareAPIKeyFactory;
        $this->guzzleAdapterFactory = $guzzleAdapterFactory;
        $this->cloudflareZonesFactory = $cloudflareZonesFactory;
        $this->url = $url;
        $this->logger = $logger;
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
        $baseUrl = $this->url->getBaseUrl();
        $urlToClean = [];

        foreach ($paths as $path) {
            $path = \str_replace($pubPath, '', $path);
            $urlToClean[] = $baseUrl . $path;
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
