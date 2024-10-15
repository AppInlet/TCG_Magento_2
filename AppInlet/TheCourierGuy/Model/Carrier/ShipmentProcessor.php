<?php

namespace AppInlet\TheCourierGuy\Model\Carrier;

use AppInlet\TheCourierGuy\Helper\Shiplogic;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use AppInlet\TheCourierGuy\Observer\TCGQuote;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use AppInlet\TheCourierGuy\Helper\Data as Helper;
use stdClass;

class ShipmentProcessor
{
    /**
     * @var ShipmentRepositoryInterface
     */
    private ShipmentRepositoryInterface $shipmentRepository;
    /**
     * @var ShipmentTrackInterfaceFactory
     */
    private ShipmentTrackInterfaceFactory $trackFactory;
    /**
     * @var Monolog
     */
    private Monolog $monolog;
    /**
     * @var Helper
     */
    private Helper $helper;
    /**
     * @var TCGQuote
     */
    private TCGQuote $tcgQuote;
    /**
     * @var ApiPlug
     */
    private ApiPlug $apiPlug;
    /**
     * @var Shiplogic
     */
    private Shiplogic $shipLogic;
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;
    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;
    /**
     * @var ShipmentSender
     */
    private ShipmentSender $shipmentSender;

    /**
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShipmentTrackInterfaceFactory $trackFactory
     * @param Monolog $monolog
     * @param Helper $helper
     * @param Shiplogic $shipLogic
     * @param TCGQuote $tcgQuote
     * @param ApiPlug $apiPlug
     * @param Filesystem $filesystem
     * @param DirectoryList $directoryList
     */
    public function __construct(
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentTrackInterfaceFactory $trackFactory,
        Monolog $monolog,
        Helper $helper,
        Shiplogic $shipLogic,
        TCGQuote $tcgQuote,
        ApiPlug $apiPlug,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        ShipmentSender $shipmentSender
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->monolog = $monolog;
        $this->helper = $helper;
        $this->tcgQuote = $tcgQuote;
        $this->apiPlug = $apiPlug;
        $this->shipLogic = $shipLogic;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->shipmentSender = $shipmentSender;
    }

    /**
     * @param $shiplogicApi
     * @param $shipmentId
     * @return string
     */
    public function getWaybillLink($shiplogicApi, $shipmentId)
    {
        $url = '';
        try {
            $url = $shiplogicApi->getShipmentLabel($shipmentId);
            $url = json_decode($url)->url;
        } catch (\Exception $exception) {
            $url = $exception->getMessage();
        }

        return $url;
    }

    /**
     * @param $shipmentId
     * @param $waybillNumber
     * @return void
     */
    public function addCustomTrack($shipmentId, $waybillNumber): void
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
     * @param $order
     * @param $body
     * @param bool $returnShipment
     * @return stdClass
     */
    public function createShipmentBody($order, $body, bool $returnShipment = false): stdClass
    {
        $shippingAddress = $order->getShippingAddress();

        $telephone = $this->helper->getConfig('shop_mobile');
        $email     = $this->helper->getConfig('shop_email');

        $createShipmentBody = new stdClass();

        $collection_address                     = $this->shipLogic->getAddressDetail($body['sender']);
        $collection_contact                     = new stdClass();
        $collection_contact->name               = $body['sender']['company'];
        $collection_contact->mobile_number      = $telephone;
        $collection_contact->email              = $email;

        $createShipmentBody->collection_contact = $collection_contact;
        $createShipmentBody->collection_address = $collection_address;

        $delivery_address                     = $this->shipLogic->getAddressDetail($body['receiver']);
        $delivery_contact                     = new stdClass();
        $delivery_contact->name               = $order->getCustomerName();
        $delivery_contact->mobile_number      = $shippingAddress->getTelephone();
        $delivery_contact->email              = $shippingAddress->getEmail();

        $createShipmentBody->delivery_contact = $delivery_contact;
        $createShipmentBody->delivery_address = $delivery_address;

        $parcels = $body['parcels'];


        $createShipmentBody->parcels = $parcels;

        $createShipmentBody->special_instructions_collection = '';
        $createShipmentBody->special_instructions_delivery   = '';
        $createShipmentBody->declared_value                  = $body['declared_value'];
        $createShipmentBody->service_level_code              = $body['service_level_code'];

        if ($returnShipment) {
            $createShipmentBody->delivery_contact = $collection_contact;
            $createShipmentBody->delivery_address = $collection_address;

            $createShipmentBody->collection_contact = $delivery_contact;
            $createShipmentBody->collection_address = $delivery_address;
        }
        return $createShipmentBody;
    }

    /**
     * @throws GuzzleException
     */
    public function buildShipment($order, $observerShipment): void
    {
        $shippingMethod = $order->getShippingMethod();

        $shippingMethodCode = explode('appinlet_the_courier_guy_', $shippingMethod)[1] ?? null;

        $quote_data = $this->tcgQuote->prepareQuote($order);
        $quoteId = $order->getQuoteId();

        $requestDestinationDetails = $quote_data['requestDestinationDetails'];
        $productData               = $quote_data['productData'];
        $quote                     = $quote_data['quote'];
        $orderIncrementId          = $quote_data['orderIncrementId'];

        $shipLogicApi = $this->shipLogic;

        $body = $this->apiPlug->prepare_api_data(
            $requestDestinationDetails,
            $productData,
            $quote,
            $orderIncrementId
        );
        $body['service_level_code'] = $shippingMethodCode;

        $request_body = $this->createShipmentBody($order, $body, $observerShipment === null);

        $response = $shipLogicApi->createShipment($request_body);
        $response = json_decode($response);

        $shipmentId        = $response->id;
        $trackingReference = $response->short_tracking_reference;
        $order->addCommentToStatusHistory("TCG Shipment ID: $shipmentId");
        $order->addCommentToStatusHistory("TCG Tracking Reference: $trackingReference");

        $media = $this->filesystem->getDirectoryWrite($this->directoryList::MEDIA);

        $fileName = "appinlet_the_courier_guy/" . $quoteId . ".pdf";

        $media->writeFile($fileName, base64_decode($shipmentId));

        if ($observerShipment) {
            $this->addCustomTrack($observerShipment->getId(), $trackingReference);
            $waybillUrl = $this->getWaybillLink($shipLogicApi, $shipmentId);
            $order->addCommentToStatusHistory("<a href='$waybillUrl' download >Link to waybill</a>");

            try {
                if (!$observerShipment->getEmailSent()) {
                    $this->shipmentSender->send($observerShipment);
                    $observerShipment->setEmailSent(true);
                }
            } catch (Exception $e) {
                $this->monolog->error($e->getMessage());
            }
        }
    }
}
