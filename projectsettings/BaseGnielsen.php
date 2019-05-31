<?php

namespace projectsettings;

use projectsettings;
use filters;
use db;
use config;
use utils;
use lib;
use projectstructure;

class BaseGnielsen {

    //tables
    public $maintable;
    public $timetable;
    public $skutable;
    public $storetable;
    public $tablename;
    public $territorytable;
    public $territoryLevel;
    public $productplanotable;
    public $storeplanotable;
    public $activetable;
    public $clustertable;
    public $masterplanotable;
    public $clientconfigtable;
    public $configTable;
    public $accounttable;
    //time vars
    public $weekperiod;
    public $monthperiod;
    public $yearperiod;
    public $dateperiod;
    public $dateField;
    public $periodField;
    public $timeSelectionUnit;
    //core setting vars
    public $aid;
    public $clientID;
    public $GID;
    public $currencySign;
	public $databaseName;
    public $groupName;
    public $groupType;

    //measure vars
    public $ProjectValue;
    public $ProjectValueText;
    public $ProjectVolume;
    public $ProjectVolumeText;
    //query vars
    public $link;
    public $timeHelperTables;
    public $timeHelperLink;
    public $productHelperTables;
    public $productHelperLink;
    public $geoHelperTables;
    public $geoHelperLink;
    public $projectHelperLink;
    public $menuProjectHelperLink;
    public $tablename_for_calendar;
    public $where_clause_for_calendar;
    public $extraQueryPartForNotCalledCalculation;
    public $extraQueryPartForCalledCalculation;
    public $extraProductHelperQueryPart;
    //data storage vars
    public $calendarItems;
    public $measureArray;
    public $measureArray_for_productKBI_only;
    public $dataArray;
    public $privateLabelFilterField;
	public $filterMaster;
    public $filterSelection;
    public $productSubFilterTabs;
    public $accountSubFilterTabs;
    public $dataTable;
    public $tableUsedForQuery=array();
    public $performanceTabMappings=array();
    public $useRequiredTablesOnly=false;
    public $forceUseOriginalLink=false;
    public $performanceGridShowAllOtherAfter=10;

    public $calculateVsiOhq = false;
    public $isStaticPage;
    public $skipCommonCacheHash;
    public $headerFooterSourceText;
    public $showContributionAnalysisPopup;
    
	//category summary pods vars	
	public $catSummaryPageDataTwoField;
    
    public $hiddenSkusQueryString = "";
    public $detailedDriverAnalysisChartTabMappings;

    public $projectTypeID;
    //[START] Dynamic data builder
        public $ddbconfigtable;
        public $measuresSelectionTable;
        public $outputColumnsTable;
        public $timeSelectionTable;
        public $filterSettingTable;
        public $outputDateOptions;
    //[END] Dynamic data builder

    public $logoPath; 

    public function __construct($gids=array(10)) {
		$this->timetable            = "";
        $this->skutable             = "product";
        $this->storetable           = "market";
        $this->activetable          = "";
        $this->clientconfigtable    = "client_config";
        $this->filterMaster         = "filter_master";
        $this->filterSelection      = "filter_selection";
        $this->masterplanotable     = "";
        $this->grouptable           = "fgroup";
        $this->measuresTable        = "measures";
        $this->formulaTable         = "formula";

        $this->isPlanoOnly = true;

        $this->weekperiod = "";
        $this->yearperiod = "";
        $this->dateperiod = "";

        $this->logoPath             = 'https://secure.ultralysis.com/assets/img/';
        $this->clientLogoDir        = 'client-logo-by-id';
        $this->retailerLogoDir      = 'group-logo';

        /*** 
         * To enable new flow for pages 
         * >> Product and Market filter on tab click
         * >> Added Sticky Filter for Product and Market filter
         * >> Added logic for clean dom object and rebuild page
         * >> All above features controlled by this single flag
        ***/
        //$this->fetchProductAndMarketFilterOnTabClick = true;

        // To enable setting for include future dates in time selection filter
        $this->includeFutureDates = false;

        // Available OPTIONS for timeSelectionUnit [weekYear, weekMonth, date, week, days, period, none (Just for Gaylea GT)]
        // weekYear, weekMonth only available in case of includeFutureDates true
        $this->timeSelectionUnit = "none";

        // Available OPTIONS for projectStructureType [MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW (Standard lcl Structure), MEASURE_AND_TYLY_AS_COLUMN (Nielsen Structure), MEASURE_AS_SINGLE_COLUMN_AND_TYLY_AS_ROW (New ASD Online Structure)]
        $this->projectStructureType = projectstructure\ProjectStructureType::$MEASURE_AND_TYLY_AS_COLUMN;

         // Available OPTIONS for timeSelectionStyle ['GENERAL','DROPDOWN']
        $this->timeSelectionStyle = 'DROPDOWN';
        $this->timeSelectionStyleDDArray = array(
            ['data'=>"YTD",'label'=>"YTD", 'jsonKey' => 'YTD'],
            ['data'=>"4",  'label'=>"Latest 4 Weeks", 'jsonKey' => 'L4'],
            ['data'=>"12", 'label'=>"Latest 12 Weeks", 'jsonKey' => 'L12'],
            ['data'=>"52", 'label'=>"Latest 52 Weeks", 'jsonKey' => 'L52']
        );

        $this->GID = implode(",",$gids);
        $this->projectType = 1;
        
        $this->privateLabelFilterField = "";

        $this->controlFlagField = $this->skutable.".control_flag";
		
		//category summary pods vars        
        $this->catSummaryPageDataTwoField = "";
        
        // menu configuration
        $this->menuArray = array();
        $this->prefix = "pm_";
        $this->pageTable = $this->prefix . "pages";
        $this->menuTable = $this->prefix . "menus";
        $this->assignMenusTable = $this->prefix . "assignmenus";
        $this->assignPagesTable = $this->prefix . "assignpages";
        $this->assignMenuListsTable = $this->prefix . "assignmenulists";
        $this->assignClientPagesTable = $this->prefix."assign_client_pages";
        $this->configTable = $this->prefix . "config";
        $this->breakdownTable = $this->prefix . "db_table_breakdowns";

        $this->pageConfigTable             = "pm_pages_config";
        $this->templateMasterTable         = "template_master";

        $this->getAllPageConfiguration();

		/* PAGE CONFIGURATION VARS FOR DYNAMIC FLOW */
		$this->pageName				= $_REQUEST["pageName"];
		$this->pageID				= (isset($_REQUEST["pageID"]) && !empty($_REQUEST["pageID"])) ? $_REQUEST["pageID"] : 
										((isset($_REQUEST['DataHelper']) && $_REQUEST['DataHelper'] == "true") ? $this->getDefaultPageConfiguration() : "");
        $this->isDynamicPage 		= (empty($this->pageID)) ? false : true;

        $this->extraQueryPartForCalledCalculation       = " AND level".$this->territoryLevel." <> 'NOT CALLED' ";
        $this->extraQueryPartForNotCalledCalculation    = " AND level".$this->territoryLevel." = 'NOT CALLED' ";

        $this->defaultProjectSettings = array(
            'has_private_label'         => 0,
            'has_private_email_label'   => 0,
            'has_ty_data'               => 1,
            'has_ly_data'               => 1,
            'has_market_filter'         => 0,
            'has_product_filter'        => 0
        );

        $this->projectHelperLink = " AND projectID=" . $this->projectID . " ";

        $this->fetchGroupDetail();
        $this->isRedisCachingEnabled = $this->hasRedisCaching();
        $this->databaseName = $_REQUEST['connectedDatabaseName'];
        $this->clientConfiguration = array();

        $this->getAllClientConfig();
		
		$this->ProjectValue = "SALES";
        $this->ProjectValueText = "Value(".$this->currencySign.")";
        /* $this->ProjectValueText = "Value(\$CAN)"; */
		$this->ProjectVolume = "YIELD";
        $this->ProjectVolumeText = "YIELD";
        $this->ProjectDist = "DIST";

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
		
        /**
         * Added for creating dynamic dataArray from ConfigurationCheck Class
         * It is useful to provide proper link and tables to dynamic dataArray
        */
		$this->tableArray['product']['tables'] 	= $this->skutable;
		$this->tableArray['product']['link'] 	= " WHERE clientID='".$this->clientID."' AND GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart;
		$this->tableArray['product']['type'] 	= 'P';

 		$this->tableArray['market']['tables'] 	= $this->storetable;
		$this->tableArray['market']['link'] 		= " WHERE ".$this->storetable.".GID IN (".$this->GID.")".
                                                  " AND ".$this->storetable.".marketID IN ( SELECT DISTINCT " . 
                                                    $this->maintable . ".marketID FROM " . $this->maintable . " WHERE ".
                                                    $this->maintable.".GID IN (".$this->GID.") ) ";
		$this->tableArray['market']['type'] 		= 'M';
        
		/*$this->tableArray['territory']['tables'] 	= $this->territorytable;
		$this->tableArray['territory']['link'] 		= " WHERE ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid." ";
		$this->tableArray['territory']['type'] 		= 'P';*/

        $this->dataArray = array();
        
        /* $this->dataArray();
        $commonFilterQueryPart = $this->commonFilterQueryString("P"); */
        
        $this->productHelperTables = $this->skutable;
        $this->productHelperLink = $this->tableArray['product']['link'];

        /* $commonFilterQueryPart = $this->commonFilterQueryString("M"); */
        $this->geoHelperTables = $this->tableArray['market']['tables'];
        $this->geoHelperLink = $this->tableArray['market']['link'];

        // $this->territoryHelperTables = $this->territorytable;
        // $this->territoryHelperLink = $this->tableArray['territory']['link'];
        
        $this->accountHelperTables = $this->accounttable;
        $this->accountHelperLink = " WHERE $this->accounttable.GID IN ($this->GID) ";

        $this->menuProjectHelperLink = " AND ".$this->assignMenusTable.".projectID=".$this->projectID." AND ".$this->assignClientPagesTable.".projectID=".$this->projectID
                                      ." AND ".$this->pageTable.".projectID=".$this->projectID;

        $this->hasGlobalFilter = $this->hasGlobalFilter();
        
        /* ** DO NOT REMOVE BELOW LINES OF HELPER LINK THAT IS REDECLARE FOR OVERWRITING PERPOSE ** */
        /* ** WE ARE APPENDING GLOBAL FILTER FIELDS HERE TO FILTER FILTER DATA ** */
        $this->productHelperLink = $this->tableArray['product']['link'];
        $this->geoHelperLink = $this->tableArray['market']['link'];
        $this->territoryHelperLink = $this->tableArray['territory']['link'];
        /* ** DO NOT REMOVE ABOVE LINES OF HELPER LINK THAT IS REDECLARE FOR OVERWRITING PERPOSE ** */

        $this->hasMeasureFilter = $this->hasMeasureFilter();

        
		if (!$this->isDynamicPage)
			$this->dataArray();

        /* ============================= FRONTEND CONFIGURATION ========================================= */
        $this->pageArray = array();


        /* ============================= GLOBAL VARS CONFIGURATION ========================================= */

        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
        );
        

