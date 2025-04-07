<?php

namespace AppInlet\TheCourierGuy\Observer;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Magento\Quote\Model\QuoteFactory;

class TCGQuote
{

    private ApiPlug $apiPlug;
    private Monolog $monolog;
    private Helper $helper;
    private QuoteFactory $quoteFactory;
    private ShipmentFactory $shipmentFactory;

    public function __construct(
        ShipmentFactory $shipmentFactory,
        ApiPlug $apiPlug,
        Monolog $monolog,
        Helper $helper,
        QuoteFactory $quoteFactory
    ) {
        $this->quoteFactory    = $quoteFactory;
        $this->helper          = $helper;
        $this->shipmentFactory = $shipmentFactory;
        $this->apiPlug         = $apiPlug;
        $this->monolog         = $monolog;
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
                ->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

            foreach ($order->getAllItems() as $item) {
                if ($item->getIsVirtual()) {
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
                    'quantity' => $item->getQtyOrdered(),
                    'weight'   => $item->getQtyOrdered() * ($prodWeight ?: $this->helper->getConfig('typicalweight')),
                    'length'   => $prodLength ?: $this->helper->getConfig('typicallength'),
                    'width'    => $prodWidth ?: $this->helper->getConfig('typicalwidth'),
                    'height'   => $prodHeight ?: $this->helper->getConfig('typicalheight'),
                ];

                $this->monolog->info('Quote LineItem:', $lineItem);

                $productData[] = $lineItem;
                $packageItemId++;
            }

            $requestDestinationDetails = [
                "street"      => $order->getShippingAddress()->getData("street"),
                "city"        => $order->getShippingAddress()->getData("city"),
                "postal_code" => $order->getShippingAddress()->getData("postcode")
            ];

            $result = [
                'requestDestinationDetails' => $requestDestinationDetails,
                'productData'               => $productData,
                'quote'                     => $quote,
                'orderIncrementId'          => $orderIncrementId,
            ];
        }

        return $result;
    }

    public function createQuote($order)
    {
        $quote_data = $this->prepareQuote($order);

        $requestDestinationDetails = $quote_data['requestDestinationDetails'];
        $productData               = $quote_data['productData'];
        $quote                     = $quote_data['quote'];
        $orderIncrementId          = $quote_data['orderIncrementId'];

        return $this->apiPlug->getQuote(
            $requestDestinationDetails,
            $productData,
            $quote,
            $orderIncrementId
        );
    }
}
