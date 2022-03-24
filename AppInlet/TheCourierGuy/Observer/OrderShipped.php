<?php

namespace AppInlet\TheCourierGuy\Observer;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Helper\Shiplogic;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use ShipLogicApiException;
use stdClass;


class OrderShipped implements ObserverInterface
{

    private $shiplogic;

    public function __construct(
        ShipmentFactory $shipmentFactory,
        ApiPlug $apiPlug,
        Monolog $monolog,
        ShipmentRepositoryInterface $shipmentRepository,
        DirectoryList $directoryList,
        Filesystem $filesystem,
        ShipmentTrackInterfaceFactory $trackFactory,
        ShipmentSender $shipmentSender,
        TCGQuote $tcgQuote,
        Helper $helper,
        Shiplogic $shiplogic
    ) {
        $this->tcgQuote           = $tcgQuote;
        $this->shipmentSender     = $shipmentSender;
        $this->shipmentFactory    = $shipmentFactory;
        $this->apiPlug            = $apiPlug;
        $this->monolog            = $monolog;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory       = $trackFactory;
        $this->directoryList      = $directoryList;
        $this->filesystem         = $filesystem;
        $this->helper             = $helper;
        $this->shiplogic          = $shiplogic;
    }

    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        /** @var Order $order */
        $order = $shipment->getOrder();

        $quoteId = $order->getQuoteId();

        $shippingMethod = $order->getShippingMethod();

        $tcgShipment = $this->shipmentFactory->create();

        $shipmentQuote = $tcgShipment->load($quoteId);


        if (count($shipmentQuote->getData()) == 0) {
            $shippingRates = $this->tcgQuote->createQuote($order);
            if (empty($shippingRates['rates'])) {
                return false;
            }

            $quote_data = $this->tcgQuote->prepareQuote($order);

            $requestDestinationDetails = $quote_data['requestDestinationDetails'];
            $productData               = $quote_data['productData'];
            $quote                     = $quote_data['quote'];
            $orderIncrementId          = $quote_data['orderIncrementId'];

            $shiplogicapi = $this->shiplogic;


            $body = $this->apiPlug->prepare_api_data(
                $requestDestinationDetails,
                $productData,
                $quote,
                $orderIncrementId
            );

            $request_body = $this->createShipmentBody($order, $body, $shippingRates);

            $response = $shiplogicapi->createShipment($request_body);
            $response = json_decode($response);

            $shipmentQuoteId = $response->id;

            $media = $this->filesystem->getDirectoryWrite($this->directoryList::MEDIA);

            $fileName = "appinlet_the_courier_guy/" . $quoteId . ".pdf";

            $media->writeFile($fileName, base64_decode($shipmentQuoteId));

            $this->addCustomTrack($shipment->getId(), "TCG-" . $shipmentQuoteId);


            try {
                if ( ! $shipment->getEmailSent()) {
                    $this->shipmentSender->send($shipment);
                    $shipment->setEmailSent(true);
                }
            } catch (Exception $e) {
                $this->monolog->error($e->getMessage());
            }
        }
    }


    public function createShipmentBody($order, $body, $shippingRates)
    {
        $shippingAddress = $order->getShippingAddress();

        $telephone = $this->helper->getConfig('shop_mobile');
        $email     = $this->helper->getConfig('shop_email');

        $createShipmentBody = new stdClass();

        $createShipmentBody->collection_address = $this->getSender($body['sender']);
        $collection_contact                     = new stdClass();
        $collection_contact->name               = $body['sender']['company'];
        $collection_contact->mobile_number      = $telephone;
        $collection_contact->email              = $email;
        $createShipmentBody->collection_contact = $collection_contact;

        $createShipmentBody->delivery_address = $this->getReceiver($body['receiver']);
        $delivery_contact                     = new stdClass();
        $delivery_contact->name               = $order->getCustomerName();
        $delivery_contact->mobile_number      = $shippingAddress->getTelephone();
        $delivery_contact->email              = $shippingAddress->getEmail();
        $createShipmentBody->delivery_contact = $delivery_contact;

        $parcels = $body['parcels'];


        $createShipmentBody->parcels = $parcels;

        $createShipmentBody->special_instructions_collection = '';
        $createShipmentBody->special_instructions_delivery   = '';
        $createShipmentBody->declared_value                  = $body['declared_value'];
        $createShipmentBody->service_level_id                = $shippingRates['rates'][0]['service_level']['id'];

        return $createShipmentBody;
    }


    public function addCustomTrack($shipmentId, $waybillNumber)
    {
        $number  = $waybillNumber;
        $carrier = 'The Courier Guy';
        $title   = 'The Courier Guy';

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
            $track    = $this->trackFactory->create()->setNumber(
                $number
            )->setCarrierCode(
                $carrier
            )->setTitle(
                $title
            );
            $shipment->addTrack($track);
            $this->shipmentRepository->save($shipment);
        } catch (NoSuchEntityException $e) {
            $this->monolog->error($e->getMessage());
        }
    }

    /**
     * @param array $parameters
     *
     * @return object
     * @throws ShipLogicApiException
     */
    private function getSender($parameters)
    {
        $sender                 = new stdClass();
        $sender->company        = $parameters['company'];
        $sender->street_address = $parameters['street_address'];
        $sender->local_area     = $parameters['city'];
        $sender->city           = $parameters['city'];
        $sender->zone           = $parameters['zone'];
        $sender->country        = $parameters['country'];
        $sender->code           = $parameters['code'];

        return $sender;
    }

    /**
     * @param array $package
     *
     * @return object
     * @throws ShipLogicApiException
     */
    private function getReceiver($destination)
    {
        $receiver                 = new stdClass();
        $receiver->company        = $destination['company'];
        $receiver->street_address = $destination['street_address'];
        $receiver->local_area     = $destination['city'];
        $receiver->city           = $destination['city'];
        $receiver->zone           = $destination['zone'];
        $receiver->country        = $destination['country'];
        $receiver->code           = $destination['code'];

        return $receiver;
    }
}
