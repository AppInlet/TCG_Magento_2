<?php

namespace AppInlet\TheCourierGuy\Observer;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Model\Carrier\ShipmentProcessor;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Message\ManagerInterface;

class OrderShipped implements ObserverInterface
{
    private Helper $helper;
    private ShipmentProcessor $shipmentProcessor;
    private ManagerInterface $messageManager;

    public function __construct(
        Helper $helper,
        ShipmentProcessor $shipmentProcessor,
        ManagerInterface $messageManager
    ) {
        $this->helper            = $helper;
        $this->shipmentProcessor = $shipmentProcessor;
        $this->messageManager    = $messageManager;
    }

    /**
     * @throws GuzzleException
     * @throws FileSystemException
     */
    public function execute(Observer $observer)
    {
        if ($this->helper->getConfig('disable_tcg_shipment_at_create_shipment') === "1") {
            return;
        }

        $shipment = $observer->getEvent()->getShipment();

        $order = $shipment->getOrder();

        try {
            $this->shipmentProcessor->buildShipment($order, $shipment);
        } catch (GuzzleException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }
}
