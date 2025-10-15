<?php

namespace AppInlet\TheCourierGuy\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CustomRatePricingOptions implements OptionSourceInterface
{
    /**
     * Pricing type options for custom rates
     */
    const PRICING_TYPE_DEFAULT    = 'default';
    const PRICING_TYPE_FIXED      = 'fixed';
    const PRICING_TYPE_PERCENTAGE = 'percentage';
    const PRICING_TYPE_SURCHARGE  = 'surcharge';

    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('-- Please Select --')],
            ['value' => self::PRICING_TYPE_DEFAULT, 'label' => __('Use default Rate')],
            ['value' => self::PRICING_TYPE_FIXED, 'label' => __('Fixed Rate')],
            ['value' => self::PRICING_TYPE_SURCHARGE, 'label' => __('Surcharge')],
            ['value' => self::PRICING_TYPE_PERCENTAGE, 'label' => __('Percentage of TCG Rate')]
        ];
    }
}
