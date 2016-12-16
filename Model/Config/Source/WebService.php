<?php
namespace Feedaty\Badge\Model\Config\Source;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\StoreManagerInterface;
use Feedaty\Badge\Helper\Data as DataHelp;

class  WebService {

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Feedaty\Badge\Helper\Data
     */
    protected $_dataHelper;

    /**
    * Constructor
    *
    * @param $scopeConfig
    * @param $storeManager
    * @param $dataHelper
    *
    */
    public function __construct( ScopeConfigInterface $scopeConfig, StoreManagerInterface $storeManager, DataHelp $dataHelper) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->_dataHelper = $dataHelper;
    }


    /**
    * Function _getMerchantCode
    *
    * @param $store
    *
    *
    * @return feedaty_credentials
    */
    public function _get_MerchantData($store = null) {

        $mode = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        //if mode is in multimerchant
        if($mode == 1) {
        
            $feedaty_code_multisite = unserialize($this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code_multisite', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

            if (!is_null($store)) {
                foreach ($feedaty_code_multisite as $record) {
                    foreach ($record as $k => $v)
                        $tmp[$k] = $v;
                    if ($tmp['merchant_code_language'] == $store && strlen(trim($tmp['merchant_code'])) > 0 && strlen(trim($tmp['merchant_secret'])) > 0) {
                        $feedaty_credentials['code'] = $tmp['merchant_code'];
                        $feedaty_credentials['secret']  = $tmp['merchant_secret'];
                    }
                }
            } else {
                foreach ($feedaty_code_multisite as $record) {
                    foreach ($record as $k => $v)
                        $tmp[$k] = $v;
                    if ($tmp['merchant_code_language'] == $this->storeManager->getStore()->getCode() && strlen(trim($tmp['merchant_code'])) > 0) {
                        $feedaty_credentials['code'] = $tmp['merchant_code'];
                        $feedaty_credentials['secret'] = $tmp['merchant_secret'];
                    }
                }
            }
        }

        //if mode is in standard
        else {
            $feedaty_credentials['code'] = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $feedaty_credentials['secret'] = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }

        return $feedaty_credentials;
    }

    /**
    * Function getReqToken - get the request token
    *  
    * @return $response
    *
    */
    private function getReqToken(){
        
        $header = array( 'Content-Type: application/x-www-form-urlencoded');
        $url = "http://api.feedaty.com/OAuth/RequestToken";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);

        $response = json_decode(curl_exec($ch));

        curl_close($ch);

        return $response;
    }


    /**
    * Function serializeData - serialize data to send 
    * 
    * @param $fields
    *
    * @return $dati
    */
    private function serializeData($fields){
        $data = '';
        foreach($fields as $k => $v){
            $data .= $k . '=' . urlencode($v) . '&';
        }
        rtrim($data, '&');
        return $data;
    }


    /**
    * Function getAccessToken - get the access token
    *
    * @param $token
    *
    * @return $response - the access token
    */
    private function getAccessToken($token,$merchant,$secret){

        $encripted_code = $this->encryptToken($token,$merchant,$secret);

        $fields = array( 'oauth_token' => $token->RequestToken,'grant_type'=>'authorization' );
        $header = array( 'Content-Type: application/x-www-form-urlencoded','Authorization: Basic '.$encripted_code,'User-Agent: Fiddler' );
        $dati = $this->serializeData($fields);
        $url = "http://api.feedaty.com/OAuth/AccessToken";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dati);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);

        $response = json_decode(curl_exec($ch));

        curl_close($ch);

        return $response;
    }


    /**
    * Function encryptToken
    *
    * @param $token
    * @param $merchant
    * @param $secret
    *
    * @return $base64_sha_token - the encrypted token
    */
    private function encryptToken($token,$merchant,$secret){

        $sha_token = sha1($token->RequestToken.$secret);
        $base64_sha_token = base64_encode($merchant.":".$sha_token);
        return $base64_sha_token;   
    }


    /**
    * Function retrive_informations_product
    *
    * @param int $id
    *
    */
    public function retrive_informations_product($id) {

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $cache = $om->get('Magento\Framework\App\CacheInterface');

		$content = $cache->load("feedaty_product_".$id);
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
		$language = preg_replace('/\_/', '-', $resolver->getLocale());

        if ($language != 'en-US' && $language != 'es-ES' && $language != 'it-IT' && $language != 'fr-FR' && $language != 'de-DE') {
            $language = 'en-US';
        }


		if (!$content || strlen($content) == 0 || $content === "null") {

            $mode = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            if($mode == 0) $feedaty_code = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            elseif ($mode == 1) {
                $merchant_data = $this->_get_MerchantData();
                $feedaty_code = $merchant_data['code'];
            }

			$ch = curl_init();

            $resolver = $om->get('Magento\Framework\Locale\Resolver');
            $url = 'http://widget.zoorate.com/go.php?function=feed&action=ws&task=product&merchant_code='.$feedaty_code.'&ProductID='.$id.'&language='.$language;

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, '3');
			$content = trim(curl_exec($ch));
			curl_close($ch);
			
			if (strlen($content) > 0)
			$cache->save($content, "feedaty_product_".$id, array("feedaty_cache"), 3*60*60); // 3 hours of cache
		}
		
		$data = json_decode($content,true);
		
		return $data;
	}
	

    /**
    * Function retrive_informations_store
    *
    * @return $data
    *
    */
	public function retrive_informations_store() {

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $cache = $om->get('Magento\Framework\App\CacheInterface');

		$content = $cache->load("feedaty_store");
		
		if (!$content || strlen($content) < 5 || $content === "null") {

            $mode = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if($mode == 0) $feedaty_code = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            elseif ($mode == 1) {
                $merchant_data = $this->_get_MerchantData();
                $feedaty_code = $merchant_data['code'];
            }

			$ch = curl_init();
            $url = 'http://widget.zoorate.com/go.php?function=feed&action=ws&task=merchant&merchant_code='.$feedaty_code;
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, '3');
			$content = trim(curl_exec($ch));
			curl_close($ch);

			if (strlen($content) > 0)
			$cache->save($content, "feedaty_store", array("feedaty_cache"), 3*60*60); // 3 hours of cache
		}
		
		$data = json_decode($content,true);
    
		return $data;
	}


    /**
    * Function getProductRichSnippet 
    *
    * @param $product_id
    *
    * @return $response - the html product's rich snippet
    *
    */
    public function getProductRichSnippet($product_id){

        $merchant_data = $this->_get_MerchantData();
        $path = 'http://white.zoorate.com/gen';
        $dati = array( 'w' => 'wp','MerchantCode' => $merchant_data['code'],'t' => 'microdata', 'version' => 2, 'sku' => $product_id );
        $header = array( 'Content-Type: text/html','User-Agent: Fiddler' );
        $dati = $this->serializeData($dati);
        $path.='?'.$dati;
        $ch = curl_init($path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    

    /**
    * Function getMerchantRichSnippet
    *
    * @return $response - the html merchant's rich snippet
    *
    */
    public function getMerchantRichSnippet(){

        $merchant_data = $this->_get_MerchantData();
        $path = 'http://white.zoorate.com/gen';
        $dati = array(
                'w' => 'wp',
                'MerchantCode' => $merchant_data['code'],
                't' => 'microdata',
                'version' => 2,
        );
        $header = array('Content-Type: text/html',
                'User-Agent: Fiddler'
        );
        $dati = $this->serializeData($dati);
        $path.='?'.$dati;
        $path = str_replace("=2&", "=2", $path);
        $ch = curl_init($path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }



	/**
    * Function send_order 
    *
    * @param $data
    * @param $merchant 
    * @param $secret
    *
    * @return $content - curl response for send order
    */
	public function send_order( $data,$merchant,$secret ) {
        
        $ch = curl_init();
        $url = 'http://api.feedaty.com/Orders/Insert';

        $token = $this->getReqToken();
        
        $accessToken = $this->getAccessToken($token, $merchant, $secret);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, '60');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/json', 'Authorization: Oauth '.$accessToken->AccessToken));
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $content = trim(curl_exec($ch));

        curl_close($ch);

	}


    /**
    * Function _get_FeedatyData
    *
    * @return $data
    */
    public function _get_FeedatyData() {

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $cache = $om->get('Magento\Framework\App\CacheInterface');

        $content = $cache->load("feedaty_store");

        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $language = preg_replace('/\_/', '-', $resolver->getLocale());

        if($language != 'en-US' && $language != 'it-IT' && $language != 'es-ES' && $language != 'fr-FR' && $language != 'de-DE')
            $language = 'en-US';

        WebService::send_notification($this->scopeConfig,$this->storeManager,$this->_dataHelper);

        $mode = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $merchant_data = $this->_get_MerchantData();
        $feedaty_code = $merchant_data['code'];
    

        $string = "FeedatyData".$feedaty_code.$language;

        $content =$cache->load($string);

		if (!$content || strlen($content) == 0 || $content === "null") {
            $ch = curl_init();
            $url = 'http://widget.zoorate.com/go.php?function=feed_be&action=widget_list&merchant_code='.$feedaty_code.'&language='.$language;

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, '60');
            $content = trim(curl_exec($ch));
            curl_close($ch);

            $cache->save($content, "FeedatyData".$feedaty_code.$language, array("feedaty_cache"), 24*60*60); // 24 hours of cache
        }

        $data = json_decode($content,true);
        return $data;
    }


    /**
    * Function send_notification
    *
    * @param object $_scopeConfig
    * @param object $_storeManager
    * @param object $_dataHelper
    */
    public static function send_notification($_scopeConfig,$_storeManager,$_dataHelper) {


        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $cache = $om->get('Magento\Framework\App\CacheInterface');

        $content = $cache->load("feedaty_notification");

        $cnt = $_scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)."-".$_scopeConfig->getValue('feedaty_badge_options/widget_store/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)."-".$_scopeConfig->getValue('feedaty_badge_options/widget_products/product_enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($content != $cnt) {
            $store = $_storeManager->getStore();

            $ver = json_decode(json_encode($_dataHelper->getExtensionVersion()),true);

            $prodMetadata = $om->get('Magento\Framework\App\ProductMetadataInterface');

            $fdata['keyValuePairs'][] = array("Key" => "Platform", "Value" => "Magento ".$prodMetadata->getVersion());
            $fdata['keyValuePairs'][] = array("Key" => "Version", "Value" => (string) $_dataHelper->getExtensionVersion());
            $fdata['keyValuePairs'][] = array("Key" => "Url", "Value" => $_storeManager->getStore()->getBaseUrl());
            $fdata['keyValuePairs'][] = array("Key" => "Os", "Value" => PHP_OS);
            $fdata['keyValuePairs'][] = array("Key" => "Php Version", "Value" => phpversion());
            $fdata['keyValuePairs'][] = array("Key" => "Name", "Value" => $store->getName());
            $fdata['keyValuePairs'][] = array("Key" => "Action", "Value" => "Enabled");
            $fdata['keyValuePairs'][] = array("Key" => "Position_Merchant", "Value" => $_scopeConfig->getValue('feedaty_badge_options/widget_store/store_position', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $fdata['keyValuePairs'][] = array("Key" => "Position_Product", "Value" => $_scopeConfig->getValue('feedaty_badge_options/widget_products/product_position', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $fdata['keyValuePairs'][] = array("Key" => "Status", "Value" => $_scopeConfig->getValue('feedaty_global/sendorder/sendorder', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $fdata['merchantCode'] = $_scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            $ch = curl_init();

            $url = 'http://www.zoorate.com/ws/feedatyapi.svc/SetPluginKeyValue';

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, '60');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($fdata));
            curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/json','Expect:'));
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            $content = trim(curl_exec($ch));

            curl_close($ch);
            
            $cache->save($cnt, "feedaty_notification", array("feedaty_cache"), 10*24*60*60);
        }
    }
}	
