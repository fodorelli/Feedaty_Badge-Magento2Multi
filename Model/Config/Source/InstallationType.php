<?php
namespace Feedaty\Badge\Model\Config\Source;

use \Magento\Framework\Option\ArrayInterface;

class InstallationType implements ArrayInterface
{
    public function toOptionArray()
    {
    	$return = array(
    		array("value"=>"0","label"=>__("Standard")),
    		array("value"=>"1","label"=>__("Multi Merchant"))
    	);
		
		return $return;
    }
}
