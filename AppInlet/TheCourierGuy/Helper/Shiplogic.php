<?php

namespace AppInlet\TheCourierGuy\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use stdClass;
use AppInlet\TheCourierGuy\Model\Carrier\ShipmentProcessor;

class Shiplogic extends Data
{
    public const API_BASE = 'https://api.shiplogic.com/v2/';
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
        $credentials = $this->getShipLogicCredentials();
        $apiKey      = $credentials['shiplogic_api_key'];

        $client  = new Client();
        $amzDate = date('Ymd\THis\Z');
        $headers = [
            'X-Amz-Date'    => $amzDate,
            'Cookie'        => 'XDEBUG_SESSION=PHPSTORM',
            'Content-Type'  => 'application/json',
            'Authorization' => "Bearer $apiKey",
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

        $response = $client->send($request);

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
        $this->sender             = $this->getAddressDetail($parameters);
        $this->receiver           = $this->getAddressDetail($package);
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

        $body->collection_address = $this->getAddressDetail($parameters['sender']);
        $body->delivery_address   = $this->getAddressDetail($parameters['receiver']);

        $parcelsArray = $this->shipLogicApiPayload->getContentsPayload($parameters['boxSizes'], $parameters['parcels']);

        $parcels      = [];

        unset($parcelsArray['fitsFlyer']);

        foreach ($parcelsArray as $parcelArray) {
            $parcel                        = new stdClass();
            $parcel->submitted_length_cm   = $parcelArray['dim1'];
            $parcel->submitted_width_cm    = $parcelArray['dim2'];
            $parcel->submitted_height_cm   = $parcelArray['dim3'];
            $parcel->submitted_description = $this->removeTrailingComma($parcelArray['description']);
            $parcel->item_count            = $parcelArray['itemCount'];
            $parcel->submitted_weight_kg   = $parcelArray['actmass'];
            $parcels[]                     = $parcel;
        }

        foreach ($parcelsArray as $parcelArray) {
            $quantity = $parcelArray['quantity'] ?? 1;

            $length = $parcelArray['submitted_length_cm'] ?? null;
            $width  = $parcelArray['submitted_width_cm'] ?? null;
            $height = $parcelArray['submitted_height_cm'] ?? null;
            $weight = $parcelArray['submitted_weight_kg'] ?? null;

            if (!$length || !$width || !$height || !$weight) {
                continue;
            }

            for ($i = 0; $i < $quantity; $i++) {
                $parcel                      = new stdClass();
                $parcel->submitted_length_cm = $length;
                $parcel->submitted_width_cm  = $width;
                $parcel->submitted_height_cm = $height;
                $parcel->submitted_weight_kg = $weight;
                $parcels[]                   = $parcel;
            }
        }

        $body->parcels        = $parcels;
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
        } catch (Exception $ex) {
            return [];
        }
    }

    public function removeTrailingComma($string)
    {
        $lastOccurrence = strrpos($string, ', ');

        if ($lastOccurrence !== false) {
            return substr($string, 0, $lastOccurrence);
        } else {
            return $string;
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
        return $this->makeAPIRequest('createShipment', ['body' => json_encode($body)]);
    }

    public function getShipmentLabel(int $id): string
    {
        return $this->makeAPIRequest('getShipmentLabel', ['param' => $id]);
    }

    /**
     * @return array
     */
    protected function getShipLogicCredentials(): array
    {
        return [
            'shiplogic_api_key' => $this->getConfig('shiplogic_api_key'),
        ];
    }

    /**
     * @param $parameters
     *
     * @return stdClass
     */
    public function getAddressDetail($parameters): stdClass
    {
        $addressDetail                 = new stdClass();
        $addressDetail->company        = $parameters['company'];
        $addressDetail->street_address = $parameters['street_address'];
        $addressDetail->local_area     = $parameters['local_area'];
        $addressDetail->city           = $parameters['city'];
        $addressDetail->zone           = $parameters['zone'];
        $addressDetail->country        = $parameters['country'];
        $addressDetail->code           = $parameters['code'];

        return $addressDetail;
    }
}
