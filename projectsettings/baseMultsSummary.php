<?php

namespace projectsettings;

use projectsettings;
use filters;
use db;
use config;
use utils;
use lib;
use projectstructure;

class baseMultsSummary {

    //tables
    public $maintable;
    public $skutable;
	public $storetable;
    public $timetable;
	public $relayCommenttable;
    public $clustertable;
    public $clusterDetailsTable;
    public $instockRecoveryTable;
	public $instockRecoveryLevelTable;
    public $tablename;
	public $timeHelperTables;
    public $productHelperTables;
    public $commentHelperTables;
	public $clientconfigtable;
	public $grouptable;
    public $link;
	public $timeHelperLink;
    public $productHelperLink;
	public $commentHelperLink;
	public $prefix;
    public $pageTable;
    public $menuTable;
    public $assignMenusTable;
    public $assignPagesTable;
    public $assignMenuListsTable;
    public $assignClientPagesTable;
    public $configTable;
    public $accounttable;
    public $projectHelperLink;
	public $menuProjectHelperLink;
	public $performanceTabMappings;
	
    //core setting vars
    public $aid;
	public $uid;
	public $menuArray;
    public $measureArray;
    public $dataArray;
    public $defaultProjectSettings;
    
    //time vars
    public $dateperiod;
    public $dateField;
    public $yearperiod;
    public $weekperiod;
    public $ProjectValue;
    public $ProjectVolume;
    public $projectID;
    public $deptID;
    public $GID=2;
    public $currencySign;
    public $groupName;
    public $clientLogo;
    public $retailerLogo;
    public $PROJECTDEV;
    public $footerCompanyName;
    public $regionDataSetting;
	public $skuDataSetting;
	public $default_page_slug;
	public $isStaticPage;

    // client's vars
    public $supplierHelperLink;
    public $dataTable;
    public $tableUsedForQuery=array();
    public $useRequiredTablesOnly=false;

    public $projectTypeID;
    //[START] Dynamic data builder
        public $ddbconfigtable;
        public $measuresSelectionTable;
        public $outputColumnsTable;
        public $timeSelectionTable;
        public $filterSettingTable;
        public $outputDateOptions;
    //[END] Dynamic data builder
    
    public $isSifPageRequired;
    public $logoPath;
    
