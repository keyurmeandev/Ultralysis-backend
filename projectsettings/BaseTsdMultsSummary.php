<?php

namespace projectsettings;

use db;
use projectsettings;
use filters;
use lib;
use projectstructure;

class BaseTsdMultsSummary  {

    //TABLE NAMES
    public $maintable;
    public $multstable;
    public $tesco_depot_daily;
    public $skutable;
    public $depottable;
    public $storetable;
    public $rangedtable;
    public $clustertable;
    public $clusterDetailsTable;
	public $territoryTable;
    public $configTable;
    public $periodtable;
    public $clientconfigtable;
    public $clusterID;
    public $GID;
    public $DatePeriod;
    public $tablename;
    public $link;
	public $skuHelperTables;
    public $skuHelperLink;
    public $storeHelperTables;
    public $storeHelperLink;
    public $projectHelperLink;
    public $menuProjectHelperLink;
    public $productHelperTables;
    public $productHelperLink;
    public $multstablename;
    public $multslink;
    public $latestDateHelperTables;
    public $latestDateHelperLink;
    public $aid;
    public $projectType;
    public $projectID;
    public $PROJECTDEV;
    public $ProjectValue;
    public $ProjectVolume;
    public $clusterList;
	public $pageArray;
    public $yearperiod;
    public $weekperiod;

