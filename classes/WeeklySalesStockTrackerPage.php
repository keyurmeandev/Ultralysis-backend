<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class WeeklySalesStockTrackerPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $redisCache;
    public $isExport;
    public $aggregateSelection;

    public function __construct() {
        $this->jsonOutput = array();
    }

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->isExport = false;

        if ($this->settingVars->isDynamicPage) 
        {
            if (isset($this->settingVars->gridFilterDDMapping) && !empty($this->settingVars->gridFilterDDMapping)) {
              $this->jsonOutput['gridFilterDDMapping'] = $this->settingVars->gridFilterDDMapping;
            }

            $this->productAndMarketFilterData = $this->redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
            
            if(empty($this->productAndMarketFilterData))
                $this->productAndMarketFilterData = $this->prepareProductAndMarketFilterData();

            $account = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
           
            $fields[] = $account[0];
            $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }

            $this->buildDataArray($fields);
            $this->buildPageArray();

            $accountFieldPart = explode("#", $account[0]);
            $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
            $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

            $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];    
            $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
            $this->accountCsvName = $this->settingVars->dataArray[$accountField]['NAME_CSV'];
            
            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["BAR_ACCOUNT_TITLE" => $this->displayCsvNameArray[$vl], "ACCOUNT" => strtoupper($this->dbColumnsArray[$vl])];
                }
            }
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') 
        {
            $ExtraCols = [];
            if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0)
            {
                foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) 
                {
                    $this->jsonOutput["extraCols"][] = array("fieldName" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], "title" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']);
                }
            }

            $this->dh = $this->settingVars->pageArray[$this->settingVars->pageName]['DH'];
            // $productFilterData = $this->getAllFilterData();
            $filterDataConfig = array();
            
            $dataHelpers = explode("-", $this->dh);
            $defaultSelectedField = '';
            $rnkDyArr = [];

            if (!empty($dataHelpers)) {
                foreach ($dataHelpers as $key => $account) {
                    if($account != "") {
                        $combineAccounts = explode("#", $account);
                        foreach ($combineAccounts as $accountKey => $singleAccount) {
                                $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                                if ($tempId != "")
                                    $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'];
                                else
                                    $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                                
                                $filterDataConfig[$keyName]["data"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'] : $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                                
                                $filterDataConfig[$keyName]["label"] = $this->settingVars->dataArray[$singleAccount]['NAME_CSV'];
                                
                                $filterDataConfig[$keyName]["indexName"] = ($tempId != "") ? 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME'])."_".strtoupper($this->settingVars->dataArray[$singleAccount]['ID']).']' : 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME']).']';

                                $filterDataConfig[$keyName]["field"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME']."##".$this->settingVars->dataArray[$singleAccount]['ID'] : $this->settingVars->dataArray[$singleAccount]['NAME'];
                                
                                $filterDataConfig[$keyName]["TYPE"] = $this->settingVars->dataArray[$singleAccount]['TYPE'];
                                
                                $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]+1 : 1;

                                $filterDataConfig[$keyName]["RANK"] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] : 1;
                                
                                $filterDataConfig[$keyName]["dataList"] = array();
                                $filterDataConfig[$keyName]["selectedDataList"] = array();

                        }
                    }
                }
            }

            /*[START] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/
            $typeArray = array_column($filterDataConfig, 'TYPE');
            $rankArray = array_column($filterDataConfig, 'RANK');
            array_multisort($typeArray, SORT_ASC, SORT_STRING, $rankArray, SORT_ASC, SORT_NUMERIC, $filterDataConfig);

            if(!empty($filterDataConfig)) {
                $cntr = 1;
                foreach ($filterDataConfig as $ky => $vl) {
                $filterDataConfig[$ky]['RANK'] = $cntr;
                    $cntr++;
                }
            }
            /*[END] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/

            //$this->jsonOutput["filterDataConfig"] = $filterDataConfig;
            $defaultSelectedField = array_values($filterDataConfig)[0];
            $this->jsonOutput["defaultSelectedField"] = $defaultSelectedField['field'];
            $this->jsonOutput["defaultSelectedFieldName"] = $defaultSelectedField['label'];
            //$this->jsonOutput["productAndMarketFilterData"] = $productFilterData;
        }
        
        if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchGrid' || $_REQUEST['action'] == 'export')){
            if($_REQUEST['action'] == 'export')
                $this->isExport = true;
                
            $this->gridData();
        } else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchInlineMarketAndProductFilter')) {
            $this->fetchInlineMarketAndProductFilterData();
        } else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'skuChange')) {
            $this->changeSku();
        }

        return $this->jsonOutput;
    }

    public function fetchInlineMarketAndProductFilterData()
    {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true); //collecting time selection data
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function buildPageArray() 
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {

            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );

            /*[START] CODE FOR INLINE FILTER STYLE*/
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }

        /*[START] SETTING THE AGGREGATE SELECTION DATA */
        if(isset($_REQUEST['aggregateSelection']) && !empty($_REQUEST['aggregateSelection']))
            $this->aggregateSelection = $_REQUEST['aggregateSelection'];
        else
            $this->aggregateSelection = 'weeks';
        /*[END] SETTING THE AGGREGATE SELECTION DATA */

        return;
    }    
    
    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }    
    
    public function gridData() 
    {
        $requestedYear = '';
        $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
        $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput, $requestedYear, $showAsOutput);

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $this->accountID;
        
        $ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {

                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']];

                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }       

        if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $this->accountName = $this->jsonOutput["defaultSelectedField"];
        } 
        

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;

        $aggDateFromat        = '%e-%c';
        $aggDateColHeadFormat = 'D';
        $aggDateArrayFormat   = 'j-n';
        $aggDate              = 'd-M-Y';
        if($this->aggregateSelection == 'weeks'){
            //$aggDateFromat = '%u-%b';
            //$aggDateColHeadFormat = 'W-M';
            //$aggDateArrayFormat = 'W-M';
            //$aggDate = 'Y-m-W';
            $aggDateFromat = '%u';
            $aggDateColHeadFormat = 'W';
            $aggDateArrayFormat = 'W';
            $aggDate = 'Y-W';
        }
        else if($this->aggregateSelection == 'months'){
            $aggDateFromat = '%M';
            $aggDateColHeadFormat = 'M';
            $aggDateArrayFormat = 'F';
            $aggDate = 'Y-m';
        }
        
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $dtKey => $tyDate) {

                //$colsHeader['TY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($tyDate)), "MYDATE" => $tyDate, "DAY" => date('D', strtotime($tyDate)));
                $tmpDtFmt = date($aggDate, strtotime($tyDate));
                if(!isset($dateArray[$tmpDtFmt])){
                    $colsHeader['TY'][] = array("FORMATED_DATE" => date($aggDate, strtotime($tyDate)), "MYDATE" => $tmpDtFmt, "DAY" => date($aggDateColHeadFormat, strtotime($tyDate)));

                    $colsHeader['LY'][] = array("FORMATED_DATE" => date($aggDateColHeadFormat, strtotime($tyDate)), "MYDATE" => $tmpDtFmt);
                
                    $dateArray[$tmpDtFmt] = date($aggDateArrayFormat, strtotime($tyDate));
                    

                    //[START] Getting LY date array
                    if(isset($this->settingVars->lyDates) && isset($this->settingVars->lyDates[$dtKey])){
                        $lyDates = $this->settingVars->lyDates[$dtKey];
                        //$colsHeader['LY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($lyDates)), "MYDATE" => $lyDates, "DAY" => date('D', strtotime($lyDates)));
                        $tyLyDateArrMapping[$dateArray[$tmpDtFmt]] = date($aggDateArrayFormat, strtotime($lyDates));
                    }
                }
                //[END] Getting LY date array
            }
        }

        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        $productTable = $this->settingVars->skutable;

        $timeFilterQue   = filters\timeFilter::$tyWeekRange;
        $timeFilterQueLy = filters\timeFilter::$lyWeekRange;
      //  $timeFilterQueFieldExtraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere;

        $storeSelect = 
            "SUM(".$this->settingVars->ProjectVolume."/".$productTable.".PPC) as SUM_SALES_CASES, ".
            "SUM(".$this->settingVars->ProjectValue."/".$this->settingVars->ProjectVolume.") as AVE_SELLING_PRICE, ".
            "SUM(".$maintable.".AveStoreStock) as STORE_STOCK_CASES, ". 
            "SUM(".$maintable.".AveDepotStock) as DEPOT_STOCK_CASES, ".
            "SUM(".$maintable.".WhsOrdered) as DISPATCH_CASES, ".
            "SUM(".$maintable.".WhsReceived) as SHORTED_CASES " ;

        $query = "SELECT ".$this->accountID." AS ACCOUNT, "
            .$this->accountName." as SKU, " 
            ." MAX(".$maintable.".period) as MYDATE ".
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") . ", "
            .$maintable.".period as FORMATED_DATE, "
            .$storeSelect. 
            " FROM " . $this->settingVars->tablename .$this->queryPart . 
            " AND (" .$timeFilterQue. ") ". 
            " GROUP BY ACCOUNT, FORMATED_DATE, SKU  ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY MYDATE,ACCOUNT ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $havingTYValue = "DAILY_FCAST";
        $dataPnameSum = [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $seasonalData) {
                $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        $accountArray = $gridResult = $columnsHeader = $finalExeclData = array();
        
            
        $dateArray = array_unique(array_column($result, 'MYDATE'));
        
        foreach (array_keys($seasonalDataArray) as $account) {
           
            $tmpSalesCases['ACCOUNT']      = $account;
            $tmpSalesCases['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmpAveSell['ACCOUNT']      = $account;
            $tmpAveSell['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmpStoreStock['ACCOUNT']   = $account;
            $tmpStoreStock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmpDepotStock['ACCOUNT']   = $account;
            $tmpDepotStock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;


            $tmpDispatchStock['ACCOUNT']   = $account;
            $tmpDispatchStock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;


            $tmpShortedStock['ACCOUNT']   = $account;
            $tmpShortedStock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;


            foreach ($dateArray as $dayMydate => $dayMonth) {
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    $data = $seasonalDataArray[$account][$dayMonth];
                    
                    $dtKey = 'dt'.$dayMonth;

                    foreach ($ExtraCols as $extraCols) {
                        $tmpSalesCases[$extraCols['NAME_ALIASE']]     = $data[$extraCols['NAME_ALIASE']];
                        $tmpAveSell[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $tmpStoreStock[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $tmpDepotStock[$extraCols['NAME_ALIASE']]  = $data[$extraCols['NAME_ALIASE']];
                        $tmpDispatchStock[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $tmpShortedStock[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                    }
                    
                    $tmpSalesCases[$dtKey] = $data['SUM_SALES_CASES']*1;
                    $tmpSalesCases['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpSalesCases['SKU'] = $data['SKU'];
                    $tmpSalesCases['ROWDESC'] = 'Sum Of Sales Cases';

                    $tmpAveSell[$dtKey] = $data['AVE_SELLING_PRICE']*1;
                    $tmpAveSell['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpAveSell['SKU'] = $data['SKU'];
                    $tmpAveSell['ROWDESC'] = 'Sum Of Ave Selling Price';
                    
                    $tmpStoreStock[$dtKey] = $data['STORE_STOCK_CASES']*1;
                    $tmpStoreStock['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpStoreStock['SKU'] = $data['SKU'];
                    $tmpStoreStock['ROWDESC'] = 'Sum Of Store Stock Cases';

                    $tmpDepotStock[$dtKey] = $data['DEPOT_STOCK_CASES']*1;
                    $tmpDepotStock['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpDepotStock['SKU'] = $data['SKU'];
                    $tmpDepotStock['ROWDESC'] = 'Sum Of Depot Stock Cases';

                    $tmpDispatchStock[$dtKey] = $data['DISPATCH_CASES']*1;
                    $tmpDispatchStock['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpDispatchStock['SKU'] = $data['SKU'];
                    $tmpDispatchStock['ROWDESC'] = 'Sum Of Cases Dispatch';

                    $tmpShortedStock[$dtKey] = $data['SHORTED_CASES']*1;
                    $tmpShortedStock['ACCOUNT'] = $data['ACCOUNT'];
                    $tmpShortedStock['SKU'] = $data['SKU'];
                    $tmpShortedStock['ROWDESC'] = 'Sum Of Cases Shorted';
                    
                    $dateYear = substr($dayMonth, 0, 4);
                    $dateDate = substr($dayMonth, -2);
                    $dateData = $dateYear.'-'.$dateDate;
                    $columnsHeader[$dtKey] = array("FORMATED_DATE" => $dateData, "MYDATE" => $dateData, 'DAY' => $dateDate);
                }
            }
            $finalExeclData[$account][] = $tmpSalesCases;
            $finalExeclData[$account][] = $tmpAveSell;
            $finalExeclData[$account][] = $tmpStoreStock;
            $finalExeclData[$account][] = $tmpDepotStock;
            $finalExeclData[$account][] = $tmpDispatchStock;
            $finalExeclData[$account][] = $tmpShortedStock;
        }

        $allCumSortedKeys = array_keys($finalExeclData);

        $finalData = []; $finalExeclDataSorted = [];


        $finalResult = $pinArray = array();
        $cnt = 0;
        foreach ($allCumSortedKeys as $sKey => $sVal) {
            foreach ($finalExeclData[$sVal] as $key => $value) {
                $finalResult[$cnt] = $value;
                if(in_array($sVal, $pinArray)) {
                    // unset($finalResult[$cnt]['ACCOUNT']);
                    unset($finalResult[$cnt]['SKU']);
                }
                $pinArray[] = $sVal;
               $cnt++;
            }
            $cnt++;
        }

        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }

        $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
        $this->jsonOutput['accountCsvName'] = $this->accountCsvName;
        $this->jsonOutput['gridAllColumnsHeader'] = $columnsHeader;
        $this->jsonOutput['gridData'] = array_values($finalResult);
    }

    public function getAllFilterData(){
        $selectionFields = []; $selectionTables = []; 
        foreach ($this->productAndMarketFilterData as $key => $value) {
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
        $query = "SELECT DISTINCT ".implode(',', $this->measureFields)." FROM ".$this->settingVars->tablename." ". $this->queryPart;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        return $result;
    }

    /**
     * skuSelect()
     * It will prepare graph data as per selected SKU
     *
     * @return void
     */
    function changeSku() {
        $pin="";
        $chartData = array();
        if(isset($_REQUEST['ACCOUNTPIN'])){
           $pin = $_REQUEST['ACCOUNTPIN'];
        }
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        

        $maintable = $this->settingVars->maintable;
        $productTable = $this->settingVars->skutable;

        $timeFilterQue   = filters\timeFilter::$tyWeekRange;
        $timeFilterQueLy = filters\timeFilter::$lyWeekRange;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();     
          $storeSelect = 
            "SUM(".$this->settingVars->ProjectVolume."/".$productTable.".PPC) as SUM_SALES_CASES, ".
            "SUM(".$this->settingVars->ProjectValue."/".$this->settingVars->ProjectVolume.") as AVE_SELLING_PRICE, ".
            "SUM(".$maintable.".AveStoreStock) as STORE_STOCK_CASES, ". 
            "SUM(".$maintable.".AveDepotStock) as DEPOT_STOCK_CASES, ".
            "SUM(".$maintable.".WhsOrdered) as DISPATCH_CASES, ".
            "SUM(".$maintable.".WhsReceived) as SHORTED_CASES " ;

        $query = "SELECT ".$this->accountID." AS ACCOUNT, "
            .$this->accountName." as SKU, " 
            ." MAX(".$maintable.".period) as MYDATE ".
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") . ", "
            .$maintable.".period as FORMATED_DATE, "
            .$storeSelect. 
            " FROM " . $this->settingVars->tablename .$this->queryPart." AND ".$this->accountID." IN ('".$pin."') ".
            " AND (" .$timeFilterQue . ") ". 
            " GROUP BY ACCOUNT, FORMATED_DATE, SKU  ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY MYDATE,ACCOUNT ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $title = $result[0]['SKU'];
        $table = array();
        /* Get data array*/
        if (is_array($result) && !empty($result)) {

            foreach ($result as $key => $row) {

                $dateYear = substr($row['MYDATE'], 0, 4);
                $dateDate = substr($row['MYDATE'], -2);
                $weekRange = $dateYear.'-'.$dateDate;
                $i = $weekRange; 
                $chartData['STORE_STOCK_CASES'][]=$row['STORE_STOCK_CASES'];
                $chartData['DEPOT_STOCK_CASES'][]=$row['DEPOT_STOCK_CASES'];
                $chartData['DISPATCH_CASES'][]=$row['DISPATCH_CASES'];
                $chartData['SHORTED_CASES'][]=$row['SHORTED_CASES'];
                $chartData['AVE_SELLING_PRICE'][]=$row['AVE_SELLING_PRICE'];
                $chartData['SUM_SALES_CASES'][]=$row['SUM_SALES_CASES'];
                $chartData['week'][] = $weekRange ; 

            }

            $table['rows']  =$chartData;
            $table['title'] =$title;
        }

        $this->jsonOutput['distributionLinechart'] = $table;
    }

}
?>