        // sku selectin list
        $this->pageArray["SKU_SELECTION_LIST"] = array(
            array('skuID' => 0, 'skuKey' => 'F10', 'skuName' => 'Sku(P)'),
            array('skuID' => 1, 'skuKey' => 'F11', 'skuName' => 'Sku(M)')
        );

        $this->uploadDir = __DIR__ . "/../../".$_REQUEST['projectDIR']."/templates/";
        $this->uploadUrl = $this->get_full_url()."/".$_REQUEST['projectDIR']."/templates/";
		
		if (!$this->isDynamicPage)
			$this->pageArray();

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

    public function configureClassVars()
    {
        $this->dateField = "";
        $this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->skutable . "," . $this->storetable;
        
        if (!empty($this->territorytable)) {
            $this->tablename .= "," . $this->territorytable . " ";
            $this->copy_tablename = $this->tablename;
        }

        if (!empty($this->accounttable) && $this->accounttable != $this->maintable) {
            $this->tablename .= "," . $this->accounttable . " ";
            $this->copy_tablename = $this->tablename;
        }        
        
        $commontables   = $this->maintable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.GID IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.marketID=$this->storetable.marketID " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";

        $accountlink  = ((!empty($this->accounttable) && $this->accounttable != $this->maintable) ? $this->accountLink : "");
                        
        $skulink        = "AND $this->maintable.skuID=$this->skutable.skuID " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.GID IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$skulink.$accountlink;

        /*$this->timeHelperTables = " " . $this->timetable . "," . $this->maintable . " ";
        $this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->dateperiod . "=" . 
                                $this->timetable . "." . $this->dateperiod . 
                                " AND ".$this->maintable.".GID=".$this->timetable.".gid " .
                                " AND ".$this->timetable.".GID IN (".$this->GID.") ";*/

        $this->timeHelperTables = '';
        $this->timeHelperLink = "";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".skuID NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable[$this->storetable]['tables']        = $this->storetable;
        $this->dataTable[$this->storetable]['link']          = $storelink;

        $this->dataTable[$this->accounttable]['tables']        = $this->accounttable;
        $this->dataTable[$this->accounttable]['link']          = $accountlink;
        
        /** ******************************************** USED ONLY FOR CALENDAR PAGE *********************** */
        /*$this->calendarItems = array();
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
        );*/

        //$this->tablename_for_calendar = $this->maintable . "," . $this->timetable;
        //$this->where_clause_for_calendar = $this->maintable . "." . $this->dateperiod . "=" . $this->timetable . "." . $this->dateperiod ." AND " .$this->maintable.".GID=.".$this->timetable.".gid AND ".$this->timetable.".gid IN (".$this->GID.") ";

        /*$this->tablename_for_calendar = $this->timetable;
        $this->where_clause_for_calendar = $this->timetable.".GID IN (".$this->GID.") "." AND ". $this->timetable . "." . $this->dateperiod . " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";*/
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
            $this->measureArray['M3']['dataDecimalPlaces']   = 2;  
            
            /*$this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M4']['attr'] = "COUNT";
            $this->measureArray['M4']['dataDecimalPlaces']   = 0;

            $this->measureArray['M5']['VAL'] = "VAT";
            $this->measureArray['M5']['ALIASE'] = "VAT";
            $this->measureArray['M5']['attr'] = "SUM";*/
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3'/*,
                "distributionOverTime" => 'M4'*/
            );
            
