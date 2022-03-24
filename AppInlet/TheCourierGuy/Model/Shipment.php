<?php

namespace AppInlet\TheCourierGuy\Model;


use Magento\Framework\Model\AbstractModel;

class Shipment extends AbstractModel
{

    protected function _construct()
    {
        $this->_init(ResourceModel\Shipment::class);
    }
}
