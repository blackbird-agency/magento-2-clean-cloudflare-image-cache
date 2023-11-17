<?php
declare(strict_types=1);


namespace Blackbird\CleanCloudflareImageCache\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 * @package Blackbird\CleanCloudflareImageCache\Model
 **/
class Config
{
    protected const CONFIG_PATH_CLOUDFLARE_IS_USED = 'blackbird_clean_image_cache/cloudflare/is_used';
    protected const CONFIG_PATH_CLOUDFLARE_EMAIL = 'blackbird_clean_image_cache/cloudflare/email';
    protected const CONFIG_PATH_CLOUDFLARE_API_KEY = 'blackbird_clean_image_cache/cloudflare/api_key';
    protected const CONFIG_PATH_CLOUDFLARE_ZONE_ID = 'blackbird_clean_image_cache/cloudflare/zone_id';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isUsed($websiteId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_CLOUDFLARE_IS_USED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getEmail($websiteId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_CLOUDFLARE_EMAIL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getApiKey($websiteId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_CLOUDFLARE_API_KEY,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getZoneId($websiteId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_CLOUDFLARE_ZONE_ID,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }
}
