<?php

namespace projectsettings;

class GrenadeMults extends BaseMults {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "grenade_mults";

		if($this->hasTerritory($aid, $projectID))
			$this->territorytable = "territory";

		//$this->clustertable = "arla_custom_product_group";
        //$this->clustertable = "cluster";
		$this->masterplanotable = "";
        $this->accounttable     = "fgroup";
        $this->clientID = "GRENADE";
        $this->aid = $aid;
        $this->projectID = $projectID;
		$this->GID = implode(",",$gids);

        parent::__construct($gids);

        if ($projectID == 32)
            $this->fetchProductAndMarketFilterOnTabClick = false;

        if (count($gids) > 1) {
            $this->weekperiod = "$this->timetable.week";
            $this->yearperiod = "$this->timetable.year";
        }

        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID = $this->accounttable.gid AND $this->maintable.accountID = ".$aid . " ";
		
        $this->currencySign = "£";

        if (count($gids) > 1)
            $this->groupName = "Mults";

        if (!$this->hasMeasureFilter) {
            // measure selection list
            if(in_array(3,$gids)) {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => false),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => true)
                );
            } else if(in_array(2,$gids)){ //Asda
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false),
                    array('measureID' => 5, 'jsonKey'=>'VAT', 'measureName' => 'Value (exc VAT)', 'selected' => false)
                );
            } else {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false)
                );
            }
        }
        
        $this->configureClassVars();
        
        if($this->projectTypeID == 6) // DDB
        {
            if (!$this->hasMeasureFilter) {
                if((in_array(3,$gids) || in_array(1,$gids)) && count($gids) != 3 ) {
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                        array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                        array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                        array('measureID' => 3, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'Stores Selling', 'selected' => true)
                    );
                    $this->measureArray['M3']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                    $this->measureArray['M3']['ALIASE'] = "DISTRIBUTION";
                    $this->measureArray['M3']['attr'] = "COUNT";
                    $this->measureArray['M3']['dataDecimalPlaces']   = 0;                    
                    
                }
                elseif(in_array(2,$gids) && count($gids) != 3) {
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                        array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                        array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                        array('measureID' => 3, 'jsonKey'=>'VAT', 'measureName' => 'VALUE EX. VAT', 'selected' => true),
                        array('measureID' => 4, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'Stores Selling', 'selected' => true)
                    );
                    
                    $this->measureArray['M3']['VAL'] = "VAT";
                    $this->measureArray['M3']['ALIASE'] = "VAT";
                    $this->measureArray['M3']['attr'] = "SUM";
                    unset($this->measureArray['M3']['dataDecimalPlaces']);

                    $this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                    $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
                    $this->measureArray['M4']['attr'] = "COUNT";
                    $this->measureArray['M4']['dataDecimalPlaces']   = 0;
                }
                else
                {
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                        array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                        array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                        array('measureID' => 3, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'Stores Scanning', 'selected' => true)
                    );
                    
                    $this->measureArray['M3']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                    $this->measureArray['M3']['ALIASE'] = "DISTRIBUTION";
                    $this->measureArray['M3']['attr'] = "COUNT";
                    $this->measureArray['M3']['dataDecimalPlaces']   = 0;            
                }
            }
            
            $this->accountHelperTables = $this->accounttable.", ".$this->maintable;
            $this->accountHelperLink = " WHERE $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID = $this->accounttable.gid AND $this->maintable.accountID = ".$aid . " ";
            
            $this->filterPages[5]['config'] = array(
                    "table_name" => $this->accounttable,
                    "helper_table" => $this->accountHelperTables,
                    "setting_name" => "account_settings",
                    "helper_link" => $this->accountHelperLink,
                    "type" => "A",
                    "enable_setting_name" => "has_account"
                );
        }

        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE GID IN (".$this->GID.")";
		$this->dataArray['F17']['use_alias_as_tag'] = true;

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
		
        $this->pageArray["PROJECT_FOOTER_TEXT"] = "All data contained is owned by ".$this->pageArray["PROJECT_NAME"]." and made available under strict terms. Activity is monitored, and violation of terms will be reported to ".$this->pageArray["PROJECT_NAME"].".";

        $this->stock_qty = $this->maintable.".VAT";
    }

}

?>