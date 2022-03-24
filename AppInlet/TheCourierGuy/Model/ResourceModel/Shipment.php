<?php

namespace AppInlet\TheCourierGuy\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class TodoItem
 */
class Shipment extends AbstractDb
{
    /**
     * Init
     */
    protected function _construct() // phpcs:ignore PSR2.Methods.MethodDeclaration
    {
        $this->_init('appinlet_theCourierguy_shipping', 'entity_id');
        $this->_isPkAutoIncrement = false;
    }
}
