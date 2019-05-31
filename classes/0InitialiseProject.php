<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class InitialiseProject extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public $projectType;     
     
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->projectType = $_REQUEST['projectType'];
        
        switch ($this->projectType)
        {
            case 'tesco-store-daily':
                if($_REQUEST['SIF'] == "YES")
                {
                    $this->checkConfiguration();
                    $this->getAllSkus();
                    $this->getCluster();
                    $this->getLatestDateOfMainProject();
                    $this->getLatestDateOfDcTable();
                    $this->fetch_all_timeSelection_data();

                    //$this->jsonOutput['timeSelectionUnit'] = $this->settingVars->timeSelectionUnit;
                    $this->fetch_all_marketSelection_data();
                    $this->fetchMarketTabsSettings();
                    $this->jsonOutput["measureSelectionListSIF"] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
                    unset($this->jsonOutput['selectedIndexFrom'], $this->jsonOutput['selectedIndexTo']);
                }
                else
                {
                    if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                        $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                    else
                        $this->jsonOutput['clientLogo']         = 'no-logo.jpg';                
                        
                    if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                        $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
                    else
                        $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';                        

                    $this->getLatestDateOfMultsProject();
                    $this->fetch_all_timeSelection_data();
                    $this->getSkuSelectionList();
                    $this->jsonOutput['groupName']              = $this->settingVars->groupName;
                }
            break;
            case 'impulseViewJS':
                if($_REQUEST['SIF'] == "YES")
                {
                    $this->checkConfiguration();
                    $this->getAllSkus();
                    $this->getCluster();
                    $this->getLatestDateOfMainProject();
                    $this->getLatestDateOfDcTable();
                    $this->fetch_all_timeSelection_data();

                    $this->fetch_all_marketSelection_data();
                    $this->fetchMarketTabsSettings();
                    $this->jsonOutput["measureSelectionListSIF"] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
                    unset($this->jsonOutput['selectedIndexFrom'], $this->jsonOutput['selectedIndexTo']);
                }
                else
                {
                    if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                        $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                    else
                        $this->jsonOutput['clientLogo']         = 'no-logo.jpg';                
                        
                    if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                        $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
                    else
                        $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';                        

                    $this->getLatestDateOfMultsProject();
                    $this->fetch_all_timeSelection_data();
                    $this->getSkuSelectionList();
                    $this->jsonOutput['groupName']              = $this->settingVars->groupName;
                }
            break;
            case 'relayplus':
                if($_REQUEST['SIF'] == "YES")
                {
                    $this->getRegions();
                    $this->getAllSkus();
                    $this->getLastMyDate();
                    $this->getCluster();
                    $this->fetch_all_timeSelection_data();
                    $this->fetch_all_marketSelection_data();
                    $this->fetchMarketTabsSettings();
                    $this->jsonOutput["measureSelectionListSIF"] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
                    unset($this->jsonOutput['selectedIndexFrom'], $this->jsonOutput['selectedIndexTo']);

                    return $this->jsonOutput;
                }
                else
                {
                    $this->jsonOutput['weekRangeList']  = $this->settingVars->weekRangeList;
                    $this->jsonOutput['dayList']        = $this->settingVars->dayList;
                    $this->jsonOutput['daysList']       = $this->settingVars->daysList;

                    /* $this->jsonOutput['footerCompanyName']  = (isset($this->settingVars->footerCompanyName)) ? $this->settingVars->footerCompanyName : ''; */
                    $this->jsonOutput['projectID']          = utils\Encryption::encode($this->settingVars->projectID);
                    $this->jsonOutput['sifProjectID']          = utils\Encryption::encode($this->settingVars->sifProjectID);
                    
                    if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                        $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                    else
                        $this->jsonOutput['clientLogo']         = 'no-logo.jpg';
                    
                    if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                        $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
                    else
                        $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';    

                    if(isset($this->settingVars->default_load_pageID) && !empty($this->settingVars->default_load_pageID))
                        $this->jsonOutput['default_load_pageID'] = $this->settingVars->default_load_pageID;

                    $this->getLastMyDate();
                    $this->fetch_all_timeSelection_data();
                    $this->getSkuSelectionList();
                }
            break;

            case 'lcl':
                $this->fetch_all_timeSelection_data();
                $this->fetchGlobalFilter();
                $this->getGlobalFilterByPage();
                break;

            case 'nielsen':
                $this->fetch_all_timeSelection_data();
                $this->fetchGlobalFilter();
                $this->getGlobalFilterByPage();
                $this->getLastMyDateForNielsen();
                $this->jsonOutput['headerFooterSourceText'] = $this->settingVars->headerFooterSourceText;
                if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                    $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                else
                    $this->jsonOutput['clientLogo']         = 'no-logo.jpg';
                break;                
        }

        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        /*$filterDataProductInlineConfig = []; $rankC = 1; $productAndMarketFilterData = [];
        if(isset($this->jsonOutput['marketSelectionTabs']) && is_array($this->jsonOutput['marketSelectionTabs']) && count($this->jsonOutput['marketSelectionTabs']) >0){
            foreach ($this->jsonOutput['marketSelectionTabs'] as $mkey => $mValue) {
                $indx = str_replace('.', '_', $mValue['data']);
                $filterDataProductInlineConfig[$indx] = ['label'    =>$mValue['label'],
                                                         'field'    =>$mValue['data'],
                                                         'indexName'=>$mValue['indexName'],
                                                         'data'     =>$indx,
                                                         'RANK'     =>$rankC
                                                        ];
                $rankC++;
            }
        }
        if(isset($this->jsonOutput['productSelectionTabs']) && is_array($this->jsonOutput['productSelectionTabs']) && count($this->jsonOutput['productSelectionTabs'])>0){
            foreach ($this->jsonOutput['productSelectionTabs'] as $pkey => $pValue) {
                $indx = str_replace('.', '_', $pValue['data']);
                $filterDataProductInlineConfig[$indx] = ['label'    =>$pValue['label'],
                                                         'field'    =>$pValue['data'],
                                                         'indexName'=>$pValue['indexName'],
                                                         'data'     =>$indx,
                                                         'RANK'     =>$rankC
                                                        ];
                $rankC++;
            }
        }

        $this->jsonOutput['filterDataProductInlineConfig'] = $filterDataProductInlineConfig;
        if(count($filterDataProductInlineConfig)>0)
            $this->jsonOutput['productAndMarketFilterData'] = $this->getAllProductAndMarketInlineFilterData();*/


        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $this->getAllProductAndMarketInlineFilterData();
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */

        return $this->jsonOutput;
    }

    function getLastMyDateForNielsen() {
        $query = "SELECT MAX(" . $this->settingVars->maintable . ".mydate) AS MYDATE FROM " . $this->settingVars->maintable . " WHERE ".$this->settingVars->maintable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->maintable.".GID IN (".$this->settingVars->GID.") ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);        
		if(is_array($result) && !empty($result))		
			$this->jsonOutput['lastMyDateForNielsen'] = $result[0];
    }    
    
    public function getSkuSelectionList()
    {
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);

        $skuList = $configurationCheck->settings['sku_settings'];
        $skuDefaultLoad = $configurationCheck->settings['sku_default_load'];
        
        $tmpArr = array();
        if($configurationCheck->settings['has_sku_filter'])
        {
            if($skuList != "" && $skuDefaultLoad != "")
            {
                $settings = explode("|", $skuList);
                foreach($settings as $key => $field)
                    $fields[] = $field;
                
                $this->buildDataArray($fields, false);
                
                foreach($settings as $key => $field)
                {
                    $fieldPart = explode("#", $field);
                    $fieldName = (count(explode(".", $fieldPart[0])) > 1 ) ? strtoupper($fieldPart[0]) : strtoupper("product.".$fieldPart[0]);
                    $fieldName2 = (count($fieldPart) > 1 && count(explode(".", $fieldPart[1])) > 1) ? strtoupper($fieldPart[1]) : ((count($fieldPart) > 1) ? strtoupper("product.".$fieldPart[1]) : "" );
                    $fieldName = (!empty($fieldName2)) ? $fieldName . "_" . $fieldName2 : $fieldName;
                
                    if($fieldPart[0] == $skuDefaultLoad)
                        $selected = true;
                    else
                        $selected = false;
                    
                    $data = "product.".$this->settingVars->dataArray[$fieldName]['NAME_CSV'];
                    $data = (array_key_exists("ID",$this->settingVars->dataArray[$fieldName])) ? $data."#product.".$this->settingVars->dataArray[$fieldName]['ID_CSV'] : $data;
                    
                    $tmpArr[] = array('label' => $this->settingVars->dataArray[$fieldName]['NAME_CSV'], 'data' => $data, 'selected' => $selected);
                }
            }
        }

        $this->jsonOutput['skuSelectionList'] = $tmpArr;
    }    
    
    public function getGlobalFilterByPage()
    {
        if($this->settingVars->hasGlobalFilter)
        {
            $globalFilterEnabledList = array();
            if(is_array($this->settingVars->pageConfiguration) && !empty($this->settingVars->pageConfiguration))
            {
                foreach($this->settingVars->pageConfiguration as $pageID => $settings ){
                    if(array_key_exists('has_global_filter', $settings)){
                        $globalFilterEnabledList[$pageID] = $settings['has_global_filter'];    
                    }
                }
            }
            $this->jsonOutput['globalFilterEnabledList'] = $globalFilterEnabledList;
        }
    }
    
    public function checkConfiguration(){

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return;
    }    
    
    /** ***
     * COLLECTS ALL TIME SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** 
     */
    public function fetch_all_timeSelection_data() {
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        //COLLECT TIME SELECTION DATA
        switch ($this->settingVars->timeSelectionUnit) {
            case 'days':
                $timeSelectionDataCollectors->getOnlyDays($this->jsonOutput, 'days_');
                break;
			case 'weekYear':
				filters\timeFilter::getYTD($this->settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                $includeDate = (isset($this->settingVars->includeDateInTimeFilter)) ? $this->settingVars->includeDateInTimeFilter : true;
				$timeSelectionDataCollectors->getAllWeek_with_future_dates($this->jsonOutput, 'with_future_', $includeDate);
				break;
            case 'weekMonth':
                filters\timeFilter::getYTDMonth($this->settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                $includeDate = (isset($this->settingVars->includeDateInTimeFilter)) ? $this->settingVars->includeDateInTimeFilter : true;
                $timeSelectionDataCollectors->getAllMonth_with_future_dates($this->jsonOutput, 'with_future_', $includeDate);
                break;                
        }
    }

    public function fetchGlobalFilter()
    {
        $this->jsonOutput['hasGlobalFilter'] = $this->settingVars->hasGlobalFilter;
        if ($this->settingVars->hasGlobalFilter) {
            $globalFilterField = $this->settingVars->globalFilterFieldDataArrayKey;
            
            $globalFilterFieldID = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID'] : $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldIDAlias = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID_ALIASE'] : $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
            $globalFilterFieldName = $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldNameAlias = $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];

            $groupByPart = $selectPart = array();
            $orderByPart = "";
            $selectPart[] = $globalFilterFieldID . " AS PRIMARY_ID";
            $selectPart[] = $globalFilterFieldName . " AS PRIMARY_LABEL";
            $groupByPart[] = "PRIMARY_ID";
            $groupByPart[] = "PRIMARY_LABEL";
            
            if(isset($this->queryVars->projectConfiguration['global_filter_order_by_field']) && $this->queryVars->projectConfiguration['global_filter_order_by_field'] != '')
            {
                $orderByPart = "MAX(CAST(".$this->settingVars->dataArray[$this->settingVars->globalFilterOrderByFieldDataArrayKey]['NAME']." AS UNSIGNED) ) AS ORDER_BY_FIELD ";
            }
            
            $helperTableName = $this->settingVars->dataArray[$globalFilterField]['tablename'];
            $helperLink = $this->settingVars->dataArray[$globalFilterField]['link'];

            $includeIdInLabel = false;
            if (isset($this->settingVars->dataArray[$globalFilterField]['include_id_in_label']))
                $includeIdInLabel = ($this->settingVars->dataArray[$globalFilterField]['include_id_in_label']) ? true : false;

            $this->jsonOutput['globalFilterJsonKey'] = "globalFilter_".$globalFilterFieldNameAlias;
            $this->jsonOutput['globalFilterKey'] = $this->settingVars->globalFilterFieldDataArrayKey;
            $this->jsonOutput['defaultGlobalFilterVal'] = $this->settingVars->defaultGlobalFilterVal;
            datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Data($selectPart, $groupByPart, "globalFilter_".$globalFilterFieldNameAlias, $helperTableName, $helperLink, $this->jsonOutput, $includeIdInLabel, $this->settingVars->global_filter_field, $orderByPart);
            
            /*[START] SAVE productSelectionTabs AND marketSelectionTabs DATA TO THE REDIS CACHE WHICH WILL BE USED ON ExcelPosTracker Export*/
                $redisCache = new utils\RedisCache($this->queryVars);
                $redisCache->requestHash = 'productAndMarketSelectionTabsRedisList';
                $redisOutput = $redisCache->checkAndReadByStaticHashFromCache($redisCache->requestHash);
                $redisOutput['FSG'] = $this->jsonOutput["globalFilter_".$globalFilterFieldNameAlias];
                $redisCache->setDataForStaticHash($redisOutput);
            /*[START] SAVE productSelectionTabs AND marketSelectionTabs DATA TO THE REDIS CACHE WHICH WILL BE USED ON ExcelPosTracker Export*/
        }
    }
    
    /**
     * getAllSkus()
     * It will list all skus
     * 
     * @return void
     */
    public function getAllSkus()
    {
        if (!isset($this->settingVars->skuDataSetting))
            return;

        $skuid = $this->settingVars->skuDataSetting['ID'];
        $sku = $this->settingVars->skuDataSetting['NAME'];
        $skuTable = $this->settingVars->skuDataSetting['tablename'];
        $skuLink = $this->settingVars->skuDataSetting['link'];
        
        $query = "SELECT DISTINCT ". $skuid . " AS SKUID" .
            ", TRIM(" . $sku . ") AS SKU".
            " FROM ". $skuTable . $skuLink .
            " GROUP BY SKUID, SKU ORDER BY SKU ASC";        
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $allSkus    = array();
        
        if($this->projectType == "relayplus")
        {
            foreach($result as $key => $data)
            {
                $temp           = array();
                $temp['data']   = $data['SKUID'];
                $temp['label']  = $data['SKU']." #".$data['SKUID'];
                $allSkus[]  = $temp;
            }
            $this->jsonOutput['skuListRetailLink'] = $allSkus;
        }
        
        if($this->projectType == "tesco-store-daily")
        {
            $allSkus[] = array("data" => "all", "label" => "ALL");
            foreach($result as $key => $data)
            {
                $tmp = array();
                $tmp['data'] = $data['SKUID'];
                $tmp['label'] = trim($data['SKU'])." {".$data['SKUID']."}";
                $allSkus[] = $tmp;
            }
            $this->jsonOutput['skuList'] = $allSkus;
        }
    }
    
    /**
     * getCluster()
     * It will list all clusters
     * 
     * @return void
     */
    public function getCluster()
    {
        $clusterList = array();

        if(isset($this->queryVars->projectConfiguration['has_cluster']) && $this->queryVars->projectConfiguration['has_cluster'] == 1 )
        {
			$query = "SELECT * FROM ".$this->settingVars->clustertable;
			$clusters = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            if(!empty($clusters) && is_array($clusters))
            {
                $clusterData = array();
                foreach($clusters as $data)
                    $clusterData[$data['cl']] = $data['cl_name'];
            }

            if (isset($this->queryVars->projectConfiguration['cluster_default_load']) && !empty($this->queryVars->projectConfiguration['cluster_default_load']))
                $defaultLoad = $this->queryVars->projectConfiguration['cluster_default_load'];
            
            $_REQUEST['clusterID'] = $defaultLoad;
            $this->settingVars->setCluster();

            if (isset($this->queryVars->projectConfiguration['cluster_settings']) && !empty($this->queryVars->projectConfiguration['cluster_settings']))
            {
                $settings = explode("|", $this->queryVars->projectConfiguration['cluster_settings']);
                foreach($settings as $data)
                {
                    $tmp = array();
                    $tmp['cl'] = $data;
                    $tmp['CLUSTER'] = $clusterData[$data];
                    
                    if($defaultLoad == $data)
                        $tmp['defaultLoad'] = 1;
                    else
                        $tmp['defaultLoad'] = 0;
                    
                    $clusterList[] = $tmp;
                }
            }
        }

        $this->jsonOutput['CLUSTER_LIST'] = $clusterList;
    }
    
    function getLatestDateOfMainProject() {
        $result = filters\timeFilter::getLatestMydate($this->settingVars);
        $this->jsonOutput['LatestMainDate'] = date("d M Y", strtotime($result));
    }

    function getLatestDateOfDcTable() {
        $result = str_replace("'","", filters\timeFilter::getLastNDaysDateFromDepotDaily($this->settingVars));
        if($result != "")
            $this->jsonOutput['LatestDcDate'] = date("d M Y", strtotime($result));
        else
            $this->jsonOutput['LatestDcDate'] = "";
    }

    function getLatestDateOfMultsProject() {
        if (isset($this->settingVars->maxYearWeekCombination) && !empty($this->settingVars->maxYearWeekCombination)) {
            $dateCombination = $this->settingVars->maxYearWeekCombination;
            $this->jsonOutput['LatestMultsDate'] = $dateCombination[0] . str_pad($dateCombination[1],2,'0', STR_PAD_LEFT) . 
                ((isset($dateCombination[2]) && !empty($dateCombination[2])) ? " (w/e " . date("D d M Y", strtotime($dateCombination[2])) . ")" : "");
        } else {
            $redisCache = new utils\RedisCache($this->queryVars);

            if(!empty($this->settingVars->yearperiod) && !empty($this->settingVars->weekperiod))
            {
            
                $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
                $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                        ",".$this->settingVars->weekperiod . " AS WEEK" .
                        (($this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                        "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                        "GROUP BY YEAR,WEEK " .
                        "ORDER BY YEAR DESC,WEEK DESC";
                $queryHash = md5($query);
                $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

                if ($redisOutput === false) {
                    $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
                    $redisCache->setDataForSubKeyHash($dateList, $queryHash);
                } else {
                    $dateList = $redisOutput;
                }

                $dateCombination = $dateList[0];

                $this->jsonOutput['LatestMultsDate'] = $dateCombination[0] . $dateCombination[1] . 
                    ((isset($dateCombination[2]) && !empty($dateCombination[2])) ? " (w/e " . date("D d M Y", strtotime($dateCombination[2])) . ")" : "");
                
            }    
        }
    }

    private function getLastMyDate() {
        if (isset($this->settingVars->latestMydate) && !empty($this->settingVars->latestMydate)) {
            $latestMydate = $this->settingVars->latestMydate;
        } else {
            $selectPart = "";
            if(isset($_REQUEST['SIF']) && $_REQUEST['SIF'] == "YES")
                $selectPart = $this->settingVars->DatePeriod;
            else
                $selectPart = $this->settingVars->timetable . ".mydate";
            
            if (empty($selectPart))
                return;

            $query = "SELECT MAX(" . $selectPart . ") AS mydate FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink;

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            $latestMydate = $result[0]["mydate"];
        }
        
        if (!isset($_REQUEST['SIF']))
            $this->jsonOutput['myProductBasedate'] = $latestMydate;
        else
            $this->jsonOutput['myStoreBasedate'] = $latestMydate;
    }
  
    public function buildDataArray($fields, $isCsvColumn) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
		$this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }  
    
    /**
     * getRegions()
     * It will list all regions
     * 
     * @return void
     */
    public function getRegions()
    {
        if (!isset($this->settingVars->regionDataSetting))
            return;

        $regionAccount = $this->settingVars->regionDataSetting['NAME'];
        $regionTable = $this->settingVars->regionDataSetting['tablename'];
        $regionLink = $this->settingVars->regionDataSetting['link'];
        
        $query = "SELECT ".$regionAccount." AS ACCOUNT ".
            "FROM ".$regionTable." ".$regionLink." ".
            "AND ".$regionAccount." IS NOT NULL ".
            "GROUP BY ACCOUNT ".
            "ORDER BY ACCOUNT ASC ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if (!empty($result)) {
            foreach($result as $key=>$data) {
                $dataVal    = ($id==""?$data['ACCOUNT']:$data['ID']);
                $temp   = array(
                     'data'=>$dataVal,
                     'label'=>$data['ACCOUNT']
                );
                $this->jsonOutput['REGION'][] = $temp;
            }
        }
    }  
  
    // FOR TSD SIF PAGES
    public function fetchMarketTabsSettings() {

        if (is_array($this->settingVars->marketOptions_DisplayOptions) && !empty($this->settingVars->marketOptions_DisplayOptions)) {
            foreach ($this->settingVars->marketOptions_DisplayOptions as $key => $marketSelectionTab) {
                $xmlTagAccountName  = $this->settingVars->dataArray[$marketSelectionTab['data']]['NAME'];
                $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$marketSelectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);

                if (isset($this->settingVars->dataArray[$marketSelectionTab['data']]['use_alias_as_tag']))
                    $xmlTag = ($this->settingVars->dataArray[$marketSelectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$marketSelectionTab['data']]['NAME_ALIASE']) : $xmlTag;

                if (is_array($this->jsonOutput[$xmlTag]) && !empty($this->jsonOutput[$xmlTag])) {
                    if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
                    {
                        $this->settingVars->marketOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput['commonFilter'][$xmlTag]['dataList'];
                        $this->settingVars->marketOptions_DisplayOptions[$key]['selectedDataList'] = (isset($this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'] : array();
                    }
                    else
                        $this->settingVars->marketOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput[$xmlTag];
                }
            }
        }

        $this->jsonOutput['marketSelectionTabs'] = $this->settingVars->marketOptions_DisplayOptions;
    }
    
    // FOR TSD SIF PAGES
    public function fetch_all_marketSelection_data() 
    {
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING     

        if (!empty($dataHelpers)) {
            
            foreach ($dataHelpers as $key => $account) {
                if($account != "")
                {
                    //IN RARE CASES WE NEED TO ADD ADITIONAL FIELDS IN GROUP BY CLUAUSE AND ALSO AS LABEL WITH THE ORIGINAL ACCOUNT
                    //E.G: FOR RUBICON LCL - SKU DATA HELPER IS SHOWN AS 'SKU #UPC'
                    //IN ABOVE CASE WE SEND DH VALUE = F1#F2 [ASSUMING F1 AND F2 ARE SKU AND UPC'S INDEX IN DATAARRAY]
                    $combineAccounts = explode("#", $account);
                    $selectPart = array();
                    $groupByPart = array();
                    
                    foreach ($combineAccounts as $accountKey => $singleAccount) {
                        $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                        if ($tempId != "") {
                            $selectPart[] = $tempId . " AS " . getAdjectiveForIndex($accountKey) . '_ID';
                            $groupByPart[] = getAdjectiveForIndex($accountKey) . '_ID';
                        }
                        
                        $tempName = $this->settingVars->dataArray[$singleAccount]['NAME'];
                        $selectPart[] = $tempName . " AS " . getAdjectiveForIndex($accountKey) . '_LABEL';
                        $groupByPart[] = getAdjectiveForIndex($accountKey) . '_LABEL';
                        
                    }
                    
                    $helperTableName = $this->settingVars->dataArray[$combineAccounts[0]]['tablename'];
                    $helperLink = $this->settingVars->dataArray[$combineAccounts[0]]['link'];
                    $tagNameAccountName = $this->settingVars->dataArray[$combineAccounts[0]]['NAME'];

                    //IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
                    //AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
                    $tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'] : strtoupper($tagNameAccountName);

                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']))
                        $tagName = ($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']) : $tagName;

                    $includeIdInLabel = false;
                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']))
                        $includeIdInLabel = ($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']) ? true : false;
                    
                    if($this->settingVars->dataArray[$combineAccounts[0]]['TYPE'] == 'M')
                    {
                        datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Data($selectPart, $groupByPart, $tagName, $helperTableName, $helperLink, $this->jsonOutput, $includeIdInLabel, $account);
                    }
                }
            }
        }
    } 

    /*[START] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
    /*public function getAllProductAndMarketInlineFilterData(){
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
        
        if(empty($redisOutput))
            return ;

        $selectionFields = []; $selectionTables = []; 
        foreach ($redisOutput as $key => $value) {
            if(!empty($value)){
                $tbl = '';
                foreach ($value as $k => $val) {
                    if(!empty($val)){
                        $tbl = explode('.', $val);
                        if(!empty($tbl[0])){
                             $selectionTables[] = $tbl[0];
                             $selectionFields[] = $val;
                        }
                    }
                }
            }
        }

        $this->measureFields = array_values($selectionFields);
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        //print_r($this->queryPart);
        $query = "SELECT DISTINCT ".implode(',', $this->measureFields)." FROM ".$this->settingVars->tablename." ". $this->queryPart;

        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        return $result;
    }*/

    private function getAllProductAndMarketInlineFilterData(){

        $redisCache = new utils\RedisCache($this->queryVars);
        $dataProductInlineFields = $this->settingVars->dataProductInlineFields;
        $filterDataProductInlineConfig = $this->settingVars->filterDataProductInlineConfig;
        $this->measureFields = array_values($dataProductInlineFields);
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $query = "SELECT DISTINCT ".implode(',', $filterDataProductInlineConfig)." FROM ".$this->settingVars->tablename." ". $this->queryPart;
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $this->jsonOutput['productAndMarketFilterData'] = $result;
        unset($this->settingVars->dataProductInlineFields);
        unset($this->settingVars->filterDataProductInlineConfig);
    }
    /*[END] GETTING DATA FOR THE PRODUCT AND MARKET INLINE SELECTION FILTER*/
}
?>