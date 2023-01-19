<?php

namespace AppInlet\TheCourierGuy\Helper;

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Http\Message\RequestInterface;
use stdClass;

class Shiplogic extends Data
{
    const API_BASE                     = 'https://api.shiplogic.com/';
    const TCG_SHIP_LOGIC_GETRATES_BODY = 'tcg_ship_logic_getrates_body';
    private $access_key_id;
    private $secret_access_key;
    private $sender;
    private $receiver;
    private $apiMethods = [
        'getRates'         => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'rates',
        ],
        'getOptInRates'    => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'rates/opt-in',
        ],
        'createShipment'   => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'shipments',
        ],
        'getShipments'     => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments?tracking_ref=',
        ],
        'trackShipment'    => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments?tracking_reference=',
        ],
        'getShipmentLabel' => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments/label?id=',
        ],
    ];

    /**
     * @param string $apiMethod
     * @param array $data
     *
     * @return string
     * @throws GuzzleException
     */
    public function makeAPIRequest(string $apiMethod, array $data): string
    {
        $client  = new Client();
        $amzDate = date('Ymd\THis\Z');
        $headers = [
            'X-Amz-Date'   => $amzDate,
            'Cookie'       => 'XDEBUG_SESSION=PHPSTORM',
            'Content-Type' => 'application/json',
        ];
        $method  = $this->apiMethods[$apiMethod]['method'];
        $uri     = $this->apiMethods[$apiMethod]['endPoint'];


        if ($method === 'POST') {
            $request = new Request(
                $method,
                $uri,
                $headers,
                $data['body']
            );
        } elseif ($method === 'GET') {
            $uri     .= $data['param'];
            $request = new Request(
                $method,
                $uri,
                $headers
            );
        }

        $signedRequest = $this->signRequest($request);

        $response = $client->send($signedRequest);

        return $response->getBody()->getContents();
    }

    /**
     * @param array $package
     * @param array $parameters
     *
     * @return array
     * @throws GuzzleException
     */
    public function getOptInRates(array $package, array $parameters): array
    {
        $this->sender             = $this->getSender($parameters);
        $this->receiver           = $this->getReceiver($package);
        $body                     = new stdClass();
        $body->collection_address = $this->sender;
        $body->delivery_address   = $this->receiver;
        $hash                     = 'tcg_optin_' . hash('sha256', serialize($body));
        $optInRates               = get_transient($hash);
        if ($optInRates) {
            return $optInRates;
        }
        try {
            $optInRates = $this->makeAPIRequest(
                'getOptInRates',
                ['body' => json_encode($body)]
            );
            $optInRates = json_decode($optInRates, true);
        } catch (Exception $exception) {
            $optInRates = [];
        }
        if (!empty($optInRates)) {
            set_transient($hash, $optInRates, 300);
        }

        return $optInRates;
    }

    /**
     * @param array $parameters
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRates(array $parameters): array
    {
        $body = new stdClass();

        $body->collection_address = $this->getSender($parameters['sender']);
        $body->delivery_address   = $this->getReceiver($parameters['receiver']);
        $parcels                  = [];
        $parcelsArray             = $parameters['parcels'];

        foreach ($parcelsArray as $parcelArray) {
            $parcel                      = new stdClass();
            $parcel->submitted_length_cm = $parcelArray['submitted_length_cm'];
            $parcel->submitted_width_cm  = $parcelArray['submitted_width_cm'];
            $parcel->submitted_height_cm = $parcelArray['submitted_height_cm'];
            $parcel->submitted_weight_kg = $parcelArray['submitted_weight_kg'];
            $parcels[]                   = $parcel;
        }

        $body->parcels = $parcels;
        $body->declared_value = $parameters['declared_value'];

        if (!empty($parameters['opt_in_rates'])) {
            $body->opt_in_rates = $parameters['opt_in_rates'];
        }

        if (!empty($parameters['opt_in_time_based_rates'])) {
            $body->opt_in_time_based_rates = $parameters['opt_in_time_based_rates'];
        }

        try {
            $response = $this->makeAPIRequest('getRates', ['body' => json_encode($body)]);

            return json_decode($response, true);
        } catch (Exception $exception) {
            return [];
        }
    }

    /**
     * @param object $body
     *
     * @return string
     * @throws GuzzleException
     */
    public function createShipment(object $body): string
    {
        return $this->makeAPIRequest(
            'createShipment',
            ['body' => json_encode($body)]
        );
    }

    public function getShipmentLabel(int $id): string
    {
        return $this->makeAPIRequest('getShipmentLabel', ['param' => $id]);
    }

    protected function getShipLogicCredentials()
    {
        return array(
            "access_key_id"     => $this->getConfig('shiplogic_access_key_id'),
            "secret_access_key" => $this->getConfig('shiplogic_secret_access_key')
        );
    }

    /**
     * @param array $parameters
     *
     * @return object
     */
    private function getSender($parameters)
    {
        $sender                 = new stdClass();
        $sender->company        = $parameters['company'];
        $sender->street_address = $parameters['street_address'];
        $sender->local_area     = $parameters['local_area'];
        $sender->city           = $parameters['city'];
        $sender->zone           = $parameters['zone'];
        $sender->country = $parameters['country'];
        $sender->code    = $parameters['code'];

        return $sender;
    }

    /**
     * @param array $package
     *
     * @return object
     */
    private function getReceiver($parameters)
    {
        $receiver                 = new stdClass();
        $receiver->company        = $parameters['company'];
        $receiver->street_address = $parameters['street_address'];
        $receiver->local_area     = $parameters['local_area'];
        $receiver->city           = $parameters['city'];
        $receiver->zone           = $parameters['zone'];
        $receiver->country        = $parameters['country'];
        $receiver->code           = $parameters['code'];
        return $receiver;
    }

    private function signRequest(RequestInterface $request): RequestInterface
    {
        $credentials     = $this->getShipLogicCredentials();
        $accessKeyId     = $credentials['access_key_id'];
        $secretAccessKey = $credentials['secret_access_key'];
        $signature       = new SignatureV4('execute-api', 'af-south-1');
        $credentials     = new Credentials($accessKeyId, $secretAccessKey);

        return $signature->signRequest($request, $credentials);
    }
}