            $this->detailedDriverAnalysisChartTabMappings = array(
                "valueChart" => 'M1',
                "unitChart" => 'M2',
                "priceChart" => 'M3'/*,
                "sellingStoreChart" => 'M4'*/
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
            'label' => 'Sku', 'data' => 'F1', 'indexName' => 'FS[F1]', 'selectedItemLeft' => 'selectedBrandLeft',
            'selectedItemRight' => 'selectedBrandRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Brand', 'data' => 'F3', 'indexName' => 'FS[F3]', 'selectedItemLeft' => 'selectedCategoryLeft',
            'selectedItemRight' => 'selectedCategoryRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Category', 'data' => 'F4', 'indexName' => 'FS[F4]', 'selectedItemLeft' => 'selectedOwnLabelLeft',
            'selectedItemRight' => 'selectedOwnLabelRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Own Label', 'data' => 'F5', 'indexName' => 'FS[F5]', 'selectedItemLeft' => 'selectedSkuLeft',
            'selectedItemRight' => 'selectedSkuRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Lcl Super', 'data' => 'F6', 'indexName' => 'FS[F6]', 'selectedItemLeft' => 'selectedLclSuperLeft',
            'selectedItemRight' => 'selectedLclSuperRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Lcl Dept', 'data' => 'F7', 'indexName' => 'FS[F7]', 'selectedItemLeft' => 'selectedLclDeptLeft',
            'selectedItemRight' => 'selectedLclDeptRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Lcl CatGrp', 'data' => 'F8', 'indexName' => 'FS[F8]', 'selectedItemLeft' => 'selectedLclCatGrpLeft',
            'selectedItemRight' => 'selectedLclCatGrpRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Lcl Cat', 'data' => 'F9', 'indexName' => 'FS[F9]', 'selectedItemLeft' => 'selectedLclCatLeft',
            'selectedItemRight' => 'selectedLclCatRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->productOptions_DisplayOptions[] = array(
            'label' => 'Lcl SubCat', 'data' => 'F10', 'indexName' => 'FS[F10]', 'selectedItemLeft' => 'selectedLclSubCatLeft',
            'selectedItemRight' => 'selectedLclSubCatRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );


        /**
         * Market selection tab list
         * This array is used to enable filter tabs on Account selection
         * This is also used at Output Columns MARKET OPTIONS 
         * To remove option from user interface just need to comment out or 
         * remove any index from below list
         *
         */
        $this->marketOptions_DisplayOptions = array();
        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Division', 'data' => 'F12', 'indexName' => 'FS[F12]', 'selectedItemLeft' => 'selectedDivisionLeft',
            'selectedItemRight' => 'selectedDivisionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );

        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Banner', 'data' => 'F11', 'indexName' => 'FS[F11]', 'selectedItemLeft' => 'selectedBannerLeft',
            'selectedItemRight' => 'selectedBannerRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );

        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Store', 'data' => 'F13', 'indexName' => 'FS[F13]', 'selectedItemLeft' => 'selectedStoreLeft',
            'selectedItemRight' => 'selectedStoreRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Region', 'data' => 'F14', 'indexName' => 'FS[F14]', 'selectedItemLeft' => 'selectedRegionLeft',
            'selectedItemRight' => 'selectedRegionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Province', 'data' => 'F15', 'indexName' => 'FS[F15]', 'selectedItemLeft' => 'selectedProvinceLeft',
            'selectedItemRight' => 'selectedProvinceRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'City', 'data' => 'F26', 'indexName' => 'FS[F26]', 'selectedItemLeft' => 'selectedPostalCodeLeft',
            'selectedItemRight' => 'selectedPostalCodeRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
        // $this->marketOptions_DisplayOptions[] = array(
        //     'label' => 'Postal Code', 'data' => 'F16', 'indexName' => 'FS[F16]', 'selectedItemLeft' => 'selectedPostalCodeLeft',
        //     'selectedItemRight' => 'selectedPostalCodeRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        // );
        // $this->marketOptions_DisplayOptions[] = array(
        //     'label' => 'Prov Split', 'data' => 'F30', 'indexName' => 'FS[F30]', 'selectedItemLeft' => 'selectedPostalCodeLeft',
        //     'selectedItemRight' => 'selectedPostalCodeRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        // );
        // $this->marketOptions_DisplayOptions[] = array(
        //     'label' => 'Municipality', 'data' => 'F25', 'indexName' => 'FS[F25]', 'selectedItemLeft' => 'selectedPostalCodeLeft',
        //     'selectedItemRight' => 'selectedPostalCodeRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        // );
        // $this->marketOptions_DisplayOptions[] = array(
        //     'label' => 'Territory', 'data' => 'F17', 'indexName' => 'FS[F17]', 'selectedItemLeft' => 'selectedPostalCodeLeft',
        //     'selectedItemRight' => 'selectedPostalCodeRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        // );

        /* ============================= PAGE SETTINGS ========================================= */

        /**
         *  Executive Summary Page
         */
        $this->pageArray["EXE_SUMMARY_PAGE"]["DH"] = "F1-F3-F4-F5-F2#F19-F6-F7-F8-F9-F10-F11-F12-F13-F14-F15-F16-F26-F30-F25";
        $this->pageArray["EXE_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "banner_alt_grp1", "DATA_TWO" => "agg1");
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Banner";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Banner Performance";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Brand";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Brand Performance";
        
        /**
         *  Category Summary Page
         */
        $this->pageArray["CAT_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "banner_alt_grp1", "DATA_TWO" => "agg2");
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Banner";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Banner Performance";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Category";
        $this->pageArray["CAT_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Category Performance";


        /**
         * Product Banner Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        // $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("BRAND" => "F3");
        // $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("SKU" => "F28");
        // $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("DIVISION" => "F12");
        // $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("BANNER" => "F11");

        $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BRAND" => "F3");
        $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F28");
        $this->pageArray["PRODUCT_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("BANNER" => "F11");
        
        /**
         * Product Province Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["PRODUCT_PROVINCE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BRAND" => "F3");
        $this->pageArray["PRODUCT_PROVINCE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F2");
        $this->pageArray["PRODUCT_PROVINCE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("PROVINCE" => "F15");

        /**
         * Product Store Performance Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["PRODUCT_STORE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BRAND" => "F3");
        $this->pageArray["PRODUCT_STORE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F28");
        $this->pageArray["PRODUCT_STORE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");

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
         * Product Treemap Page
         */
        $this->pageArray["PRODUCT_TREEMAP"]["MAPS"] = array(
            "SKU" => "F2", 
            "BRAND" => "F3", 
            "CATEGORY" => "F4", 
            "OWNLABEL" => "F5", 
            "LCL_SUPER" => "F6", 
            "LCL_DEPT" => "F7", 
            "LCL_CAT_GRP" => "F8", 
            "LCL_CAT" => "F9", 
            "LCL_SUBCAT" => "F10"
        );

        $this->pageArray["PRODUCT_VIEW_PAGE"]["ACCOUNT"] = "F2";
        $this->pageArray["PRODUCT_VIEW_PAGE"]["DISTRIBUTION"] = "F13";

        $this->pageArray["DETAIL_DRIVER_ANALYSIS"]["STORE_FIELD"] = "F22";

        $this->pageArray["PROVINCE_SALES_BY_STORE"]["PROVINCE"] = "F11";
        $this->pageArray["PROVINCE_SALES_BY_STORE"]["STORE_FIELD"] = "F15";

        /**
         * Two Store Comparison Page
         */
        $this->pageArray["TWO_STORE_COMPARISON"]["ACCOUNT_FIELD"] = "F2";
        $this->pageArray["TWO_STORE_COMPARISON"]["STORE_FIELD"] = "F22";
        

        /**
         * Grouth Decline Driver Page
         */
        $this->pageArray["GROWTH_DECLINE_DRIVER"]["SKU_FIELD"] = "F2";
        $this->pageArray["GROWTH_DECLINE_DRIVER"]["STORE_FIELD"] = "F13";
        
        /**
         * Price Distribution Tracker Page
         */
        $this->pageArray["PRICE_DISTRIBUTION_TRACKER"]["ACCOUNT"] = "F2";
        $this->pageArray["PRICE_DISTRIBUTION_TRACKER"]["STORE_FIELD"] = "F22";
        $this->pageArray["PRICE_DISTRIBUTION_TRACKER"]["TOP_GRID_COLUMN_NAME"] = array("account" => "WEEK", "sales" => "SALES", "avg" => "AVE PRICE", "store" => "STORES SELLING");
        $this->pageArray["PRICE_DISTRIBUTION_TRACKER"]["BOTTOM_GRID_COLUMN_NAME"] = array("ID" => "SKU ID", "name" => "SKU", "sales" => "SALES", "units" => "UNITS", "avg" => "AVE PRICE (P)", "store" => false);
        
        /**
         * Distribution Quality Page
         */
        $this->pageArray["DISTRIBUTION_QUALITY"]["ACCOUNT"] = "F2";
        $this->pageArray["DISTRIBUTION_QUALITY"]["DEFAULT_TIME_FRAME"] = "12";
        $this->pageArray["DISTRIBUTION_QUALITY"]["GRID_COLUMN_NAME"] = array(
            "ID"=>array("title"=>"SKU ID","type"=>"string","footerTemplate"=>''),
            "ACCOUNT"=>array("title"=>"SKU","type"=>"string","footerTemplate"=>'<div style="text-align: left">Total</div>'),
            "salesLw"=>array("title"=>"SALES","type"=>"number","aggregate"=>"sum","format"=> "{0:n0}","footerTemplate"=>"#= kendo.toString(sum, 'n0') #"),
            "noOfStores"=>array("title"=>"DISTRIBUTION","type"=>"number", "format"=> "{0:n0}", "footerTemplate"=>"#= window.getTotalStore() #"),
            "aveSales"=>array("title"=>"SALES/STORES/WEEKS","type"=>"number","format"=> "{0:n2}","aggregate"=>"sum","footerTemplate"=>"#= window.calculateAverageUnits(data) #")
        );

    
         /**
         * Product KBI Page
         */
        $this->pageArray["PRODUCT_KBI"]["ACCOUNT"] = "F23";
        $this->pageArray["PRODUCT_KBI"]["COUNT_FIELD"] = "F22";
        $this->pageArray["PRODUCT_KBI"]["DOWNLOAD_LINK_PARAMETER"] = array("HEADER" => "STORES", "ACCOUNTS" => "F13-F11-F15", "SKU_FIELD" => "F23", "ACCOUNT" => "F23");
    
        /**
         * LCL Category Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["LCL_CATEGORY_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("LCL CAT" => "F9");
        $this->pageArray["LCL_CATEGORY_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("LCL SUB CAT" => "F10");
        $this->pageArray["LCL_CATEGORY_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
    
        /**
         * LCL Category Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CATEGORY_SKU_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("CATEGORY" => "F4");
        $this->pageArray["CATEGORY_SKU_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F28");
        $this->pageArray["CATEGORY_SKU_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("BANNER" => "F11");

        /**
         * LCL Category Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CATEGORY_SKU_STORE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("CATEGORY" => "F4");
        $this->pageArray["CATEGORY_SKU_STORE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F28");
        $this->pageArray["CATEGORY_SKU_STORE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");
    
        /**
         * Banner product Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["BANNER_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("DIVISION" => "F12");
        $this->pageArray["BANNER_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BANNER" => "F11");
        $this->pageArray["BANNER_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("CATEGORY" => "F3");
        $this->pageArray["BANNER_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
    
        /**
         * Banner Contribution Analysis page
         * ACCOUNT as field name which is refered from dataArray
         */
        $this->pageArray["BANNER_CONTRIBUTION_ANALYSIS"]["ACCOUNT"] = "F11";
    
        
        /**
         * Banner Efficiency page
         */
        $this->pageArray["BANNER_EFFICIENCY"]["ACCOUNT"] = "F11";
        $this->pageArray["BANNER_EFFICIENCY"]["COUNT_ACCOUNT"] = "F13";
        $this->pageArray["BANNER_EFFICIENCY"]["BAR_ACCOUNT_TITLE"] = "Banners";
    
        
        /**
         * STORE PRODUCT Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["STORE_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("STORE" => "F13");
        $this->pageArray["STORE_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND" => "F3");
        $this->pageArray["STORE_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");

        
        /**
         * Store Contribution Analysis page
         * ACCOUNT as field name which is refered from dataArray
         */
        $this->pageArray["STORE_CONTRIBUTION_ANALYSIS"]["ACCOUNT"] = "F24";
        
        
        /**
         * Store Efficiency page
         */
        $this->pageArray["STORE_EFFICIENCY"]["ACCOUNT"] = "F13";
        $this->pageArray["STORE_EFFICIENCY"]["COUNT_ACCOUNT"] = "F2";
        $this->pageArray["STORE_EFFICIENCY"]["BAR_ACCOUNT_TITLE"] = "Stores";
        

        /**
         * Store Treemap Page
         */
        $this->pageArray["STORE_TREEMAP"]["MAPS"] = array(
            "DIVISION" => "F12", 
            "BANNER" => "F11", 
            "STORE" => "F13", 
            "REGION" => "F14", 
            "PROVINCE" => "F15", 
            "CITY"=> "F26",
            "POSTAL CODE"=> "F27",
            "PROVE SPLITE"=> "F30",
            "MUNICIPALITY"=> "F25",
            "TERRITORY"=> "F17"
        );

        /**
         * STORE Region Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["STORE_REGION_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("REGION" => "F14");
        $this->pageArray["STORE_REGION_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("BANNER" => "F11");
        $this->pageArray["STORE_REGION_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND" => "F3");
        $this->pageArray["STORE_REGION_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F28");

        
        /**
         * Store vs Area Report Page
         */
        $this->pageArray["STORE_VS_AREA_REPORT"]["AREA_FIELD"] = "F11";
        $this->pageArray["STORE_VS_AREA_REPORT"]["ACCOUNT_FIELD"] = "F22";
        $this->pageArray["STORE_VS_AREA_REPORT"]["ACCOUNT_FILTER_FIELD"] = "sno";
        $this->pageArray["STORE_VS_AREA_REPORT"]["ACCOUNT_FILTER_TABLE"] = "store";
        $this->pageArray["STORE_VS_AREA_REPORT"]["PRODUCT_FIELD"] = "F1";
        
		/**
         * Distribution Gaps Store Details Page
         */
        $this->pageArray["DISTRIBUTION_GAPS_STORE_DETAILS"]["PRIVATE_LABEL"] = true;
		
		/**
         * Distribution Gap Page
         * Custom column name with data type of grid
         */
        $this->pageArray["DISTRIBUTION_GAP"]["GRID_COLUMN_CONFIGURATION"] = array(
            "plano" => array("title" => "PLANO", "type" => "string"),
            "pin" => array("title" => "ARTICLE", "type" => "number"),
            "pname" => array("title" => "SKU NAME", "type" => "string"),
            "upc" => array("title" => "UPC", "type" => "string"),
            "maxDist" => array("title" => "MAX DIST", "type" => "number"),
            "sellingStores" => array("title" => "SELLING STORES", "type" => "number"),
            "pctSelling" => array("title" => "% SELLING", "type" => "number"),
        );
            
        /**
         * Distribution Gap Finder Page
         */
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["totalStoresAccounts"] = "F15-F11";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["bannerAccount"] = "F11";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["provinceAccount"] = "F15";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["pinAccount"] = "F1";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["sellingStoresAccounts"] = "F1-F11-F15";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["gridAccounts"] = "F1-F11-F29-F12-F19-F15";
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["TOP_GRID_COLUMN_NAME"] = array(
            "PIN_ROLLUP" => "SKU ID",
            "UPC" => "UPC",
            "PIN_PNAME" => "SKU NAME",
            "SALES" => "SALES($)",
            "BANNER" => "BANNER",
            "PROVINCE" => "PROVINCE",   
            "TTL_STORES" => "TOTAL STORES",
            "SLNG_STORES" => "STORES SELLING",
            "DIST_PCT" => "DIST %",
            "GAP_VALUE" => "GAP VALUE $"
        );
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["SELLING_GRID_COLUMN_NAME"] = array("SNO", "SNAME", "SALES");
        $this->pageArray["DISTRIBUTION_GAP_FINDER"]["NOT_SELLING_GRID_COLUMN_NAME"] = array("SNO", "SNAME", "ACTIVE", "SALES_LW", "DATE_LAST_SOLD");
        
        /**
         * Distribution Gap Page
         * Custom column name with data type of grid
         */
        $this->pageArray["DISTRIBUTION_GAP"]["GRID_COLUMN_CONFIGURATION"] = array(
            "plano"=>array("title"=>"PLANO","type"=>"string"),
            "pin"=>array("title"=>"ARTICLE NO.","type"=>"number"),
            "pname"=>array("title"=>"SKU NAME","type"=>"string"),
            "format"=>array("title"=>"FORMAT","type"=>"string"),
            "category"=>array("title"=>"CATEGORY","type"=>"string"),
            "upc"=>array("title"=>"UPC","type"=>"number"),
            "maxDist"=>array("title"=>"MAX DIST","type"=>"number"),
            "sellingStores"=>array("title"=>"SELLING STORES","type"=>"number"),
            "pctSelling"=>array("title"=>"% SELLING","type"=>"number"),
        );
        $this->pageArray["DISTRIBUTION_GAP"]["SELLING_GRID_CONFIG"] = array(
            "SNO"=>array("title"=>"SNO","type"=>"number"),
            "SNAME"=>array("title"=>"STORE NAME","type"=>"string"),
        );
        $this->pageArray["DISTRIBUTION_GAP"]["NOT_SELLING_GRID_CONFIG"] = array(
            "SNO"=>array("title"=>"STORE NO","type"=>"number"),
            "SNAME"=>array("title"=>"STORE NAME","type"=>"string"),
            "ACTIVE"=>array("title"=>"ACTIVE","type"=>"string"),
            "SALES_LW"=>array("title"=>"TOTAL $ LW","type"=>"string"),
        );
        //$this->pageArray["DISTRIBUTION_GAP"]["IS_BOTTOM_GRID"] = true;
        
        
        /**
         * Consolidated Distribution Page
         */
        //$this->pageArray["CONSOLIDATED_DISTRIBUTION"]["PRIVATE_LABEL"] = true;
		$this->pageArray["CONSOLIDATED_DISTRIBUTION"]["totalStoresAccounts"] = "F11";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["pinAccount"] = "F31";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["regionAccount"] = "F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["sellingStoresAccounts"] = "F31-F11-F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["liveStoresAccounts"] = "F11-F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoStoresAccounts"] = "F11-F15";
		$this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoSellingAccounts"] = "F11-F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["gridAccounts"] = "F31-F11-F19-F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["TOP_GRID_COLUMN_NAME"] = array(
            "PIN_A" => "SKU ID",
            "UPC" => "UPC",
            "PNAME_A" => "SKU NAME",
            "SALES" => "SALES($)",
            "BANNER" => "BANNER",
            "PROVINCE" => "PROVINCE",
            // "REGION" => "REGION",
			"SLNG_STORES" => "STORES SELLING",
            "LIVE_STORES" => "LIVE STORES",
            "PLANO_STORES" => "PLANO STORES",            
            "WCROS_SELLING" => "WCROS (SELLING)",
            "DIST_PCT_PLANO" => "DIST% (PLANO)",
            "DIST_PCT_LIVE" => "DIST% (LIVE)",
            "PLANO_SELLING" => "PLANO SELLING",
            "PLANO_N_SELLING" => "PLANO N/SELLING"
        );
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["SELLING_GRID_COLUMN_NAME"] = array("SNO", "SNAME", "SALES", "PLANO_D");
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["NOT_SELLING_GRID_COLUMN_NAME"] = array("SNO", "SNAME", "COMPANY_SALES", "PLANO_D", "ACTIVE", "DATE_LAST_SOLD");
		
        /**
         * Territory / Store Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        
        $this->pageArray["TERRITORY_STORE_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("TERRITORY" => "F17");
        $this->pageArray["TERRITORY_STORE_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BANNER" => "F11");
        $this->pageArray["TERRITORY_STORE_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("STORE" => "F13");
        
        /**
         * Territory / Product Performance page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        
        $this->pageArray["TERRITORY_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("TERRITORY" => "F17");
        $this->pageArray["TERRITORY_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BRAND" => "F3");
        $this->pageArray["TERRITORY_PRODUCT_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
		
		/**
         * PCATEGORY BANNER PERFORMANCE page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["CATEGORY_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridGroup"] = array("CATEGORY" => "F5");
        $this->pageArray["CATEGORY_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridCategory"] = array("SUB CATEGORY" => "F4");
        $this->pageArray["CATEGORY_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridBrand"] = array("BANNER" => "F11");
        $this->pageArray["CATEGORY_BANNER_PERFORMANCE"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F28");
        $this->pageArray["CATEGORY_BANNER_PERFORMANCE"]["PRIVATE_LABEL"] = true;
        
        /**
         * Territory Contribution Analysis page
         * ACCOUNT as field name which is refered from dataArray
         */
        $this->pageArray["TERRITORY_CONTRIBUTION_ANALYSIS"]["PRIVATE_LABEL"] = true;
        $this->pageArray["TERRITORY_CONTRIBUTION_ANALYSIS"]["ACCOUNT"] = "F17";
        
        
        /**
         * Territory Treemap Page
         */
        $this->pageArray["TERRITORY_TREEMAP"]["MAPS"] = array("TERRITORY" => "F17");
        
        
        /**
         * Territory Called Page
         */
        $this->pageArray["CALLED_NOT_CALLED"]["ACCOUNTS"] = "F22-F14-F26-F17";
        
        
        /**
         * Region Over Under Seller Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridCategory"] = array("CATEGORY" => "F3");
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridBrand"] = array("PROVIENCE" => "F15");
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["GRID_FIELD"]["gridSKU"] = array("SKU" => "F2");
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["SKU_FIELD"] = "F2";
        $this->pageArray["REGIONAL_OVER_UNDER_SELLERS"]["STORE_FIELD"] = "F13";
        
        
        /**
         * Product Province Map Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["PRODUCT_PROVINCE_MAP"]["GRID_FIELD"]["gridCategory"] = array("CATEGORY" => "F3");
        $this->pageArray["PRODUCT_PROVINCE_MAP"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F2");
        $this->pageArray["PRODUCT_PROVINCE_MAP"]["GRID_FIELD"]["gridSKU"] = array("PROVIENCE" => "F15");
        $this->pageArray["PRODUCT_PROVINCE_MAP"]["mapAccount"] = "F15";


        /**
         * Product Ontario Map Page
         * The value of this array as an associative array that has key as first column name of bottom grid and value as field name
         * The value of this array should be only one key and one value as a column name and field name
         */
        $this->pageArray["PRODUCT_ONTARIO_MAP"]["GRID_FIELD"]["gridCategory"] = array("CATEGORY" => "F4");
        $this->pageArray["PRODUCT_ONTARIO_MAP"]["GRID_FIELD"]["gridBrand"] = array("SKU" => "F2");
        $this->pageArray["PRODUCT_ONTARIO_MAP"]["GRID_FIELD"]["gridSKU"] = array("MUNICIPALITY" => "F25");
        $this->pageArray["PRODUCT_ONTARIO_MAP"]["mapAccount"] = "F25";
        
        /**
         * Active Store
         */
        $this->pageArray["ACTIVE_STORE"]["GRID_SETUP"] = array(
            "sno" => "STORE NUMBER",
            "sname" => "STORE",
            "banner" => "BANNER",
            "address" => "ADDRESS",
            "city" => "CITY",
            "pv" => "PROVINCE",
            "pc" => "POSTCODE"
        );

        $this->pageArray["STORE_REPORT_DOWNLOAD"]["ACCOUNT"] = array('F22');

        $this->pageArray["RANGED_ITEMS_BY_STORE"]["STORE_FIELD"] = "F22";
        $this->pageArray["RANGED_ITEMS_BY_STORE"]["SKU_FIELD"] = "F23";


	}

    public function dataArray()
    {
        //PRODUCT HELPERS
        $this->dataArray['F1']['ID'] = $this->skutable .'.PIN_ROLLUP';
        $this->dataArray['F1']['ID_ALIASE'] = 'PIN_ROLLUP';
        $this->dataArray['F1']['NAME'] = $this->skutable .'.PNAME_ROLLUP';
        $this->dataArray['F1']['NAME_ALIASE'] = 'PIN_PNAME';
        $this->dataArray['F1']['tablename'] = $this->productHelperTables;
        $this->dataArray['F1']['link'] = $this->productHelperLink;
        $this->dataArray['F1']['use_alias_as_tag'] = true;
        $this->dataArray['F1']['include_id_in_label'] = true;
        $this->dataArray['F1']['TYPE']                = "P";

        $this->dataArray['F2']['ID'] = $this->skutable .'.PIN_ROLLUP';
        $this->dataArray['F2']['ID_ALIASE'] = 'PIN_ROLLUP';
        $this->dataArray['F2']['NAME'] = $this->skutable .'.PNAME_ROLLUP';
        $this->dataArray['F2']['NAME_ALIASE'] = 'PNAME_ROLLUP';
        $this->dataArray['F2']['tablename'] = $this->productHelperTables;
        $this->dataArray['F2']['link'] = $this->productHelperLink;
        $this->dataArray['F2']['TYPE']                = "P";

        $this->dataArray['F3']['NAME'] = $this->skutable .'.agg1';
        $this->dataArray['F3']['NAME_ALIASE'] = 'BRAND';
        $this->dataArray['F3']['tablename'] = $this->productHelperTables;
        $this->dataArray['F3']['link'] = $this->productHelperLink;
        $this->dataArray['F3']['use_alias_as_tag'] = true;
        $this->dataArray['F3']['TYPE']                = "P";

        $this->dataArray['F4']['NAME'] = $this->skutable .'.agg2';
        $this->dataArray['F4']['NAME_ALIASE'] = 'LCL_CATEGORY';
        $this->dataArray['F4']['tablename'] = $this->productHelperTables;
        $this->dataArray['F4']['link'] = $this->productHelperLink;
        $this->dataArray['F4']['use_alias_as_tag'] = true;
        $this->dataArray['F4']['TYPE']                = "P";

        $this->dataArray['F5']['NAME'] = $this->skutable .'.agg4';
        $this->dataArray['F5']['NAME_ALIASE'] = 'OWN_LABEL';
        $this->dataArray['F5']['tablename'] = $this->productHelperTables;
        $this->dataArray['F5']['link'] = $this->productHelperLink;
        $this->dataArray['F5']['use_alias_as_tag'] = true;
        $this->dataArray['F5']['TYPE']                = "P";

        $this->dataArray['F6']['NAME'] = $this->skutable .'.MERCH_SUPER';
        $this->dataArray['F6']['NAME_ALIASE'] = 'LCL_SUPER';
        $this->dataArray['F6']['tablename'] = $this->productHelperTables;
        $this->dataArray['F6']['link'] = $this->productHelperLink;
        $this->dataArray['F6']['use_alias_as_tag'] = true;
        $this->dataArray['F6']['TYPE']                = "P";

        $this->dataArray['F7']['NAME'] = $this->skutable .'.MERCH_DEPT';
        $this->dataArray['F7']['NAME_ALIASE'] = 'LCL_DEPT';
        $this->dataArray['F7']['tablename'] = $this->productHelperTables;
        $this->dataArray['F7']['link'] = $this->productHelperLink;
        $this->dataArray['F7']['use_alias_as_tag'] = true;
        $this->dataArray['F7']['TYPE']                = "P";

        $this->dataArray['F8']['NAME'] = $this->skutable .'.MERCH_CAT_GRP';
        $this->dataArray['F8']['NAME_ALIASE'] = 'LCL_CAT_GRP';
        $this->dataArray['F8']['tablename'] = $this->productHelperTables; //-- special case
        $this->dataArray['F8']['link'] = $this->productHelperLink; //-- special case
        $this->dataArray['F8']['use_alias_as_tag'] = true;
        $this->dataArray['F8']['TYPE']                = "P";

        $this->dataArray['F9']['NAME'] = $this->skutable .'.MERCH_CAT';
        $this->dataArray['F9']['NAME_ALIASE'] = 'LCL_CAT';
        $this->dataArray['F9']['tablename'] = $this->productHelperTables;
        $this->dataArray['F9']['link'] = $this->productHelperLink;
        $this->dataArray['F9']['use_alias_as_tag'] = true;
        $this->dataArray['F9']['TYPE'] = "P";

        $this->dataArray['F10']['NAME'] = $this->skutable .'.MERCH_SUBCAT';
        $this->dataArray['F10']['NAME_ALIASE'] = 'LCL_SUB_CAT';
        $this->dataArray['F10']['tablename'] = $this->productHelperTables;
        $this->dataArray['F10']['link'] = $this->productHelperLink;
        $this->dataArray['F10']['use_alias_as_tag'] = true;
        $this->dataArray['F10']['TYPE']                = "P";

        $this->dataArray['F11']['NAME'] = $this->storetable .'.banner_alt_grp1';
        $this->dataArray['F11']['NAME_ALIASE'] = 'BANNER';
        $this->dataArray['F11']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F11']['link'] = $this->geoHelperLink;
        $this->dataArray['F11']['TYPE']               = "M";

        $this->dataArray['F12']['NAME'] = $this->storetable .'.banner_alt_grp2';
        $this->dataArray['F12']['NAME_ALIASE'] = 'DIVISION';
        $this->dataArray['F12']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F12']['link'] = $this->geoHelperLink;
        $this->dataArray['F12']['TYPE']               = "M";

        $this->dataArray['F13']['ID'] = $this->storetable .'.SNO_ROLLUP';
        $this->dataArray['F13']['ID_ALIASE'] = 'SNO ROLLUP';
        $this->dataArray['F13']['NAME'] = $this->storetable .'.SNAME_ROLLUP';
        $this->dataArray['F13']['NAME_ALIASE'] = 'SNAME ROLLUP';
        $this->dataArray['F13']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F13']['link'] = $this->geoHelperLink;
        $this->dataArray['F13']['TYPE']               = "M";
        $this->dataArray['F13']['include_id_in_label'] = true;

        $this->dataArray['F14']['NAME'] = $this->storetable .'.REGION';
        $this->dataArray['F14']['NAME_ALIASE'] = 'REGION_1';
        $this->dataArray['F14']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F14']['link'] = $this->geoHelperLink;
        $this->dataArray['F14']['TYPE']               = "M";
        $this->dataArray['F14']['use_alias_as_tag'] = true;

        $this->dataArray['F15']['NAME'] = $this->storetable .'.STATE';
        $this->dataArray['F15']['NAME_ALIASE'] = 'PROVINCE';
        $this->dataArray['F15']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F15']['link'] = $this->geoHelperLink;
        $this->dataArray['F15']['TYPE']               = "M";

        $this->dataArray['F16']['NAME'] = 'SUBSTR('.$this->storetable .'.POSTCODE,1,1)';
        $this->dataArray['F16']['NAME_ALIASE'] = 'POSTCODE';
        $this->dataArray['F16']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F16']['link'] = $this->geoHelperLink;
        $this->dataArray['F16']['TYPE']               = "M";
		

        /**
         * Important
         * F17 & F18 is based on client so It is declared in client projectsettings 
         * NOTE: Do not use F17 & F18 to declare any field other wise It will be overwritten by client projectsettings
         */

		
        $this->dataArray['F19']['NAME'] = $this->skutable .'.UPC';
        $this->dataArray['F19']['NAME_ALIASE'] = 'UPC';

        $this->dataArray['F20']['NAME'] = $this->skutable .'.NG';
        $this->dataArray['F20']['NAME_ALIASE'] = 'NG';

        $this->dataArray['F21']['NAME'] = $this->skutable .'.agg_int';
        $this->dataArray['F21']['NAME_ALIASE'] = 'CUSTOMER SKU CODE';

        $this->dataArray['F22']['ID'] = $this->storetable . '.SNO';
        $this->dataArray['F22']['ID_ALIASE'] = 'SNO';
        $this->dataArray['F22']['NAME'] = $this->storetable .'.SNAME';
        $this->dataArray['F22']['NAME_ALIASE'] = 'SNAME';
        $this->dataArray['F22']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F22']['link'] = $this->geoHelperLink;
        $this->dataArray['F22']['TYPE']               = "M";

        $this->dataArray['F23']['ID'] = $this->skutable . '.PIN';
        $this->dataArray['F23']['ID_ALIASE'] = 'PIN';
        $this->dataArray['F23']['NAME'] = $this->skutable .'.PNAME_ROLLUP';
        $this->dataArray['F23']['NAME_ALIASE'] = 'PNAME ROLLUP';
        $this->dataArray['F23']['tablename'] = $this->productHelperTables; //-- special case
        $this->dataArray['F23']['link'] = $this->productHelperLink; //-- special case
        $this->dataArray['F23']['TYPE']               = "P";

        $this->dataArray['F24']['ID'] = $this->storetable . '.SNO';
        $this->dataArray['F24']['ID_ALIASE'] = 'SNO';
        $this->dataArray['F24']['NAME'] = $this->storetable .'.SNAME_ROLLUP';
        $this->dataArray['F24']['NAME_ALIASE'] = 'SNAME ROLLUP';
        $this->dataArray['F24']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F24']['link'] = $this->geoHelperLink;
        $this->dataArray['F24']['TYPE']               = "M";

        $this->dataArray['F25']['NAME'] = $this->storetable .".customagg2";
        $this->dataArray['F25']['NAME_ALIASE'] = 'MUNICIPALITY';
        $this->dataArray['F25']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F25']['link'] = $this->geoHelperLink;
        $this->dataArray['F25']['TYPE']               = "M";

        $this->dataArray['F26']['NAME'] = $this->storetable .'.CITY';
        $this->dataArray['F26']['NAME_ALIASE'] = 'CITY';
        $this->dataArray['F26']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F26']['link'] = $this->geoHelperLink;
        $this->dataArray['F26']['TYPE']               = "M";

        $this->dataArray['F27']['NAME'] = $this->storetable .'.POSTCODE';
        $this->dataArray['F27']['NAME_ALIASE'] = 'POSTCODE';

        $this->dataArray['F28']['ID'] = "PIN_ROLLUP";
        $this->dataArray['F28']['NAME'] = 'PNAME_ROLLUP-UPC-NG-agg_int';

        $this->dataArray['F29']['NAME'] = $this->storetable .".banner_alt";
        $this->dataArray['F29']['NAME_ALIASE'] = 'SUB_BANNER';

        $this->dataArray['F30']['NAME'] = $this->storetable .".customagg1";
        $this->dataArray['F30']['NAME_ALIASE'] = 'PROV_SPLIT';
        $this->dataArray['F30']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F30']['link'] = $this->geoHelperLink;
        $this->dataArray['F30']['TYPE']               = "M";

        $this->dataArray['F31']['ID'] = $this->skutable .'.PIN';
        $this->dataArray['F31']['ID_ALIASE'] = 'PIN_A';
        $this->dataArray['F31']['NAME'] = $this->skutable .'.PNAME';
        $this->dataArray['F31']['NAME_ALIASE'] = 'PNAME_A';
        $this->dataArray['F31']['tablename'] = $this->productHelperTables;
        $this->dataArray['F31']['link'] = $this->productHelperLink;
        $this->dataArray['F31']['TYPE']                = "P";

        $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
        $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

        $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
        $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR'; 
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

    public function hasTerritory($aId,$projectId)
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(isset($queryVars->projectConfiguration['has_territory']) && $queryVars->projectConfiguration['has_territory'] == 1 )
        {
            if(isset($queryVars->projectConfiguration['has_territory_level']) && $queryVars->projectConfiguration['has_territory_level'] > 0)
                $this->territoryLevel = $queryVars->projectConfiguration['has_territory_level'];
            else
                $this->territoryLevel = "1";
            return true;
        }
        else
            return false;
    }

    public function hasRedisCaching()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(isset($queryVars->projectConfiguration['has_redis_caching']) && $queryVars->projectConfiguration['has_redis_caching'] == 1 )
            return true;
        else
            return false;
    }