    public function __construct($accountID, $projectID) {
                
        $this->aid          = $accountID;
		$this->projectID	= $projectID;
        $this->projectType 	= 2;
        
        $this->tesco_depot_daily    = "ferrero_tesco_depot_daily";
        $this->skutable             = "product";
        $this->storetable           = "store";
        $this->depottable           = "depot";
        $this->rangedtable          = "ferrero_tesco_ranged";
        $this->clustertable         = "cluster";
        $this->clusterDetailsTable  = "cluster_details";
		$this->territoryTable  		= "territory";  
        $this->multstable           = "ferrero_mults_summary";
        $this->periodtable          = "tesco_period";
        $this->clientconfigtable    = "client_config";
        $this->clientLogo           = "ferrero.png";

        $this->clusterID    = $_REQUEST['CID'];
        $this->GID          = 1;
        $this->DatePeriod   = "mydate";
		
        $this->yearperiod   = $this->periodtable . ".accountYear";
        $this->weekperiod   = $this->periodtable . ".accountWeek";
		
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
        
        /* PAGE CONFIGURATION VARS FOR DYNAMIC FLOW */
        $this->pageName             = $_REQUEST["pageName"];
        $this->pageID               = (isset($_REQUEST["pageID"]) && !empty($_REQUEST["pageID"])) ? $_REQUEST["pageID"] : 
                                        ((isset($_REQUEST['DataHelper']) && $_REQUEST['DataHelper'] == "true") ? $this->getDefaultPageConfiguration() : "");        
        
        $this->isDynamicPage        = (empty($this->pageID)) ? false : true;
        
        $this->projectHelperLink = " AND projectID=" . $this->projectID . " ";
        
        $this->currencySign	= $this->fetchCurrencySign();
        
        $this->ProjectValue   = $this->maintable . ".sales";
        $this->ProjectVolume  = $this->maintable . ".unit";
        $this->ProjectGmargin = $this->maintable . ".GMargin";        
        
        // Available OPTIONS for projectStructureType [MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW (Standard lcl Structure), MEASURE_AND_TYLY_AS_COLUMN (Nielsen Structure), MEASURE_AS_SINGLE_COLUMN_AND_TYLY_AS_ROW (New ASD Online Structure)]
        $this->projectStructureType = projectstructure\ProjectStructureType::$MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW;        
        
        // default settings
		$this->defaultProjectSettings = array(
            'has_dc_stock_inc'      => 0,
            'has_territory'   		=> 0,
            'has_territory_level'	=> NULL,
            'has_ranged'			=> 0
        );        
        
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
        
        // Adding period filter for daily_14 table as now we are keepping history data for client
        $getLastDaysDate = filters\timeFilter::getDatesWithinRange(0, 14, $this);
        $periodFilter = (is_array($getLastDaysDate) && !empty($getLastDaysDate)) ? " AND ".$this->maintable.".".$this->DatePeriod." IN (".implode(",", $getLastDaysDate).")" : "";

/*         $this->tablename = $this->maintable . "," . $this->skutable . "," . $this->storetable." ";
        $this->link = " WHERE ".$this->maintable.".accountID=" . $this->aid . " AND " . $this->maintable . ".skuID=" . $this->skutable . ".skuID " .
                " AND " . $this->maintable . ".SNO=" . $this->storetable . ".SNO " .				
                " AND " . $this->maintable . ".GID=" . $this->storetable . ".GID " .
				" AND " . $this->maintable . ".skuID=" . $this->skutable . ".skuID " .				
                " AND " . $this->storetable . ".GID=". $this->GID.
                " AND " . $this->skutable . ".clientID='" . $this->clientID . "' ".$periodFilter; */
				
		if(isset($_REQUEST['territoryLevel']))
		{
			$this->tablename .= "," . $this->territoryTable." ";
			$this->link		 .= " AND " . $this->territoryTable . ".accountID = " . $this->aid .
								" AND " . $this->storetable . ".SNO=" . $this->territoryTable . ".SNO " .
								" AND " . $this->storetable . ".GID=" . $this->territoryTable . ".GID ";
		}		

/* 		$this->skuHelperTables   = " " . $this->maintable . "," . $this->skutable . " ";		
		$this->skuHelperLink 	 = " WHERE ".$this->maintable.".skuID = ".$this->skutable.".skuID".
									" AND ". $this->maintable . ".accountID=" . $this->aid .
									" AND " . $this->maintable . ".GID=" . $this->skutable . ".gid" .
									" AND ". $this->maintable . ".GID=" . $this->GID.
									" AND " . $this->skutable . ".clientID='" . $this->clientID . "' "; */
		
/* 		$this->storeHelperTables = " " . $this->maintable . "," . $this->storetable . " ";		
		$this->storeHelperLink 	 = " WHERE ".$this->maintable.".SNO = ".$this->storetable.".sno".
									" AND ". $this->maintable . ".GID=" . $this->storetable . ".gid".
									" AND ". $this->maintable . ".accountID=" . $this->aid .
									" AND ". $this->maintable . ".GID=" . $this->GID; */

        $this->productHelperTables  = " " . $this->multstable . "," . $this->skutable . " ";
        $this->productHelperLink    = " WHERE " . $this->multstable . ".PIN=" . $this->skutable . ".skuID_rollup AND clientID='" . $this->clientID . "' AND " . $this->skutable . ".gid=" . $this->GID . "";
        
        /**
         * Added for creating dynamic dataArray from ConfigurationCheck Class
         * It is useful to provide proper link and tables to dynamic dataArray
        */
        $this->tableArray['product']['tables']  = $this->productHelperTables;
        $this->tableArray['product']['link']    = $this->productHelperLink;
        $this->tableArray['product']['type']    = 'P';        
        
        $this->latestDateHelperTables   = " " . $this->multstable . ", " . $this->periodtable;
        $this->latestDateHelperLink     = " WHERE " . $this->multstable . ".period=" . $this->periodtable . ".period AND " . $this->multstable . ".accountID=" . $this->aid." AND " . $this->multstable . ".GID=" . $this->GID." ";

        $this->timeHelperTables     = " " . $this->periodtable . ", " . $this->multstable;
        $this->timeHelperLink       = " WHERE " . $this->multstable . ".period=" . $this->periodtable . ".period AND " . $this->multstable . ".gid=" . $this->GID." ";
        
        $this->multstablename       = $this->multstable . "," . $this->skutable . "," . $this->periodtable;
        $this->multslink            = " WHERE " . $this->multstable . ".accountID=" . $this->aid . " AND " . $this->multstable . ".PIN=" . $this->skutable . ".skuID " .
                                        " AND " . $this->multstable . ".gid=" . $this->skutable . ".gid" .
                                        " AND clientID='" . $this->clientID . "' AND " . $this->skutable . ".gid=" . $this->GID . " " .
                                        " AND " . $this->multstable . ".period=" . $this->periodtable . ".period" .
                                        " AND hide<>1 ";

		$this->projectHelperLink = " AND projectID=" . $this->projectID . " ";
        
        $this->menuProjectHelperLink = " AND ".$this->assignMenusTable.".projectID=".$this->projectID." AND ".$this->assignClientPagesTable.".projectID=".$this->projectID;

/*         $this->ProjectValue = $this->maintable . '.value';
        $this->ProjectVolume = $this->maintable . '.units'; */

        $this->dataArray = array();
        $this->pageArray = array();
        
        if (!$this->isDynamicPage) {
            $this->dataArray();
            $this->pageArray();
        }        
        
        $this->measureArray = array();
        $this->measureArray['M1']['VAL']    = $this->multstable . ".sales";
        $this->measureArray['M1']['ALIASE'] = "VALUE";
        $this->measureArray['M1']['attr']   = "SUM";

        $this->measureArray['M2']['VAL']    = $this->multstable . ".qty";
        $this->measureArray['M2']['ALIASE'] = "VOLUME";
        $this->measureArray['M2']['attr']   = "SUM";
        
        /* $this->measureArray['M3']['ALIASE'] = "PRICE";
        $this->measureArray['M3']['attr']   = "PRICE";
                
        $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
        $this->measureArray['M4']['attr']   = "DISTRIBUTION"; */

        $this->dataArray = array();

        $this->dataArray['F1']['NAME_ALIASE']   = 'category';
		
        $this->dataArray['F2']['ID']            = $this->skutable . '.skuID';
        $this->dataArray['F2']['ID_ALIASE']     = "skuID";
        $this->dataArray['F2']['NAME']          = $this->skutable . '.sku';
        $this->dataArray['F2']['NAME_ALIASE']   = 'SKU';
        $this->dataArray['F2']['tablename']     = $this->productHelperTables;
        $this->dataArray['F2']['link']          = $this->productHelperLink;
        $this->dataArray['F2']['TYPE']          = "P";

        $this->dataArray['F3']['ID']            = $this->depottable . '.depotNo';
        $this->dataArray['F3']['ID_ALIASE']     = "depotID";
        $this->dataArray['F3']['NAME']          = $this->depottable . '.depot_name';
        $this->dataArray['F3']['NAME_ALIASE']   = 'DEPOT';

        $this->dataArray['F4']['ID']            = $this->storetable . '.SNO';
        $this->dataArray['F4']['ID_ALIASE']     = "SNO";
        $this->dataArray['F4']['NAME']          = $this->storetable . '.sname';
        $this->dataArray['F4']['NAME_ALIASE']   = 'SNAME';

        $this->dataArray['F5']['NAME']          = 'BANNER';
        $this->dataArray['F5']['NAME_ALIASE']   = 'BANNER';
        $this->dataArray['F5']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F5']['link']          = $this->storeHelperLink;
        //$this->dataArray['F5']['TYPE']            = "M";
        
        $this->dataArray['F6']['NAME']          = 'store_district';
        $this->dataArray['F6']['NAME_ALIASE']   = 'STOREDISTRICT';
        $this->dataArray['F6']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F6']['link']          = $this->storeHelperLink;
        //$this->dataArray['F6']['TYPE']            = "M";
        
        $this->dataArray['F7']['NAME']          = 'REGION';
        $this->dataArray['F7']['NAME_ALIASE']   = 'REGION';
        $this->dataArray['F7']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F7']['link']          = $this->storeHelperLink;
        //$this->dataArray['F7']['TYPE']            = "M";

        $this->dataArray['F8']['NAME']          = 'FORMAT';
        $this->dataArray['F8']['NAME_ALIASE']   = 'FORMAT';
        $this->dataArray['F8']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F8']['link']          = $this->storeHelperLink;
        //$this->dataArray['F8']['TYPE']            = "M";
		
        $this->dataArray['F9']['NAME']          = 'sku_rollup';
        $this->dataArray['F9']['NAME_ALIASE']   = 'sku_rollup';
        $this->dataArray['F9']['tablename']     = $this->productHelperTables;
        $this->dataArray['F9']['link']          = $this->productHelperLink;
        $this->dataArray['F9']['TYPE']          = "P";
        
        $this->dataArray['F10']['NAME']         = $this->multstable . '.sales';
        $this->dataArray['F10']['NAME_ALIASE']  = 'sales';

        $this->dataArray['F11']['NAME']         = $this->multstable . '.GMargin';
        $this->dataArray['F11']['NAME_ALIASE']  = 'GMargin';

        $this->dataArray['F12']['NAME']         = $this->skutable . '.category';
        $this->dataArray['F12']['NAME_ALIASE']  = 'CATEGORY';
        $this->dataArray['F12']['tablename']    = $this->productHelperTables;
        $this->dataArray['F12']['link']         = $this->productHelperLink;

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
            'label' => 'Format', 'data' => 'F8', 'indexName' => 'FS[F8]', 'selectedItemLeft' => 'selectedRegionLeft',
            'selectedItemRight' => 'selectedRegionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );				

        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Region', 'data' => 'F7', 'indexName' => 'FS[F7]', 'selectedItemLeft' => 'selectedRegionLeft',
            'selectedItemRight' => 'selectedRegionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );
		
        $this->marketOptions_DisplayOptions[] = array(
            'label' => 'Store District', 'data' => 'F6', 'indexName' => 'FS[F6]', 'selectedItemLeft' => 'selectedRegionLeft',
            'selectedItemRight' => 'selectedRegionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );

		$this->marketOptions_DisplayOptions[] = array(
            'label' => 'Banner', 'data' => 'F5', 'indexName' => 'FS[F5]', 'selectedItemLeft' => 'selectedRegionLeft',
            'selectedItemRight' => 'selectedRegionRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => false
        );

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
            'label' => 'Category', 'data' => 'F12', 'indexName' => 'FS[F12]', 'selectedItemLeft' => 'selectedCategoryLeft',
            'selectedItemRight' => 'selectedCategoryRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );
		
		$this->pageArray["INTERACTIVE_SUMMARY_PAGE"]["DH"] = "F5-F6-F7-F8-F9-F2-F12";

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
            'label' => 'Category', 'data' => 'F12', 'indexName' => 'FS[F12]', 'selectedItemLeft' => 'selectedCategoryLeft',
            'selectedItemRight' => 'selectedCategoryRight', 'dataList' => array(), 'selectedDataList' => array(), 'selected' => true
        );
		
		$this->pageArray["INTERACTIVE_SUMMARY_PAGE"]["DH"] = "F5-F6-F7-F8-F9-F2-F12";
    }    
    