    public function __construct($accountID,$uid,$projectID) {
		
		$this->aid 							= $accountID;
		$this->uid 							= $uid;
        $this->projectID                    = $projectID;
		$this->projectType 					= 2;

        $this->skutable             		= "product";
        $this->timetable          			= "period_list";
		$this->relayCommenttable			= "relay_comments";
        $this->clustertable         		= "cluster";
        $this->clusterDetailsTable  		= "cluster_details";
		$this->instockRecoveryTable			= "instock_recovery";
		$this->instockRecoveryLevelTable	= "instock_recovery_levels";
		$this->clientconfigtable 			= "client_config";
        $this->seasonalTimeframeTable       = "seasonal_timeframe";
        $this->grouptable                   = "fgroup";
        $this->measuresTable                = "measures";
        $this->formulaTable                 = "formula";

        $this->defaultClusterId     		= 1;

        $this->isSifPageRequired            = true;
        $this->ferreroTescoSbaTable = "ferrero_tesco_sba";
        $this->ferreroDailyTrackerTable = "ferrero_daily_tracker";
        $this->logoPath             = 'https://secure.ultralysis.com/assets/img/';
        $this->clientLogoDir        = 'client-logo-by-id';
        $this->retailerLogoDir      = 'group-logo';

        /*** 
         * To enable/disable fetching all measure data in one go
         * For Performance In Box Summary Page. It will help when 
         * we have large amount of data for perticular client Like UB
        ***/
        $this->fetchAllMeasureForSummaryPerformanceInBox = true;

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

        // Available OPTIONS for timeSelectionUnit [weekYear, weekMonth, date, week, days, period]
        // weekYear, weekMonth only available in case of includeFutureDates true
        $this->timeSelectionUnit = "weekYear";
		
		// menu configuration
        $this->menuArray              = array();
        $this->prefix                 = "pm_";
        $this->pageTable              = $this->prefix . "pages";
        $this->menuTable              = $this->prefix . "menus";
        $this->assignMenusTable       = $this->prefix . "assignmenus";
        $this->assignPagesTable       = $this->prefix . "assignpages";
        $this->assignMenuListsTable   = $this->prefix . "assignmenulists";
        $this->assignClientPagesTable = $this->prefix."assign_client_pages";
        $this->configTable            = $this->prefix . "config";
        $this->breakdownTable         = $this->prefix . "db_table_breakdowns";
        $this->pageConfigTable        = "pm_pages_config";
        $this->templateMasterTable    = "template_master";

        $this->privateLabelFilterField = $this->skutable.".pl";
        $this->getAllPageConfiguration();

        /* PAGE CONFIGURATION VARS FOR DYNAMIC FLOW */
        $this->pageName             = $_REQUEST["pageName"];
        $this->pageID               = (isset($_REQUEST["pageID"]) && !empty($_REQUEST["pageID"])) ? $_REQUEST["pageID"] : 
                                        ((isset($_REQUEST['DataHelper']) && $_REQUEST['DataHelper'] == "true") ? $this->getDefaultPageConfiguration() : "");
                                        
        $this->isDynamicPage        = (empty($this->pageID)) ? false : true;

        $this->projectHelperLink = " AND projectID=" . $this->projectID . " ";
        
        // $this->currencySign  = $this->fetchCurrencySign();
        $this->fetchGroupDetail();
        $this->isRedisCachingEnabled = $this->hasRedisCaching();

        $this->ProjectValue   = $this->maintable . ".sales";
        $this->ProjectVolume  = $this->maintable . ".qty";
        $this->ProjectGmargin = $this->maintable . ".GMargin";
        
        // Available OPTIONS for projectStructureType [MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW (Standard lcl Structure), MEASURE_AND_TYLY_AS_COLUMN (Nielsen Structure), MEASURE_AS_SINGLE_COLUMN_AND_TYLY_AS_ROW (New ASD Online Structure)]
        $this->projectStructureType = projectstructure\ProjectStructureType::$MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW;        
        
        // default settings
        $this->defaultProjectSettings = array(
            'has_currency'              => $this->currencySign,
            'rp_has_territory'          => 0,
            'rp_has_territory_level'    => NULL,
            'has_product_filter'        => 0,
            'has_market_filter'         => 0,
        );
		
        $this->databaseName = $_REQUEST['connectedDatabaseName'];
        $this->clientConfiguration = array();        
        $this->getAllClientConfig();
        
		$this->menuArray['MF1']['ID'] = $this->pageTable . '.pageID';
        $this->menuArray['MF1']['NAME'] = $this->pageTable . '.pagetitle';
        $this->menuArray['MF1']['SLUG'] = $this->pageTable . '.pageurl';
        $this->menuArray['MF1']['STATUS'] = $this->pageTable . '.status';

        $this->menuArray['MF2']['ID'] = $this->menuTable . '.menuID';
        $this->menuArray['MF2']['NAME'] = $this->menuTable . '.menutitle';
        $this->menuArray['MF2']['STATUS'] = $this->menuTable . '.status';

        $this->menuArray['MF3']['ID'] = $this->assignPagesTable . '.assignID';
        $this->menuArray['MF3']['ASSIGN_MENU_ID'] = $this->assignPagesTable . '.menuID';
        $this->menuArray['MF3']['ASSIGN_PAGE_ID'] = $this->assignPagesTable . '.pageID';
        $this->menuArray['MF3']['PAGE_ORDER'] = $this->assignPagesTable . '.pageorder';
        $this->menuArray['MF3']['STATUS'] = $this->assignPagesTable . '.status';
        
        $this->menuArray['MF4']['ID'] = $this->assignMenusTable . '.assignID';
        $this->menuArray['MF4']['CLIENT_ID'] = $this->assignMenusTable . '.clientID';
        $this->menuArray['MF4']['ASSIGN_MENU_ID'] = $this->assignMenusTable . '.menuID';
        $this->menuArray['MF4']['MENU_ORDER'] = $this->assignMenusTable . '.menuorder';
        $this->menuArray['MF4']['STATUS'] = $this->assignMenusTable . '.status';
        
        $this->menuArray['MF5']['SETTING_NAME'] = $this->configTable . '.setting_name';
        $this->menuArray['MF5']['SETTING_VALUE'] = $this->configTable . '.setting_value';

        $this->menuArray['MF6']['TEMPLATE_SLUG'] = $this->templateMasterTable . '.templateslug';
        $this->menuArray['MF6']['TEMPLATE_ID'] = $this->templateMasterTable . '.templateID';
        $this->menuArray['MF6']['PAGE_TEMPLATE_ID'] = $this->pageConfigTable . '.templateID';

        // end menu configuration

		$this->yearperiod = $this->timetable . ".accountyear";
        $this->weekperiod = $this->timetable . ".accountweek";
        $this->dateperiod = "period";

        $this->supplierHelperLink = "";

        
        $this->productHelperTables = " " . $this->maintable . "," . $this->skutable . " ";
        $this->productHelperLink = " WHERE " . $this->maintable . ".PIN=" . $this->skutable . ".PIN AND clientID='" . $this->clientID . "' AND ".$this->skutable.".GID = ".$this->GID." AND ".$this->maintable.".GID = ".$this->skutable.".gid AND hide=0 ";
		
		$this->commentHelperTables = " " . $this->tablename . "," . $this->relayCommenttable . " ";
        $this->commentHelperLink = " WHERE " . $this->maintable . ".PIN=" . $this->relayCommenttable . ".SIN 
									AND " . $this->maintable . ".PO_Number=" . $this->relayCommenttable . ".po_number ".
									" AND ".$this->relayCommenttable.".clientID='" . $this->clientID . "' ";

        $this->accountHelperTables = $this->accounttable;
        $this->accountHelperLink = " WHERE $this->accounttable.GID IN ($this->GID) ";

        /**
         * Added for creating dynamic dataArray from ConfigurationCheck Class
         * It is useful to provide proper link and tables to dynamic dataArray
        */
        $this->tableArray['product']['tables']  = $this->productHelperTables;
        $this->tableArray['product']['link']    = $this->productHelperLink;
        $this->tableArray['product']['type']    = 'P';

		//menu link
		$this->menuProjectHelperLink = " AND ".$this->assignMenusTable.".projectID=".$this->projectID." AND ".$this->assignClientPagesTable.".projectID=".$this->projectID;
		
        $this->hasMeasureFilter = $this->hasMeasureFilter();
        
        $this->dataArray = array();
        $this->pageArray = array();
        
        if (!$this->isDynamicPage) {
            $this->dataArray();
            $this->pageArray();
        }
		


        $this->weekRangeList =array(
            array("value" => "YTD", "label" => "YTD"),
            array("value" => "4", "label" => "LAST 4 WEEKS"),
            array("value" => "13", "label" => "LAST 13 WEEKS"),
            array("value" => "52", "label" => "LAST 52 WEEKS")
        );
        
        $this->dayList =array(
            array("value" => 7, "label" => "Last 7 Days"),
            array("value" => 14, "label" => "Last 14 Days")
        );

        $this->daysList =array(
            array("value" => 1, "label" => "Last Day"),
            array("value" => 3, "label" => "Last 3 Days"),
            array("value" => 5, "label" => "Last 5 Days"),
            array("value" => 7, "label" => "Last 7 Days"),
            array("value" => 14, "label" => "Last 14 Days")
        );
		
        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units')
        );

