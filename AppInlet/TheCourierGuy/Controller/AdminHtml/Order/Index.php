<?php

namespace AppInlet\TheCourierGuy\Controller\AdminHtml\Order;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Model\Carrier\ShipmentProcessor;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order;
use Psr\Log\LoggerInterface;

class Index extends Order
{
    private ShipmentProcessor $shipmentProcessor;

    public function __construct(
        Action\Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        Helper $helper,
        ShipmentProcessor $shipmentProcessor,
    ) {
        $this->logger = $logger;
        $this->shipmentProcessor = $shipmentProcessor;
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $translateInline,
            $resultPageFactory,
            $resultJsonFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $orderManagement,
            $orderRepository,
            $logger
        );
    }

    public function execute()
    {
        $order = $this->_initOrder();
        if ($order) {
            try {
                $this->shipmentProcessor->buildShipment($order, null);
                $this->messageManager->addSuccessMessage(__('Return TCG Shipment processed!'));
            } catch (GuzzleException $e) {
                $this->messageManager->addErrorMessage(
                    __('We can\'t process your Return TCG Shipment request' . $e->getMessage())
                );
                $this->logger->critical($e);
            }

            return $this->resultRedirectFactory->create()->setPath(
                'sales/order/view',
                [
                    'order_id' => $order->getEntityId()
                ]
            );
        }

        return $this->resultRedirectFactory->create()->setPath('sales/*/');
    }
}
