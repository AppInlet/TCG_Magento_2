<?php

namespace AppInlet\TheCourierGuy\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RatesList implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('Select Options')],
            ['value' => 'AIR', 'label' => __('The Courier Guy AIR: Fuel charge')],
            ['value' => 'ECO', 'label' => __('The Courier Guy ECO: Fuel charge')],
            ['value' => 'ECOB', 'label' => __('The Courier Guy ECOB: Fuel charge')],
            ['value' => 'ECOR', 'label' => __('The Courier Guy ECOR: Fuel charge')],
            ['value' => 'ECORB', 'label' => __('The Courier Guy ECORB: Fuel charge')],
            ['value' => 'INN', 'label' => __('The Courier Guy INN: Fuel charge')],
            ['value' => 'LLS', 'label' => __('The Courier Guy LLS: Fuel charge')],
            ['value' => 'LLX', 'label' => __('The Courier Guy LLX: Fuel charge')],
            ['value' => 'LOF', 'label' => __('The Courier Guy LOF: Fuel charge')],
            ['value' => 'LOX', 'label' => __('The Courier Guy LOX: Fuel charge')],
            ['value' => 'LSE', 'label' => __('The Courier Guy LSE: Fuel charge')],
            ['value' => 'LSF', 'label' => __('The Courier Guy LSF: Fuel charge')],
            ['value' => 'LSX', 'label' => __('The Courier Guy LSX: Fuel charge')],
            ['value' => 'NFS', 'label' => __('The Courier Guy NFS: Fuel charge')],
            ['value' => 'OVN', 'label' => __('The Courier Guy OVN: Fuel charge')],
            ['value' => 'OVNR', 'label' => __('The Courier Guy OVNR: Fuel charge')],
            ['value' => 'RIN', 'label' => __('The Courier Guy RIN: Fuel charge')],
            ['value' => 'SDX', 'label' => __('The Courier Guy SDX: Fuel charge')],
            ['value' => 'SPX', 'label' => __('The Courier Guy SPX: Fuel charge')]
        ];
    }
}
