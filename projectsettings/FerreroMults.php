<?php

namespace projectsettings;

class FerreroMults extends BaseMults {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "ferrero_mults";

		if($this->hasTerritory($aid, $projectID))
			$this->territorytable = "territory";

        //$this->clustertable = "cluster";
        $this->accounttable = "fgroup";
        
		$this->masterplanotable = "";
        $this->clientID = "FERRERO";
        $this->aid = $aid;
        $this->projectID = $projectID;
		$this->GID = implode(",",$gids);
        $this->accountLink = " AND $this->accounttable.GID = $this->maintable.gid AND $this->accounttable.GID IN ($this->GID) ";

        parent::__construct($gids);
		
        $this->currencySign = "£";
        $this->groupName = "Mults";

        if(!$this->hasMeasureFilter){
            // measure selection list
            if(in_array(3,$gids)) {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => false),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => true)
                );    
            } else {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false)
                );
            }
        }

        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE GID IN (".$this->GID.")";
		$this->dataArray['F17']['use_alias_as_tag'] = true;

/*         $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1"; */
		
		/**
         *  Executive Summary Page
         */
        $this->pageArray["EXE_SUMMARY_PAGE"]["DH"] = "F1-F3-F4-F5-F2#F19-F6-F7-F8-F9-F10-F11-F12-F13-F14-F15-F16-F26-F30-F25";
        $this->pageArray["EXE_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "agg2", "DATA_TWO" => "banner_alt");
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Brand Range";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Brand Range Performance";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Format";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Format Performance";
		
		/**
         *  Category Summary Page
         */
        $this->pageArray["CAT_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "ppg", "DATA_TWO" => "banner_alt");
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Buyer";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Buyer Performance";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Format";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Format Performance";
		
		/**
         * Region Over Under Seller Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridCategory"] = array("RANGE" => "F4");
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F2");
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridSKU"] = array("BARB" => "F32");
		$this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["SKU_FIELD"] = "F2";
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["STORE_FIELD"] = "F13";
		
        // set project name and footer text
		/*if(in_array(1,$gids))
			$this->pageArray["PROJECT_NAME"] = "FERRERO TESCO";
		elseif(in_array(2,$gids))
			$this->pageArray["PROJECT_NAME"] = "FERRERO ASDA";
		elseif(in_array(3,$gids))
			$this->pageArray["PROJECT_NAME"] = "FERRERO MORRISON";
		elseif(in_array(5,$gids))
			$this->pageArray["PROJECT_NAME"] = "FERRERO COOP";
		elseif(in_array(6,$gids))
			$this->pageArray["PROJECT_NAME"] = "FERRERO SAINSBURY";*/
			
        $this->pageArray["PROJECT_FOOTER_TEXT"] = "All data contained is owned by ".$this->pageArray["PROJECT_NAME"]." and made available under strict terms. Activity is monitored, and violation of terms will be reported to ".$this->pageArray["PROJECT_NAME"].".";
										
        $this->configureClassVars();
    }

}

?>