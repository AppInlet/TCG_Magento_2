<?php

namespace AppInlet\TheCourierGuy\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->installShipmentTable($setup);
        $setup->endSetup();
    }

    protected function installShipmentTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('appinlet_theCourierguy_shipping')
        //using quote number as entity id $order->getQuoteId()
        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => false,
                'nullable' => false,
                'primary'  => true,
            ],
            'Entity Id'
        )->addColumn(
            'shipping_quote_id',
            Table::TYPE_TEXT,
            60,
            [
                'nullable' => true,
            ],
            'Shipping Quote Id'
        )->addColumn(
            'shipping_quote_class',
            Table::TYPE_TEXT,
            60,
            [
                'nullable' => true,
            ],
            'Shipping Class'
        )->addColumn(
            'shipping_quote_rate',
            Table::TYPE_FLOAT,
            null,
            [
                'nullable' => true,
            ],
            'Shipping Quote Rate'
        )->addColumn(
            'shipping_postal_code',
            Table::TYPE_TEXT,
            60,
            [
                'nullable' => true,
            ],
            'Shipping Postal Code'
        )->addColumn(
            'tracker_code',
            Table::TYPE_TEXT,
            255,
            [
                'nullable' => true,
            ],
            'Tracker Code'
        )->addColumn(
            'waybill_url',
            Table::TYPE_TEXT,
            60,
            [
                'nullable' => true,
            ],
            'Waybill URL'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT,
            ],
            'Created At'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT_UPDATE,
            ],
            'Updated At'
        );

        $setup->getConnection()->createTable($table);
    }
}
