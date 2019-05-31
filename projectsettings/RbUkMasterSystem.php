<?php

namespace projectsettings;
// year week
class RbUkMasterSystem extends BaseMults {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "rbuk_mults";        

		if($this->hasTerritory($aid, $projectID))
			$this->territorytable = "territory";

		$this->masterplanotable = "";
		$this->accounttable = "fgroup";
        $this->clientID = "RBUK";
        $this->aid = $aid;
        $this->projectID = $projectID;
		$this->GID = implode(",",$gids);
        
        parent::__construct($gids);


        /*if ($projectID == 353)
            $this->fetchProductAndMarketFilterOnTabClick = false;*/

        $this->weekperiod = "$this->timetable.week";
        $this->yearperiod = "$this->timetable.year";

        $this->currencySign = "£";
        $this->groupName = "Mults";

        if(!$this->hasMeasureFilter){
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
            );        
        }

        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE GID IN (".$this->GID.")";
		$this->dataArray['F17']['use_alias_as_tag'] = true;
		
		$this->dataArray['F35']['NAME'] 		= "gname";
        $this->dataArray['F35']['NAME_ALIASE'] 	= 'CUSTOMER';
		
		$this->dataArray['F36']['NAME'] 		= "Level1";
        $this->dataArray['F36']['NAME_ALIASE'] 	= 'AREA MANAGER';
		
		$this->dataArray['F37']['NAME'] 		= "Level2";
        $this->dataArray['F37']['NAME_ALIASE'] 	= 'FE';

        $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
        $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

        $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
        $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR'; 
		
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
         *  Customer Summary Page
         */
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "gname", "DATA_TWO" => "agg2");
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Customer";
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Customer Performance";
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Brand Range";
        $this->pageArray["CUSTOMER_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Brand Range Performance";
						
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
		
		/**
         * Customer Brand range Sku Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CUSTOMER_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("CUSTOMER" => "F35");
        $this->pageArray["CUSTOMER_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND RANGE" => "F4");
        $this->pageArray["CUSTOMER_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
		
		/**
         * Customer Store Brand Range Sku Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CUSTOMER_STORE_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("CUSTOMER" => "F35");
        $this->pageArray["CUSTOMER_STORE_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("STORE" => "F13");
        $this->pageArray["CUSTOMER_STORE_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND RANGE" => "F4");
        $this->pageArray["CUSTOMER_STORE_BRANDRANGE_SKU_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
		
		/**
         * Brand Range Sku Customer Store Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["BRANDRANGE_SKU_CUSTOMER_STORE_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("BRAND RANGE" => "F4");
        $this->pageArray["BRANDRANGE_SKU_CUSTOMER_STORE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("SKU" => "F2");
        $this->pageArray["BRANDRANGE_SKU_CUSTOMER_STORE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("CUSTOMER" => "F35");
        $this->pageArray["BRANDRANGE_SKU_CUSTOMER_STORE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");
		
		/**
         * Level1 Level2 Store Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */		 
        $this->pageArray["AREAMANAGER_FE_STORE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("AREA MANAGER" => "F36");
        $this->pageArray["AREAMANAGER_FE_STORE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("FE" => "F37");
        $this->pageArray["AREAMANAGER_FE_STORE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");
		
		/**
         * Customer Performance by Product Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CUSTOMER_PERFORMANCE_BY_PRODUCT"]["GRID_FIELD"]["gridCategory"] = array("CUSTOMER" => "F35");
        $this->pageArray["CUSTOMER_PERFORMANCE_BY_PRODUCT"]["GRID_FIELD"]["gridBrand"] = array("BRAND" => "F3");
        $this->pageArray["CUSTOMER_PERFORMANCE_BY_PRODUCT"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");				
		
        $this->territoryHelperTables    = $this->storetable.",".$this->territorytable;
        $this->territoryHelperLink      = " WHERE ".$this->storetable.".SNO=".$this->territorytable.".SNO".
                                        " AND ".$this->storetable.".GID=".$this->territorytable.".GID".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".accountID=".$this->aid.
                                        " AND ".$this->storetable.".SNO IN (SELECT DISTINCT " . 
                                            $this->maintable . ".SNO FROM " . $this->maintable . 
                                            " WHERE ".$this->maintable.".GID IN (".$this->GID.")) ";

        $this->accountHelperTables      = $this->accounttable;
        $this->accountHelperLink        = " WHERE ".$this->accounttable.".gid IN (SELECT DISTINCT " . 
                                            $this->maintable . ".GID FROM " . $this->maintable . 
                                            " WHERE ".$this->maintable.".GID IN (".$this->GID.")) ";
		
        // set project name and footer text
		$this->pageArray["PROJECT_NAME"] = "FERRERO MASTER SYSTEM";
					
        $this->pageArray["PROJECT_FOOTER_TEXT"] = "All data contained is owned by ".$this->pageArray["PROJECT_NAME"]." and made available under strict terms. Activity is monitored, and violation of terms will be reported to ".$this->pageArray["PROJECT_NAME"].".";
										
        $this->configureClassVars();
        
        if($this->projectTypeID == 6) // DDB
        {
            if(!$this->hasMeasureFilter)
            {
                if (count($gids) == 1)
                {
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                        array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                        array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                        array('measureID' => 3, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => true)
                    );
                    
                    $this->measureArray['M3']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                    $this->measureArray['M3']['ALIASE'] = "DISTRIBUTION";
                    $this->measureArray['M3']['attr'] = "COUNT";
                    $this->measureArray['M3']['dataDecimalPlaces']   = 0;
                }
                else
                {
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                        array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                        array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true)
                    );
                }
            }
            
            $this->filterPages[5]['config'] = array(
                    "table_name" => $this->accounttable,
                    "helper_table" => $this->accountHelperTables,
                    "setting_name" => "account_settings",
                    "helper_link" => $this->accountHelperLink,
                    "type" => "A",
                    "enable_setting_name" => "has_account"
                );
        }
    }
	public function configureClassVars() {

		$this->dateField = $this->maintable . "." . $this->dateperiod;
		$this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->timetable . "," . $this->skutable . "," . $this->storetable. "," . $this->grouptable;
		
		if (!empty($this->territorytable)) {
			$this->copy_tablename .= "," . $this->territorytable . " ";
			$this->tablename .= "," . $this->territorytable . " ";
		}

		$commontables   = $this->maintable . "," . $this->timetable. "," . $this->grouptable;
		$commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.mydate=$this->timetable.mydate " .
                        "AND $this->maintable.GID=$this->timetable.GID " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ".
                        "AND $this->maintable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
        				"AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
        				AND $this->storetable.GID=$this->territorytable.GID 
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
        				"AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink;
		

		/*$this->timeHelperTables = " " . $this->timetable . "," . $this->maintable . " ";
		$this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->dateperiod . "=" . 
								$this->timetable . "." . $this->dateperiod . " AND ".
								$this->timetable.".GID IN (".$this->GID.") ";*/

        $this->timeHelperTables = $this->timetable;
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";

		if($this->hasHiddenSku()) {
            //$commontables .= ", ".$this->skutable;
            //$commonlink   .= $skulink;
            //$skulink       = '';
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

		$this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;

		/** ******************************************** USED ONLY FOR CALENDAR PAGE *********************** */
		$this->calendarItems = array();
		array_push($this->calendarItems, array(
			'DATA' => $this->maintable . "." . $this->dateperiod,
			'ALIASE' => "MYDATE"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => $this->timetable.'.week',
			'ALIASE' => "WEEK"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => $this->timetable.'.year',
			'ALIASE' => "YEAR"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => "accountweek",
			'ALIASE' => "ACCOUNTWEEK"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => "accountyear",
			'ALIASE' => "ACCOUNTYEAR"
				)
		);

		$this->tablename_for_calendar = $this->maintable . "," . $this->timetable;
		$this->where_clause_for_calendar = $this->maintable . "." . $this->dateperiod . "=" . $this->timetable . "." . $this->dateperiod . " ";
		/** ****************************************************************************************************************** */

        if($this->hasMeasureFilter){
            $this->getMeasureSettings();
        }else{
    		$this->measureArray = array();
    		$this->measureArray['M1']['VAL'] = $this->ProjectValue;
    		$this->measureArray['M1']['ALIASE'] = "VALUE";
    		$this->measureArray['M1']['attr'] = "SUM";

    		$this->measureArray['M2']['VAL'] = $this->ProjectVolume;
    		$this->measureArray['M2']['ALIASE'] = "VOLUME";
    		$this->measureArray['M2']['attr'] = "SUM";

    		$this->measureArray['M3']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
    		$this->measureArray['M3']['ALIASE'] = "PRICE";      
            $this->measureArray['M3']['attr'] = "";
    		
    		$this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
    		$this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
    		$this->measureArray['M4']['attr'] = "COUNT";
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4'
            );
        }
		
        $this->measureArray_for_productKBI_only = array();
		$this->measureArray_for_productKBI_only['M0']['VAL'] = $this->ProjectVolume;
		$this->measureArray_for_productKBI_only['M0']['ALIASE'] = "VOL";
		$this->measureArray_for_productKBI_only['M0']['attr'] = "SUM";


		$this->measureArray_for_productKBI_only['M1']['VAL'] = $this->ProjectValue;
		$this->measureArray_for_productKBI_only['M1']['ALIASE'] = "VAL";
		$this->measureArray_for_productKBI_only['M1']['attr'] = "SUM";
        
        $this->getClientProjectName();
        
        //[START] LOAD DDB SETTING
        if($this->projectTypeID == 6) // DDB
            $this->setDynamicDataBuilderSetting();        
        
	}

}

?>