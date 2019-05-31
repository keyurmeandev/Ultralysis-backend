<?php
namespace projectsettings;
    
use projectsettings;
use filters;
use db;
use config;
use utils;
use projectstructure;
use lib;
    
class baseRetailLinkDaily {

    //tables
    public $maintable;
    public $skutable;
    public $storetable;
    public $ranged_items;
	public $territoryTable;
	public $grouptable;
	public $clientconfigtable;
	public $configTable;
    public $tablename;
    public $link;
    public $storeHelperTables;
    public $geoHelperTables;
    public $storeHelperLink;
    public $geoHelperLink;
	public $productHelperTables;
    public $productHelperLink;
    public $territoryHelperTables;
    public $territoryHelperLink;
    //core setting vars
    public $aid;
    //time vars
    public $yearperiod;
    public $weekperiod;
    public $DatePeriod;
    public $ProjectValue;
    public $ProjectVolume;
    public $projectID;
    public $clientID;
    public $GID=2;
    public $clusterID;
    public $projectLogo;
    public $PROJECTDEV;
    public $footerCompanyName;
    // client's vars
    public $supplierHelperLink;
    //currency var
    public $currencySign;
    public $groupName;
	public $tableUsedForQuery=array();
	public $useRequiredTablesOnly=false;

    //[START] Dynamic data builder
        public $ddbconfigtable;
        public $measuresSelectionTable;
        public $outputColumnsTable;
        public $timeSelectionTable;
        public $filterSettingTable;
        public $outputDateOptions;
    //[END] Dynamic data builder    
    
    public function __construct($accountID,$projectID) {

		$this->aid 					= $accountID;
		$this->projectID 			= $projectID;
        $this->skutable             = "product";
        $this->storetable           = "store";
        $this->ranged_items         = "ranged_items";
		$this->territorytable = $this->territoryTable = "territory";
        $this->grouptable           = "fgroup";
		$this->clientconfigtable	= "client_config";
		$this->configTable 			= "pm_config";
        $this->DatePeriod           = "mydate";
        $this->clustertable			= "cluster";
        $this->measuresTable        = "measures";
        $this->formulaTable         = "formula";
        $this->projectType			= 2;
        $this->prefix = "pm_";
        $this->breakdownTable = $this->prefix . "db_table_breakdowns";
        $this->InstockSummaryStoreListDLCol = false;

        $this->stockFieldName = "OHQ"; // For Live Uplift Monitor page

        $this->uploadPath = dirname(__FILE__).'/../uploads/';
        $this->uploadURL = "//".$_SERVER['HTTP_HOST'].'/'.basename(dirname($_SERVER['PHP_SELF'])).'/uploads/';

        /*** 
         * To enable new flow for pages
         * >> Product and Market filter on tab click
         * >> Added Sticky Filter for Product and Market filter
         * >> Added logic for clean dom object and rebuild page
         * >> All above features controlled by this single flag
        ***/
        // $this->fetchProductAndMarketFilterOnTabClick = true;

        // To enable setting for include future dates in time selection filter
        $this->includeFutureDates = false;

        // Available OPTIONS for timeSelectionUnit [weekYear, weekMonth, date, week, days, period, none (Just for Gaylea GT)]
        // weekYear, weekMonth only available in case of includeFutureDates true
        $this->timeSelectionUnit = "days";

        // Available OPTIONS for projectStructureType [MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW (Standard lcl Structure), MEASURE_AND_TYLY_AS_COLUMN (Nielsen Structure), MEASURE_AS_SINGLE_COLUMN_AND_TYLY_AS_ROW (New ASD Online Structure)]
        $this->projectStructureType = projectstructure\ProjectStructureType::$MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW;        
        
        // You need to configure this if you want to enable "days" as timeSelectionUnit
        $this->daysSelectionArray = array(
            1 => "Last Day",
            3 => "Last 3 Days",
            5 => "Last 5 Days",
            7 => "Last 7 Days",
            14 => "Last 14 Days",
        );

        $this->pageConfigTable             = "pm_pages_config";
        $this->templateMasterTable         = "template_master";

        $this->privateLabelFilterField = $this->skutable.".pl";

        $this->getAllPageConfiguration();

        /* PAGE CONFIGURATION VARS FOR DYNAMIC FLOW */
        $this->pageName             = $_REQUEST["pageName"];
        $this->pageID               = (isset($_REQUEST["pageID"]) && !empty($_REQUEST["pageID"])) ? $_REQUEST["pageID"] : 
                                        ((isset($_REQUEST['DataHelper']) && $_REQUEST['DataHelper'] == "true") ? $this->getDefaultPageConfiguration() : "");
        $this->isDynamicPage        = (empty($this->pageID)) ? false : true;

        // $this->currencySign	= $this->fetchCurrencySign();
        $this->fetchGroupDetail();
        $this->isRedisCachingEnabled = $this->hasRedisCaching();

		
		$this->menuArray['MF5']['SETTING_NAME'] = $this->configTable . '.setting_name';
        $this->menuArray['MF5']['SETTING_VALUE'] = $this->configTable . '.setting_value';

        $this->geoHelperTables = $this->storeHelperTables = " " . $this->maintable . "," . $this->storetable . " ";      
        $this->geoHelperLink = $this->storeHelperLink   = " WHERE ".$this->maintable.".SNO = ".$this->storetable.".sno AND " . $this->storetable . ".gid=".$this->GID;
		
        $this->territoryHelperTables    = $this->territoryTable;
        $this->territoryHelperLink      = " WHERE ".$this->territoryTable.".GID IN (".$this->GID.") AND ".$this->territoryTable.".accountID=".$this->aid." ";

		$this->productHelperTables = " " . $this->maintable . "," . $this->skutable . " ";		
		$this->productHelperLink 	 = " WHERE ".$this->maintable.".SIN = ".$this->skutable.".PIN".
									" AND ". $this->maintable . ".accountID=" . $this->aid .									
									" AND ". $this->skutable . ".gid=" . $this->GID.
                                    " AND ". $this->maintable . ".gid=" . $this->skutable . ".gid ".
									" AND " . $this->skutable . ".clientID='" . $this->clientID . "' ";

		//project link
		$this->projectHelperLink = " AND projectID=".$this->projectID." ";								
										
        $this->ProjectValue     = $this->maintable . ".sales";
        $this->ProjectVolume    = $this->maintable . ".qty";

        $this->hasMeasureFilter = $this->hasMeasureFilter();

        $this->dataArray = array();
        $this->pageArray = array();

        $this->staticDataArray();

        $this->regionDataSetting = $this->dataArray['F19'];
        $this->skuDataSetting = $this->dataArray['F2'];

        if (!$this->isDynamicPage) {
            $this->dataArray();
            $this->pageArray();
        }

        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Unit')
        );
       