    public function hasMeasureFilter()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        if(isset($queryVars->projectConfiguration['has_measure_filter']) && $queryVars->projectConfiguration['has_measure_filter'] == 1 )
            return true;
        else
            return false;
    }

    public function hasGlobalFilter()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();
        $globalFilterQueryString = '';
        $this->databaseName = $_REQUEST['connectedDatabaseName'];
        if(isset($queryVars->projectConfiguration['has_global_filter']) && $queryVars->projectConfiguration['has_global_filter'] == 1 ){
            if(isset($queryVars->projectConfiguration['global_filter_field']) && !empty($queryVars->projectConfiguration['global_filter_field']) ){
                $global_filter_field = $queryVars->projectConfiguration['global_filter_field'];
                if(isset($queryVars->projectConfiguration['global_filter_order_by_field']) && $queryVars->projectConfiguration['global_filter_order_by_field'] != '')
                    $global_filter_order_by_field = $queryVars->projectConfiguration['global_filter_order_by_field'];

                $this->defaultGlobalFilterVal = (isset($queryVars->projectConfiguration['default_global_filter'])) ? $queryVars->projectConfiguration['default_global_filter'] : "";

                $configurationCheck = new config\ConfigurationCheck($this, projectsettings\settingsGateway::getInstance());
                if($global_filter_order_by_field != '')
                    $configurationCheck->buildDataArray(array($global_filter_field, $global_filter_order_by_field), true);
                else
                    $configurationCheck->buildDataArray(array($global_filter_field), true);
                    
                $dbColumnsArray = $configurationCheck->dbColumnsArray;

                $globalFilterFieldPart = explode("#", $global_filter_field);
                $globalFilterField = strtoupper($dbColumnsArray[$globalFilterFieldPart[0]]);
                $globalFilterField = (count($globalFilterFieldPart) > 1) ? strtoupper($globalFilterField . "_" . $dbColumnsArray[$globalFilterFieldPart[1]]) : $globalFilterField;

                $this->globalFilterFieldDataArrayKey = $globalFilterField;
                
                $this->globalFilterOrderByFieldDataArrayKey = strtoupper($dbColumnsArray[$global_filter_order_by_field]);
                
                if (isset($_REQUEST["FSG"]) && $_REQUEST["FSG"] != '') {
                    $filterType = (isset($this->dataArray[$globalFilterField]['TYPE'])) ? $this->dataArray[$globalFilterField]['TYPE'] : "";
                    $globalFilterQueryString = filters\productAndMarketFilter::include_product_and_market_filters($queryVars, $this, $filterType, 'FSG');
                }

                if (isset($this->dataArray[$globalFilterField]['tablename']) && !empty($this->dataArray[$globalFilterField]['tablename']) && 
                    !empty($globalFilterQueryString)) {
                    $this->tableArray[$this->dataArray[$globalFilterField]['tablename']]['link'] .= $globalFilterQueryString;
                }
                
            }
            return true;
        }
        else
            return false;
    }

    public function getMeasureSettings()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_measure_configuration');
        
        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $query = "SELECT m.*, f.formula, f.alias as formulaAlias FROM ".
                $this->measuresTable." m ,".$this->formulaTable." f WHERE f.formulaID = m.formulaID AND m.accountID=".$this->aid.
                " AND m.projectID = ".$this->projectID." AND m.status = 1 AND measureFields is not null ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(!empty($result) && is_array($result)){
                $searchFields = array_search("", array_column($result, 'measureFields'));
                if($searchFields === false ){
                    $result = utils\SortUtility::sort2DArray($result, 'measureOrder', utils\SortTypes::$SORT_ASCENDING);
                    $searchKey = array_search(1, array_column($result, 'defaultLoad'));
                    $defaultLoad = ($searchKey !== false ) ? $result[$searchKey] : array();
                    $this->measureArray = array();
                    $this->pageArray["MEASURE_SELECTION_LIST"] = array();
                    foreach ($result as $index => $measureData) {
                        $measureSelectionList = array();
                        if($measureData['showAsFilter'] == 1){
                            $measureSelectionList['measureID']      = $measureData['measureID'];
                            $measureSelectionList['jsonKey']        = $measureData['alias'];
                            $measureSelectionList['dataDecimalPlaces'] = $measureData['data_decimal_places'];
                            $measureSelectionList['measureName']    = $measureData['measureName'];
                            
                            if($this->projectTypeID == 6) // DDB
                                $measureSelectionList['selected']       = ($measureData['measureSelected'] == 1)  ? true : false;
                            else
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

                    $this->performanceTabMappings = array();
                    
                    if($this->projectTypeID != 6) // DDB
                    {
                        if(isset($queryVars->projectConfiguration['performance_tab_mapping']) && !empty($queryVars->projectConfiguration['performance_tab_mapping']) ) {
                            $settings = explode('|', $queryVars->projectConfiguration['performance_tab_mapping']);
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
                }
                else{
                    $response = array("configuration" => array("status" => "fail", "messages" => array('Measure Fields configuration not found.')));
                    echo json_encode($response);
                    exit();    
                }

            } else {
                $response = array("configuration" => array("status" => "fail", "messages" => array('Measure configuration not found.')));
                echo json_encode($response);
                exit();
            }

            $measureRedisArray = array(
                'MEASURE_SELECTION_LIST' => $this->pageArray["MEASURE_SELECTION_LIST"],
                'measureArray'           => $this->measureArray,
                'performanceTabMappings' => $this->performanceTabMappings
            );

            $redisCache->setDataForStaticHash($measureRedisArray);
        } else {
            $this->pageArray["MEASURE_SELECTION_LIST"] = $redisOutput['MEASURE_SELECTION_LIST'];
            $this->measureArray = $redisOutput['measureArray'];
            $this->performanceTabMappings = $redisOutput['performanceTabMappings'];
        }
    }

    public function hasHiddenSku()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_has_hidden_sku');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $query = "SELECT skuID FROM ".$this->skutable." WHERE clientID='".$this->clientID."'".
                    " AND GID IN (".$this->GID.") AND hide=1 ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($result) && !empty($result))
            {
                $result = array_column($result, "skuID");
                $this->hiddenSkusQueryString = "'".implode($result, "','")."'";
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

    public function fetchGroupDetail()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_fgroup_detail');

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

    public function getAllPageConfiguration(){
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
    
	public function getClientProjectName() {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('base_project_name');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $ultraUtility   = lib\UltraUtility::getInstance();
            $result = $ultraUtility->getClientProjectByID($this->aid,$this->projectID);
            $redisCache->setDataForStaticHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result)) {
            $this->pageArray["PROJECT_NAME"] = $result[0]['PNAME'];
            $this->pageArray["COMPANY_NAME"] = $result[0]['CNAME'];
        } else {
            $this->pageArray["PROJECT_NAME"] = "";
            $this->pageArray["COMPANY_NAME"] = "";
        }
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
            array("id" => "WEEK", "value" => "Week", "selected" => false)
        );

        $this->dataArray['MYDATE']['NAME'] = $this->maintable . "." . $this->dateperiod;
        $this->dataArray['MYDATE']['NAME_ALIASE'] = 'MYDATE';
        $this->dataArray['MYDATE']['TYPE'] = "T";
        
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