<?php
namespace config;

use lib;
use db;
use datahelper;
use filters;
use utils;
use projectstructure;

class ConfigureProject {

    public $settingVars;   //containes all setting variables
    public $queryVars;     //containes query related variables [queryHandler,linkid]
    public $jsonOutput;

    public function __construct($settingVars, $queryVars) {
        $this->settingVars  = $settingVars;
        $this->queryVars    = $queryVars;
    }

    public function initializeProject(&$jsonOutput) {
        $this->fetch_all_timeSelection_data(); //collecting time selection data
        $this->fetch_all_product_and_marketSelection_data(); //collecting product and market filter data            
        $this->settingGlobalVars(); // INITIAL GLOBAL VARS FOR WHILE PROJECT   

        $this->jsonOutput['clientID']     = $this->settingVars->clientID;
        $this->jsonOutput['currencySign'] = $this->settingVars->currencySign;
        $this->jsonOutput['timeSelectionUnit'] = $this->settingVars->timeSelectionUnit;
        
        if(isset($this->settingVars->timeSelectionStyle) && $this->settingVars->timeSelectionStyle == 'DROPDOWN'){
            $this->jsonOutput['timeSelectionStyle'] = $this->settingVars->timeSelectionStyle;
            $this->jsonOutput['timeSelectionStyleDDArray'] = $this->settingVars->timeSelectionStyleDDArray;
        }

        if(isset($this->settingVars->default_load_pageID) && !empty($this->settingVars->default_load_pageID))
            $this->jsonOutput['default_load_pageID'] = $this->settingVars->default_load_pageID;        
            
        if(isset($this->settingVars->default_page_slug) && !empty($this->settingVars->default_page_slug))
        {
            $this->jsonOutput['default_page_slug'] = $this->settingVars->default_page_slug;
            $this->jsonOutput['is_static_page'] = (isset($this->settingVars->isStaticPage) ? $this->settingVars->isStaticPage : false);
        }

        $jsonOutput = $this->jsonOutput;
    }

    public function settingGlobalVars() {
        $this->getMenus();
        $this->getMeasureSelectionList();
        $this->getSkuSelectionList();
		$this->fetchProductAndMarketTabsSettings(); // setting product and market tabs data
        $this->getProjectNameAndProjectID();
        $this->getCompanyName();
        $this->getProjectSettings();
        $this->fetchSavedFilterList();
        $this->getTerritory();
        unset($this->jsonOutput['filters']);
    }

    	
	
    private function getTerritory() {
        $settingName = $this->settingVars->menuArray['MF5']['SETTING_NAME']; // Fetch setting_name field name
        $settingValue = $this->settingVars->menuArray['MF5']['SETTING_VALUE']; // Fetch setting_value field name
        $settings = $this->jsonOutput["settings"];

        if (!empty($settingName) && !empty($settingValue)) {
            if (array_key_exists('has_territory', $settings) && $settings['has_territory'] == '1') {
                if (array_key_exists('territory_settings', $settings) && !empty($settings['territory_settings'])) {
                    $territorySettings = explode("|", $settings['territory_settings']);
                    $result = array();
                    if(is_array($this->settingVars->clientConfiguration) && !empty($this->settingVars->clientConfiguration)){
                        foreach ($territorySettings as $field) {
                            $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_column'));
                            if ($searchKeyDB !== false && $this->settingVars->clientConfiguration[$searchKeyDB]['show_in_pm'] == 'Y' && $this->settingVars->clientConfiguration[$searchKeyDB]['db_table'] == $this->settingVars->territorytable ) {
                                $result[] = $this->settingVars->clientConfiguration[$searchKeyDB];
                            }
                        }                       
                    }
                    $result = utils\SortUtility::sort2DArray($result, 'rank', utils\SortTypes::$SORT_ASCENDING);
                    
                    if(is_array($result) && !empty($result))
                    {
                        $output = array();
                        foreach($result as $key => $data)
                        {
                            $output[] = array("data" => $this->settingVars->territorytable.".".$data['csv_column'], "label" => $data['csv_column'], "field" => $data['db_column']);
                        }
                    
                        $this->jsonOutput['territoryList'] = $output;
                    }
                    else
                    {
                        $response = array("configuration" => array("status" => "fail", "messages" => array("Seems territory configuration is missing.")));
                        echo json_encode($response);
                        exit();                     
                    }
                }
            }
        }
    }

