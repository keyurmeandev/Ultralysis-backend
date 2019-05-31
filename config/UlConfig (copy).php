<?php
namespace config;

use projectsettings;
use filters;
use db;
use utils;
use classes;
use datahelper;
use projectstructure;

/*******
 * ABSTRACT CONFIG CLASS, all data fetching class will extend this class
 * All of the common variables will be kept here, at one place.
 * It will additionally collect time ranges 'getSlice' 
 * SO WE DON'T NEED TO DELCARE THE FOLLOWING VARIABLES IN ALL DATA-CLASSES AGAIN AND AGAIN
 *******/
 abstract class UlConfig{
    /************** DEFAULT VARIABLES EVELRY DATA CLASS SHOULD HAVE ****************************/
    public $settingVars;   //containes all setting variables
    public $queryVars;     //containes query related variables [queryHandler,linkid]
    public $jsonOutput;     //holds all return data
    public $ValueVolume;   //holds measure selection sent from client application
    public $queryPart;	   //holds table joining strings and additional filter parts
    /*******************************************************************************************/
    
    public function initiate($settingVars){
        $this->settingVars  = $settingVars;
        $this->queryVars    = projectsettings\settingsGateway::getInstance();
        $this->ValueVolume  = getValueVolume($this->settingVars);
        // filters\timeFilter::getSlice($this->settingVars);
        $this->prepareTimeFilter();
        $this->jsonOutput    = array();

		$configurationCheck = new ConfigurationCheck($this->settingVars, $this->queryVars);
		$configurationCheck->checkConfiguration();

		$this->configureProductFilter();
        $this->configureSkuFilter();
		$this->configureMarketFilter();

		//IF NEEDED COLLECT & PROVIDE DATA HELPERS
        if ($_REQUEST['DataHelper'] == "true") {
        	$configureProject = new ConfigureProject($this->settingVars, $this->queryVars);
        	$configureProject->initializeProject($this->jsonOutput);
        }

        /*LOADED WHEN THE PROJECT TYPE IS DDB */
        if (isset($_REQUEST['projectType']) && $_REQUEST['projectType'] == "ddb") {
            $this->configureProductAndMarketFilter();
        }
    }

    public function prepareTimeFilter()
    {
        $projectStructureType = "projectstructure\\".$this->settingVars->projectStructureType;
        $structureClass = new $projectStructureType();
        $structureClass->prepareTimeFilter($this->settingVars, $this->queryVars, array());
    }

    /*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/   
    public function getAll(){

        // $tablejoins_and_filters       = $this->settingVars->link;
        $tablejoins_and_filters = '';

        if (isset($_REQUEST['commonFilterApplied']) && $_REQUEST['commonFilterApplied'] == true && 
        	isset($_REQUEST["ADV_FS"]) && $_REQUEST["ADV_FS"] != '') {
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars, "", "ADV_FS");
        }
	
        if (isset($_REQUEST["FSG"]) && $_REQUEST["FSG"] != '') {
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars, "", "FSG");
        }
	
    	if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
    	    foreach($_REQUEST['FS'] as $key=>$data)
    	    {
        		if(!empty($data) && isset($this->settingVars->dataArray[$key]))
        		{
        		    $filterKey      = !key_exists('ID',$this->settingVars->dataArray[$key]) ? $this->settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
        		    if($filterKey=="CLUSTER")
        		    {
        			$this->settingVars->tablename 	 = $this->settingVars->tablename.",".$this->settingVars->clustertable;
        			$tablejoins_and_filters		.= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
        		    }
        		}
    	    }
        }

        // will work when calling with sendRequest services
    	if (isset($_REQUEST["HidePrivate"]) && $_REQUEST["HidePrivate"] == 'true') {
			$fieldPart = explode(".", $this->settingVars->privateLabelFilterField);
			if (count($fieldPart) > 1) {
				if (!in_array($fieldPart[0], $this->settingVars->tableUsedForQuery))
					$this->settingVars->tableUsedForQuery[] = $fieldPart[0];
			}
            $tablejoins_and_filters	 .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
    	}

    	// will work when calling with default services
        if (!isset($_REQUEST["HidePrivate"])){            
            if($this->settingVars->pageArray[$this->settingVars->pageName]["PRIVATE_LABEL"] != null)
            if($this->settingVars->pageArray[$this->settingVars->pageName]["PRIVATE_LABEL"]==true){
            	$fieldPart = explode(".", $this->settingVars->privateLabelFilterField);
				if (count($fieldPart) > 1) {
					if (!in_array($fieldPart[0], $this->settingVars->tableUsedForQuery))
						$this->settingVars->tableUsedForQuery[] = $fieldPart[0];
				}
                $tablejoins_and_filters	 .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
            }
        }

        if(isset($_REQUEST['territoryLevel']))
        {
            if (!in_array($this->settingVars->territoryTable, $this->settingVars->tableUsedForQuery))
                $this->settingVars->tableUsedForQuery[] = $this->settingVars->territoryTable;
        }
        
		if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {		
			if($_REQUEST["Level"] == '1')
			{
				$tablejoins_and_filters .= " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." <> 'NOT CALLED' ";
			}
			else if($_REQUEST["Level"] == '2')
			{
				$tablejoins_and_filters .= " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." = 'NOT CALLED' ";
			}
		}
        
        // asort($this->settingVars->tableUsedForQuery);
        $this->settingVars->updateDefaultConfiguration($this->settingVars->tableUsedForQuery);
        $tablejoins_and_filters1 = $this->settingVars->link;
        $tablejoins_and_filters1 .= $tablejoins_and_filters;
        
        return $tablejoins_and_filters1;
    }

    public function prepareTablesUsedForQuery($accounts = array())
    {
    	if (is_array($accounts) && !empty($accounts)) {
    		foreach ($accounts as $k => $account) {
		    	$fieldPart = explode(".", $account);
		        if (count($fieldPart) > 1) {
		            if (!in_array($fieldPart[0], $this->settingVars->tableUsedForQuery))
		                $this->settingVars->tableUsedForQuery[] = $fieldPart[0];
		        }
    		}
    	}
        return;
    }

    public function prepareTablesUsedForMeasure($measureNum)
    {
    	$measureFields = array();
    	if (!is_numeric($measureNum))
    		return $measureFields;

    	
        $measure = $this->settingVars->measureArray['M'.$measureNum];
        if (array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields']))
            $measureFields = $measure['usedFields'];

        return $measureFields;
    }
	
	/* 
		table_name = get data from client_config table where db_table = table_name
		helper_table = is used for value of "tablename" key in dataArray
		setting_name = Column(setting_value) value of pm_config table
		helper_link = is used for value of "link" key in dataArray
		type = to separate product filter and market filter, P for Product and M For Market
		empty_product_display_option = if product or market filter configured from project-manager then empty marketOptions_DisplayOptions array to get configured filter.
	*/	
	
	public function configureProductFilter()
	{
		if(!isset($this->settingVars->product_filter_configuration) || empty($this->settingVars->product_filter_configuration))
		{
            $empty_display_option = false;

			$this->settingVars->product_filter_configuration = array();
			if($this->checkHasSettingEnable('has_product_filter')) {
                $empty_display_option = !$empty_display_option;
				$this->settingVars->product_filter_configuration['product'] = array(
					"table_name" => $this->settingVars->skutable, 
					"helper_table" => $this->settingVars->productHelperTables, 
					"setting_name" => "product_settings", 
					"helper_link" => $this->settingVars->productHelperLink, 
					"type" => "P", 
					"empty_display_option" => $empty_display_option
				);
			}
            
			if($this->checkHasSettingEnable('has_kdr')) {
                $empty_display_option = ($this->checkHasSettingEnable('has_product_filter')) ? false : true;
				$this->settingVars->product_filter_configuration['kdr'] = array(
					"table_name" => $this->settingVars->skulisttable, 
					"helper_table" => $this->settingVars->skulisttable, 
					"setting_name" => "kdr_settings", 
					"helper_link" => $this->settingVars->skuListHelperLink, 
					"type" => "K", 
					"empty_display_option" => $empty_display_option
				);
			}
            
            foreach($this->settingVars->product_filter_configuration as $key => $filterConfig)
                $this->buildFilter($filterConfig, "PRODUCT", "productOptions_DisplayOptions");
		}
	}
    
    public function configureSkuFilter()
	{
		if(!isset($this->settingVars->sku_filter_configuration) || empty($this->settingVars->sku_filter_configuration))
		{
			$this->settingVars->sku_filter_configuration = array();
			if($this->checkHasSettingEnable('has_sku_filter')) {
				$this->settingVars->sku_filter_configuration['sku'] = array(
					"table_name" => $this->settingVars->skutable, 
					"helper_table" => $this->settingVars->productHelperTables, 
					"setting_name" => "sku_settings", 
					"helper_link" => $this->settingVars->productHelperLink, 
					"type" => "P", 
					"empty_display_option" => true
				);
			}
            
            foreach($this->settingVars->sku_filter_configuration as $key => $filterConfig)
                $this->buildFilter($filterConfig, "SKU", "skuOptions_DisplayOptions");
		}
    }        
	
	public function configureMarketFilter()
	{
		if(!isset($this->settingVars->market_filter_configuration) || empty($this->settingVars->market_filter_configuration))
		{
            $this->settingVars->market_filter_configuration = array();

            $empty_display_option = false;

            if($this->checkHasSettingEnable('has_account')) {
                $empty_display_option = !$empty_display_option;
                $this->settingVars->market_filter_configuration['account'] = array(
                    "table_name" => $this->settingVars->accounttable, 
                    "helper_table" => $this->settingVars->accountHelperTables, 
                    "setting_name" => "account_settings", 
                    "helper_link" => $this->settingVars->accountHelperLink, 
                    "type" => "A",
                    "empty_display_option" => $empty_display_option
                );
            }

            if($this->checkHasSettingEnable('has_market_filter')) {
                $empty_display_option = ($this->checkHasSettingEnable('has_account')) ? false : true;
                $this->settingVars->market_filter_configuration['market'] = array(
                    "table_name" => $this->settingVars->storetable, 
                    "helper_table" => $this->settingVars->geoHelperTables , 
                    "setting_name" => "market_settings", 
                    "helper_link" => $this->settingVars->geoHelperLink, 
                    "type" => "M", 
                    "empty_display_option" => $empty_display_option
                );
            }
            
            if($this->checkHasSettingEnable('has_territory')) {
                $empty_display_option = ($this->checkHasSettingEnable('has_account') || $this->checkHasSettingEnable('has_market_filter')) ? false : true;
                $this->settingVars->market_filter_configuration['territory'] = array(
                    "table_name" => $this->settingVars->territorytable, 
                    "helper_table" => $this->settingVars->territoryHelperTables, 
                    "setting_name" => "territory_settings", 
                    "helper_link" => $this->settingVars->territoryHelperLink, 
                    "type" => "T", 
                    "empty_display_option" => $empty_display_option
                );
            }
            
            foreach($this->settingVars->market_filter_configuration as $key => $filterConfig)
                $this->buildFilter($filterConfig, "STORE", "marketOptions_DisplayOptions");
		}
	}
	
    public function buildFilter($filterConfig, $filterType, $displayOptions)
    {
        if(!empty($filterConfig) && !empty($filterType) && !empty($displayOptions))
        {
			$type 						= $filterConfig['type'];
			$tableName 					= $filterConfig['table_name'];
			$helperLink 				= $filterConfig['helper_link'];
			$helperTable  				= $filterConfig['helper_table'];
			$settingNameConfig 			= $filterConfig['setting_name'];
			$emptyDisplayOption         = $filterConfig['empty_display_option'];
            
            // Set pm_config fields to fetch setting data
            $settingName = $this->settingVars->menuArray['MF5']['SETTING_NAME']; // Fetch setting_name field name
            $settingValue = $this->settingVars->menuArray['MF5']['SETTING_VALUE']; // Fetch setting_value field name
            $pageName = $this->settingVars->pageName;
            
            // Checking for fields configured in projectsettings or not 
            if (!empty($settingName) && !empty($settingValue)) {
                
                $isTerritoryEnable = false;
                if($settingNameConfig == "territory_settings" && isset($this->queryVars->projectConfiguration[$settingNameConfig]) && !empty($this->queryVars->projectConfiguration[$settingNameConfig]) )
                    $isTerritoryEnable = true;
                    
                if (isset($this->queryVars->projectConfiguration[$settingNameConfig]) && !empty($this->queryVars->projectConfiguration[$settingNameConfig]) ) {
                    $setting = $this->queryVars->projectConfiguration[$settingNameConfig];
                    
                    if (!empty($setting)) {
                        // We are storing db_columns as a "|" (PIPE) separated
                        $settings = explode("|", $setting);
                        
                        // Explode with # because we are getting some value with # ie (PNAME#PIN) And such column name combination not match with db_column.
                        foreach($settings as $key => $data) {
                            $originalCol = explode("#", $data);
                            if(is_array($originalCol) && !empty($originalCol)) {
                                $settings[$key] = $originalCol[0];
                                $settings[]     = $originalCol[1];
                            }
                        }
                        $dbColumns = array();
                        $clientConfiguration = $this->settingVars->clientConfiguration;
                        if(is_array($clientConfiguration) && !empty($clientConfiguration)){
                            foreach ($settings as $field) {
                                $searchKeyDB  = array_search($tableName.".".$field, array_column($this->settingVars->clientConfiguration, 'db_table_db'));
                                if ($searchKeyDB !== false && $clientConfiguration[$searchKeyDB]['show_in_pm'] == 'Y' && $clientConfiguration[$searchKeyDB]['db_table'] == $tableName ) {
                                    $dbColumns[] = $clientConfiguration[$searchKeyDB];
                                }
                            }
                        }
                        $dbColumns = utils\SortUtility::sort2DArray($dbColumns, 'rank', utils\SortTypes::$SORT_ASCENDING);
                        
                        // DO NOT DELETE ITS REQUIRED TO INITIALIZE AGAIN
                        $settings = explode("|", $setting);
                        
                        if (is_array($settings) && !empty($settings) && is_array($dbColumns) && !empty($dbColumns)) {
                            // We are overwriting DisplayOptions as we found configuration in pm_config table.
                            if($emptyDisplayOption) {
                                $this->settingVars->$displayOptions = [];
                                if($filterType != "SKU")
                                    $this->settingVars->pageArray[$filterType."_TREEMAP"]["MAPS"] = [];
                            }
                            foreach ($settings as $key => $tabField) {
                                $selected = false;
                                // We are assuming first column tab will show active
                                if ($key == 0 && empty($this->settingVars->$displayOptions))
                                    $selected = true;
                                    
                                $includeID = explode("#", $tabField);
                                if(is_array($includeID) && !empty($includeID))
                                    $tabField = $includeID[0];
                                
                                $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($tableName.".".$tabField, array_column($dbColumns, 'db_table_db')) : '';
                                
                                if (is_numeric($searchKey)) {
                                    $tabFieldUpper = strtoupper($tableName.".".$tabField);
                                    $mapField = $tableName.".".$tabField;
                                    if(is_array($includeID) && !empty($includeID) && isset($includeID[1])) {
                                        $ID = $tableName.".".$includeID[1];
                                        $mapField .= '#'.$ID;
                                        $tabIdFieldUpper = strtoupper($includeID[1]);
                                        
                                        $tabFieldUpper = strtoupper($tabFieldUpper."_".$ID);
                                        
                                        $this->settingVars->dataArray[$tabFieldUpper]['ID'] = $ID;
                                        $this->settingVars->dataArray[$tabFieldUpper]['ID_ALIASE'] = $tabIdFieldUpper;
                                        $this->settingVars->dataArray[$tabFieldUpper]['ID_ALIASE_WITH_TABLE'] = strtoupper($tableName."_".$includeID[1]);
                                        $this->settingVars->dataArray[$tabFieldUpper]['include_id_in_label'] = true;
                                        
                                        $searchKey1 = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($tableName.".".$includeID[1], array_column($dbColumns, 'db_table_db')) : '';
                                        
                                        $this->settingVars->dataArray[$tabFieldUpper]['ID_CSV'] = $dbColumns[$searchKey1]['csv_column'];
                                    }
                                    
                                    $this->settingVars->dataArray[$tabFieldUpper]['NAME'] = $tableName.".".$tabField;
                                    $this->settingVars->dataArray[$tabFieldUpper]['NAME_ALIASE'] = strtoupper($tableName."_".$tabField);
                                    $this->settingVars->dataArray[$tabFieldUpper]['NAME_CSV'] = $dbColumns[$searchKey]['csv_column'];
                                    $this->settingVars->dataArray[$tabFieldUpper]['tablename'] = $helperTable;
                                    $this->settingVars->dataArray[$tabFieldUpper]['link'] = $helperLink;
                                    $this->settingVars->dataArray[$tabFieldUpper]['TYPE'] = $type;
                                    $this->settingVars->dataArray[$tabFieldUpper]['use_alias_as_tag'] = true;
                                    
                                    $datahelpers = (!empty($this->settingVars->pageArray[$pageName]["DH"])) ? explode('-', $this->settingVars->pageArray[$pageName]["DH"]) : array();
                                    
                                    if (!in_array($tabFieldUpper, $datahelpers))
                                        $datahelpers[] = $tabFieldUpper;
                                        
                                    $this->settingVars->pageArray[$pageName]["DH"] = implode("-", $datahelpers);
                                    
                                    if($filterType == "PRODUCT")
                                        $this->settingVars->productDataHelper .= '-'.$tabFieldUpper;
                                        
                                    // Setting up DisplayOptions array to configure tabs
                                    $this->settingVars->{$displayOptions}[] = array(
                                        'label'             => $dbColumns[$searchKey]['csv_column'], 
                                        'data'              => $tabFieldUpper, 
                                        'indexName'         => 'FS['.$tabFieldUpper.']', 
                                        'selectedItemLeft'  => 'selected'.ucfirst($dbColumns[$searchKey]['db_column']).'Left',
                                        'selectedItemRight' => 'selected'.ucfirst($dbColumns[$searchKey]['db_column']).'Right', 
                                        'dataList'          => array(), 
                                        'selectedDataList'  => array(), 
                                        'selected'          => $selected,
                                        'mapField'          => $mapField
                                    );
                                    
                                    if($settingNameConfig == "market_settings" && $filterType == "STORE")
                                        $this->settingVars->pageArray["STORE_TREEMAP"]["MAPS"][$dbColumns[$searchKey]['csv_column']] = $tabFieldUpper;
                                    
                                    if($filterType == "PRODUCT")
                                        $this->settingVars->pageArray["PRODUCT_TREEMAP"]["MAPS"][$dbColumns[$searchKey]['csv_column']] = $tabFieldUpper;
                                    
                                    if ($key == 0 && $filterType == "STORE") {
                                        if($isTerritoryEnable)
                                            $this->settingVars->pageArray["TERRITORY_TREEMAP"]["MAPS"] = array($dbColumns[$searchKey]['csv_column'] => $tabFieldUpper);
                                    }
                                    
                                    if($_REQUEST["territory"] != "" && $_REQUEST["territory"] != "undefined" && $_REQUEST["territory"] == $dbColumns[$searchKey]['db_column'] && $filterType == "STORE") {
                                        $this->settingVars->pageArray["TERRITORY_TREEMAP"]["MAPS"] = array($dbColumns[$searchKey]['csv_column'] => $tabFieldUpper);
                                    }
                                }
                            }
                            
                            $commonFilterQueryPart = $this->settingVars->commonFilterQueryString($type);
                            foreach ($settings as $key => $tabField) {
                                $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($tableName.".".$tabField, array_column($dbColumns, 'db_table_db')) : '';
                                if (is_numeric($searchKey)) {
                                    $tabFieldUpper = strtoupper($tableName.".".$tabField);
                                    $this->settingVars->dataArray[$tabFieldUpper]['link'] = $helperLink . $commonFilterQueryPart;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    public function defaultPrivateLable()
    {
        $this->settingVars->link .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
        return true;
    }

	private function checkHasSettingEnable($setting_name)
	{
		if(isset($this->queryVars->projectConfiguration[$setting_name]) && $this->queryVars->projectConfiguration[$setting_name] == 1 )
			return true;
		else
			return false;
	}	
	
    public function getPageConfiguration($setting_name, $pageID) {
        $settings = array();
        
        if (empty($pageID) || (is_array($this->settingVars->pageConfiguration) && empty($this->settingVars->pageConfiguration[$pageID])) )
            return $setting;

        $cid = $this->settingVars->aid;
        $pid = $this->settingVars->projectID;

        if(!empty($setting_name) && isset($this->settingVars->pageConfiguration[$pageID][$setting_name]) )
            $settings = explode("|", $this->settingVars->pageConfiguration[$pageID][$setting_name]);

        if(empty($setting_name) && is_array($this->settingVars->pageConfiguration[$pageID]) && !empty($this->settingVars->pageConfiguration[$pageID])){
        	$settings = $this->settingVars->pageConfiguration[$pageID];
        }

        return $settings;
    }

    public function configurationFailureMessage($message = '')
    {
        $message = (empty($message)) ? "Page isn't configured properly." : $message;
        $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
        echo json_encode($response);
        exit();
    }
    
    public function prepareMeasureSelectPart()
    {
        $projectStructureType = "projectstructure\\".$this->settingVars->projectStructureType;
        $structureClass = new $projectStructureType();
        $measureSelectPart = $structureClass->prepareMeasureSelectPart($this->settingVars, $this->queryVars);
        return $measureSelectPart;

        /*$measureArr = $measureSelectionArr = $ddbMeasureHavingPart = array();
        
        foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
            $options = array();
            $measureKey = 'M' . $measureVal['measureID'];
            $measure = $this->settingVars->measureArray[$measureKey];
            
            if (!empty(filters\timeFilter::$tyWeekRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = "TY" . $measure['ALIASE'];
                $measureTYValue = "TY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
            }else{
            	if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = $measure['ALIASE'];
            }

            if (!empty(filters\timeFilter::$lyWeekRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = "LY" . $measure['ALIASE'];
                $measureLYValue = "LY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureLYValue] = trim(filters\timeFilter::$lyWeekRange);
            }else{
            	if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = $measure['ALIASE'];
            }
            $measureArr[$measureKey] = MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);           
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);   

            // This array will use for ddb project only
            if($measure['attr'] == "SUM")
                $ddbMeasureHavingPart[] = "TY".$measure['ALIASE']." <>0 ";
        }

        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
        
        $measureFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
        
        $result = array("measureSelectionArr" => $measureSelectionArr, "havingTYValue" => $havingTYValue, 
        				"havingLYValue" => $havingLYValue, "measureFields" => $measureFields, "ddbMeasureHavingPart" => $ddbMeasureHavingPart);
        return $result;*/
    }
    
    public function prepareMeasureSelectPartById($requiredMeasure = array(), $measureArray = array())
    {
        if(empty($measureArray))
            $measureArray = $this->settingVars->measureArray;
       
        $keyAlias = $measureFields = $measureArr = $measureSelectionArr = array();
        
        foreach ($measureArray as $key => $measureVal) 
        {
            if(empty($requiredMeasure) || in_array(str_replace("M", "", $key), $requiredMeasure))
            {
                $options = array();
                $measure = $this->settingVars->measureArray[$key];
                
                if (!empty(filters\timeFilter::$tyWeekRange)) {
                    if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                        $havingTYValue = "TY" . $measure['ALIASE'];
                    $measureTYValue = "TY" . $measure['ALIASE'];
                    $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
                }

                if (!empty(filters\timeFilter::$lyWeekRange)) {
                    if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                        $havingLYValue = "LY" . $measure['ALIASE'];
                    $measureLYValue = "LY" . $measure['ALIASE'];
                    $options['tyLyRange'][$measureLYValue] = trim(filters\timeFilter::$lyWeekRange);
                }
                
                $measureArr[$key] = MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($key), $options);           
                $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$key]);  
                
                if (array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                    foreach ($measure['usedFields'] as $usedField) {
                        $measureFields[] = $usedField;
                    }
                }
                $keyAlias[$key] = $measure['ALIASE'];
            }
        }
        
        $result = array("measureSelectionArr" => $measureSelectionArr, "havingTYValue" => $havingTYValue, 
        				"havingLYValue" => $havingLYValue, "measureFields" => $measureFields, "keyAlias" => $keyAlias);
        return $result;        
    }

    /* PROJECT: DDB */
    private function configureProductAndMarketFilter()
    {
        if(!isset($this->settingVars->filterPages))
            return ;

        foreach($this->settingVars->filterPages as $key => $filterPage)
        {
            if($filterPage['isDynamic'] && $this->checkHasSettingEnable($filterPage['config']['enable_setting_name']))
            {
                $ddbFilterDisplayOptionName = 'ddbOptions_DisplayOptions';
                $this->settingVars->$ddbFilterDisplayOptionName = [];
                $this->buildFilter($filterPage['config'], "DDB", $ddbFilterDisplayOptionName);
                
                $tabsConfiguration = $this->settingVars->$ddbFilterDisplayOptionName;
                
                if(!empty($tabsConfiguration))
                    $this->settingVars->filterPages[$key]['tabsConfiguration'] = $tabsConfiguration;
                else
                    unset($this->settingVars->filterPages[$key]);
            }
            elseif($filterPage['isDynamic'])
                $this->settingVars->filterPages[$key]['isVisible'] = false;
            
            // NOTE: ADDED BY Shashank For DDB BREAKDOWN PURPOSE. DONT DELETE IT.
            $this->settingVars->filterPagesReplication[$key] = $this->settingVars->filterPages[$key];
            unset($this->settingVars->filterPages[$key]['config']);
        }
        $this->settingVars->filterPages = array_values($this->settingVars->filterPages);
    }
}
?>