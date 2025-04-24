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
    private string $email;
    private string $password;
    private Shiplogic $shipLogic;

    public function __construct(
        Helper $helper,
        Monolog $logger,
        Curl $curl,
        Shiplogic $shipLogic
    ) {
        $this->curl        = $curl;
        $this->logger      = $logger;
        $this->helper      = $helper;
        $this->email       = $this->helper->getConfig('account_number');
        $this->password    = $this->helper->getConfig('password');
        $this->shipLogic   = $shipLogic;
    }

    public function prepare_api_data($request, $itemsList, $quote, $reference, array $insuranceData = [])
    {
        $request['region'] = $quote->getShippingAddress()->getRegion();

        $quoteParams = [];
        $quoteParams['details'] = [];

        $declaredValue = $insuranceData['declared_value'] ?? 0.0;
        $quoteParams['details']['reference'] = $reference;

        $tel       = $quote->getShippingAddress()->getTelephone();
        $firstName = $quote->getShippingAddress()->getFirstname();
        $lastName  = $quote->getShippingAddress()->getLastname();

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

        return $this->prepareData($quoteParams, $quote, (float) $declaredValue);
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
    public function getQuote($request, $itemsList, $quote, $reference, $insuranceData = []): array
    {
        $declaredValue = $insuranceData['declared_value'] ?? 0.0;

        $data = $this->prepare_api_data($request, $itemsList, $quote, $reference, [
            'declared_value' => (float) $declaredValue
        ]);

        $data['boxSizes'] = $this->gatherBoxSizes();

        if (count($data['parcels']) > 0) {
            return $this->shipLogic->getRates($data);
        } else {
            return [
                'message' => 'Please add address to list shipping methods.',
                'rates'   => []
            ];
        }
    }
    public function signRequest(
        RequestInterface $request,
        string $accessKeyId,
        string $secretAccessKey
    ): RequestInterface {
        $signature   = new SignatureV4('execute-api', 'af-south-1');
        $credentials = new Credentials($accessKeyId, $secretAccessKey);

        return $signature->signRequest($request, $credentials);
    }

    public function gatherBoxSizes(): array
    {
        $parameters = [];

        $parameters['product_length_per_parcel_1'] = $this->helper->getConfig('length_of_flyer');
        $parameters['product_width_per_parcel_1']  = $this->helper->getConfig('width_of_flyer');
        $parameters['product_height_per_parcel_1'] = $this->helper->getConfig('height_of_flyer');

        $parameters['product_length_per_parcel_2'] = $this->helper->getConfig('length_of_medium_parcel');
        $parameters['product_width_per_parcel_2']  = $this->helper->getConfig('width_of_medium_parcel');
        $parameters['product_height_per_parcel_2'] = $this->helper->getConfig('height_of_medium_parcel');

        $parameters['product_length_per_parcel_3'] = $this->helper->getConfig('length_of_large_parcel');
        $parameters['product_width_per_parcel_3']  = $this->helper->getConfig('width_of_large_parcel');
        $parameters['product_height_per_parcel_3'] = $this->helper->getConfig('height_large_parcel');

        return $parameters;
    }

    protected function prepareData($quoteParams, $quote, float $declaredValue = 0.0)
    {
        $details      = $quoteParams['details'];
        $current_date = date("Y-m-d\T00:00:00P");
        $t2           = date("Y-m-d\T00:00:00P", strtotime('+2 days'));

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
            "parcels"             => $quoteParams['contents'],
            "declared_value"      => $declaredValue,
            "collection_min_date" => $current_date,
            "delivery_min_date"   => $t2
        ];
    }
}
