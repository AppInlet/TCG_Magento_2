<?php

namespace AppInlet\TheCourierGuy\Controller\Index;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ObjectManager;

class Updatequote extends Action
{

    public function execute()
    {
        $objectManager = ObjectManager::getInstance();
        $post          = $this->getRequest()->getPostValue();

        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        $quote = $cart->getQuote();
        if (isset($post['place_id'])) {
            $quote->setCourierguyPlaceId($post['place_id']);
            try {
                $quote->save();
                echo 'Place Post Success';
                die();
            } catch (Exception $ex) {
                echo 'Place Post Failed';
            }
        }

        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }
}
