<?php
namespace AppInlet\TheCourierGuy\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View as OrderView;

class TCGBtnReturnShipmentOrderView
{

    /**
     * @param
     */
    public function beforeSetLayout(OrderView $subject)
    {
        $order = $subject->getOrder();
        $shippingMethod = $order->getShippingMethod();
        if ($shippingMethod && str_contains($shippingMethod, "the_courier_guy") && !$order->canShip()) {
            $message = __('Are you sure you want to Return TCG Shipment?');
            $subject->addButton(
                'returnshipment',
                [
                    'label' => __('Return TCG Shipment'),
                    'onclick' => "confirmSetLocation('{$message}', '{$subject->getUrl('return_shipment/order/index')}')",
                    'class' => 'ship primary'
                ]
            );
        }
    }
}
