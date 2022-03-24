<?php

namespace AppInlet\TheCourierGuy\Plugin\Quote\Address;

use Magento\Quote\Model\Quote\Address\AbstractResult;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Address\RateResult\Method;

class AddShippingClassesToMethodRate
{
    /**
     * @param AbstractResult $rate
     *
     * @return Rate
     */
    public function afterImportShippingRate($subject, $result, $rate)
    {
        if ($rate instanceof Method) {
            $result->setShippingclasses(
                $rate->getShippingclasses()
            );
        }

        return $result;
    }
}