    /**
     * getProjectSettings()
     * It will list all settings for the project
     * 
     * @return array
     */
    public function getProjectSettings() {
        $cid = $this->settingVars->aid;

        $settingName = $this->settingVars->menuArray['MF5']['SETTING_NAME'];
        $settingValue = $this->settingVars->menuArray['MF5']['SETTING_VALUE'];
        $settings = $this->settingVars->defaultProjectSettings;

        if (!empty($settingName) && !empty($settingValue)) {
            $table = $this->settingVars->configTable;
            if (isset($this->queryVars->projectConfiguration['has_private_label']) && $this->queryVars->projectConfiguration['has_private_label'] == '1')
                $this->defaultPrivateLable();
        }
        $settings = array_merge($settings,(is_array($this->queryVars->projectConfiguration) ? $this->queryVars->projectConfiguration : array()));

        $this->jsonOutput["settings"] = $settings;
    }

    public function defaultPrivateLable()
    {
        $this->settingVars->link .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
        return true;
    }
    
    public function getMenus() {
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('menu_configuration');

        if ($redisOutput === false) {
            $menuBuilt = new lib\MenuBuilt($this->settingVars->projectID,$this->settingVars->aid,$this->queryVars);
            $menuList = $menuBuilt->getMenus(true);
            $redisCache->setDataForStaticHash($menuList);
        } else {
            $menuList = $redisOutput;
        }
        $this->jsonOutput["MENU_LIST"] = $menuList;

        if (!empty($this->settingVars->default_load_pageID) && isset($menuList) && is_array($menuList) && count($menuList) > 0) {
            foreach ($menuList as $ky => $vl) {
                if (is_array($vl)) {
                    $arrIdx = array_search($this->settingVars->default_load_pageID, array_column($vl,'pageID'));
                    if ($arrIdx !== false) {
                        $default_load_title = $menuList[$ky][$arrIdx]['title'];
                        break;
                    }
                }
            }
           $this->jsonOutput["default_load_title"] = $default_load_title;
        }
    }

    private function getCompanyName() {
        $this->jsonOutput["COMPANY_NAME"] = $this->settingVars->pageArray["COMPANY_NAME"];
    }

