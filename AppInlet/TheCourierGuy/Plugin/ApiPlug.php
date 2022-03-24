<?php

namespace AppInlet\TheCourierGuy\Plugin;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Http\Message\RequestInterface;

class ApiPlug
{

    public function __construct(Helper $helper, Monolog $logger, Curl $curl, PayloadPrep $payloadPrep)
    {
        $this->curl        = $curl;
        $this->logger      = $logger;
        $this->helper      = $helper;
        $this->payloadPrep = $payloadPrep;
        $this->email       = $this->helper->getConfig('account_number');
        $this->password    = $this->helper->getConfig('password');
    }

    public function prepare_api_data($request, $itemsList, $quote, $reference)
    {
        $request['region'] = $quote->getShippingAddress()->getRegion();

        $quoteParams            = array();
        $quoteParams['details'] = array();


        /** added these just to make sure these tests are not processed as actual waybills */
        $quoteParams['details']['specinstruction'] = "";
        $quoteParams['details']['reference']       = $reference;

        $tel       = $quote->getShippingAddress()->getTelephone();
        $firstName = $quote->getShippingAddress()->getFirstname();
        $lastName  = $quote->getShippingAddress()->getLastname();
        $email     = $quote->getBillingAddress()->getCustomerEmail();

        $toAddress = array(
            'destperadd1'    => $request['street'],
            'destperadd2'    => '',
            'destperadd3'    => $request['city'],
            'destperadd4'    => $request['region'],
            'destperphone'   => $tel,
            'destpercell'    => $tel,
            'destpers'       => $firstName . " " . $lastName,
            'destpercontact' => $firstName,
            'destperpcode'   => $request['postal_code'],
            'destperemail'   => $quote->getCustomerEmail(),
        );


        // $products = $this->payloadPrep->getContentsPayload($itemsList);

        $quoteParams['details']  = array_merge($quoteParams['details'], $toAddress);
        $quoteParams['contents'] = is_array($itemsList) ? $itemsList : array();

        return $this->prepareData($quoteParams, $quote);
    }

    public function getQuote($request, $itemsList, $quote, $reference)
    {
        $objectManager = ObjectManager::getInstance();
        $shiplogic     = $objectManager->create('AppInlet\TheCourierGuy\Helper\Shiplogic');

        $data = $this->prepare_api_data($request, $itemsList, $quote, $reference);

        return $shiplogic->getRates($data);
    }

    function signRequest(RequestInterface $request, string $accessKeyId, string $secretAccessKey): RequestInterface
    {
        $signature   = new SignatureV4('execute-api', 'af-south-1');
        $credentials = new Credentials($accessKeyId, $secretAccessKey);

        return $signature->signRequest($request, $credentials);
    }

    protected function prepareData($quoteParams, $quote)
    {
        $items      = $quoteParams['contents'];
        $total      = (float)$quote->getGrandTotal();
        $items_data = array();

        foreach ($items as $item) {
            $item_data                        = array();
            $item_data['submitted_length_cm'] = (int)$item['length'];
            $item_data['submitted_width_cm']  = (int)$item['width'];
            $item_data['submitted_height_cm'] = (int)$item['height'];
            $item_data['submitted_weight_kg'] = (int)$item['weight'];
            array_push($items_data, $item_data);
        }

        $details = $quoteParams['details'];

        $current_date = date("Y-m-d");
        $t2           = date('Y-m-d', strtotime('+2 days'));

        $sender_address = $this->helper->getConfig('shop_address_1') . " " . $this->helper->getConfig('shop_address_2');

        return array(
            "sender"              => array(
                "company"        => $this->helper->getConfig('company'),
                "type"           => "business",
                "street_address" => $sender_address,
                "local_area"     => $this->helper->getConfig('city'),
                "city"           => $this->helper->getConfig('city'),
                "zone"           => $this->helper->getConfig('zone'),
                "country"        => "ZA",
                "code"           => $this->helper->getConfig('shop_postal_code'),
                "lat"            => "",
                "lng"            => ""
            ),
            "receiver"            => array(
                "company"        => "",
                "street_address" => $details['destperadd1'] . ' ' . $details['destperadd2'],
                "type"           => "",
                "local_area"     => "",
                "city"           => $details['destperadd3'],
                "zone"           => $details['destperadd4'],
                "country"        => "ZA",
                "code"           => $details['destperpcode']
            ),
            "parcels"             => $items_data,
            "declared_value"      => $total,
            "collection_min_date" => $current_date,
            "delivery_min_date"   => $t2
        );
    }

}
