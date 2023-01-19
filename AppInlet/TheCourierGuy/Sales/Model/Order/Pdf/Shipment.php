<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace AppInlet\TheCourierGuy\Sales\Model\Order\Pdf;

use AppInlet\TheCourierGuy\Model\ShipmentFactory;
use AppInlet\TheCourierGuy\Plugin\ApiPlug;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Sales\Model\Order\Pdf\AbstractPdf;
use Magento\Sales\Model\Order\Pdf\Config;
use Magento\Sales\Model\Order\Pdf\ItemsFactory;
use Magento\Sales\Model\Order\Pdf\Total\Factory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Pdf;
use Zend_Pdf_Color_GrayScale;
use Zend_Pdf_Color_Rgb;
use Zend_Pdf_Page;
use Zend_Pdf_Style;

/**
 * Sales Order Shipment PDF model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Shipment extends AbstractPdf
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @param Data $paymentData
     * @param StringUtils $string
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     * @param Config $pdfConfig
     * @param Factory $pdfTotalFactory
     * @param ItemsFactory $pdfItemsFactory
     * @param TimezoneInterface $localeDate
     * @param StateInterface $inlineTranslation
     * @param Renderer $addressRenderer
     * @param StoreManagerInterface $storeManager
     * @param ResolverInterface $localeResolver
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Data $paymentData,
        StringUtils $string,
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        Config $pdfConfig,
        Factory $pdfTotalFactory,
        ItemsFactory $pdfItemsFactory,
        TimezoneInterface $localeDate,
        StateInterface $inlineTranslation,
        Renderer $addressRenderer,
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        array $data = [],
        DirectoryList $directoryList,
        ShipmentFactory $shipmentFactory,
        ApiPlug $apiPlug
    ) {
        $this->apiPlug         = $apiPlug;
        $this->fileSystem      = $filesystem;
        $this->shipmentFactory = $shipmentFactory;
        $this->directoryList   = $directoryList;
        $this->_storeManager   = $storeManager;
        $this->_localeResolver = $localeResolver;
        parent::__construct(
            $paymentData,
            $string,
            $scopeConfig,
            $filesystem,
            $pdfConfig,
            $pdfTotalFactory,
            $pdfItemsFactory,
            $localeDate,
            $inlineTranslation,
            $addressRenderer,
            $data
        );
    }

    /**
     * Return PDF document
     *
     * @param \Magento\Sales\Model\Order\Shipment[] $shipments
     *
     * @return Zend_Pdf
     */
    public function getPdf($shipments = [])
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);
        foreach ($shipments as $shipment) {
            //if shipment is TCG shipment

            $order = $shipment->getOrder();

            $quoteId = $order->getQuoteId();

            $shippingMethod = $order->getShippingMethod();

            $tcgShipment = $this->shipmentFactory->create();

            $shipmentQuote = $tcgShipment->load($quoteId);


            if (count($shipmentQuote->getData()) != 0) {
                if ($shippingMethod = "appinlet_the_courier_guy_appinlet_the_courier_guy") {
                    $mediaPath = "";
                    //check if pub folder is being used as root

                    $pub = $this->directoryList->getUrlPath("pub");

                    if ($pub == "pub") {
                        //pub is not being used as root
                        $mediaPath = "pub/" . $this->directoryList::MEDIA . "/";
                    } else {
                        //pub directory is being used as root
                        $mediaPath = $this->directoryList::MEDIA . "/";
                    }


                    $fileName = $mediaPath . "/appinlet_the_courier_guy/" . $quoteId . ".pdf";

                    //if waybill has not been saved. get base64 encoded waybill via API and save it

                    if (! file_exists($fileName)) {
                        $waybillArray = $this->apiPlug->getWaybill($shipmentQuote->getData('shipping_quote_id'));

                        $media = $this->fileSystem->getDirectoryWrite($this->directoryList::MEDIA);

                        $filePathName = "appinlet_the_courier_guy/" . $quoteId . ".pdf";

                        $media->writeFile($filePathName, base64_decode($waybillArray['waybillBase64']));
                    }
                    //end of if waybill has not been saved


                    $tcgWaybill = new Zend_Pdf($fileName, null, true);

                    return $tcgWaybill;
                }
            }

            //end if shipment is TCG shipment, into other shipments

            if ($shipment->getStoreId()) {
                $this->_localeResolver->emulate($shipment->getStoreId());
                $this->_storeManager->setCurrentStore($shipment->getStoreId());
            }
            $page  = $this->newPage();
            $order = $shipment->getOrder();
            /* Add image */
            $this->insertLogo($page, $shipment->getStore());
            /* Add address */
            $this->insertAddress($page, $shipment->getStore());
            /* Add head */
            $this->insertOrder(
                $page,
                $shipment,
                $this->_scopeConfig->isSetFlag(
                    self::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID,
                    ScopeInterface::SCOPE_STORE,
                    $order->getStoreId()
                )
            );
            /* Add document text and number */
            $this->insertDocumentNumber($page, __('Packing Slip # ') . $shipment->getIncrementId());
            /* Add table */
            $this->_drawHeader($page);
            /* Add body */
            foreach ($shipment->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $page, $order);
                $page = end($pdf->pages);
            }
            if ($shipment->getStoreId()) {
                $this->_localeResolver->revert();
            }
        }
        $this->_afterGetPdf();

        return $pdf;
    }

    /**
     * Create new page and assign to PDF object
     *
     * @param array $settings
     *
     * @return Zend_Pdf_Page
     */
    public function newPage(array $settings = [])
    {
        /* Add new table head */
        $page                     = $this->_getPdf()->newPage(Zend_Pdf_Page::SIZE_A4);
        $this->_getPdf()->pages[] = $page;
        $this->y                  = 800;
        if (! empty($settings['table_header'])) {
            $this->_drawHeader($page);
        }

        return $page;
    }

    /**
     * Draw table header for product items
     *
     * @param Zend_Pdf_Page $page
     *
     * @return void
     */
    protected function _drawHeader(Zend_Pdf_Page $page)
    {
        /* Add table head */
        $this->_setFontRegular($page, 10);
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0.93, 0.92, 0.92));
        $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y - 15);
        $this->y -= 10;
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));

        //columns headers
        $lines[0][] = ['text' => __('Products'), 'feed' => 100];

        $lines[0][] = ['text' => __('Qty'), 'feed' => 35];

        $lines[0][] = ['text' => __('SKU'), 'feed' => 565, 'align' => 'right'];

        $lineBlock = ['lines' => $lines, 'height' => 10];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new Zend_Pdf_Color_GrayScale(0));
        $this->y -= 20;
    }
}
