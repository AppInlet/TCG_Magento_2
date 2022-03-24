<?php

namespace AppInlet\TheCourierGuy\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RatesList implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('Select Options')],
            ['value' => 'ECO', 'label' => __('Economy (Domestic Road Freight)')],
            ['value' => 'LOF', 'label' => __('Local Overnight Flyer')],
            ['value' => 'LOX', 'label' => __('Local Overnight Parcels')],
            ['value' => 'NFS', 'label' => __('National Flyer Service')],
            ['value' => 'OVN', 'label' => __('Overnight Courier')]
        ];
    }
}
