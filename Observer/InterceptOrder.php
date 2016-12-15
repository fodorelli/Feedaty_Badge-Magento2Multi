<?php
namespace Feedaty\Badge\Observer;

use Feedaty\Badge\Model\Config\Source\WebService;
use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\UrlInterface;
use \Magento\Catalog\Helper\Image;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Feedaty\Badge\Helper\Data as DataHelp;

class InterceptOrder implements ObserverInterface
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
    * @var \Magento\Catalog\Helper\Image
    */
    protected $imageHelper;

    /**
    * @var Feedaty\Badge\Helper\Data
    */
    protected $_dataHelpler;

    /**
    * Constructor
    * 
    */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Image $imageHelper,
        DataHelp $dataHelpler
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->_dataHelpler = $dataHelpler;
    }

    

    /**
    * Function execute
    *
    * @param $observer
    */
    public function execute(\Magento\Framework\Event\Observer $observer){


            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $order = $observer->getEvent()->getOrder();

            $order_id = $order->getIncrementId();
            $id_store = $order->getStoreId();

            $verify = 0;

            $orderopt = $this->scopeConfig->getValue('feedaty_global/feedaty_sendorder/sendorder', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            foreach (($order->getAllStatusHistory()) as $orderComment) {
                if($orderComment->getStatus() === $orderopt) $verify++;
            }

            if ($order->getStatus() == $orderopt && $verify <= 1) {

                $baseurl_store = $this->storeManager->getStore($order->getStore_id())->getBaseUrl(UrlInterface::URL_TYPE_LINK);

                $objproducts = $order->getAllItems();

                unset($fd_products);
                
                foreach ($objproducts as $itemId => $item) {
                    unset($tmp);

                    if (!$item->getParentItem()) {
                        $fd_oProduct = $objectManager->get('Magento\Catalog\Model\Product')->load((int) $item->getProductId());

                        if ($fd_oProduct->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {

                            $selectionCollection = $fd_oProduct->getTypeInstance(true)->getSelectionsCollection(
                                $fd_oProduct->getTypeInstance(true)->getOptionsIds($fd_oProduct), $fd_oProduct
                            );
                            foreach($selectionCollection as $option) {
                                $bundleproduct = $objectManager->get('Magento\Catalog\Model\Product')->load($option->product_id);

                                $tmp['SKU'] = $bundleproduct->getProductId();
                                $tmp['Brand'] = $bundleproduct->getBrand();
                                $tmp['Name'] = $bundleproduct->getName();

                                if ($fd_oProduct->getImage() != "no_selection"){
                                    $tmp['ThumbnailURL'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $fd_oProduct->getImage();
                                }
                                else $tmp['ThumbnailURL'] = "";

                                if (is_null($tmp['Brand'])) $bundleproduct['Brand']  = "";

                                // data to make the url
                                $fd_pr_tmp_url = $fd_oProduct->setStoreId($id_store)->getUrlInStore();
                                $pattern = $this->storeManager->getStore()->getBaseUrl();
                                $replacement = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

                                //replace the wrong baseurl
                                $tmp['URL'] = str_replace($pattern, $replacement, $fd_pr_tmp_url);

                                $fd_products[] = $tmp;

                            }
                        } 
                        else {
                            $tmp['SKU'] = $item->getProductId();
                            $tmp['Brand'] = $item->getBrand();
                            $tmp['Name'] = $item->getName();
                            

                            //get the image url
                            if ($fd_oProduct->getImage() != "") {
                                $tmp['ThumbnailURL'] = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) .'catalog/product'.$fd_oProduct->getImage();
                            }
                            else
                                $tmp['ThumbnailURL'] = "";

                            // data to make the url
                            $fd_pr_tmp_url = $fd_oProduct->setStoreId($id_store)->getUrlInStore();
                            $pattern = $this->storeManager->getStore()->getBaseUrl();
                            $replacement = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

                            //replace the wrong baseurl
                            $tmp['URL'] = str_replace($pattern, $replacement, $fd_pr_tmp_url);

                            if (is_null($tmp['Brand'])) $tmp['Brand']  = "";

                            //$tmp['Price'] = $item->getPrice();
                            $fd_products[] = $tmp;
                        }
                    }
                }

                $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

                // Formatting the array to be sent
                $tmp_order['ID'] = $order->getId();
                $tmp_order['Date'] = date("Y-m-d H:i:s");
                $tmp_order['CustomerID'] = $order->getCustomerEmail();
                $tmp_order['CustomerEmail'] = $order->getCustomerEmail();
                $tmp_order['Platform'] = "Magento ".$productMetadata->getVersion();

                $shippingAddress = $order->getShippingAddress()->getCountryId();

                if ( $shippingAddress == 'IT' || $shippingAddress == 'EN' ||  $shippingAddress == 'ES' ||  $shippingAddress == 'DE' || $shippingAddress == 'FR' )
                {
                    $tmp_order['Culture'] = strtolower($shippingAddress);
                }
                else $tmp_order['Culture'] = 'en';

                $tmp_order['Products'] = $fd_products;

                $fd_data[] = $tmp_order;

                $webService = new WebService( $this->scopeConfig, $this->storeManager, $this->_dataHelpler );

                $mode = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                if($mode == 0) {

                    $merchant_code = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                    $merchant_secret = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_secret',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                    $webService->send_order($fd_data, $merchant_code, $merchant_secret);
                }

                elseif ($mode == 1) {
                    $merchant_data = $webService->_get_MerchantData($this->storeManager->getStore($order->getStore_id())->getCode());

                    $webService->send_order($fd_data, $merchant_data['code'], $merchant_data['secret']);
                }

            }

        }
}
