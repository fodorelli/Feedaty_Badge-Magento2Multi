<?php
namespace Feedaty\Badge\Block\System\Config\Form\Field;

use \Magento\Framework\Data\Form\Element\AbstractElement;
use \Magento\Backend\Block\Template\Context;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;


class MerchantCodes extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray 
{



    public function __construct( Context $context, StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig )
    {
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;

        parent::__construct($context);
    }


    public function _construct()
    {

        $this->addColumn('merchant_code_language', [
            'label' => __('Store'),
            'style' => 'width:120px',
            'type' => 'options'
        ]);

        $this->addColumn('merchant_code', [
            'label' => __('Merchant code'),
            'style' => 'width:120px'
        ]);

        $this->addColumn('merchant_secret', [
            'label' => __('Merchant secret'),
            'style' => 'width:120px'
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add field');

        $this->setTemplate('Magento_Config::system/config/form/field/array.phtml');

        parent::_construct();
    }

    public function renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new \Exception('Wrong column name specified.');
        }

        $column = $this->_columns[$columnName];
        $inputName = $this->_getCellInputElementName($columnName);


        if ($columnName == "merchant_code_language") {
            $rendered = '<input type="hidden" name="' .$inputName. '" value="<%-' .$columnName. '%>">'.'<%-' .$columnName. '%>';
            return $rendered;
        } else {
            return '<input type="text" name="' . $inputName . '" value="<%-' . $columnName . '%>" ' .
            ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
            (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
            (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>';
        }
    }

    public function getArrayRows()
    {
    
        $mode = $this->_scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        foreach ($this->_storeManager->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();

                foreach ($stores as $store) {
                    $result[$store->getCode()] = "";
                }
            }
        }

        $element = $this->getElement();
        $rowColumnValues = [];


        if ($element->getValue() && is_array($element->getValue()) && $mode == 1) {
            foreach ($element->getValue() as $rowId => $row) {
                unset($tmp);
                foreach ($row as $key => $value) {
                    $tmp[$key] = $this->escapeHtml($value);
                    //$rowColumnValues[$this->_getCellInputElementId($rowId, $key)] = $row[$key];
                }

                $result[$tmp['merchant_code_language']] = array($tmp['merchant_code'],$tmp['merchant_secret']);

            }

        }

        $i=0;

        foreach ($result as $key => $value) {
            if (!empty($value)) {
            
                $row['merchant_code_language'] = $this->escapeHtml($key);
                $row['merchant_code'] = $this->escapeHtml($value[0]);
                $row['merchant_secret'] = $this->escapeHtml($value[1]);
                $row['_id'] = $i ;
                $row['column_values'] = $rowColumnValues ;
                $resultok[$i] = new \Magento\Framework\DataObject($row);
                $this->_prepareArrayRow($resultok[$i]);
                $i++;
            }
            else
            {
                $row['merchant_code_language'] = $this->escapeHtml($key);
                $row['merchant_code'] = $this->escapeHtml($value);
                $row['merchant_secret'] = $this->escapeHtml($value);
                $row['_id'] = $i ;
                $row['column_values'] = $rowColumnValues ;
                $resultok[$i] = new \Magento\Framework\DataObject($row);
                $this->_prepareArrayRow($resultok[$i]);
                $i++;

            }
        }

        $this->_arrayRowsCache = $resultok;

        return $this->_arrayRowsCache;

    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setElement($element);
        $mode = $this->_scopeConfig->getValue('feedaty_global/feedaty_preferences/installation_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($mode == 0) {
            $element->setDisabled('disabled');
        }
       

        $fieldId = $this->getElement()->getId();

        $html = "<div id=".$fieldId.">".$this->_toHtml()."</div>";
        $html = str_replace('class="action-add" title="Add" type="button"','class="action-add" title="Add" type="button" style="display:none"',$html);
        $html = str_replace('class="action-delete" type="button"','class="action-delete" type="button" style="display:none"',$html);

        $this->_arrayRowsCache = null; // doh, the object is used as singleton!
         
        return $html;
    }
}