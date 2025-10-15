<?php

namespace AppInlet\TheCourierGuy\Observer;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use AppInlet\TheCourierGuy\Plugin\ShipLogicApiPayload;
use Magento\Quote\Model\QuoteFactory;
use stdClass;

class TCGQuote
{

    private ApiPlug $apiPlug;
    private Monolog $monolog;
    private Helper $helper;
    private QuoteFactory $quoteFactory;
    private ShipmentFactory $shipmentFactory;
    private ShipLogicApiPayload $shipLogicApiPayload;

    public function __construct(
        ShipmentFactory $shipmentFactory,
        ApiPlug $apiPlug,
        Monolog $monolog,
        Helper $helper,
        QuoteFactory $quoteFactory,
        ShipLogicApiPayload $shipLogicApiPayload,
    ) {
        $this->quoteFactory        = $quoteFactory;
        $this->helper              = $helper;
        $this->shipmentFactory     = $shipmentFactory;
        $this->apiPlug             = $apiPlug;
        $this->monolog             = $monolog;
        $this->shipLogicApiPayload = $shipLogicApiPayload;
    }

    /**
     * @param $order
     *
     * @return array
     */
    public function prepareQuote($order): array
    {
        $quoteId          = $order->getQuoteId();
        $orderIncrementId = $order->getIncrementId();
        $quote            = $this->quoteFactory->create()->load($quoteId);
        $result           = [];

        $shippingMethod = $order->getShippingMethod();
        $this->monolog->info('In prepareQuote: Shipping method: ' . $shippingMethod);

        if (strpos($shippingMethod, 'appinlet_the_courier_guy_') === 0) {
            $productData   = [];
            $packageItemId = 0;

            $productRepo = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(
                    \Magento\Catalog\Api\ProductRepositoryInterface::class
                );

            foreach ($order->getAllItems() as $item) {
                if ($item->getIsVirtual()) {
                    continue;
                }

                if ($item->getProductType(
                ) === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                    continue;
                }

                $product = $productRepo->getById($item->getProductId());

                $prodLength = $product->getData('length');
                $prodWidth  = $product->getData('width');
                $prodHeight = $product->getData('height');
                $prodWeight = $product->getWeight();

                $lineItem = [
                    'key'      => $packageItemId,
                    'name'     => $item->getName(),
                    'quantity' => (int)$item->getQtyOrdered(),
                    'weight'   => (int)($prodWeight ?: $this->helper->getConfig('typicalweight')),
                    'length'   => (int)($prodLength ?: $this->helper->getConfig('typicallength')),
                    'width'    => (int)($prodWidth ?: $this->helper->getConfig('typicalwidth')),
                    'height'   => (int)($prodHeight ?: $this->helper->getConfig('typicalheight')),
                ];

                $this->monolog->info('Quote LineItem:', $lineItem);

                $productData[] = $lineItem;
                $packageItemId++;
            }

            $parcelsArray = $this->shipLogicApiPayload->getContentsPayload(
                $this->apiPlug->gatherBoxSizes(),
                $productData
            );

            $parcels = [];

            unset($parcelsArray['fitsFlyer']);

            foreach ($parcelsArray as $parcelArray) {
                $parcel                        = new stdClass();
                $parcel->submitted_length_cm   = $parcelArray['dim1'];
                $parcel->submitted_width_cm    = $parcelArray['dim2'];
                $parcel->submitted_height_cm   = $parcelArray['dim3'];
                $parcel->submitted_description = $this->removeTrailingComma($parcelArray['description']);
                $parcel->item_count            = $parcelArray['itemCount'];
                $parcel->submitted_weight_kg   = $parcelArray['actmass'];
                $parcels[]                     = $parcel;
            }

            foreach ($parcelsArray as $parcelArray) {
                $quantity = $parcelArray['quantity'] ?? 1;

                $length = $parcelArray['submitted_length_cm'] ?? null;
                $width  = $parcelArray['submitted_width_cm'] ?? null;
                $height = $parcelArray['submitted_height_cm'] ?? null;
                $weight = $parcelArray['submitted_weight_kg'] ?? null;

                if (!$length || !$width || !$height || !$weight) {
                    continue;
                }

                for ($i = 0; $i < $quantity; $i++) {
                    $parcel                      = new stdClass();
                    $parcel->submitted_length_cm = $length;
                    $parcel->submitted_width_cm  = $width;
                    $parcel->submitted_height_cm = $height;
                    $parcel->submitted_weight_kg = $weight;
                    $parcels[]                   = $parcel;
                }
            }

            $requestDestinationDetails = [
                "street"      => $order->getShippingAddress()->getData("street"),
                "city"        => $order->getShippingAddress()->getData("city"),
                "postal_code" => $order->getShippingAddress()->getData("postcode")
            ];

            $result = [
                'requestDestinationDetails' => $requestDestinationDetails,
                'productData'               => $parcels,
                'quote'                     => $quote,
                'orderIncrementId'          => $orderIncrementId,
            ];
        }

        return $result;
    }

    public function removeTrailingComma($string)
    {
        $lastOccurrence = strrpos($string, ', ');

        if ($lastOccurrence !== false) {
            return substr($string, 0, $lastOccurrence);
        } else {
            return $string;
        }
    }
}
