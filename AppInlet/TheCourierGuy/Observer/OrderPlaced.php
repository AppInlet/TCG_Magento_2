<?php

namespace AppInlet\TheCourierGuy\Observer;

use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderPlaced implements ObserverInterface
{

    public function __construct(
        TCGQuote $tcgQuote,
        Monolog $monolog
    ) {
        $this->tcgQuote = $tcgQuote;
        $this->monolog  = $monolog;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');

        $this->tcgQuote->createQuote($order);
    }
}
