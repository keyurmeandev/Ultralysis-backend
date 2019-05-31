<?php

namespace projectsettings;

use projectsettings;
use filters;
use db;
use config;

class BaseMults extends BaseLcl {
    public function __construct($gids) {
	
		parent::__construct($gids);

		$this->period 	  = "period";

        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ($CAN)'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
        );

		/**
         * Format Region Store Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["FORMAT_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("FORMAT" => "F29");
        $this->pageArray["FORMAT_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("REGION" => "F14");
        $this->pageArray["FORMAT_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");

		/**
         * Format Region Store Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["BARB_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("COUNTRY" => "F33");
        $this->pageArray["BARB_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BARB" => "F32");
        $this->pageArray["BARB_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");
		
		/**
         * Range Brand Sku Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("RANGE" => "F4");
        $this->pageArray["PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND" => "F3");
        $this->pageArray["PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
		
		/**
         * Buyer Range Sku Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["BUYER_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BUYER" => "F34");
        $this->pageArray["BUYER_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("RANGE" => "F4");
        $this->pageArray["BUYER_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
		
		$this->pageArray["SINGLE_SKU_STORE_HISTORY_CHECK"]["SKU_ACCOUNT"] = "F2";
        $this->pageArray["SINGLE_SKU_STORE_HISTORY_CHECK"]["STORE_ACCOUNT"] = "F13";
		
        /**
         * Contribution Analysis Page
         * ACCOUNT as field name which is refered from dataArray
         */
        $this->pageArray["CONTRIBUTION_ANALYSIS"]["ACCOUNT"] = "F2";

        /**
         * Product Efficiency Page
         */
        $this->pageArray["PRODUCT_EFFICIENCY"]["ACCOUNT"] = "F2";
        $this->pageArray["PRODUCT_EFFICIENCY"]["COUNT_ACCOUNT"] = "F13";
        $this->pageArray["PRODUCT_EFFICIENCY"]["BAR_ACCOUNT_TITLE"] = "Skus";
        
		/**
         * Range Sku Barb Map Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CATEGORY_BARB_NETWORK_MAP"]["GRID_FIELD"]["gridCategory"] = array("RANGE" => "F4");
        $this->pageArray["CATEGORY_BARB_NETWORK_MAP"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F2");
        $this->pageArray["CATEGORY_BARB_NETWORK_MAP"]["GRID_FIELD"]["gridSKU"] = array("BARB" => "F32");
        $this->pageArray["CATEGORY_BARB_NETWORK_MAP"]["mapAccount"] = "F32";
		
		$this->dataArray['F32']['NAME'] 		= 'ORG';
        $this->dataArray['F32']['NAME_ALIASE'] 	= 'BARB';
		
		$this->dataArray['F33']['NAME'] 		= 'country';
        $this->dataArray['F33']['NAME_ALIASE'] 	= 'COUNTRY';
		
		$this->dataArray['F34']['NAME'] 		= 'ppg';
        $this->dataArray['F34']['NAME_ALIASE'] 	= 'BUYER';
    }
}

?>