        $this->databaseName = $_REQUEST['connectedDatabaseName'];
        $this->clientConfiguration = array();                
        $this->getAllClientConfig();
        
        /*SEETTING THE PROJECT TYPE ID FROM THE SESSION VARS */
        if(isset($_SESSION['PROJECT_DETAILS_'.$this->projectID]) && isset($_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]) && isset($_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]['PROJECT_TYPE_ID'])){    
            $this->projectTypeID = $_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]['PROJECT_TYPE_ID'];
        }        
        
    }

	public function configureClassVars()
	{
		$this->dateField = $this->maintable . "." . $this->DatePeriod;
		$this->copy_tablename = $this->tablename = $this->maintable . "," . $this->skutable . "," . $this->storetable;

        if(isset($_REQUEST['territoryLevel']))
        {
            $this->tablename .= "," . $this->territoryTable." ";
            $this->copy_tablename = $this->tablename;
        }

        if (!empty($this->accounttable) && $this->accounttable != $this->maintable) {
            $this->tablename .= "," . $this->accounttable . " ";
            $this->copy_tablename = $this->tablename;
        }
		
        $commontables = $this->maintable;
		$commonlink   = " WHERE " . $this->maintable . ".accountID=" . $this->aid . " AND ".$this->maintable . ".gid=" . $this->GID . " ";

		$skulink 	  = " AND ".$this->maintable.".SIN = ".$this->skutable.".PIN".
					" AND ". $this->skutable . ".gid=" . $this->GID.
                    " AND ". $this->maintable . ".gid=" . $this->skutable.".gid " .
					" AND " . $this->skutable . ".clientID='" . $this->clientID . "' AND hide<>1 ";
									
		$storelink    = " AND ".$this->maintable.".SNO = ".$this->storetable.".sno AND ".$this->maintable.".gid = ".$this->storetable.".gid AND " . $this->storetable . ".gid=".$this->GID." ";

        $territorylink = (isset($_REQUEST['territoryLevel']) ? " AND " . $this->territoryTable . ".accountID = " . $this->aid .
                        " AND " . $this->storetable . ".SNO=" . $this->territoryTable . ".SNO " .
                        " AND " . $this->storetable . ".GID=" . $this->territoryTable . ".GID " : "" . $this->territoryTable . ".GID=".$this->GID." ");

        $accountlink  = ((!empty($this->accounttable) && $this->accounttable != $this->maintable) ? $this->accountLink : "");
		
        $this->timeHelperTables = " ". $this->maintable . " ";
        $this->timeHelperLink = " WHERE " . $this->maintable . ".accountID=" . $this->aid . " ";		
		
		$this->copy_link     = $this->link     = $commonlink.$storelink.$skulink.$accountlink;

        if($this->hasHiddenSku()) {
            $commontables .= ", ".$this->skutable;
            $commonlink   .= $skulink;
            $skulink       = '';
        }
		
        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

		$this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']        = $this->territoryTable;
        $this->dataTable['territory']['link']          = $territorylink;

        if (!empty($this->accounttable) && $this->accounttable != $this->maintable) {
            $this->dataTable[$this->accounttable]['tables']        = $this->accounttable;
            $this->dataTable[$this->accounttable]['link']          = $accountlink;
        }
		
        
        if($this->hasMeasureFilter){
            $this->getMeasureSettings();
        }else{
            $this->measureArray                 = array();
            $this->measureArray['M1']['VAL']    = $this->ProjectValue;
            $this->measureArray['M1']['ALIASE'] = "VALUE";
            $this->measureArray['M1']['attr']   = "SUM";

            $this->measureArray['M2']['VAL']    = $this->ProjectVolume;
            $this->measureArray['M2']['ALIASE'] = "VOLUME";
            $this->measureArray['M2']['attr']   = "SUM";    

            $this->measureArray['M3']['VAL']    = "(SUM(IFNULL(".$this->ProjectValue.",0))/SUM(IFNULL(".$this->ProjectVolume.",1)))";
            $this->measureArray['M3']['ALIASE'] = "PRICE";
            $this->measureArray['M3']['attr']   = "PRICE";
            $this->measureArray['M3']['dataDecimalPlaces']   = 2;

            $this->measureArray['M4']['VAL']    = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M4']['attr']   = "COUNT";
            $this->measureArray['M4']['dataDecimalPlaces']   = 0;

            $this->measureArray['M5']['VAL']    = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['measureName'] = "RATE OF SALE";
            $this->measureArray['M5']['ALIASE'] = "RATEOFSALE";
            $this->measureArray['M5']['attr']   = "COUNT";
            $this->measureArray['M5']['dataDecimalPlaces']   = 2;

            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4',
                "stockOverTime" => 'M1',
                "availabilityOverTime" => 'M1',
                "rateOfSale" => 'M5'
            );
        }
        	
        //[START] LOAD DDB SETTING
        if($this->projectTypeID == 6) // DDB
        {
            $this->setDynamicDataBuilderSetting();
            $this->getClientProjectName();
        }
	}
	
    public function staticDataArray(){

        $this->dataArray['F2']['ID']            = $this->skutable . '.PIN';
        $this->dataArray['F2']['ID_ALIASE']     = "SKUID";
        $this->dataArray['F2']['NAME']          = $this->skutable . '.PNAME';
        $this->dataArray['F2']['NAME_ALIASE']   = 'SKU';
        $this->dataArray['F2']['tablename']     = $this->productHelperTables;
        $this->dataArray['F2']['link']          = $this->productHelperLink;
        $this->dataArray['F2']['TYPE']          = "P";

        $this->dataArray['F19']['NAME']         = $this->storetable . '.region';
        $this->dataArray['F19']['NAME_ALIASE']  = 'REGION';
        $this->dataArray['F19']['tablename']    = $this->storeHelperTables;
        $this->dataArray['F19']['link']         = $this->storeHelperLink;

        $this->dataArray['F6']['NAME']          = $this->maintable . '.ModularPlanDesc';
        $this->dataArray['F6']['NAME_ALIASE']   = 'PLANOGRAM';

        $this->dataArray['F7']['NAME']          = $this->maintable . '.TSI';
        $this->dataArray['F7']['NAME_ALIASE']   = 'TSI';

        $this->dataArray['F8']['NAME']          = $this->maintable . '.VSI';
        $this->dataArray['F8']['NAME_ALIASE']   = 'VSI';

        $this->dataArray['F9']['NAME']          = $this->maintable . '.GSQ';
        $this->dataArray['F9']['NAME_ALIASE']   = 'GSQ';

        $this->dataArray['F10']['NAME']         = $this->maintable . '.On_Hand_Adj_Qty';
        $this->dataArray['F10']['NAME_ALIASE']  = 'OHAQ';

        $this->dataArray['F11']['NAME']         = $this->maintable . '.Backroom_Adj_Qty';
        $this->dataArray['F11']['NAME_ALIASE']  = 'BAQ';

        $this->dataArray['F12']['NAME']         = $this->maintable . '.OHQ';
        $this->dataArray['F12']['NAME_ALIASE']  = 'OHQ';

        $this->dataArray['F13']['NAME']         = $this->ranged_items . '.StoreTrans';
        $this->dataArray['F13']['NAME_ALIASE']  = 'StoreTrans';

        $this->dataArray['F14']['NAME']         = $this->maintable . '.MSQ';
        $this->dataArray['F14']['NAME_ALIASE']  = 'MSQ';

        $this->dataArray['F21']['NAME']         = $this->maintable . '.OpenDate';
        $this->dataArray['F21']['NAME_ALIASE']  = 'ODATE';
        
        $this->dataArray['F22']['NAME']         = $this->maintable . '.insertdate';
        $this->dataArray['F22']['NAME_ALIASE']  = 'IDATE';

        $this->dataArray['F20']['NAME']         = $this->maintable . '.ItemStatus';
        $this->dataArray['F20']['NAME_ALIASE']  = 'ITEMSTATUS';
        
        $this->dataArray['F24']['NAME']         = $this->storetable . '.SNO';
        $this->dataArray['F24']['NAME_ALIASE']  = 'SNO';
        $this->dataArray['F24']['NAME_CSV']     = 'SNO';

        $this->dataArray['F25']['NAME']         = $this->storetable . '.SNAME';
        $this->dataArray['F25']['NAME_ALIASE']  = 'SNAME';
        $this->dataArray['F25']['NAME_CSV']     = 'STORE NAME';

        $this->dataArray['F26']['NAME']         = $this->storetable . '.ADDRESS';
        $this->dataArray['F26']['NAME_ALIASE']  = 'ADDRESS';
        $this->dataArray['F26']['NAME_CSV']     = 'ADDRESS';

        $this->dataArray['F27']['NAME']         = $this->storetable . '.CITY';
        $this->dataArray['F27']['NAME_ALIASE']  = 'CITY';
        $this->dataArray['F27']['NAME_CSV']     = 'CITY';

        $this->dataArray['F28']['NAME']         = $this->storetable . '.POSTCODE';
        $this->dataArray['F28']['NAME_ALIASE']  = 'POSTCODE';
        $this->dataArray['F28']['NAME_CSV']     = 'POSTCODE';

        $this->dataArray['F29']['NAME']         = $this->storetable . '.STATE';
        $this->dataArray['F29']['NAME_ALIASE']  = 'STATE';
        $this->dataArray['F29']['NAME_CSV']     = 'STATE';
        
    }

    public function pageArray()
    {
        $this->pageArray["INSTOCK_SUMMARY_PAGE"]["ACCOUNT"] = "F24-F25-F26-F27-F28-F29";
    
        $this->pageArray["RANK_MONITOR_ALL_PAGE"]["ACCOUNT"] = "F3";
        $this->pageArray["RANK_MONITOR_ALL_PAGE"]["GRID_FIELD"] = "F2";

        $this->pageArray["RANK_MONITOR_BY_PAGE"]["ACCOUNT"] = "F3";
        $this->pageArray["RANK_MONITOR_BY_PAGE"]["BY_FIELD"] = "F2";

        $this->pageArray["STOCK_COVER_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["STOCK_COVER_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["STOCK_COVER_BY_SKU_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["STOCK_COVER_BY_SKU_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["SALES_DOCTOR"]["SKU_FIELD"] = "F2";
        $this->pageArray["SALES_DOCTOR"]["STORE_FIELD"] = "F3";        
        
        $this->pageArray["BOTTOM_OUTS_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["BOTTOM_OUTS_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["ZERO_SALES_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["ZERO_SALES_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["HIGH_SELL_THRU_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["HIGH_SELL_THRU_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["MY_STOCK_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["MY_STOCK_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["ADJUSTMENTS_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["ADJUSTMENTS_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["STORE_SELL_THRU_OPTIMIZER_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["STORE_SELL_THRU_OPTIMIZER_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["INSTOCK_SUMMARY_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["INSTOCK_SUMMARY_PAGE"]["STORE_FIELD"] = "F3";

        $this->pageArray["ALL_OOS_DETAILS_PAGE"]["SKU_FIELD"] = "F2";
        $this->pageArray["ALL_OOS_DETAILS_PAGE"]["STORE_FIELD"] = "F3";
        
        $this->pageArray["PLANO_OVERVIEW_PAGE"]["STORE_FIELD"] = "F3";
        $this->pageArray["PLANO_OVERVIEW_PAGE"]["SKU_FIELD"] = "F2";

        $this->pageArray["TRAITED_NOT_VALID_LIST"]["SKU_FIELD"] = "F2";
        $this->pageArray["TRAITED_NOT_VALID_LIST"]["STORE_FIELD"] = "F3";

    }

    public function dataArray()
    {
        // $this->dataArray['F1']['NAME'] = $this->storetable . '.cl3';
        $this->dataArray['F1']['NAME']   		= $this->skutable.'.MERCH_CAT';
        $this->dataArray['F1']['NAME_ALIASE']   = 'CATEGORY';

        $this->dataArray['F3']['ID']            = $this->storetable . '.SNO';
        $this->dataArray['F3']['ID_ALIASE']     = "SNO";
        $this->dataArray['F3']['NAME']          = $this->storetable . '.sname';
        $this->dataArray['F3']['NAME_ALIASE']   = 'SNAME';

        $this->dataArray['F4']['NAME']          = $this->storetable . '.banner';
        $this->dataArray['F4']['NAME_ALIASE']   = 'BANNER';

        $this->dataArray['F5']['NAME']          = $this->skutable . '.upc';
        $this->dataArray['F5']['NAME_ALIASE']   = 'ANA';

        $this->dataArray['F15']['NAME']         = $this->maintable . '.sales';
        $this->dataArray['F15']['NAME_ALIASE']  = 'sales';

        $this->dataArray['F16']['NAME']         = $this->ranged_items . '.StoreOrder';
        $this->dataArray['F16']['NAME_ALIASE']  = 'StoreOrder';

        $this->dataArray['F17']['NAME']         = $this->ranged_items . '.StoreWhs';
        $this->dataArray['F17']['NAME_ALIASE']  = 'StoreWhs';

        $this->dataArray['F18']['NAME']         = $this->maintable . '.mydate';
        $this->dataArray['F18']['NAME_ALIASE']  = 'MYDATE';

        $this->dataArray['F23']['NAME']         = $this->skutable .'.agg2';
        $this->dataArray['F23']['NAME_ALIASE']  = 'CAT';
    }
	
    public function setCluster() {
        $this->dataArray['F1']['NAME'] = $this->storetable . '.cl' . $_REQUEST['clusterID'];
        $this->clusterID = $this->storetable. '.cl' . $_REQUEST['clusterID'];  
    }

    public function getAllPageConfiguration() {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_pages_configuration');

        $this->pageConfiguration = array();

        if ($redisOutput === false) {
            $query = "SELECT pageID, templateID, setting_name, setting_value  FROM ". 
                $this->pageConfigTable . "  WHERE accountID = ".$this->aid." AND projectID = ".$this->projectID."  ";
            $pageConfig = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($pageConfig) && !empty($pageConfig)){
                foreach ($pageConfig as $config) {
                    $this->pageConfiguration[$config['pageID']][$config['setting_name']] = $config['setting_value'];
                    
                    if($config['setting_name'] == 'default_load_pageID')
                        $this->pageConfiguration['000'][$config['setting_name']] = $config['setting_value'];
                }
                $redisCache->setDataForStaticHash($this->pageConfiguration);
            }
        } else {
            $this->pageConfiguration = $redisOutput;
        }

        $this->default_load_pageID = $this->pageConfiguration['000']['default_load_pageID'];
    }

    public function getDefaultPageConfiguration() {
        return $this->default_load_pageID;
    }

    public function updateDefaultConfiguration($dataTables)
    {
		if (!$this->useRequiredTablesOnly || $this->forceUseOriginalLink) {
			$this->tablename = $this->copy_tablename;
			$this->link = $this->copy_link;
			return;
		}

		$tables = $this->dataTable['default']['tables'];
		$link   = $this->dataTable['default']['link'];
		
        if (is_array($dataTables) && !empty($dataTables)) {
            if (in_array('territory', $dataTables) && !in_array('store', $dataTables))
                $dataTables[] = 'store';
                
            foreach ($dataTables as $key => $dataTable) {
                if (isset($this->dataTable[$dataTable])) {
					if (array_key_exists('tables', $this->dataTable[$dataTable]) && !empty($this->dataTable[$dataTable]['tables']) &&
                        array_key_exists('link', $this->dataTable[$dataTable]) && !empty($this->dataTable[$dataTable]['link'])) {
                        if(!in_array($dataTable, array_map('trim',explode(",", $tables))))
                        {
                            $tables .= ", ".$this->dataTable[$dataTable]['tables'];
                            $link   .= $this->dataTable[$dataTable]['link'];
                        }
					}
                }
            }
        }
		$this->tablename = $tables;
		$this->link = $link;
		return;
    }

    public function hasRedisCaching()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(isset($queryVars->projectConfiguration['has_redis_caching']) && $queryVars->projectConfiguration['has_redis_caching'] == 1 )
            return true;
        else
            return false;
    }

    public function hasHiddenSku()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('lcl_has_hidden_sku');

        if ($redisOutput === false) {
            $query = "SELECT PIN FROM ".$this->skutable." WHERE clientID='".$this->clientID."'".
                    " AND GID IN (".$this->GID.") AND hide=1 ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($result) && !empty($result))
            {
                $result = array_column($result, "PIN");
                $this->hiddenSkusQueryString = implode($result, ",");
            }
            $redisCache->setDataForStaticHash($this->hiddenSkusQueryString);
        } else {
            $this->hiddenSkusQueryString = $redisOutput;
        }

        if($this->hiddenSkusQueryString != "")
            return true;
        else
            return false;
    }

	public function getMydateSelect($dateField, $withAggregate = true) {
		$dateFieldPart = explode('.', $dateField);
		$dateField = (count($dateFieldPart) > 1) ? $dateFieldPart[1] : $dateFieldPart[0];
		
		switch ($dateField) {
			case "period":
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".mydate) " : $this->maintable.".mydate ";
				break;
			case "mydate":
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".mydate) " : $this->maintable.".mydate ";
				break;
		}
		
		return $selectField;
	}    
    
    public function commonFilterQueryString($filterType)
    {
        $tablejoins_and_filters = "";
        
        if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
            return $tablejoins_and_filters;
        
        $queryVars = projectsettings\settingsGateway::getInstance();
        
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '' && isset($_REQUEST['commonFilterApplied']) && $_REQUEST['commonFilterApplied'] == true)
        {
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($queryVars,$this,$filterType);
        }
        
        return $tablejoins_and_filters;
    }

    public function fetchGroupDetail()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('lcl_fgroup_detail');

        if ($redisOutput === false) {
            $query = "SELECT currency,gname FROM ".$this->grouptable." WHERE gid IN (".$this->GID.") ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            $redisCache->setDataForStaticHash($result);
        } else {
            $result = $redisOutput;
        }

        if(is_array($result) && !empty($result) && isset($result[0]['currency']) && !empty($result[0]['currency']))
            $this->currencySign = html_entity_decode($result[0]['currency']);
        else
            $this->currencySign = '$';

        if(is_array($result) && !empty($result) && isset($result[0]['gname']) && !empty($result[0]['gname']))
            $this->groupName = $result[0]['gname'];

        return true;
    }

    public function hasMeasureFilter()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(isset($queryVars->projectConfiguration['has_measure_filter']) && $queryVars->projectConfiguration['has_measure_filter'] == 1 )
            return true;
        else
            return false;
    }

    public function getMeasureSettings()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('lcl_measure_configuration_sif');
        
        if ($queryVars->isInitialisePage || $redisOutput === false) {

            $query = "SELECT m.*,f.formula,f.alias as formulaAlias FROM ".
                $this->measuresTable." m ,".$this->formulaTable." f WHERE f.formulaID = m.formulaID AND m.accountID=".$this->aid.
                " AND m.projectID = ".$this->projectID." AND m.status = 1 AND measureFields is not null ";

            $redisQueryOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisQueryOutput === false) {
                $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
                $redisCache->setDataForHash($result);
            } else {
                $result = $redisQueryOutput;
            }

            if(!empty($result) && is_array($result)){
                $searchFields = array_search("", array_column($result, 'measureFields'));
                if($searchFields === false ){
                    $result = utils\SortUtility::sort2DArray($result, 'measureOrder', utils\SortTypes::$SORT_ASCENDING);
                    $searchKey = array_search(1, array_column($result, 'defaultLoad'));
                    $defaultLoad = ($searchKey !== false ) ? $result[$searchKey] : array();
                    $this->measureArray = array();
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array();
                    foreach ($result as $index => $measureData) {
                        if($measureData['showInSif'] == 1){ //SIF = YES CONDITION
                            $measureSelectionList = array();
                            if($measureData['showAsFilter'] == 1){
                                $measureSelectionList['measureID']      = $measureData['measureID'];
                                $measureSelectionList['jsonKey']        = $measureData['alias'];
                                $measureSelectionList['dataDecimalPlaces'] = $measureData['data_decimal_places'];
                                $measureSelectionList['measureName']    = $measureData['measureName'];
                                $measureSelectionList['selected']       = (( empty($defaultLoad) && count($this->pageArray["MEASURE_SELECTION_LIST"]) == 0 ) || ( $defaultLoad['measureID'] == $measureData['measureID'] ) ) ? true : false ;

                                $this->pageArray["MEASURE_SELECTION_LIST"][] = $measureSelectionList;
                            }

                            $measureFields = explode('|',$measureData['measureFields']);

                            $this->measureArray['M'.($measureData['measureID'])]['VAL'] = $measureData['formula'];
                            $this->measureArray['M'.($measureData['measureID'])]['ALIASE'] = $measureData['alias'];
                            $this->measureArray['M'.($measureData['measureID'])]['measureName'] = $measureData['measureName'];
                            $this->measureArray['M'.($measureData['measureID'])]['dataDecimalPlaces'] = $measureData['data_decimal_places'];
                            $this->measureArray['M'.($measureData['measureID'])]['ATTR'] = $measureData['formulaAlias'];
                            $this->measureArray['M'.($measureData['measureID'])]['usedFields'] = $measureFields;
                        }
                    }

                    $this->performanceTabMappings = array();

                    if(isset($queryVars->projectConfiguration['performance_tab_mapping_sif']) && !empty($queryVars->projectConfiguration['performance_tab_mapping_sif']) ) {
                        $settings = explode('|', $queryVars->projectConfiguration['performance_tab_mapping_sif']);
                        foreach ($settings as $value) {
                            $tabSettings = explode('#', $value);
                            $this->performanceTabMappings[$tabSettings[0]] = "M".$tabSettings[1];
                        }
                    }else{
                        $response = array("configuration" => array("status" => "fail", "messages" => array('Performance tab measure mapping not found.')));
                        echo json_encode($response);
                        exit();
                    }
                }
                else{
                    $response = array("configuration" => array("status" => "fail", "messages" => array('Measure Fields configuration not found.')));
                    echo json_encode($response);
                    exit();
                }

            } else{
                $response = array("configuration" => array("status" => "fail", "messages" => array('Measure configuration not found.')));
                echo json_encode($response);
                exit();
            }
            
            $measureRedisArray = array(
                'MEASURE_SELECTION_LIST' => $this->pageArray["MEASURE_SELECTION_LIST"],
                'measureArray'           => $this->measureArray,
                'performanceTabMappings' => $this->performanceTabMappings
            );

            /*Do not remove it because this is fix when the Redis cache function works two times*/
            $redisCache->requestHash = 'lcl_measure_configuration_sif';
            $redisCache->setDataForStaticHash($measureRedisArray);
            
        } else {
            $this->pageArray["MEASURE_SELECTION_LIST"] = $redisOutput['MEASURE_SELECTION_LIST'];
            $this->measureArray = $redisOutput['measureArray'];
            $this->performanceTabMappings = $redisOutput['performanceTabMappings'];
        }
    }
    
    public function getAllClientConfig() {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_data_manager_configuration');//relay_plus_data_manager_configuration

        if ($redisOutput === false) {
            $query  = "SELECT csv_column, db_column, db_table, CONCAT(db_table,'.',csv_column) as db_table_csv, CONCAT(db_table,'.',db_column) as db_table_db,show_in_pm,rank from ".$this->clientconfigtable." as a".
                        " WHERE a.cid=".$this->aid." AND database_name = '".$this->databaseName."' ";

            $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            $this->clientConfiguration = $result;

            $redisCache->setDataForStaticHash($this->clientConfiguration);
        } else {
            $this->clientConfiguration = $redisOutput;
        }


        return $this->clientConfiguration;
    }    
    
    public function get_full_url() {
        $https = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0 ||
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
                
        return ($https ? 'https://' : 'http://').
            (!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
            (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
            ($https && $_SERVER['SERVER_PORT'] === 443 ||
            $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT']))).
            substr($_SERVER['SCRIPT_NAME'],0, -19);
                
    }    
    
    //Function : DDB Project Setting Load
    public function setDynamicDataBuilderSetting(){
        $this->ddbconfigTable           = "config_master";
        $this->measuresSelectionTable   = "measures_selection";
        $this->outputColumnsTable       = "output_columns";
        $this->timeSelectionTable       = "time_selection";
        $this->filterSettingTable       = "filter_setting";
       
        $this->outputDateOptions = array(
			array("id" => "MYDATE", "value" => "My Date", "selected" => false)
		);

        if(!$this->hasMeasureFilter){
            $this->measureArray['M3']['VAL'] 	= $this->maintable . '.OHQ';;
            $this->measureArray['M3']['ALIASE'] = "OHQ";
            $this->measureArray['M3']['attr'] 	= "SUM";
        
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected'=>true),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected'=>true),
                array('measureID' => 3, 'jsonKey'=>'OHQ', 'measureName' => 'OHQ', 'selected'=>true)
            );
        }
        
		$this->dataArray['MYDATE']['NAME']              = $this->maintable.".mydate";
		$this->dataArray['MYDATE']['NAME_ALIASE']       = 'MYDATE';
		$this->dataArray['MYDATE']['TYPE']              = "T";
        
		$this->dataArray['PERIOD']['NAME']              = $this->maintable.".period";
		$this->dataArray['PERIOD']['NAME_ALIASE']       = 'PERIOD';
		$this->dataArray['PERIOD']['TYPE']              = "T";
        
        //SET FILTER PAGES
        $this->filterPages = array(
            array("filterHeader" => "Time Selection", "outputColumnHeader" => "", "templateName" => 'views/filter/timeSelection.html', "isDynamic" => false, "step" => 1, "isVisible" => true),
            array("filterHeader" => "Product Selection", "outputColumnHeader" => "Product Options", "templateName" => 'views/filter/filter.html', "isDynamic" => true, "step" => 2, "isVisible" => true, "tabsConfiguration" => array(), "config" => array(
                    "table_name" => $this->skutable,
                    "helper_table" => $this->productHelperTables,
                    "setting_name" => "product_settings",
                    "helper_link" => $this->productHelperLink,
                    "type" => "P",
                    "enable_setting_name" => "has_product_filter"
                )
            ),
            array("filterHeader" => "KDR Selection", "outputColumnHeader" => "KDR Options", "templateName" => 'views/filter/filter.html', "isDynamic" => true, "step" => 3, "isVisible" => true, "tabsConfiguration" => array(), "config" => array(
                    "table_name" => $this->skulisttable,
                    "helper_table" => $this->skulisttable,
                    "setting_name" => "kdr_settings",
                    "helper_link" => $this->skuListHelperLink,
                    "type" => "K",
                    "enable_setting_name" => "has_kdr"
                )
            ),
            array("filterHeader" => "Market Selection", "outputColumnHeader" => "Market Options", "templateName" => 'views/filter/filter.html', "isDynamic" => true, "step" => 4, "isVisible" => true, "tabsConfiguration" => array(), "config" => array(
                    "table_name" => $this->storetable,
                    "helper_table" => $this->geoHelperTables,
                    "setting_name" => "market_settings",
                    "helper_link" => $this->geoHelperLink,
                    "type" => "M",
                    "enable_setting_name" => "has_market_filter"
                )
            ),
            array("filterHeader" => "Territory Selection", "outputColumnHeader" => "Territory Options", "templateName" => 'views/filter/filter.html', "isDynamic" => true, "step" => 5, "isVisible" => true, "tabsConfiguration" => array(), "config" => array(
                    "table_name" => $this->territoryTable,
                    "helper_table" => $this->territoryHelperTables,
                    "setting_name" => "territory_settings",
                    "helper_link" => $this->territoryHelperLink,
                    "type" => "T",
                    "enable_setting_name" => "has_territory"
                )
            ),
            array("filterHeader" => "Account Selection", "outputColumnHeader" => "Account Options", "templateName" => 'views/filter/filter.html', "isDynamic" => true, "step" => 6, "isVisible" => true, "tabsConfiguration" => array(),  "config" => array( 
                    "table_name" => $this->fgroupTable, 
                    "helper_table" => $this->fgroupTable,
                    "setting_name" => "account_settings", 
                    "helper_link" => $this->fgroupTableHelperLink, 
                    "type" => "A",
                    "enable_setting_name" => "has_account" 
                )
            ),
            array("filterHeader" => "Measures Selection", "outputColumnHeader" => "", "templateName" => 'views/filter/measureSelection.html', "isDynamic" => false, "step" => 7, "isVisible" => true),
            array("filterHeader" => "Output Selection", "outputColumnHeader" => "", "templateName" => 'views/filter/outputColumn.html', "isDynamic" => false, "step" => 8, "isVisible" => true)
        );
    }
  
	public function getClientProjectName() {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('relay_plus_project_name');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $ultraUtility   = lib\UltraUtility::getInstance();
            $result = $ultraUtility->getClientProjectByID($this->aid,$this->projectID);
            $redisCache->setDataForStaticHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result)) {
            $this->pageArray["PROJECT_NAME"] = $result[0]['PNAME'];
            $this->pageArray["COMPANY_NAME"] = (isset($this->footerCompanyName) && !empty($this->footerCompanyName)) ? $this->footerCompanyName :  $result[0]['CNAME'];
        } else {
            $this->pageArray["PROJECT_NAME"] = "";
            $this->pageArray["COMPANY_NAME"] = "";
        }
    }
  
}    
?>