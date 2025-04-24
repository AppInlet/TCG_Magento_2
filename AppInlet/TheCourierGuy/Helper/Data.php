<?php

/**
 * Copyright © 2025 App Inlet (Pty) Ltd . All rights reserved.
 */

namespace AppInlet\TheCourierGuy\Helper;

use Exception;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\PageCache\Version;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use AppInlet\TheCourierGuy\Plugin\ShipLogicApiPayload;
class Data extends AbstractHelper
{
    public const XML_PATH_CATALOG = 'carriers/';

    /**
     * @var WriterInterface
     */
    protected $configWriter;
    protected LoggerInterface $logger;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;
    protected ShipLogicApiPayload $shipLogicApiPayload;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param LoggerInterface $logger
     * @param ShipLogicApiPayload $shipLogicApiPayload
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        LoggerInterface $logger,
        ShipLogicApiPayload $shipLogicApiPayload,
    ) {
        $this->configWriter              = $configWriter;
        $this->cacheTypeList             = $cacheTypeList;
        $this->cacheFrontendPool         = $cacheFrontendPool;
        $this->logger                    = $logger;
        $this->shipLogicApiPayload       = $shipLogicApiPayload;
        parent::__construct($context);
    }

    /**
     * @param $field
     * @param null $storeId
     *
     * @return string
     */
    public function getConfigValue($field, $storeId = null)
    {
        if ($fieldValue = $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        )
        ) {
            return $fieldValue;
        }

        return "";
    }

    /**
     * @param $code
     * @param null $storeId
     *
     * @return string
     */
    public function getConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_CATALOG . "appinlet_the_courier_guy/" . $code, $storeId);
    }

    /**
     * @param $code
     * @param $value
     */
    public function SetConfigData($code, $value)
    {
        $path = self::XML_PATH_CATALOG . "appinlet_the_courier_guy/" . $code;
        try {
            $this->configWriter->save($path, $value);
            $this->flushCache();
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Flush config cache
     */
    public function flushCache()
    {
        $_types = [
            'config'
        ];

        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
    public function isInsuranceEnabled(): bool
    {
        return $this->getConfig('enable_insurance') === '1';
    }
}