    /**
     * Getting measure selection list data ( front end )
     */
    private function getMeasureSelectionList() {
        $measureSelectionList = array();
        if(is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])){
            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measure) {
                $measure['dataDecimalPlaces'] = (isset($measure['dataDecimalPlaces'])) ? $measure['dataDecimalPlaces'] : 0;
                $measureSelectionList[] = $measure;
            }
        }
        $this->jsonOutput["measureSelectionList"] = $measureSelectionList;
    }
    
    /**
     * Getting sku selection list data ( front end )
     */
    private function getSkuSelectionList() {
        //$this->jsonOutput["skuSelectionList"] = $this->settingVars->pageArray["SKU_SELECTION_LIST"];
		if(isset($this->jsonOutput["settings"]['sku_settings']) && $this->jsonOutput["settings"]['sku_settings'] != "")
			$this->jsonOutput["skuSelectionList"] = $this->jsonOutput["settings"]['sku_settings'];
    }
    

    /** ***
     * COLLECTS ALL TIME SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** 
     */
    public function fetch_all_timeSelection_data() {
        echo $projectStructureType = "projectstructure\\".$this->settingVars->projectStructureType;exit;
        $structureClass = new $projectStructureType();
        $structureClass->fetchAllTimeSelectionData($this->settingVars, $this->jsonOutput, array());
    }

    /**
     * Getting Project Name and ProjectID
     */
    private function getProjectNameAndProjectID(){
        $this->jsonOutput["projectID"] = utils\Encryption::encode($this->settingVars->projectID);
        $this->jsonOutput["projectName"] = $this->settingVars->pageArray["PROJECT_NAME"];
    }
    
    public function fetchFilterTabsSettings($displayOptions, $fetchProductAndMarketFilterOnTabClick = false)
    {
        if (is_array($this->settingVars->$displayOptions) && !empty($this->settingVars->$displayOptions)) {
            foreach ($this->settingVars->$displayOptions as $key => $selectionTab) {

                $xmlTagAccountName  = $this->settingVars->dataArray[$selectionTab['data']]['NAME'];
                $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
                
                $xmlTag = ($this->settingVars->dataArray[$selectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE']) : $xmlTag;
            
                if(isset($this->settingVars->dataArray[$selectionTab['data']]['ID_ALIASE']) && !empty($this->settingVars->dataArray[$selectionTab['data']]['ID_ALIASE']))
                    $xmlTag .= "_". $this->settingVars->dataArray[$selectionTab['data']]['ID_ALIASE'];

                if ($fetchProductAndMarketFilterOnTabClick){
                    $this->settingVars->{$displayOptions}[$key]['isDataLoaded'] = false;
                    $this->settingVars->{$displayOptions}[$key]['curPage'] = 1;
                    $totalRecordCnt = (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) ? count($this->jsonOutput['filters'][$xmlTag]) : 0;
                    $this->settingVars->{$displayOptions}[$key]['totalPage'] = ($totalRecordCnt > 0 && $this->settingVars->productAndMarketFilterTabDataLoadLimit > 0) ? ceil($totalRecordCnt/$this->settingVars->productAndMarketFilterTabDataLoadLimit) : 1;
                    $this->settingVars->{$displayOptions}[$key]['showPaging'] = ($this->settingVars->{$displayOptions}[$key]['totalPage'] > 1) ? true : false;
                    continue;
                }

                if (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) {
                    if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true) {
                        $this->settingVars->{$displayOptions}[$key]['dataList'] = $this->jsonOutput['filters']['commonFilter'][$xmlTag]['dataList'];
                        $this->settingVars->{$displayOptions}[$key]['selectedDataList'] = (isset($this->jsonOutput['filters']['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['filters']['commonFilter'][$xmlTag]['selectedDataList'] : array();
                        $this->settingVars->{$displayOptions}[$key]['isDataLoaded'] = true;
                    } else {
                        $this->settingVars->{$displayOptions}[$key]['dataList'] = $this->jsonOutput['filters'][$xmlTag];
                        $this->settingVars->{$displayOptions}[$key]['isDataLoaded'] = true;
                    }
                }
            }
        }
    }
    
    public function fetchProductAndMarketTabsSettings() {
        
        $this->fetchFilterTabsSettings("productOptions_DisplayOptions", $this->settingVars->fetchProductAndMarketFilterOnTabClick);
        $this->fetchFilterTabsSettings("skuOptions_DisplayOptions");
        $this->fetchFilterTabsSettings("marketOptions_DisplayOptions", $this->settingVars->fetchProductAndMarketFilterOnTabClick);
        
        $this->jsonOutput['productSelectionTabs'] = $this->settingVars->productOptions_DisplayOptions;
        $this->jsonOutput['marketSelectionTabs'] = $this->settingVars->marketOptions_DisplayOptions;

        /*[START] SAVE productSelectionTabs AND marketSelectionTabs DATA TO THE REDIS CACHE WHICH WILL BE USED ON ExcelPosTracker Export*/
            $redisCache = new utils\RedisCache($this->queryVars);
            $redisCache->requestHash = 'productAndMarketSelectionTabsRedisList';
            $redisCache->setDataForStaticHash(['P'=>$this->settingVars->productOptions_DisplayOptions,'M'=>$this->settingVars->marketOptions_DisplayOptions]);
        /*[START] SAVE productSelectionTabs AND marketSelectionTabs DATA TO THE REDIS CACHE WHICH WILL BE USED ON ExcelPosTracker Export*/
        $this->jsonOutput['skuSelectionTabs'] = $this->settingVars->skuOptions_DisplayOptions;
    }
    
    /** ***
     * COLLECTS ALL PRODUCT AND MARKET SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** 
     */

    public function fetch_all_product_and_marketSelection_data() {
        
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING     

        if (!empty($dataHelpers)) {

            $selectPart = $resultData = $helperTables = $helperLinks = $tagNames = $includeIdInLabels = $groupByPart = array();
            $filterDataProductInlineConfig = $dataProductInlineFields = [];
            
            foreach ($dataHelpers as $key => $account) {
                if($account != "")
                {
                    //IN RARE CASES WE NEED TO ADD ADITIONAL FIELDS IN GROUP BY CLUAUSE AND ALSO AS LABEL WITH THE ORIGINAL ACCOUNT
                    //E.G: FOR RUBICON LCL - SKU DATA HELPER IS SHOWN AS 'SKU #UPC'
                    //IN ABOVE CASE WE SEND DH VALUE = F1#F2 [ASSUMING F1 AND F2 ARE SKU AND UPC'S INDEX IN DATAARRAY]
                    $combineAccounts = explode("#", $account);
                    
                    foreach ($combineAccounts as $accountKey => $singleAccount) {
                        $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                        if ($tempId != "") {
                            $selectPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $tempId . " AS " . $this->settingVars->dataArray[$singleAccount]['ID_ALIASE'];
                            $groupByPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $this->settingVars->dataArray[$singleAccount]['ID_ALIASE'];

                            /*[START] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
                                $filterDataProductInlineConfig[] = $tempId . " AS " . $this->settingVars->dataArray[$singleAccount]['ID_ALIASE_WITH_TABLE'];
                                $dataProductInlineFields[] = $tempId;
                            /*[END] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
                        }
                        
                        $tempName = $this->settingVars->dataArray[$singleAccount]['NAME'];
                        $selectPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $tempName . " AS " . $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                        $groupByPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];

                        /*[START] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
                            $filterDataProductInlineConfig[] = $tempName . " AS " . $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                            $dataProductInlineFields[] = $tempName;
                        /*[END] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
                    }
                    
                    $helperTables[$this->settingVars->dataArray[$combineAccounts[0]]['TYPE']] = $this->settingVars->dataArray[$combineAccounts[0]]['tablename'];
                    $helperLinks[$this->settingVars->dataArray[$combineAccounts[0]]['TYPE']] = $this->settingVars->dataArray[$combineAccounts[0]]['link'];
                    
                    //datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Data($selectPart, $groupByPart, $tagName, $helperTableName, $helperLink, $this->jsonOutput, $includeIdInLabel, $account);
                }
            }

            if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1 && count($filterDataProductInlineConfig) >0){
                $this->settingVars->filterDataProductInlineConfig = $filterDataProductInlineConfig;
                $this->settingVars->dataProductInlineFields = $dataProductInlineFields;
            }
            
            if(is_array($selectPart) && !empty($selectPart)){
                foreach ($selectPart as $type => $sPart) {
                    $resultData[$type] = datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Query_Data($sPart, $groupByPart[$type], $helperTables[$type], $helperLinks[$type]);
                }
                $redisCache = new utils\RedisCache($this->queryVars);
                $redisCache->requestHash = 'productAndMarketFilterData';
                $redisCache->setDataForStaticHash($selectPart);
            }

            foreach ($dataHelpers as $key => $account) {
                if($account != "")
                {
                    $combineAccounts = explode("#", $account);

                    $tagNameAccountName = $this->settingVars->dataArray[$combineAccounts[0]]['NAME'];

                    //IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
                    //AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
                    $tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'] : strtoupper($tagNameAccountName);

                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']))
                    {
                        $tagName = ($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']) : $tagName;
                        
                        if(isset($this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE']) && !empty($this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE']))
                            $tagName .= "_". $this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE'];
                    }

                    $includeIdInLabel = false;
                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']))
                        $includeIdInLabel = ($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']) ? true : false;

                    $tempId = key_exists('ID', $this->settingVars->dataArray[$combineAccounts[0]]) ? $this->settingVars->dataArray[$combineAccounts[0]]['ID'] : "";
                    
                    $nameAliase = $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'];
                    $idAliase = isset($this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE']) ? $this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE'] : "";

                    $type = $this->settingVars->dataArray[$combineAccounts[0]]['TYPE'];

                    if( !isset($this->jsonOutput['filters']) || !array_key_exists($tagName, $this->jsonOutput['filters']) )
                        datahelper\Product_And_Market_Filter_DataCollector::getFilterData( $nameAliase, $idAliase, $tempId, $resultData[$type], $tagName , $this->jsonOutput, $includeIdInLabel, $account);
                }
            }

            // NOTE: THIS IS FOR FETCHING PRODUCT AND MARKET WHEN USER CLICKS TAB. 
            // TO FETCH TAB DATA SERVER BASED. TO AVOID BROWSER HANG. 
            // WE FACE ISSUE IN MJN AS MANY FILTERS ENABLED FOR ITS PROJECTS
            if ($this->settingVars->fetchProductAndMarketFilterOnTabClick) {
                $redisCache = new utils\RedisCache($this->queryVars);
                $redisCache->requestHash = 'productAndMarketFilterTabData';
                $redisCache->setDataForStaticHash($this->jsonOutput['filters']);
            }
        }
    }

    private function fetchSavedFilterList()
    {
        if($this->settingVars->filterMaster != "")
        {
            $filterList = "SELECT * FROM ".$this->settingVars->filterMaster.' WHERE '.$this->settingVars->filterMaster.'.cid = '.$this->settingVars->aid;
            $result = $this->queryVars->queryHandler->runQuery($filterList, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $filters = $filterList = array();   

            if (is_array($result) && !empty($result)) {
                foreach ($result as $filterData) {
                    $filterList[$filterData['id']] = $filterData['name'];
                    $filters[] = array(
                        'filterId' => $filterData['id'],
                        'label' => $filterData['name']
                    );
                }
            }
            $this->jsonOutput['filter_list'] = $filterList;
            $this->jsonOutput['filters'] = $filters;
        }
    }
}

?>