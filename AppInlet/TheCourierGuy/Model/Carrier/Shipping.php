<?php

namespace AppInlet\TheCourierGuy\Model\Carrier;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
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

// does shipping for magento.


class Shipping extends AbstractCarrier implements
    CarrierInterface
{
    /**
     * @var string
     */
    protected $code = 'appinlet_the_courier_guy';

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    protected $quoteFactory;

    protected $quoteModel;


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
        array $data = []
    ) {
        $this->shipmentFactory = $shipmentFactory;

        $this->checkoutSession = $checkoutSession;
        $this->monolog         = $monolog;


        $this->apiPlug = $apiPlug;

        $this->helper = $helper;
        $this->logger = $logger;

        $this->cart = $cart;

        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;

        $this->quoteFactory = $quoteFactory;
        $this->quoteModel   = $quoteModel;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->code => $this->helper->getConfig('title')];
    }


    /**
     * @param RateRequest $request
     *
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->helper->getConfig('active')) {
            $this->monolog->info("TheCourierGuy plugin is not active");

            return false;
        }

        $quote = $this->cart->getQuote();
        $items = $request->getAllItems();
        if (empty($quote->getId())) {
            foreach ($items as $item) {
                /** @var Quote $quote */
                $quote = $item->getQuote();
            }
        }
        $grandTotal          = $quote->getGrandTotal();
        $freeshippingminimum = $this->helper->getConfig('freeshippingminimum');

        $quoteId = $quote->getId();
        $result  = $this->rateResultFactory->create();


        $shippingPrice = $this->helper->getConfig('flat_rate');
        if ($grandTotal >= $freeshippingminimum) {
            $shippingPrice = 0;
        }

        $productData = array();
        if ($request->getDestPostcode() && $request->getDestCity()) {
            $packageItemId = 0;
            foreach ($items as $key => $item) {
                $lineItem = $this->prepareLineItem($item, $packageItemId);
                array_push($productData, $lineItem);
                $packageItemId++;
            }
        }

        $requestDestinationDetails = [
            "street"      => $request->getDestStreet(),
            "city"        => $request->getDestCity(),
            "postal_code" => $request->getDestPostcode()
        ];

        $shippingClasses = $this->apiPlug->getQuote($requestDestinationDetails, $productData, $quote, $quoteId);

        if (!isset($shippingClasses['rates'][0])) {
            $error = $shippingClasses['message'];
            $this->monolog->info($error);
            throw new LocalizedException(__($error));
        } else {
            foreach ($shippingClasses['rates'] as $rate) {
                $method = $this->setShippingMethod($shippingPrice, $rate, $shippingClasses);
                $result->append($method);
            }

            /** make free if grand total is >= minimum free shipping amount */
            $rate              = (int)($shippingClasses['rates'][0]['rate']);
            $percentage_markup = (int)($this->helper->getConfig('percentagemarkup'));

            if ($grandTotal >= $freeshippingminimum) {
                $shippingPrice = 0;
            } elseif ($this->helper->getConfig('flat_rate_active')) {
                $shippingPrice = $this->helper->getConfig('flat_rate');
            } else {
                $shippingPrice = $rate + (($percentage_markup / 100) * $rate);
            }
        }

        return $result;
    }

    /**
     * @param $item
     * @param $packageItemId
     *
     * @return array
     */
    public function prepareLineItem($item, $packageItemId)
    {
        $length     = $this->helper->getConfig('typicallength');
        $width      = $this->helper->getConfig('typicalwidth');
        $height     = $this->helper->getConfig('typicalheight');
        $weight     = $item->getQty() * $this->helper->getConfig('typicalweight');
        $itemWeight = $item->getQty() * $item->getWeight();

        return array(
            'key'      => $packageItemId,
            'name'     => $item->getName(),
            'quantity' => $item->getQty(),
            'weight'   => $itemWeight ? $itemWeight : $weight,
            'length'   => $item->getLength() ? $item->getLength() : $length,
            'width'    => $item->getWidth() ? $item->getWidth() : $width,
            'height'   => $item->getHeight() ? $item->getHeight() : $height,
        );
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
