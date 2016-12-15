<?php
namespace Feedaty\Badge\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\StoreManagerInterface;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
     protected $resultPageFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;


    /*
    * Constructor
    *
    */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->resultPageFactory = $resultPageFactory;
    }

    /*
    * Execute
    * 
    */
    public function execute() {
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=feedatyexport.csv");
        header("Content-Transfer-Encoding: binary");

        $csv = '"Order ID","UserID","E-mail","Date","Product ID","Extra","Product Url","Product Image","Platform"'."\n";
        
        $orders = $objectManager->create('\Magento\Sales\Model\Order')->getCollection()
        ->addFieldToFilter('status', $this->scopeConfig->getValue('feedaty_global/feedaty_sendorder/sendorder', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

        foreach ($orders as $order) {

            $objproducts = $order->getAllItems();
            $id_store = $order->getStoreId();

            foreach ($objproducts as $itemId => $item) {
                unset($tmp);
                if (!$item->getParentItem()) {
                    $fd_oProduct = $objectManager->create('Magento\Catalog\Model\Product')->load((int) $item->getProductId());

                    if ($fd_oProduct->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {
                        $selectionCollection = $fd_oProduct->getTypeInstance(true)->getSelectionsCollection(
                            $fd_oProduct->getTypeInstance(true)->getOptionsIds($fd_oProduct), $fd_oProduct
                        );
                        foreach($selectionCollection as $option) {
                            $bundleproduct = $objectManager->create('Magento\Catalog\Model\Product')->load($option->product_id);

                            $tmp['Id'] = $bundleproduct->getProductId();

                            // data to make the url
                            $fd_pr_tmp_url = $fd_oProduct->setStoreId($id_store)->getUrlInStore();
                            $pattern = $this->storeManager->getStore()->getBaseUrl();
                            $replacement = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

                            //replace the wrong baseurl
                            $tmp['Url'] = str_replace($pattern, $replacement, $fd_pr_tmp_url);

                            if ($fd_oProduct->getImage() != "no_selection") {
                                $tmp['ImageUrl'] = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $fd_oProduct->getImage();
                            }
                            else
                                $tmp['ImageUrl'] = "";
                            //$tmp['sku'] = $item->getSku();

                            $tmp['Name'] = $bundleproduct->getName();
                            $tmp['Brand'] = $bundleproduct->getBrand();
                            if (is_null($tmp['Brand'])) $bundleproduct['Brand']  = "";
                            $fd_products[] = $tmp;

                            

                            $csv .= '"'.$order->getId().'","'.$order->getBillingAddress()->getEmail().'","'.$order->getBillingAddress()->getEmail().'",'
                                .'"'.$order->getCreatedAt().'","'.$item->getProductId().'","'.str_replace('"','""',$tmp['Name']).'","'.$tmp['Url'].'","'.$tmp['ImageUrl'].'","Magento '.$productMetadata->getVersion().' CSV"'
                                ."\n";


                        }
                    } 
                    else {
                        $tmp['Id'] = $item->getProductId();

                        // data to make the url
                        $fd_pr_tmp_url = $fd_oProduct->setStoreId($id_store)->getUrlInStore();
                        $pattern = $this->storeManager->getStore()->getBaseUrl();
                        $replacement = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

                        //replace the wrong baseurl
                        $tmp['Url'] = str_replace($pattern, $replacement, $fd_pr_tmp_url);

                        if ($fd_oProduct->getImage() != "no_selection") {
                            $tmp['ImageUrl'] = $this->storeManager->getStore($id_store)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $fd_oProduct->getImage();
                        }
                        else
                            $tmp['ImageUrl'] = "";
                        //$tmp['sku'] = $item->getSku();

                        $tmp['Name'] = $item->getName();
                        $tmp['Brand'] = $item->getBrand();
                        if (is_null($tmp['Brand'])) $tmp['Brand']  = "";


                        $csv .= '"'.$order->getId().'","'.$order->getBillingAddress()->getEmail().'","'.$order->getBillingAddress()->getEmail().'",'
                            .'"'.$order->getCreatedAt().'","'.$item->getProductId().'","'.str_replace('"','""',$tmp['Name']).'","'.$tmp['Url'].'","'.$tmp['ImageUrl'].'","Magento '.$productMetadata->getVersion().' CSV"'
                            ."\n";
                    }
                }
            }

        }
        
        echo $csv;
    }
}