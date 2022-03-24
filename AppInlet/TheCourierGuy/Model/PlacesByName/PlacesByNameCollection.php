<?php

/**
 *
 */

namespace AppInlet\TheCourierGuy\Model\PlacesByName;

use AppInlet\TheCourierGuy\Api\PlacesByNameInterface;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class PlacesByNameCollection implements PlacesByNameInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    function __construct(
        Request $request,
        ApiPlug $apiPlug,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->apiPlug       = $apiPlug;
        $this->request       = $request;
        $this->scopeConfig   = $scopeConfig;
        $this->_storeManager = $storeManager;
    }

    public function getConfig($field)
    {
        $storeScope = ScopeInterface::SCOPE_STORE;

        return $this->scopeConfig->getValue("carriers/appinlet_the_courier_guy/$field", $storeScope);
    }

    public function getPlacesByName()
    {
        $payload = json_decode($this->request->getContent(), true);

        if ( ! isset($payload['place_name'])) {
            throw new LocalizedException(__('place name must be specified.'));

            return json_encode(['error' => "place name must be specified."]);
        }

        $places = $this->apiPlug->getAllPlacesByName($payload['place_name']);
        if ($this->getConfig("disable_boxes_depot") == 1) {
            if (count($places) >= 1) {
                $replaceTerms = array('boxes', 'depot');
                foreach ($places as $key => $place) {
                    $town = strtolower($place['town']);
                    foreach ($replaceTerms as $term) {
                        if (str_contains($town, $term)) {
                            unset($places[$key]);
                        }
                    }
                }
            }
        }

        return $places;
    }
}
