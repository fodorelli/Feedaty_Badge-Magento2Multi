<?php
namespace Feedaty\Badge\Model\Config\Source;

use \Magento\Framework\Option\ArrayInterface;

class FeedatyOrder implements ArrayInterface
{
    public function toOptionArray()
    {
    	$return = array(
    		array("value"=>"0","label"=>__("Newest reviews first")),
    		array("value"=>"1","label"=>__("Old reviews first")),
    	);
		
		return $return;
    }
}

