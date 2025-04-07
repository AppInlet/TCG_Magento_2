<?php

namespace AppInlet\TheCourierGuy\Plugin;

use AppInlet\TheCourierGuy\Helper\Data as Helper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PayloadPrep
{
    private Helper $helper;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(Helper $helper, ScopeConfigInterface $scopeConfig)
    {
        $this->helper      = $helper;
        $this->scopeConfig = $scopeConfig;
    }


    public function getOriginPayload($placeId, $townId)
    {
        return [
            'accnum'         => $this->helper->getConfig('account_number'),
            'origperadd1'    => $this->helper->getConfig('shop_address_1'),
            'origperadd2'    => $this->helper->getConfig('shop_address_2'),
            'origperadd3'    => $this->helper->getConfig('city'),
            'origperadd4'    => $this->helper->getConfig('zone'),
            'origperphone'   => $this->getStorePhone(),
            'origpercell'    => '',
            'origplace'      => $placeId,
            'origtown'       => $townId,
            'origpers'       => $this->helper->getConfig('company'),
            'origpercontact' => $this->getStorename(),
            'origperpcode'   => $this->helper->getConfig('shop_postal_code'),
            'notifyorigpers' => 1,
            'origperemail'   => $this->getStoreEmail(),
        ];
    }


    public function getDestinationPayloadForQuote($request, $placeId, $townId, $tel, $firstname, $lastname, $email)
    {
        return [
            'destperadd1'    => $request['street'],
            'destperadd2'    => '',
            'destperadd3'    => $request['city'],
            'destperadd4'    => $request['region'],
            'destperphone'   => $tel,
            'destpercell'    => $tel,
            'destplace'      => $placeId,
            'desttown'       => $townId,
            'destpers'       => $firstname . " " . $lastname,
            'destpercontact' => $firstname,
            'destperpcode'   => $request['postal_code'],
            'notifydestpers' => 1,
            'destperemail'   => $this->getStoreEmail(), /*cannot retrieve user email at checkout :( */
        ];
    }


    public function getContentsPayload($items)
    {
        echo '<pre>';
        print_r($item);
        die;
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

        $r1 = $r2 = [];

        /** Get the standard parcel sizes
         * At least one must be set or default to standard size
         */
        $globalParcels = [];
        for ($i = 1; $i < 4; $i++) {
            $globalParcel              = [];
            $product_length_per_parcel = isset($parameters['product_length_per_parcel_' . $i]) ? $parameters['product_length_per_parcel_' . $i] : '';
            $product_width_per_parcel  = isset($parameters['product_width_per_parcel_' . $i]) ? $parameters['product_width_per_parcel_' . $i] : '';
            $product_height_per_parcel = isset($parameters['product_height_per_parcel_' . $i]) ? $parameters['product_height_per_parcel_' . $i] : '';
            if ($i === 1) {
                $globalParcel[0] = $product_length_per_parcel !== '' ? (int)$product_length_per_parcel : 50;
                $globalParcel[1] = $product_width_per_parcel !== '' ? (int)$product_width_per_parcel : 50;
                $globalParcel[2] = $product_height_per_parcel !== '' ? (int)$product_height_per_parcel : 50;
                rsort($globalParcel);
                $globalParcels[0] = $globalParcel;
            } else {
                $skip = false;
                if ($product_length_per_parcel === '') {
                    $skip = true;
                }
                if ($product_width_per_parcel === '') {
                    $skip = true;
                }
                if ($product_height_per_parcel === '') {
                    $skip = true;
                }
                if (!$skip) {
                    $globalParcel[0] = (int)$product_length_per_parcel;
                    $globalParcel[1] = (int)$product_width_per_parcel;
                    $globalParcel[2] = (int)$product_height_per_parcel;
                    rsort($globalParcel);
                    $globalParcels[$i - 1] = $globalParcel;
                }
            }
        }

        // Order the global parcels by largest dimension ascending order
        if (count($globalParcels) > 1) {
            usort(
                $globalParcels,
                function ($a, $b) {
                    if ($a[0] === $b[0]) {
                        return 0;
                    }

                    return ($a[0] < $b[0]) ? -1 : 1;
                }
            );
        }

        /**
         * Items that don't fit into any of the defined parcel sizes
         * are passed as individual items with their own dimension and mass
         *
         * Now check if there are items that don't fit into any box
         */
        $tooBigItems  = [];
        $fittingItems = [];
        foreach ($items as $item) {
            $item_key = isset($item['key']) ? $item['key'] : 0;
            $fits     = $this->doesFitGlobalParcels($item, $globalParcels);
            if (!$fits['fits']) {
                $tooBigItems[] = $item_key;
            } else {
                $fittingItems[] = ['key' => $item_key, 'index' => $fits['fitsIndex']];
            }
        }

        // Order the fitting items with the biggest dimension first
        usort(
            $fittingItems,
            function ($a, $b) use ($items, $fittingItems) {
                if (isset($items[$a['key']])) {
                    $itema = $items[$a['key']];
                } else {
                    foreach ($items as $item) {
                        if ($item['key'] === $a['key']) {
                            $itema = $item;
                        }
                    }
                }
                if (isset($items[$b['key']])) {
                    $itemb = $items[$b['key']];
                } else {
                    foreach ($items as $item) {
                        if ($item['key'] === $b['key']) {
                            $itemb = $item;
                        }
                    }
                }

                $producta = $itema;
                $productb = $itemb;


                $producta_size = max(
                    (int)$producta['length'],
                    (int)$producta['width'],
                    (int)$producta['height']
                );


                $productb_size = max(
                    (int)$productb['length'],
                    (int)$productb['width'],
                    (int)$productb['height']
                );

                if ($producta_size === $productb_size) {
                    return 0;
                }

                return ($producta_size < $productb_size) ? 1 : -1;
            }
        );


        // Handle the non-fitting items next
        // Single pack sizes

        $j = 0;

        foreach ($tooBigItems as $tooBigItem) {
            $j++;
            /** Items format differs when multi-vendor plugin is enabled */
            if (isset($items[$tooBigItem])) {
                $item = $items[$tooBigItem];
            } else {
                foreach ($items as $itm) {
                    if ($itm['key'] === $tooBigItem) {
                        $item = $itm;
                    }
                }
            }

            $product = $item;

            $dim['dim1'] = (int)$product['width'];
            $dim['dim2'] = (int)$product['height'];
            $dim['dim3'] = (int)$product['length'];
            sort($dim);
            $entry['dim1'] = $dim[0];
            $entry['dim2'] = $dim[1];
            $entry['dim3'] = $dim[2];

            $entry['actmass'] = $item['quantity'] * $product['weight'];

            $r1[] = $entry;
        }

        $this->poolIfPossible($fittingItems, $items);

        /** Now the fitting items
         * We have to fit them into parcels
         * The idea is to minimise the total number of parcels - cf Talent 2020-09-09
         *
         */
        if (count($fittingItems) === 1) {
            // Handle with existing code which works
            foreach ($fittingItems as $fittingItem) {
                /** Items format differs when multi-vendor plugin is enabled */
                if (isset($items[$fittingItem['key']])) {
                    $item = $items[$fittingItem['key']];
                } else {
                    foreach ($items as $itm) {
                        if (isset($itm['key']) && $itm['key'] === $fittingItem['key']) {
                            $item = $itm;
                        }
                    }
                }

                $product = $item;

                $pdims = [$product['length'], $product['width'], $product['height']];

                // Calculate how many items will fit into a box
                $maxItems        = 0;
                $initialBoxIndex = $fittingItem['index'];
                $bestFit         = false;
                while (!$bestFit) {
                    $maxItems = $this->getMaxPackingConfiguration($globalParcels[$initialBoxIndex], $pdims);

                    $nboxes = (int)ceil($item['quantity'] / $maxItems);
                    if ($nboxes > 1 && $initialBoxIndex < (count($globalParcels) - 1)) {
                        $initialBoxIndex++;
                    } else {
                        $bestFit = true;
                    }
                }


                $product = $item;

                for ($box = 1; $box <= $nboxes; $box++) {
                    $j++;
                    $entry = [];
                    if ($box !== $nboxes) {
                        $entry['pieces'] = $maxItems;
                    } else {
                        $entry['pieces'] = $item['quantity'] - ($box - 1) * $maxItems;
                    }
                    $entry['item']        = $j;
                    $entry['description'] = $product['name'];

                    $entry['actmass'] = $entry['pieces'] * $product['weight'];

                    $entry['pieces'] = 1; // Each box counts as one piece
                    $entry['dim1']   = $globalParcels[$initialBoxIndex][0];
                    $entry['dim2']   = $globalParcels[$initialBoxIndex][1];
                    $entry['dim3']   = $globalParcels[$initialBoxIndex][2];
                    $r1[]            = $entry;
                }
            }
        } elseif (count($fittingItems) > 1) {
            // Have more than one size items to try and pack
            // Start with the smallest box that will fit all products
            // and the largest product
            $initialBoxIndex = 0;
            unset($item);

            foreach ($fittingItems as $fittingItem) {
                if ($fittingItem['index'] > $initialBoxIndex) {
                    $initialBoxIndex = $fittingItem['index'];
                }
            }

            $bestFit = false;
            $k       = $j;

            while (!$bestFit) {
                $itemIndex    = 0;
                $anyItemsLeft = true;
                $nboxes       = 1;
                $r2           = [];
                $j            = $k;
                $j++;
                $entry                = [];
                $entry['description'] = '';
                $entry['actmass']     = 0;
                $boxIsFull            = false;
                $boxRemaining         = $globalParcels[$initialBoxIndex];
                while ($anyItemsLeft && $itemIndex !== count($fittingItems)) {
                    if ($boxIsFull) {
                        $nboxes++;
                        $j++;
                        $entry                = [];
                        $entry['actmass']     = 0;
                        $entry['description'] = ' ';
                        $boxIsFull            = false;
                        $boxRemaining         = $globalParcels[$initialBoxIndex];
                    }
                    /** Items format differs when multi-vendor plugin is enabled */
                    if (!isset($item)) {
                        if (isset($items[$fittingItems[$itemIndex]['key']])) {
                            $item = $items[$fittingItems[$itemIndex]['key']];
                        } else {
                            foreach ($items as $itm) {
                                if (isset($itm['key']) && $itm['key'] === $fittingItems[$itemIndex]['key']) {
                                    $item = $itm;
                                }
                            }
                        }
                    }

                    $product = $item;


                    print_r($item);

                    $pdims = [$product['length'], $product['width'], $product['height']];

                    // Calculate max that can be filled
                    $maxItems             = $this->getMaxPackingConfiguration($boxRemaining, $pdims);
                    $entry['item']        = $j;
                    $entry['description'] .= '_' . $product['name'];
                    $entry['pieces']      = 1;
                    $entry['dim1']        = $globalParcels[$initialBoxIndex][0];
                    $entry['dim2']        = $globalParcels[$initialBoxIndex][1];
                    $entry['dim3']        = $globalParcels[$initialBoxIndex][2];
                    if ($maxItems >= $item['quantity']) {
                        // Put them all in

                        $entry['actmass'] += $product['quantity'] * $product['weight'];

                        $itemIndex++;
                        if ($itemIndex == count($fittingItems)) {
                            $anyItemsLeft = false;
                        }
                        if ($item['quantity'] === $maxItems) {
                            $boxIsFull = true;
                        }
                        // Calculate the remaining box content
                        $used            = $this->getActualPackingConfiguration(
                            $boxRemaining,
                            $pdims,
                            $item['quantity']
                        );
                        $boxRemaining[2] -= $used;
                        unset($item);
                    } else {
                        // Fill the box and calculate remainder

                        $entry['actmass'] += $maxItems * $product['weight'];

                        $boxIsFull        = true;
                        $r2[]             = $entry;
                        $item['quantity'] -= $maxItems;
                        continue;
                    }
                }
                $r2[] = $entry;
                if ($nboxes === 1 || ($nboxes > 1 && $initialBoxIndex == count($globalParcels) - 1)) {
                    $bestFit = true;
                }
                $initialBoxIndex++;
            }
        }

        foreach ($r2 as $item) {
            $r1[] = $item;
        }

        return array_values($r1);
    }

    public function getStorePhone()
    {
        return $this->scopeConfig->getValue(
            'general/store_information/phone',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getStoreEmail()
    {
        return $this->scopeConfig->getValue(
            'trans_email/ident_sales/email',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getStorename()
    {
        return $this->scopeConfig->getValue(
            'trans_email/ident_sales/name',
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getActualPackingConfiguration($parcel, $package, $count)
    {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        $usedHeight = $parcel[2];
        foreach ($boxPermutations as $permutation) {
            $nl = (int)($parcel[0] / $package[$permutation[0]]);
            $nw = (int)($parcel[1] / $package[$permutation[1]]);
            $na = $nl * $nw;
            if ($na !== 0) {
                $h = ceil($count / ($nl * $nw)) * $package[$permutation[2]];
                if ($h < $usedHeight) {
                    $usedHeight = $h;
                }
            }
        }

        return $usedHeight;
    }

    private function poolIfPossible(&$fittingItems, &$items)
    {
        $pooledItems = [];
        $dimensions  = [];

        foreach ($fittingItems as $fittingItem) {
            if (isset($items[$fittingItem['key']])) {
                $item = $items[$fittingItem['key']];
            } else {
                foreach ($items as $itm) {
                    if (isset($itm['key']) && $itm['key'] === $fittingItem['key']) {
                        $item = $itm;
                    }
                }
            }


            $product = $item;


            $pdims = [(int)$product['length'], (int)$product['width'], (int)$product['height']];

            sort($pdims, SORT_NUMERIC);
            $pvol  = (int)($pdims[0] * $pdims[1] * $pdims[2]);
            $pmass = 1.0;

            $pmass = $product['width'];

            $pcount       = $item['quantity'];
            $dimensions[] = [
                'dim'   => $pdims,
                'vol'   => $pvol,
                'count' => $pcount,
                'mass'  => $pmass,
                'key'   => $fittingItem['key'],
            ];
        }

        $pool = [];
        $k    = count($dimensions);
        while ($k > 0) {
            $match = false;
            for ($i = 0; $i < count($dimensions); $i++) {
                if (count($pool) > 0) {
                    for ($p = 0; $p < count($pool); $p++) {
                        if ($dimensions[$i]['dim'][0] === $pool[$p]['dim'][0]
                            && $dimensions[$i]['dim'][1] === $pool[$p]['dim'][1]
                            && $dimensions[$i]['dim'][2] === $pool[$p]['dim'][2]
                        ) {
                            $match    = true;
                            $newCount = $pool[$p]['count'] + $dimensions[$i]['count'];
                            $newMass  = $pool[$p]['mass'] * $pool[$p]['count'] + $dimensions[$i]['count'] * $dimensions[$i]['mass'];
                            if ($newMass != 0 && $newCount != 0) {
                                $newMass /= $newCount;
                            }

                            $pool[$p]['count'] = $newCount;
                            $pool[$p]['mass']  = $newMass;

                            array_splice($dimensions, $i, 1);

                            $k--;
                            break;
                        }
                    }
                    if (!$match && $k === 1) {
                        $k--;
                    }
                }
                for ($j = 0; $j < count($dimensions); $j++) {
                    if ($i < $j) {
                        if ($dimensions[$i]['vol'] === $dimensions[$j]['vol']) {
                            if ($dimensions[$i]['dim'][0] === $dimensions[$j]['dim'][0]
                                && $dimensions[$i]['dim'][1] === $dimensions[$j]['dim'][1]
                                && $dimensions[$i]['dim'][2] === $dimensions[$j]['dim'][2]
                            ) {
                                $match = true;
                                if (count($pool) === 0) {
                                    $massOne = ($dimensions[$i]['mass'] * $dimensions[$i]['count'] + $dimensions[$j]['mass'] * $dimensions[$j]['count']);
                                    $massTwo = ($dimensions[$i]['count'] + $dimensions[$j]['count']);

                                    $poolItem = [
                                        'dim'   => $dimensions[$i]['dim'],
                                        'vol'   => $dimensions[$i]['vol'],
                                        'count' => $dimensions[$i]['count'] + $dimensions[$j]['count'],
                                        'mass'  => ($massOne != 0) && ($massTwo != 0) ? $massOne / $massTwo : 0,
                                        'key'   => $dimensions[$i]['key']
                                    ];
                                    $pool[]   = $poolItem;
                                    $k        -= 2;
                                    array_splice($dimensions, $i, 1);
                                    array_splice($dimensions, $j - 1, 1);
                                    break 2;
                                } else {
                                    for ($p = 0; $p < count($pool); $p++) {
                                        if ($dimensions[$i]['dim'][0] === $pool[$p]['dim'][0]
                                            && $dimensions[$i]['dim'][1] === $pool[$p]['dim'][1]
                                            && $dimensions[$i]['dim'][2] === $pool[$p]['dim'][2]
                                        ) {
                                            $match             = true;
                                            $newCount          = intval($pool[$p]) + intval(
                                                $dimensions[$i]['count']
                                            ) + intval(
                                                $dimensions[$j]['count']
                                            );
                                            $newMass           = intval($pool[$p]['mass']) * intval(
                                                $pool[$p]['count']
                                            ) + intval(
                                                $dimensions[$i]['count']
                                            ) * intval(
                                                $dimensions[$i]['mass']
                                            ) + intval(
                                                $dimensions[$i]['count']
                                            ) * intval(
                                                $dimensions[$i]['mass']
                                            );
                                            $newMass           /= $newCount;
                                            $pool[$p]['count'] = $newCount;
                                            $pool[$p]['mass']  = $newMass;

                                            $k -= 2;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (!$match && count($dimensions) > 0) {
                foreach ($dimensions as $dimension) {
                    $pooledItems[] = $dimension;
                }
                $k = 0;
            }
        }
        foreach ($pool as $item) {
            $pooledItems[] = $item;
        }

        $pooledCounts[] = array_map(
            function ($pooledItem) {
                return $pooledItem['count'];
            },
            $pooledItems
        );
        $pooledCounts   = $pooledCounts[0];

        $pooledKeys[] = array_map(
            function ($pooledItem) {
                return $pooledItem['key'];
            },
            $pooledItems
        );
        $pooledKeys   = $pooledKeys[0];

        $pooled = array_combine($pooledKeys, $pooledCounts);

        $itemsKeys[] = array_map(
            function ($item) {
                return $item['key'];
            },
            $items
        );

        $fittingItemKeys[] = array_map(
            function ($fit) {
                return $fit['key'];
            },
            $fittingItems
        );
        $fittingItemKeys   = $fittingItemKeys[0];


        foreach ($items as $k => $item) {
            if (in_array($item['key'], $fittingItemKeys)) {
                if (in_array($item['key'], $pooledKeys)) {
                    $items[$k]['quantity'] = $pooled[$k];
                } else {
                    unset($items[$k]);
                }
            }
        }

        foreach ($fittingItems as $k => $fittingItem) {
            if (!in_array($fittingItem['key'], $pooledKeys)) {
                unset($fittingItems[$k]);
            }
        }
        $fittingItems = array_values($fittingItems);
    }

    private function getMaxPackingConfiguration($parcel, $package)
    {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        $maxItems = 0;
        foreach ($boxPermutations as $key => $permutation) {
            $boxItems = (int)($parcel[0] / $package[$permutation[0]]);
            $boxItems *= (int)($parcel[1] / $package[$permutation[1]]);
            $boxItems *= (int)($parcel[2] / $package[$permutation[2]]);
            $maxItems = max($maxItems, $boxItems);
        }

        return $maxItems;
    }

    private function doesFitGlobalParcels($item, $globalParcels)
    {
        $globalParcelIndex = 0;
        foreach ($globalParcels as $globalParcel) {
            $fits = $this->doesFitParcel($item, $globalParcel);
            if ($fits) {
                break;
            }
            $globalParcelIndex++;
        }

        return ['fits' => $fits, 'fitsIndex' => $globalParcelIndex];
    }

    private function doesFitParcel($item, $parcel)
    {
        $product = $item;

        $productDims    = [];
        $productDims[0] = $product['length'];
        $productDims[1] = $product['width'];
        $productDims[2] = $product['height'];

        rsort($productDims);
        $fits = false;
        if ($productDims[0] <= $parcel[0]
            && $productDims[1] <= $parcel[1]
            && $productDims[2] <= $parcel[2]
        ) {
            $fits = true;
        }

        return $fits;
    }
}