        $this->pageArray["PerformanceBoxSettings"] = array(
            'LW_PW_1' => array(
                    'title' => 'LAST WEEK vs LY',
                    'timeFrame' => 1,
                    'TY' => 'LW1',
                    'LY' => 'PW1',
                    'rank' => 0,
                ),
            'LW_PW' => array(
                    'title' => 'LAST WEEK vs PREV. WEEK',
                    'timeFrame' => 0,
                    'TY' => 'LW',
                    'LY' => 'PW',
                    'rank' => 1,
                ),
            'LW_PW_4' => array(
                    'title' => 'LAST 4 WEEKS vs LY',
                    'timeFrame' => 4,
                    'TY' => 'LW4',
                    'LY' => 'PW4',
                    'rank' => 2,
                ),
            'LW_PW_13' => array(
                    'title' => 'LAST 13 WEEKS vs LY',
                    'timeFrame' => 13,
                    'TY' => 'LW13',
                    'LY' => 'PW13',
                    'rank' => 3,
                ),
            'LW_PW_52' => array(
                    'title' => 'LAST 52 WEEKS vs LY',
                    'timeFrame' => 52,
                    'TY' => 'LW52',
                    'LY' => 'PW52',
                    'rank' => 4,
                ),
            'YTD' => array(
                    'title' => 'YTD vs LY',
                    'timeFrame' => 'YTD',
                    'TY' => 'YTD_TY',
                    'LY' => 'YTD_LY',
                    'rank' => 5,
                ),
        ); 
        
