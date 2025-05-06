<?php

namespace AppInlet\TheCourierGuy\Model\Carrier;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class Shipping extends AbstractCarrier implements CarrierInterface
{
    protected $code = 'appinlet_the_courier_guy';

    protected $rateResultFactory;
    protected $rateMethodFactory;
    protected $quoteFactory;
    protected $quoteModel;
    protected ShipmentFactory $shipmentFactory;
    protected Monolog $monolog;
    protected ApiPlug $apiPlug;
    protected Helper $helper;
    protected Session $checkoutSession;
    protected LoggerInterface $logger;
    protected Cart $cart;
    protected ProductRepositoryInterface $productRepo;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Helper $helper,
        Monolog $monolog,
        ApiPlug $apiPlug,
        Cart $cart,
        Session $checkoutSession,
        ShipmentFactory $shipmentFactory,
        QuoteFactory $quoteFactory,
        Quote $quoteModel,
        ProductRepositoryInterface $productRepo,
        array $data = []
    ) {
        $this->shipmentFactory   = $shipmentFactory;
        $this->checkoutSession   = $checkoutSession;
        $this->monolog           = $monolog;
        $this->apiPlug           = $apiPlug;
        $this->helper            = $helper;
        $this->logger            = $logger;
        $this->cart              = $cart;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->quoteFactory      = $quoteFactory;
        $this->quoteModel        = $quoteModel;
        $this->productRepo       = $productRepo;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function getAllowedMethods()
    {
        return [$this->code => $this->helper->getConfig('title')];
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->helper->getConfig('active')) {
            $this->monolog->info("TheCourierGuy plugin is not active");
            return false;
        }

        $items = $request->getAllItems();
        $quote = null;
        foreach ($items as $item) {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $item->getQuote();
            if ($quote && $quote->getId()) {
                break;
            }
        }
        if (!$quote || empty($quote->getId())) {
            $this->monolog->info("No valid quote found in RateRequest items");
            return $this->rateResultFactory->create();
        }

        $grandTotal          = $quote->getGrandTotal();
        $subtotal            = $quote->getShippingAddress()->getSubtotalInclTax();
        $freeshippingminimum = $this->helper->getConfig('freeshippingminimum');
        $quoteId             = $quote->getId();
        $result              = $this->rateResultFactory->create();

        $shippingPrice = $this->helper->getConfig('flat_rate');
        if ($subtotal >= $freeshippingminimum) {
            $shippingPrice = 0;
        }

        $productData = [];
        if ($request->getDestPostcode() && $request->getDestCity()) {
            $packageItemId = 0;
            foreach ($items as $item) {
                $lineItem      = $this->prepareLineItem($item, $packageItemId);
                $productData[] = $lineItem;
                $packageItemId++;
            }
        }

        $requestDestinationDetails = [
            "street"      => $request->getDestStreet(),
            "city"        => $request->getDestCity(),
            "postal_code" => $request->getDestPostcode()
        ];

        $insuranceData = [];
        if ($this->helper->isInsuranceEnabled()) {
            $insuranceData = [
                'liability_cover' => 'Y',
                'declared_value'  => (float)$grandTotal
            ];
        }
        $shippingClasses = $this->apiPlug->getQuote(
            $requestDestinationDetails,
            $productData,
            $quote,
            $quoteId,
            $insuranceData
        );

        if (!isset($shippingClasses['rates'][0])) {
            $error = $shippingClasses['message'];
            $this->monolog->info($error);
            return $result;
        }

        foreach ($shippingClasses['rates'] as $rate) {
            $method = $this->setShippingMethod($shippingPrice, $rate, $shippingClasses);
            $result->append($method);
        }

        $allRates      = $result->getAllRates();
        $excludedRates = explode(",", $this->helper->getConfig('excluderates'));

        foreach ($excludedRates as $exclude) {
            foreach ($allRates as $rate) {
                if ($rate->getData("method") === $exclude) {
                    $rate->unsetData();
                }
            }
        }

        $rateValue         = (int)($shippingClasses['rates'][0]['rate']);
        $percentage_markup = (int)($this->helper->getConfig('percentagemarkup'));

        if ($this->helper->getConfig('flat_rate_active') == 1 && $grandTotal <= $freeshippingminimum) {
            $shippingPrice = $this->helper->getConfig('flat_rate');
            foreach ($allRates as $rate) {
                $rate->setPrice($shippingPrice);
            }
        } elseif ($subtotal >= $freeshippingminimum) {
            $shippingPrice = 0;
            foreach ($allRates as $rate) {
                $rate->setPrice($shippingPrice);
                $rate->setData("method_title", "**FREE SHIPPING** " . $rate->getData("method_title"));
            }
        } else {
            $shippingPrice = $rateValue + (($percentage_markup / 100) * $rateValue);
        }

        return $result;
    }

    public function prepareLineItem($item, $packageItemId)
    {
        $defaultLength = (float)$this->helper->getConfig('typicallength');
        $defaultWidth  = (float)$this->helper->getConfig('typicalwidth');
        $defaultHeight = (float)$this->helper->getConfig('typicalheight');
        $defaultWeight = (float)$this->helper->getConfig('typicalweight');

        $product = $this->productRepo->getById(
            $item->getProduct()->getId(),
            false,
            $item->getStoreId(),
            false
        );

        $prodLength = $product->getData('length');
        $prodWidth  = $product->getData('width');
        $prodHeight = $product->getData('height');
        $prodWeight = $product->getWeight();

        $itemWeight = $prodWeight ?: $defaultWeight;

        return [
            'key'      => $packageItemId,
            'name'     => $item->getName(),
            'quantity' => $item->getQty(),
            'weight'   => $itemWeight,
            'length'   => $prodLength ?: $defaultLength,
            'width'    => $prodWidth ?: $defaultWidth,
            'height'   => $prodHeight ?: $defaultHeight,
        ];
    }

    protected function setShippingMethod($shippingPrice, $rate, $shippingClasses)
    {
        $method = $this->rateMethodFactory->create();

        $method->setShippingclasses($shippingClasses);
        $method->setCarrier($this->code);
        $method->setCarrierTitle($this->helper->getConfig('title'));
        $method->setMethod($rate['service_level']['code']);
        $method->setMethodTitle($rate['service_level']['name']);
        $method->setPrice($rate['rate']);
        $method->setCost($rate['rate']);

        return $method;
    }
}