    public function dataArray()
    {
        $this->dataArray['F1']['NAME_ALIASE']   = 'category';
		
        $this->dataArray['F2']['ID']            = $this->skutable . '.skuID';
        $this->dataArray['F2']['ID_ALIASE']     = "skuID";
        $this->dataArray['F2']['NAME']          = $this->skutable . '.sku';
        $this->dataArray['F2']['NAME_ALIASE']   = 'SKU';
        $this->dataArray['F2']['tablename']     = $this->productHelperTables;
        $this->dataArray['F2']['link']          = $this->productHelperLink;
        $this->dataArray['F2']['TYPE']          = "P";

        $this->dataArray['F3']['ID']            = $this->depottable . '.depotNo';
        $this->dataArray['F3']['ID_ALIASE']     = "depotID";
        $this->dataArray['F3']['NAME']          = $this->depottable . '.depot_name';
        $this->dataArray['F3']['NAME_ALIASE']   = 'DEPOT';

        $this->dataArray['F4']['ID']            = $this->storetable . '.SNO';
        $this->dataArray['F4']['ID_ALIASE']     = "SNO";
        $this->dataArray['F4']['NAME']          = $this->storetable . '.sname';
        $this->dataArray['F4']['NAME_ALIASE']   = 'SNAME';

        $this->dataArray['F5']['NAME']          = 'BANNER';
        $this->dataArray['F5']['NAME_ALIASE']   = 'BANNER';
        $this->dataArray['F5']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F5']['link']          = $this->storeHelperLink;
        //$this->dataArray['F5']['TYPE']            = "M";
        
        $this->dataArray['F6']['NAME']          = 'store_district';
        $this->dataArray['F6']['NAME_ALIASE']   = 'STOREDISTRICT';
        $this->dataArray['F6']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F6']['link']          = $this->storeHelperLink;
        //$this->dataArray['F6']['TYPE']            = "M";
        
        $this->dataArray['F7']['NAME']          = 'REGION';
        $this->dataArray['F7']['NAME_ALIASE']   = 'REGION';
        $this->dataArray['F7']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F7']['link']          = $this->storeHelperLink;
        //$this->dataArray['F7']['TYPE']            = "M";

        $this->dataArray['F8']['NAME']          = 'FORMAT';
        $this->dataArray['F8']['NAME_ALIASE']   = 'FORMAT';
        $this->dataArray['F8']['tablename']     = $this->storeHelperTables;
        $this->dataArray['F8']['link']          = $this->storeHelperLink;
        //$this->dataArray['F8']['TYPE']            = "M";
		
        $this->dataArray['F9']['NAME']          = 'sku_rollup';
        $this->dataArray['F9']['NAME_ALIASE']   = 'sku_rollup';
        $this->dataArray['F9']['tablename']     = $this->productHelperTables;
        $this->dataArray['F9']['link']          = $this->productHelperLink;
        $this->dataArray['F9']['TYPE']          = "P";
        
        $this->dataArray['F10']['NAME']         = $this->multstable . '.sales';
        $this->dataArray['F10']['NAME_ALIASE']  = 'sales';

        $this->dataArray['F11']['NAME']         = $this->multstable . '.GMargin';
        $this->dataArray['F11']['NAME_ALIASE']  = 'GMargin';

        $this->dataArray['F12']['NAME']         = $this->skutable . '.category';
        $this->dataArray['F12']['NAME_ALIASE']  = 'CATEGORY';
        $this->dataArray['F12']['tablename']    = $this->productHelperTables;
        $this->dataArray['F12']['link']         = $this->productHelperLink;        
    }
    