        /*SEETTING THE PROJECT TYPE ID FROM THE SESSION VARS */
        if(isset($_SESSION['PROJECT_DETAILS_'.$this->projectID]) && isset($_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]) && isset($_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]['PROJECT_TYPE_ID'])){    
            $this->projectTypeID = $_SESSION['PROJECT_DETAILS_'.$this->projectID][$this->projectID]['PROJECT_TYPE_ID'];
        }

        /*[START] TREEMAP Color code*/
            $this->positiveStartColorCode = '#00f000';
            $this->positiveEndColorCode   = '#005000';
            $this->negativeStartColorCode = '#f00000';
            $this->negativeEndColorCode   = '#500000';
            $this->newItemColorCode       = '#32326e';
        /*[END] TREEMAP Color code*/
    }

    public function pageArray()
    {
        /**
         * Product selection tab list
         * This array is used to enable filter tabs on Product Selection
         * This is also used at Output Columns PRODUCT OPTIONS 
         * To remove option from user interface just need to comment out or 
         * remove any index from below list
         *
         */
        $this->productOptions_DisplayOptions = array();
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Category', 'data' => 'F1', 'indexName' => 'FS[F1]', 'selectedItemLeft' => 'selectedCategoryLeft',
            'selectedItemRight' => 'selectedCategoryRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );

        $this->pageArray["SUPPLIER_ANALYSIS_PAGE"]["ACCOUNT"] = "F8";
        $this->pageArray["SUPPLIER_ANALYSIS_PAGE"]["SKU_FIELD"] = "F3";

        $this->pageArray["WHS_FILL_PERFORMANCE_PAGE"]["ACCOUNT"] = "F31";

        $this->pageArray["RATE_OF_SALE_TRACKER_PAGE"]["ACCOUNT"] = "F3";
        $this->pageArray["RATE_OF_SALE_TRACKER_PAGE"]["STORE_COUNT"] = "F7";
    }
	
    public function dataArray()
    {
        $this->dataArray['F1']['NAME']          = $this->skutable . '.category';
        $this->dataArray['F1']['NAME_ALIASE']   = 'category';
        $this->dataArray['F1']['tablename']     = $this->productHelperTables;
        $this->dataArray['F1']['link']          = $this->productHelperLink;
        $this->dataArray['F1']['TYPE']          = "P";

        $this->dataArray['F2']['NAME']          = $this->skutable . '.brand';
        $this->dataArray['F2']['NAME_ALIASE']   = 'brand';

        $this->dataArray['F3']['ID'] 			= $this->skutable . '.PIN'; //$this->maintable.'.PIN';
        $this->dataArray['F3']['ID_ALIASE'] 	= "PIN";
        $this->dataArray['F3']['NAME']          = $this->skutable . '.PIN';
        $this->dataArray['F3']['NAME_ALIASE']   = 'sku_rollup';
        $this->dataArray['F3']['tablename']     = $this->productHelperTables;
        $this->dataArray['F3']['link']          = $this->productHelperLink;
        $this->dataArray['F3']['TYPE']          = "P";

        $this->dataArray['F4']['NAME']          = $this->skutable . '.sku';
        $this->dataArray['F4']['NAME_ALIASE']   = 'SKU';
        $this->dataArray['F4']['tablename']     = $this->productHelperTables;
        $this->dataArray['F4']['link']          = $this->productHelperLink;
        $this->dataArray['F4']['TYPE']          = "P";

        $this->dataArray['F5']['NAME']          = $this->maintable . '.sales';
        $this->dataArray['F5']['NAME_ALIASE']   = 'sales';

        $this->dataArray['F6']['NAME']          = $this->maintable . '.GMargin';
        $this->dataArray['F6']['NAME_ALIASE']   = 'GMargin';

        $this->dataArray['F7']['NAME']          = $this->maintable . '.store_count';
        $this->dataArray['F7']['NAME_ALIASE']   = 'STORE';
        
        $this->dataArray['F8']['NAME']          = $this->skutable . '.supplier';
        $this->dataArray['F8']['NAME_ALIASE']   = 'supplier';
        $this->dataArray['F8']['tablename']     = $this->productHelperTables;
        $this->dataArray['F8']['link']          = $this->productHelperLink;
        $this->dataArray['F8']['TYPE']          = "P";

        $this->dataArray['F31']['ID']            = $this->skutable . '.PIN'; //$this->maintable.'.PIN';
        $this->dataArray['F31']['ID_ALIASE']     = "PIN";
        $this->dataArray['F31']['NAME']          = $this->skutable . '.PNAME';
        $this->dataArray['F31']['NAME_ALIASE']   = 'PNAME';
        $this->dataArray['F31']['tablename']     = $this->productHelperTables;
        $this->dataArray['F31']['link']          = $this->productHelperLink;
        $this->dataArray['F31']['TYPE']          = "P";

        $this->dataArray['F18']['NAME']         = $this->timetable . '.mydate';
        $this->dataArray['F18']['NAME_ALIASE']  = 'MYDATE';

        $this->dataArray['WEEK']['NAME']        = $this->weekperiod;
        $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

        $this->dataArray['YEAR']['NAME']        = $this->yearperiod;
        $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
    }

    public function getAllPageConfiguration() {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_pages_configuration');

        $this->pageConfiguration = array();

        if ($queryVars->isInitialisePage || $redisOutput === false) {
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
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(!empty($this->default_load_pageID))
        {
            $query = "SELECT templateslug, ".$this->pageConfigTable.".templateID FROM ". 
            $this->pageConfigTable . ",template_master  WHERE accountID = ".$this->aid." AND projectID = ".$this->projectID.
            " AND setting_name != 'default_load_pageID' AND pageID = ".$this->default_load_pageID. " AND ".$this->pageConfigTable.".templateID = template_master.templateID";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($result) && !empty($result))
            {
                $this->default_page_slug = $result[0]['templateslug'];
                $this->isStaticPage = false;
            }
            else
            {
                $query = "SELECT pageurl FROM pm_pages WHERE pm_pages.pageID = ".$this->default_load_pageID." AND pm_pages.accountID = ".$this->aid." AND pm_pages.projectID = ".$this->projectID." AND projectType = ".$this->projectType;
                $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
                if(is_array($result) && !empty($result))
                {
                    $this->default_page_slug = $result[0]['pageurl'];
                    $this->isStaticPage = true;
                }
            }
        }

        return $this->default_load_pageID;
    }

    public function configureClassVars()
    {
        $this->dateField = $this->timetable . "." . $this->dateperiod;
        $this->copy_tablename = $this->tablename = $this->maintable . "," . $this->skutable . "," . $this->timetable ;
        
        if (!empty($this->storetable))
		{
			$this->tablename .= "," . $this->storetable . " ";
			$this->copy_tablename = $this->tablename;
		}

        if (!empty($this->accounttable) && $this->accounttable != $this->maintable) {
            $this->tablename .= "," . $this->accounttable . " ";
            $this->copy_tablename = $this->tablename;
        }
	
        $commontables   = $this->maintable . "," . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND " . $this->maintable . ".".$this->dateperiod."=" . $this->timetable . ".".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $storelink      = ((!empty($this->storetable)) ? " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") " : "" );

        $accountlink  = ((!empty($this->accounttable) && $this->accounttable != $this->maintable) ? $this->accountLink : "");
						
        $this->copy_link     = $this->link     = $commonlink.$storelink.$skulink;

 		$this->commentHelperTables = " " . $this->tablename . "," . $this->relayCommenttable . " ";
        $this->commentHelperLink = " " . $this->link . " AND " . $this->maintable . ".PIN=" . $this->relayCommenttable . ".SIN 
									AND " . $this->maintable . ".PO_Number=" . $this->relayCommenttable . ".po_number ".
									" AND ".$this->relayCommenttable.".clientID='" . $this->clientID . "' ";
        
        $this->timeHelperTables = $this->timetable;
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";

        if($this->hasHiddenSku()) {
            $commontables .= ", ".$this->skutable;
            $commonlink   .= $skulink;
            $skulink       = '';
        }

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

		/* $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink; */

        $this->dataTable[$this->accounttable]['tables']        = $this->accounttable;
        $this->dataTable[$this->accounttable]['link']          = $accountlink;
		
        if($this->hasMeasureFilter){
            $this->getMeasureSettings();
        }else{
            $this->measureArray = array();
            $this->measureArray['M1']['VAL']    = $this->ProjectValue;
            $this->measureArray['M1']['ALIASE'] = "VALUE";
            $this->measureArray['M1']['attr']   = "SUM";

            $this->measureArray['M2']['VAL']    = $this->ProjectVolume;
            $this->measureArray['M2']['ALIASE'] = "VOLUME";
            $this->measureArray['M2']['attr']   = "SUM";
            
            $this->measureArray['M3']['VAL'] = "(SUM(IFNULL(".$this->ProjectValue.",0))/SUM(IFNULL(".$this->ProjectVolume.",1)))";
            $this->measureArray['M3']['ALIASE'] = "PRICE";
            $this->measureArray['M3']['attr']   = "PRICE";
            $this->measureArray['M3']['dataDecimalPlaces']   = 2;
                                    
            $this->measureArray['M4']['VAL'] = "MAX(store_count)";
            $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M4']['attr']   = "DISTRIBUTION";
            $this->measureArray['M4']['dataDecimalPlaces']   = 0;
    		
            $this->measureArray['M5']['VAL'] = "AveStoreStock";
            $this->measureArray['M5']['ALIASE'] = "STOCK";
            $this->measureArray['M5']['attr'] = "SUM";            

            $this->measureArray['M6']['VAL'] = "AVG(AVAILABILITY)";
            $this->measureArray['M6']['ALIASE'] = "AVAILABILITY";

            $this->measureArray['M7']['VAL'] = "MAX(store_count)";
            $this->measureArray['M7']['ALIASE'] = "RATEOFSALE";
            $this->measureArray['M7']['attr']   = "RATE OF SALE";
            $this->measureArray['M7']['dataDecimalPlaces']   = 2;
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4',
                "stockOverTime" => 'M5',
                "availabilityOverTime" => 'M6',
                "rateOfSale" => 'M7'
            );	
        }	
		
        $this->getClientProjectName();
        
        //[START] LOAD DDB SETTING
        if($this->projectTypeID == 6) // DDB
            $this->setDynamicDataBuilderSetting();        
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

        if ($queryVars->isInitialisePage || $redisOutput === false) {
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
				$selectField = ($withAggregate) ? "MAX(".$this->timetable.".mydate) " : $this->timetable.".mydate ";
				break;
			case "mydate":
				$selectField = ($withAggregate) ? "MAX(".$this->timetable.".mydate) " : $this->timetable.".mydate ";
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

        if ($queryVars->isInitialisePage || $redisOutput === false) {
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
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_measure_configuration');
        
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
                        if($measureData['showInSif'] == 0){
                            $measureSelectionList = array();
                            if($measureData['showAsFilter'] == 1){
                                $measureSelectionList['measureID']      = $measureData['measureID'];
                                $measureSelectionList['jsonKey']        = $measureData['alias'];
                                $measureSelectionList['dataDecimalPlaces'] = $measureData['data_decimal_places'];
                                $measureSelectionList['measureName']    = $measureData['measureName'];
                                $measureSelectionList['selected']       = (( empty($defaultLoad) && count($this->pageArray["MEASURE_SELECTION_LIST"]) == 0 ) || ( $defaultLoad['measureID'] == $measureData['measureID'] ) ) ? true : false ;

                                if($this->projectTypeID == 6)
                                    $measureSelectionList['selected'] = ($measureData['measureSelected']) ? true : false;
                                
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
                    
                    if(isset($queryVars->projectConfiguration['performance_tab_mapping']) && !empty($queryVars->projectConfiguration['performance_tab_mapping']) ) {
                        $settings = explode('|', $queryVars->projectConfiguration['performance_tab_mapping']);
                        foreach ($settings as $value) {
                            $tabSettings = explode('#', $value);
                            $this->performanceTabMappings[$tabSettings[0]] = "M".$tabSettings[1];
                        }
                    }else{
                        if($this->projectTypeID != 6)
                        {
                            $response = array("configuration" => array("status" => "fail", "messages" => array('Performance tab measure mapping not found.')));
                            echo json_encode($response);
                            exit();
                        }
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
            $redisCache->requestHash = 'lcl_measure_configuration';
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
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_data_manager_configuration');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
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
    
    //Function : DDB Project Setting Load
    public function setDynamicDataBuilderSetting(){
        $this->ddbconfigTable           = "config_master";
        $this->measuresSelectionTable   = "measures_selection";
        $this->outputColumnsTable       = "output_columns";
        $this->timeSelectionTable       = "time_selection";
        $this->filterSettingTable       = "filter_setting";

        $this->outputDateOptions = array(
			array("id" => "MYDATE", "value" => "My Date", "selected" => false),
			array("id" => "YEAR", "value" => "Year", "selected" => false),
			array("id" => "WEEK", "value" => "Week", "selected" => false),
			array("id" => "PERIOD", "value" => "WM Week", "selected" => false)
		);

        if(!$this->hasMeasureFilter){
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected'=>true),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected'=>true)
            );
        }
        
		$this->dataArray['WEEK']['TYPE']              = "T";
		$this->dataArray['YEAR']['TYPE']              = "T";

		$this->dataArray['MYDATE']['NAME']              = $this->timetable.".mydate";
		$this->dataArray['MYDATE']['NAME_ALIASE']       = 'MYDATE';
		$this->dataArray['MYDATE']['TYPE']              = "T";
        
		$this->dataArray['PERIOD']['NAME']              = $this->maintable.".period";
		$this->dataArray['PERIOD']['NAME_ALIASE']       = 'WMWEEK';
		$this->dataArray['PERIOD']['TYPE']              = "T";
        
        $this->dataArray['YEARWEEK']['NAME'] = "CONCAT(".$this->yearperiod.",'-',"."LPAD(".$this->weekperiod.",2,'0')".")";
        $this->dataArray['YEARWEEK']['NAME_ALIASE'] = 'YEARWEEK';
        $this->dataArray['YEARWEEK']['TYPE'] = "T";
        $this->dataArray['YEARWEEK']['csv_header'] = "YEAR-WEEK";        

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
                    "table_name" => $this->territorytable,
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

    /*public function getAllSeasonalTimeframe() {
        $queryVars = projectsettings\settingsGateway::getInstance();
        $this->seasonalTimeframeConfiguration = array();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('seasonal_timeframe_configuration');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $query = "SELECT id, timeframe_name, ty_start_date, ty_end_date, ly_start_date, ly_end_date, seasonalTimeframeOrder, status FROM ".$this->seasonalTimeframeTable." WHERE accountID=".$this->aid." AND projectID=" . $this->projectID." AND status = 1 ORDER BY seasonalTimeframeOrder ASC";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            $this->seasonalTimeframeConfiguration = $result;
            $redisCache->setDataForStaticHash($this->seasonalTimeframeConfiguration);
        } else {
            $this->seasonalTimeframeConfiguration = $redisOutput;
        }
        return $this->seasonalTimeframeConfiguration;
    }*/

    public function hasSeasonalTimeframe() {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if (isset($queryVars->projectConfiguration['has_seasonal_timeframe']) && $queryVars->projectConfiguration['has_seasonal_timeframe'] == 1)
            return true;
        else
            return false;
    }

    public function getAllSeasonalTimeframe() {
        $queryVars = projectsettings\settingsGateway::getInstance();
        $this->seasonalTimeframeConfiguration = array();
        if (isset($queryVars->projectConfiguration['has_seasonal_timeframe']) && $queryVars->projectConfiguration['has_seasonal_timeframe'] == 1) {
            $redisCache = new utils\RedisCache($queryVars);
            $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('seasonal_timeframe_configuration');

            if ($queryVars->isInitialisePage || $redisOutput === false) {
                $query = "SELECT id, timeframe_name, ty_start_date, ty_end_date, ly_start_date, ly_end_date, where_clause, seasonalTimeframeOrder, status FROM ".$this->seasonalTimeframeTable." WHERE accountID=".$this->aid." AND projectID=" . $this->projectID." AND status = 1 ";
                $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

                $seasonalArr = array_column($result,'seasonalTimeframeOrder');
                array_multisort($seasonalArr, SORT_ASC, SORT_NUMERIC, $result);

                $this->seasonalTimeframeConfiguration = $result;


                $redisCache->setDataForStaticHash($this->seasonalTimeframeConfiguration);
            } else {
                $this->seasonalTimeframeConfiguration = $redisOutput;
            }

            if(count($this->seasonalTimeframeConfiguration) > 0){
                return $this->seasonalTimeframeConfiguration;
            } else {
                $response = array("configuration" => array("status" => "fail", "messages" => array('Seasonal Timeframe configuration not found.')));
                echo json_encode($response);
                exit();
            }
        }
        return $this->seasonalTimeframeConfiguration;
    }

    public function getClientAndRetailerLogo($settingData = array())
    {
        $this->clientLogo           = isset($settingData['clientLogo']) ? $settingData['clientLogo'] : ((isset($settingData['accountID']) && !empty($settingData['accountID'])) ? $settingData['accountID'].'.png' : $this->aid.'.png');
        $this->retailerLogo         = isset($settingData['retailerLogo']) ? $settingData['retailerLogo'] : ((isset($settingData['gId']) && !empty($settingData['gId'])) ? $settingData['gId'].'.png' : $this->GID.'.png');

        if(!empty($this->clientLogo)){
            $this->clientLogo = $this->logoPath.$this->clientLogoDir.DIRECTORY_SEPARATOR.$this->clientLogo;
        } else {
            $this->clientLogo = $this->logoPath.$this->clientLogoDir.DIRECTORY_SEPARATOR.'no-logo.jpg';
        }

        if(!empty($this->retailerLogo)){
            $this->retailerLogo = $this->logoPath.$this->retailerLogoDir.DIRECTORY_SEPARATOR.$this->retailerLogo;
        } else {
            $this->retailerLogo = $this->logoPath.$this->retailerLogoDir.DIRECTORY_SEPARATOR.'no-logo.jpg';
        }
    }
}
?>