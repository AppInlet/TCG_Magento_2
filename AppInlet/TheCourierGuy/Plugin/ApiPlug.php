<?php

namespace AppInlet\TheCourierGuy\Plugin;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use AppInlet\TheCourierGuy\Helper\Shiplogic;
use AppInlet\TheCourierGuy\Logger\Logger as Monolog;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Http\Message\RequestInterface;

class ApiPlug
{
    private Curl $curl;
    private Monolog $logger;
    private Helper $helper;
    private PayloadPrep $payloadPrep;
    private string $email;
    private string $password;
    private Shiplogic $shipLogic;

    public function __construct(
        Helper $helper,
        Monolog $logger,
        Curl $curl,
        PayloadPrep $payloadPrep,
        Shiplogic $shipLogic
    ) {
        $this->curl        = $curl;
        $this->logger      = $logger;
        $this->helper      = $helper;
        $this->payloadPrep = $payloadPrep;
        $this->email       = $this->helper->getConfig('account_number');
        $this->password    = $this->helper->getConfig('password');
        $this->shipLogic = $shipLogic;
    }

    public function prepare_api_data($request, $itemsList, $quote, $reference)
    {
        $request['region'] = $quote->getShippingAddress()->getRegion();

        $quoteParams            = [];
        $quoteParams['details'] = [];

        /** added these just to make sure these tests are not processed as actual waybills */
        $quoteParams['details']['specinstruction'] = "";
        $quoteParams['details']['reference']       = $reference;

        $tel       = $quote->getShippingAddress()->getTelephone();
        $firstName = $quote->getShippingAddress()->getFirstname();
        $lastName  = $quote->getShippingAddress()->getLastname();
        $email     = $quote->getBillingAddress()->getCustomerEmail();

        $toAddress = [
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
        ];

        $quoteParams['details']  = array_merge($quoteParams['details'], $toAddress);
        $quoteParams['contents'] = is_array($itemsList) ? $itemsList : [];

        return $this->prepareData($quoteParams, $quote);
    }

    /**
     * @param $request
     * @param $itemsList
     * @param $quote
     * @param $reference
     *
     * @return array
     * @throws GuzzleException
     */
    public function getQuote($request, $itemsList, $quote, $reference): array
    {
        $data = $this->prepare_api_data($request, $itemsList, $quote, $reference);

        if (count($data['parcels']) > 0) {
            return $this->shipLogic->getRates($data);
        } else {
            return [
                'message' => 'Please add address to list shipping methods.',
                'rates'   => []
            ];
        }
    }

    public function signRequest(RequestInterface $request, string $accessKeyId, string $secretAccessKey): RequestInterface
    {
        $signature   = new SignatureV4('execute-api', 'af-south-1');
        $credentials = new Credentials($accessKeyId, $secretAccessKey);

        return $signature->signRequest($request, $credentials);
    }

    protected function prepareData($quoteParams, $quote)
    {
        $items      = $quoteParams['contents'];
        $total      = (float)$quote->getGrandTotal();
        $items_data = [];

        foreach ($items as $item) {
            $item_data                        = [];
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

        return [
            "sender"              => [
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
            ],
            "receiver"            => [
                "company"        => "",
                "street_address" => $details['destperadd1'] . ' ' . $details['destperadd2'],
                "type"           => "",
                "local_area"     => "",
                "city"           => $details['destperadd3'],
                "zone"           => $details['destperadd4'],
                "country"        => "ZA",
                "code"           => $details['destperpcode']
            ],
            "parcels"             => $items_data,
            "declared_value"      => $total,
            "collection_min_date" => $current_date,
            "delivery_min_date"   => $t2
        ];
    }
}