    public function getDefaultPageConfiguration() {
        $pageID = '';
        $queryVars = projectsettings\settingsGateway::getInstance();
        
        $query = "SELECT pageID, templateID, setting_name, setting_value  FROM ". 
            $this->pageConfigTable . "  WHERE accountID = ".$this->aid." AND projectID = ".$this->projectID.
            " AND setting_name = 'default_load_pageID'";
        $pageConfig = $queryVars->queryHandler->runQuery($query, $queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

        if(!empty($pageConfig))
            $this->default_load_pageID = $pageID = $pageConfig[0]['setting_value'];

        return $pageID;
    }    
    
    public function setCluster($linkid, $queryHandler, $projectID, $projectManagerLinkid) {
		
 		$query = "SELECT * FROM ".$this->clustertable;
		$clusters = $queryHandler->runQuery($query, $projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
		if(!empty($clusters) && is_array($clusters))
		{
			$clusterData = array();
			foreach($clusters as $data)
				$clusterData[$data['cl']] = $data['cl_name'];
		}
		
		$query = "SELECT * FROM pm_config WHERE accountID=".$this->aid." AND projectID = ".$projectID." AND setting_name = 'cluster_default_load'";
		$defaultLoad = $queryHandler->runQuery($query, $projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
		if(!empty($defaultLoad) && is_array($defaultLoad))
			$defaultLoad = $defaultLoad[0]['setting_value'];
		
		$query = "SELECT * FROM pm_config WHERE accountID=".$this->aid." AND projectID = ".$projectID." AND setting_name = 'has_cluster' AND setting_value = 1";
		$result = $queryHandler->runQuery($query, $projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
		if(!empty($result) && is_array($result))
		{
			$query = "SELECT setting_value FROM pm_config WHERE accountID = ".$this->aid." AND projectID = ".$projectID." AND setting_name = 'cluster_settings' ";
			$result = $queryHandler->runQuery($query, $projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
			
			if(!empty($result) && is_array($result) && $result[0]['setting_value'] != "")
			{
				$settings = explode("|", $result[0]['setting_value']);
				
				$ClusterList = array();
				foreach($settings as $data)
				{
					$tmp = array();
					$tmp['cl'] = $data;
					$tmp['CLUSTER'] = $clusterData[$data];
					
					if($defaultLoad == $data)
						$tmp['defaultLoad'] = 1;
					else
						$tmp['defaultLoad'] = 0;
					
					$ClusterList[] = $tmp;
				}
				$this->clusterList = $ClusterList;
				if($_REQUEST['destination'] != 'InteractiveSummary')
				{
					if (empty($_REQUEST["clusterID"])) 
					{
						$exitFlag = 1;
						foreach ($ClusterList as $value) 
						{
							if ($value['defaultLoad']) 
							{
								$this->dataArray['F1']['NAME'] = $this->storetable . '.cl' . $value['cl'];
								$this->clusterID = 'cl' . $value['cl'];
								$exitFlag = 0;
							}
						}
						if ($exitFlag) 
						{
							echo "Sorry, Cluster has not initialized</br>";
							exit;
						}
					}
					else 
					{
						$this->dataArray['F1']['NAME'] = $this->storetable . '.cl' . $_REQUEST['clusterID'];
						$this->clusterID = 'cl' . $_REQUEST['clusterID'];
					}					
				}
			}
			else
			{
				echo "Sorry, Cluster has not configured";
				exit;
			}			
		}
		else
		{
            echo "Sorry, Cluster has not enable";
            exit;		
		}
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

}

?>