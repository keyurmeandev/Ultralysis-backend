<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class DailyTrackerVolumePage extends config\UlConfig {

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
            $fieldPart = explode("#", $account[0]);
            $fields[] = $this->accountField = $fieldPart[0];
            $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }

            $this->buildDataArray($fields);
            $this->buildPageArray();

            $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
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
            $productFilterData = $this->getAllFilterData();
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
            $this->aggregateSelection = 'days';
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
        
        $ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {

                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']];

                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }       

        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $this->accountName = $_REQUEST['ACCOUNT'];
        } else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $this->accountName = $this->jsonOutput["defaultSelectedField"];
        } else {
            $this->accountName = $this->pnameField;
            $this->measureFields[] = $this->pnameField;
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
/*
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            $arrayTmp['TY'] = [];
            $arrayTmp['LY'] = [];
            
            if (count($this->settingVars->tyDates) > count($this->settingVars->lyDates)) {
                $dates = $this->settingVars->tyDates;
                $dateKey = "TY";
            } else {
                $dates = $this->settingVars->lyDates;
                $dateKey = "LY";
            }

            foreach ($dates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyDateHasError = $lyDateHasError = false;

                if ($dateKey == 'TY') {
                    $tyMydatePart[0] = $tyMydatePart[0]-1;
                    if ($this->aggregateSelection == 'days' && !checkdate($tyMydatePart[1], $tyMydatePart[2], $tyMydatePart[0])) {
                        $lyDateHasError = true;
                        $tyMydatePart[0] = $tyMydatePart[0]+1;
                    }
                    $lyDate = implode('-', $tyMydatePart);
                } else {
                    $lyDate = $tyDate;
                    $tyMydatePart[0] = $tyMydatePart[0]+1;
                    if ($this->aggregateSelection == 'days' && !checkdate($tyMydatePart[1], $tyMydatePart[2], $tyMydatePart[0])) {
                        $tyDateHasError = true;
                        $tyMydatePart[0] = $tyMydatePart[0]-1;
                    }

                    $tyDate = implode('-', $tyMydatePart);
                }
                
                $tmpDtFmt = date($aggDate, strtotime($tyDate));
                if(!isset($arrayTmp['TY'][$tmpDtFmt])){
                    $dateArray[$tmpDtFmt] = date($aggDateArrayFormat, strtotime($tyDate));
                    $colsHeader['TY'][] = array("FORMATED_DATE" => ((!$tyDateHasError) ? date($aggDateColHeadFormat, strtotime($tyDate)) : "NULL"), "MYDATE" => $tmpDtFmt);
                    $arrayTmp['TY'][$tmpDtFmt] = $tmpDtFmt;
                }

                $tmpDtLyFmt = date($aggDate, strtotime($lyDate));
                if(!isset($arrayTmp['LY'][$tmpDtLyFmt])){
                    if (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) {
                        $sign = ($_REQUEST['facings'] >= 0) ? " + " : " - ";
                        $lyDate = date('Y-m-d', strtotime($lyDate . $sign . abs($_REQUEST['facings']) . ' days'));
                    }

                    $colsHeader['LY'][] = array("FORMATED_DATE" => ((!$lyDateHasError) ? date($aggDateColHeadFormat, strtotime($lyDate)) : "NULL"), "MYDATE" => $tmpDtLyFmt);
                    $arrayTmp['LY'][$tmpDtLyFmt] = $tmpDtLyFmt;
                }
            }
        }*/

        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        /*$query = "SELECT ".$this->settingVars->volSellthruActualsTblPinField." AS PIN, ".$this->settingVars->volSellthruActualsTblDateField." AS DATE, MAX(".$this->settingVars->volSellthruActualsTblQtyField.") as QTY FROM ".$this->settingVars->volumeSellthruActualsTable." WHERE ".$this->settingVars->volSellthruActualsTblPinField." IN (SELECT DISTINCT ".$this->settingVars->mainTblPinField." FROM ".$this->settingVars->maintable." WHERE gid = ".$this->settingVars->GID." AND clientid = '".$this->settingVars->clientID."') GROUP BY PIN, DATE";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $actualSalesArray = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
                $actualSalesArray[$data['PIN'].$data['DATE']] = $data['QTY'];
        }*/

        /*$tyFromDate = $this->settingVars->fromToDateRange['fromYear'].'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
        $tyToDate   = $this->settingVars->fromToDateRange['toYear'].'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];
        $timeFilterQue = $maintable.".mydate BETWEEN '".$tyFromDate."' AND '".$tyToDate."' ";*/

        $timeFilterQue   = filters\timeFilter::$tyWeekRange;
        $timeFilterQueLy = filters\timeFilter::$lyWeekRange;
        $timeFilterQueFieldExtraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere;

        $storeSelect = "SUM(".$maintable.".store_stock) as STORE_STOCK, ". 
            "SUM(".$maintable.".stores_selling) as STORES_SELLING, ". 
            "SUM(".$maintable.".stores_stocked) as STORES_STOCKED, ". 
            "SUM(".$maintable.".stores_traited) as STORES_RANGED, ";

        if($this->aggregateSelection == 'weeks') {
            $storeSelect = "AVG(".$maintable.".store_stock) as STORE_STOCK, ". 
            "AVG(".$maintable.".stores_selling) as STORES_SELLING, ". 
            "AVG(".$maintable.".stores_stocked) as STORES_STOCKED, ". 
            // "AVG(".$maintable.".stores_traited) as STORES_RANGED, ";
            "AVG((CASE WHEN ".$maintable.".stores_traited > 0 THEN ".$maintable.".stores_traited END)) AS  STORES_RANGED, ";
        }

        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            "MAX(".$maintable.".".$this->settingVars->mainTblPinField.") as PIN, " . 
            "MAX(".$maintable.".mydate) as MYDATE ".
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") . 
            ", DATE_FORMAT(".$maintable.".mydate, '".$aggDateFromat."') as FORMATED_DATE, ". 
            " CASE WHEN  ".$timeFilterQue." THEN 'TY' ELSE 'LY' END AS ISTYLY,". 
            " CASE WHEN (SUM(".$maintable.".forecast1) > 0 ) THEN SUM(".$maintable.".qty) ELSE 0 END AS TRACKED_ACTUAL_SALES,". 
            "SUM(".$maintable.".qty) as QTY, ". 
            $storeSelect. 
            "SUM(".$maintable.".forecast1) as STC_FORECAST, " . 
            "SUM(".$maintable.".forecast2) as LY_TOTAL_BUY " . 
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" .$timeFilterQue. " OR ".$timeFilterQueLy.") ". 
            // " AND ".$maintable.".".$this->settingVars->mainTblPinField." = 5515820 ".
            //" AND ".$this->settingVars->skutable.".agg6 LIKE '%XMAS%' ". 
            $timeFilterQueFieldExtraWhere. 
            " GROUP BY ACCOUNT, FORMATED_DATE, ISTYLY ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY MYDATE ASC";


        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $finalData = [];
        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        $havingTYValue = "DAILY_FCAST";
        $total_buy= [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $seasonalData) {
                $seasonalData[$havingTYValue] = $seasonalData['STC_FORECAST'];
                $seasonalData['QTY'] = $seasonalData['QTY']*1;
                $seasonalData['STC_FORECAST'] = $seasonalData['STC_FORECAST']*1;
                $seasonalData['ACTUAL_VS_FORECAST'] = ($seasonalData['QTY'] - $seasonalData['STC_FORECAST']);

                $total_buy[$seasonalData['ACCOUNT']][$seasonalData['ISTYLY']] +=$seasonalData['LY_TOTAL_BUY'];

                if(isset($seasonalData['ISTYLY']) && $seasonalData['ISTYLY'] == 'LY') {                    
                    $seasonalDataLyArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                }else{
                    $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                    $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
                }


            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        $allActualSalesAndSTCForecastCum = [];
        $lastDt = array_keys($dateArray);
        $lastDate = 'dt'.str_replace('-','',end($lastDt));
        
        $cnt = 0; $cmpTyTotal = $cmpLyTotal = [];
        
        foreach (array_keys($seasonalDataArray) as $account) {
            $tmp = $tmp1 = $tmp2 = $tmp3 = $cumTmp = $cumTmp1 = $cumTmp3 = $totalTrackerCum = array();
            $ly_actual_sales = $ly_actual_sales_cum = $actual_vs_ly_cum = $ly_cum_vs_ly_total_buy= array();
            $stores_selling = $store_stock = $stores_stocked = $stores_ranged = $actual_vs_forecast = $actual_vs_forecast_cum = $total_buy_ly = array();
            $cumActualValue3 = 0;
            
            $tmp['ACCOUNT'] = $account;
            $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp1['ACCOUNT'] = $account;
            $tmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp2['ACCOUNT'] = $account;
            $tmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp3['ACCOUNT'] = $account;
            $tmp3['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp['ACCOUNT'] = $account;
            $cumTmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp3['ACCOUNT'] = $account;
            $cumTmp3['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp1['ACCOUNT'] = $account;
            $cumTmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $ly_actual_sales['ACCOUNT'] = $account;
            $ly_actual_sales['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $ly_actual_sales_cum['ACCOUNT'] = $account;
            $ly_actual_sales_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_ly['ACCOUNT'] = $account;
            $actual_vs_ly['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_ly_cum['ACCOUNT'] = $account;
            $actual_vs_ly_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;


            $actual_vs_forecast['ACCOUNT'] = $account;
            $actual_vs_forecast['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_forecast_cum['ACCOUNT'] = $account;
            $actual_vs_forecast_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tas_vs_forcast_cum['ACCOUNT'] = $account;
            $tas_vs_forcast_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tracked_vs_ly_cum['ACCOUNT'] = $account;
            $tracked_vs_ly_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_selling['ACCOUNT'] = $account;
            $stores_selling['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $store_stock['ACCOUNT'] = $account;
            $store_stock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_stocked['ACCOUNT'] = $account;
            $stores_stocked['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_ranged['ACCOUNT'] = $account;
            $stores_ranged['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $total_buy_ly['ACCOUNT'] = $account;
            $total_buy_ly['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
           
            

            $ly_cum_vs_ly_total_buy['ACCOUNT'] = $account;
            $ly_cum_vs_ly_total_buy['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $totalTrackerCum[$account] = array_sum(array_column($seasonalDataArray[$account], $havingTYValue));
            
            $cumTyValue = $cumActualValue = $cumActualVsForecast = 0;
            $cumActualLyValue = 0; 
            $STCForecastValueCalculationFlag = 0;
            foreach ($dateArray as $dayMydate => $dayMonth) {

                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$dayMydate);

                    foreach ($ExtraCols as $extraCols) {
                        $tmp[$extraCols['NAME_ALIASE']]     = $data[$extraCols['NAME_ALIASE']];
                        $tmp1[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $tmp3[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $cumTmp[$extraCols['NAME_ALIASE']]  = $data[$extraCols['NAME_ALIASE']];
                        $cumTmp1[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $cumTmp3[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $tmp2[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];

                        $ly_actual_sales[$extraCols['NAME_ALIASE']]     = $data[$extraCols['NAME_ALIASE']];
                        $ly_actual_sales_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $actual_vs_ly[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $actual_vs_ly_cum[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];

                        $actual_vs_forecast[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $actual_vs_forecast_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $tas_vs_forcast_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $stores_selling[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $store_stock[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $stores_stocked[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $stores_ranged[$extraCols['NAME_ALIASE']]  = $data[$extraCols['NAME_ALIASE']];
                        $tracked_vs_ly_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $total_buy_ly[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $ly_cum_vs_ly_total_buy[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                    }
                    
                    $tmp[$dtKey] = $data[$havingTYValue]*1;
                    $tmp['PIN'] = $data['PIN'];
                    $tmp['RANK'] = 1;
                    $tmp['ROWDESC'] = 'Forecast';
                    $tmp['GRIDMAPPING'] = [1, 3, 4];
                    $tmp['highlightRow'] = 1;

                    $tmp1[$dtKey] = $data['QTY']*1;
                    $tmp1['PIN'] = $data['PIN'];
                    $tmp1['RANK'] = 5;
                    $tmp1['ROWDESC'] = 'Actual Sales';
                    $tmp1['GRIDMAPPING'] = [2, 3];
                    $tmp1['highlightRow'] = 2;
                    
                    $cumTyValue += $data[$havingTYValue];
                    $cumTmp[$dtKey] = $cumTyValue;
                    $cumTmp['PIN'] = $data['PIN'];
                    $cumTmp['RANK'] = 2;
                    $cumTmp['ROWDESC'] = 'Forecast Cum.';
                    $cumTmp['GRIDMAPPING'] = [1, 4];
                    $cumTmp['highlightRow'] = 1;
                    
                    $cumActualValue += $data['QTY'];
                    $cumTmp1[$dtKey] = $cumActualValue;
                    $cumTmp1['PIN'] = $data['PIN'];
                    $cumTmp1['RANK'] = 6;
                    $cumTmp1['ROWDESC'] = 'Actual Sales Cum.';
                    $cumTmp1['GRIDMAPPING'] = [2];
                    $cumTmp1['highlightRow'] = 1;

                    $tmp3[$dtKey] = $data['TRACKED_ACTUAL_SALES']*1;
                    $tmp3['PIN'] = $data['PIN'];
                    $tmp3['RANK'] = 3;
                    $tmp3['ROWDESC'] = 'Tracked Actual Sales (TAS)';
                    $tmp3['GRIDMAPPING'] = [4];
                    $tmp3['highlightRow'] = 1;

                    $cumActualValue3 += $data['TRACKED_ACTUAL_SALES'];
                    $cumTmp3[$dtKey] = $cumActualValue3;
                    $cumTmp3['PIN'] = $data['PIN'];
                    $cumTmp3['RANK'] = 4;
                    $cumTmp3['ROWDESC'] = 'Tracked Actual Sales Cum.';
                    $cumTmp3['GRIDMAPPING'] = [4];
                    $cumTmp3['highlightRow'] = 1;

                        /*[START] GETTING LY SALES SEASONAL DATA */                        
                        $lyDate = $tyLyDateArrMapping[$dayMonth];
                        $lyData = (isset($seasonalDataLyArray[$account][$lyDate])) ? $seasonalDataLyArray[$account][$lyDate] : '';
                        $lyQty = isset($lyData['QTY']) ? $lyData['QTY']*1 : 0;
                        $lyPin = isset($lyData['PIN']) ? $lyData['PIN'] : $data['PIN'];
                        $ly_actual_sales[$dtKey]    = $lyQty;

                        $ly_actual_sales['PIN']     = $lyPin;
                        $ly_actual_sales['RANK']    = 7;
                        $ly_actual_sales['ROWDESC'] = 'LY Actual Sales';
                        $ly_actual_sales['highlightRow'] = 2;
                        $ly_actual_sales['GRIDMAPPING'] = [2];
                        
                        $cumActualLyValue += $lyQty;
                        $ly_actual_sales_cum[$dtKey]    = $cumActualLyValue;
                        $ly_actual_sales_cum['PIN']     = $lyPin;
                        $ly_actual_sales_cum['RANK']    = 8;
                        $ly_actual_sales_cum['ROWDESC'] = 'LY Actual Sales Cum.';
                        $ly_actual_sales_cum['highlightRow'] = 1;
                        $ly_actual_sales_cum['GRIDMAPPING'] = [2];

                        $actual_vs_ly[$dtKey] = $cumActualValue - $cumActualLyValue;
                        $actual_vs_ly['PIN'] = $lyPin;
                        $actual_vs_ly['RANK'] = 9;
                        $actual_vs_ly['ROWDESC'] = 'Actual vs LY Cum.';
                        $actual_vs_ly['highlightRow'] = 4;
                        $actual_vs_ly['GRIDMAPPING'] = [2];
                        //$actual_vs_ly['DECIMALPOINT'] = [1];

                        $actual_vs_ly_cum[$dtKey] = ($cumActualLyValue > 0) ? ((($cumActualValue/$cumActualLyValue)-1)*100) : 0;
                        $actual_vs_ly_cum['PIN'] = $lyPin;
                        $actual_vs_ly_cum['RANK'] = 10;
                        $actual_vs_ly_cum['ROWDESC'] = 'Actual vs LY Cum. %';
                        $actual_vs_ly_cum['highlightRow'] = 4;
                        $actual_vs_ly_cum['GRIDMAPPING'] = [2];
                        $actual_vs_ly_cum['DECIMALPOINT'] = [1];

                        
                        /*[END] GETTING LY SALES SEASONAL DATA */

                    if ($tmp[$dtKey] != 0)
                        $STCForecastValueCalculationFlag = 1;
                    
                    if ($STCForecastValueCalculationFlag == 0){
                        $data['ACTUAL_VS_FORECAST'] = 0;
                    }

                    $actual_vs_forecast[$dtKey] = $data['ACTUAL_VS_FORECAST'];
                    $actual_vs_forecast['PIN'] = $data['PIN'];
                    $actual_vs_forecast['RANK'] = 11;
                    $actual_vs_forecast['ROWDESC'] = 'Actual vs Forecast';
                    $actual_vs_forecast['GRIDMAPPING'] = [3];
                    $actual_vs_forecast['highlightRow'] = 4;

                    $cumActualVsForecast += $data['ACTUAL_VS_FORECAST'];
                    $actual_vs_forecast_cum[$dtKey] = $cumActualVsForecast;
                    $actual_vs_forecast_cum['PIN'] = $data['PIN'];
                    $actual_vs_forecast_cum['RANK'] = 12;
                    $actual_vs_forecast_cum['ROWDESC'] = 'Actual vs Forecast Cum.';
                    $actual_vs_forecast_cum['GRIDMAPPING'] = [3];
                    $actual_vs_forecast_cum['highlightRow'] = 4;

                    $tmp2[$dtKey] = ($cumTyValue > 0) ? ((($cumActualValue / $cumTyValue)-1)*100) : 0;
                    $tmp2['PIN'] = $data['PIN'];
                    $tmp2['RANK'] = 13;
                    $tmp2['ROWDESC'] = 'Actual vs Forecast Cum. %';
                    $tmp2['GRIDMAPPING'] = [3];
                    $tmp2['highlightRow'] = 4;
                    $tmp2['DECIMALPOINT'] = [1];

                    /* TAS vs Forecast Cum % */
                    $tas_vs_forcast_cum[$dtKey] = ($cumTyValue > 0) ? (($cumActualValue3 / $cumTyValue ) * 100 ) : 0;
                    $tas_vs_forcast_cum['PIN'] = $data['PIN'];
                    $tas_vs_forcast_cum['RANK'] = 14;
                    $tas_vs_forcast_cum['ROWDESC'] = 'TAS vs Forecast Cum. %';
                    $tas_vs_forcast_cum['highlightRow'] = 4;
                    $tas_vs_forcast_cum['GRIDMAPPING'] = [4];
                    $tas_vs_forcast_cum['DECIMALPOINT'] = [1];
                    /* End */

                    $tracked_vs_ly_cum[$dtKey] = (($totalTrackerCum[$account]) > 0) ? (($cumActualValue3/$totalTrackerCum[$account])*100) : 0;
                    $tracked_vs_ly_cum['PIN'] = $lyPin;
                    $tracked_vs_ly_cum['RANK'] = 15;
                    $tracked_vs_ly_cum['ROWDESC'] = 'TAS vs TOTAL BUY Cum. %';
                    $tracked_vs_ly_cum['highlightRow'] = 4;
                    $tracked_vs_ly_cum['GRIDMAPPING'] = [4];
                    $tracked_vs_ly_cum['DECIMALPOINT'] = [1];                    
                    
                    
                    $stores_selling[$dtKey] = $data['STORES_SELLING']*1;
                    $stores_selling['PIN'] = $data['PIN'];
                    $stores_selling['RANK'] = 16;
                    $stores_selling['ROWDESC'] = 'Stores Selling';
                    $stores_selling['GRIDMAPPING'] = [-1];
                    $stores_selling['highlightRow'] = 2;

                    $store_stock[$dtKey] = $data['STORE_STOCK']*1;
                    $store_stock['PIN'] = $data['PIN'];
                    $store_stock['RANK'] = 17;
                    $store_stock['ROWDESC'] = 'Store Stock';
                    $store_stock['GRIDMAPPING'] = [-1];
                    $store_stock['highlightRow'] = 2;

                    $stores_stocked[$dtKey] = $data['STORES_STOCKED']*1;
                    $stores_stocked['PIN'] = $data['PIN'];
                    $stores_stocked['RANK'] = 18;
                    $stores_stocked['ROWDESC'] = 'Stores Stocked';
                    $stores_stocked['GRIDMAPPING'] = [-1];
                    $stores_stocked['highlightRow'] = 2;

                    $stores_ranged[$dtKey] = $data['STORES_RANGED']*1;
                    $stores_ranged['PIN'] = $data['PIN'];
                    $stores_ranged['RANK'] = 19;
                    $stores_ranged['ROWDESC'] = 'Stores Ranged';
                    $stores_ranged['GRIDMAPPING'] = [-1];
                    $stores_ranged['highlightRow'] = 2;

                    $total_buy_ly[$dtKey] = $data['LY_TOTAL_BUY']*1;
                    $total_buy_ly['PIN'] = $data['PIN'];
                    $total_buy_ly['RANK'] = 20;
                    $total_buy_ly['ROWDESC'] = 'LY Total Buy';
                    $total_buy_ly['GRIDMAPPING'] = [-1];
                    $total_buy_ly['highlightRow'] = 1;

                    $ly_cum_vs_ly_total_buy[$dtKey] = ($ly_actual_sales_cum[$dtKey] > 0 && $total_buy[$account]['TY'] > 0) ? (( $ly_actual_sales_cum[$dtKey]/$total_buy[$account]['TY'])*100) : 0;
                    $ly_cum_vs_ly_total_buy['PIN'] = $lyPin;
                    $ly_cum_vs_ly_total_buy['RANK'] = 21;
                    $ly_cum_vs_ly_total_buy['ROWDESC'] = 'LY Cum. vs LY Total Buy';
                    $ly_cum_vs_ly_total_buy['highlightRow'] = 4;
                    $ly_cum_vs_ly_total_buy['GRIDMAPPING'] = [2];
                    $ly_cum_vs_ly_total_buy['DECIMALPOINT'] = [1];
                }else{

                }
            }
            
            /*[START] array prepared to matain the SORTING */
                $allActualSalesAndSTCForecastCum[$account]['ActualSalesCum'] = $cumTmp1[$lastDate];
                $allActualSalesAndSTCForecastCum[$account]['STCForecastCum'] = $cumTmp[$lastDate];
            /*[END] array prepared to matain the SORTING */

            /*$finalData[] = $tmp;
            $finalData[] = $cumTmp;
            $finalData[] = $tmp1;
            $finalData[] = $cumTmp1;
            $finalData[] = $ly_actual_sales;
            $finalData[] = $ly_actual_sales_cum;
            $finalData[] = $actual_vs_ly_cum;
            $finalData[] = $actual_vs_forecast;
            $finalData[] = $actual_vs_forecast_cum;
            $finalData[] = $tmp2;
            $finalData[] = $stores_selling;
            $finalData[] = $store_stock;
            $finalData[] = $stores_stocked;
            $finalData[] = $stores_ranged;*/

            $finalExeclData[$account][] = $tmp;
            $finalExeclData[$account][] = $cumTmp;
            $finalExeclData[$account][] = $tmp3;
            $finalExeclData[$account][] = $cumTmp3;
            $finalExeclData[$account][] = $tmp1;
            $finalExeclData[$account][] = $cumTmp1;
            $finalExeclData[$account][] = $ly_actual_sales;
            $finalExeclData[$account][] = $ly_actual_sales_cum;
            $finalExeclData[$account][] = $actual_vs_ly;
            $finalExeclData[$account][] = $actual_vs_ly_cum;
            $finalExeclData[$account][] = $actual_vs_forecast;
            $finalExeclData[$account][] = $actual_vs_forecast_cum;
            $finalExeclData[$account][] = $tmp2;
            $finalExeclData[$account][] = $tas_vs_forcast_cum;
            $finalExeclData[$account][] = $tracked_vs_ly_cum;
            $finalExeclData[$account][] = $stores_selling;
            $finalExeclData[$account][] = $store_stock;
            $finalExeclData[$account][] = $stores_stocked;
            $finalExeclData[$account][] = $stores_ranged;
            $finalExeclData[$account][] = $total_buy_ly;
            $finalExeclData[$account][] = $ly_cum_vs_ly_total_buy;
            $cnt++;
        }

        $actualSalesCumKeydata = array_column($allActualSalesAndSTCForecastCum, 'ActualSalesCum');
        $stcForecastCumKeydata = array_column($allActualSalesAndSTCForecastCum, 'STCForecastCum');
        array_multisort($actualSalesCumKeydata, SORT_DESC,$stcForecastCumKeydata, SORT_DESC, $allActualSalesAndSTCForecastCum);
        $allCumSortedKeys = array_keys($allActualSalesAndSTCForecastCum);
        $finalData = []; $finalExeclDataSorted = [];
        foreach ($allCumSortedKeys as $sKey => $sVal) {
            $finalData = array_merge($finalData,$finalExeclData[$sVal]);
            $finalExeclDataSorted[$sVal] = $finalExeclData[$sVal];
        }

        $exportFilteredList = [];
        if (isset($_REQUEST['gridViewType']) && !empty($_REQUEST['gridViewType']) && $_REQUEST['gridViewType'] > 0) {
            foreach ($finalExeclDataSorted as $Acckey => $AccVal) {
                foreach ($AccVal as $ky => $val) {
                    if(isset($val['GRIDMAPPING']) && in_array($_REQUEST['gridViewType'], $val['GRIDMAPPING']) ) {
                        $exportFilteredList[$Acckey][] = $finalExeclDataSorted[$Acckey][$ky];
                    }
                }
            }
            $finalExeclDataSorted = $exportFilteredList;
        }

        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_COLUMNS_HEADER');
        $this->redisCache->setDataForStaticHash($colsHeader);
        $gridAllColumnsHeaderHash = $this->redisCache->requestHash;
        
        $this->jsonOutput['gridData'] = $finalData;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_DATA');
        $this->redisCache->setDataForStaticHash($finalExeclDataSorted);
        $gridDataHash = $this->redisCache->requestHash;

        $sortedKeyArr = array_keys($finalExeclDataSorted);
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_DATA_KEYS');
        $this->redisCache->setDataForStaticHash($sortedKeyArr);
        $gridDataKeyHash = $this->redisCache->requestHash;
        
        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }
        $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
        $this->jsonOutput['accountCsvName'] = $this->accountCsvName;
        
        if($this->isExport)
        {
            $appliedFilters = [];
            if(isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])){
                if(isset($this->settingVars->seasonalTimeframeConfiguration)){
                    $timeFrame = array_search($_REQUEST['timeFrame'], array_column($this->settingVars->seasonalTimeframeConfiguration,'id'));
                    if($timeFrame !== false){
                       $timeFrame = $this->settingVars->seasonalTimeframeConfiguration[$timeFrame]['timeframe_name'];
                    }
                }else{
                    $timeFrame = $_REQUEST['timeFrame'];
                }
                $appliedFilters[] = 'Time Selection##'.$timeFrame;
            }

            if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                foreach($_REQUEST['FS'] as $ky=>$valDt) {
                    if(!empty($valDt)) {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                //$dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                            }
                            //if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $appliedFilters[] = $this->settingVars->dataArray[$ky]['NAME_CSV'].'##'.urldecode($dataList);
                            //}else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                //$marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            //}
                        }
                    }
                }
            }

            unset($this->jsonOutput['gridAllColumnsHeader']);
            unset($this->jsonOutput['gridData']);
            unset($this->jsonOutput['gridDataTotal']);
            unset($this->jsonOutput['allSeasonalHardStopDatesHashKey']);

            $fileName      = "Sell-Thru-" . date("Y-m-d-h-i-s") . ".xlsx";
            $savePath      = dirname(__FILE__)."/../uploads/Sell-Thru/";
            $imgLogoPath   = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
            $filePath      = $savePath.$fileName;
            $projectID     = $this->settingVars->projectID;
            $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
            $RedisPassword = $this->queryVars->RedisPassword;
            $appliedFiltersTxt = implode('$$', $appliedFilters);

            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/Sellthru.pl "'.$filePath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$gridAllColumnsHeaderHash.'" "'.$gridDataHash.'" "'.$imgLogoPath.'" "'.$this->accountCsvName.'" "'.$appliedFiltersTxt.'" "'.$gridDataKeyHash.'" "'.$this->aggregateSelection.'"');
            
            /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/Sellthru.pl "'.$filePath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$gridAllColumnsHeaderHash.'" "'.$gridDataHash.'" "'.$imgLogoPath.'" "'.$this->accountCsvName.'" "'.$appliedFiltersTxt.'" "'.$gridDataKeyHash.'
            exit;*/
            $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Sell-Thru/".$fileName;
        }
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
}
?>