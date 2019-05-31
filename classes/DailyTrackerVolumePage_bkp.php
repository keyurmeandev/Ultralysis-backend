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
        }
        
        if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchGrid' || $_REQUEST['action'] == 'export')){
            if($_REQUEST['action'] == 'export')
                $this->isExport = true;
                
            $this->gridData();
        }

        return $this->jsonOutput;
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
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $dtKey => $tyDate) {
                $colsHeader['TY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($tyDate)), "MYDATE" => $tyDate, "DAY" => date('D', strtotime($tyDate)));
                $dateArray[$tyDate] = date('j-n', strtotime($tyDate));
                $colsHeader['LY'][] = array("FORMATED_DATE" => date('D', strtotime($tyDate)), "MYDATE" => $tyDate);


                /*[START] Getting LY date array*/
                if(isset($this->settingVars->lyDates) && isset($this->settingVars->lyDates[$dtKey])){
                    $lyDates = $this->settingVars->lyDates[$dtKey];
                    //$colsHeader['LY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($lyDates)), "MYDATE" => $lyDates, "DAY" => date('D', strtotime($lyDates)));
                    $tyLyDateArrMapping[$dateArray[$tyDate]] = date('j-n', strtotime($lyDates));
                }
                /*[END] Getting LY date array*/
            }
        }

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
        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            "MAX(".$maintable.".".$this->settingVars->mainTblPinField.") as PIN, " . 
            "MAX(".$maintable.".mydate) as MYDATE ".
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") . 
            ", DATE_FORMAT(".$maintable.".mydate, '%e-%c') as FORMATED_DATE, ". 
            " CASE WHEN  ".$timeFilterQue." THEN 'TY' ELSE 'LY' END AS ISTYLY,". 
            "SUM(".$maintable.".qty) as QTY, ". 
            "SUM(".$maintable.".store_stock) as STORE_STOCK, ". 
            "SUM(".$maintable.".stores_selling) as STORES_SELLING, ". 
            "SUM(".$maintable.".stores_stocked) as STORES_STOCKED, ". 
            "SUM(".$maintable.".stores_traited) as STORES_RANGED, ". 
            "MAX(".$maintable.".forecast1) as STC_FORECAST " . 
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" .$timeFilterQue. " OR ".$timeFilterQueLy.") ". 
            //" AND ".$maintable.".".$this->settingVars->mainTblPinField." = 5515820 ".
            " AND ".$this->settingVars->skutable.".agg6 LIKE '%XMAS%' ". 
            " GROUP BY ACCOUNT, FORMATED_DATE, ISTYLY ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY MYDATE ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        $havingTYValue = "DAILY_FCAST";

        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $seasonalData) {
                $seasonalData[$havingTYValue] = $seasonalData['STC_FORECAST'];
                $seasonalData['QTY'] = $seasonalData['QTY']*1;
                $seasonalData['STC_FORECAST'] = $seasonalData['STC_FORECAST']*1;
                $seasonalData['ACTUAL_VS_FORECAST'] = ($seasonalData['QTY'] - $seasonalData['STC_FORECAST']);
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

        $cnt = 0; $cmpTyTotal = $cmpLyTotal = [];
        foreach (array_keys($seasonalDataArray) as $account) {
            $tmp = $tmp1 = $tmp2 = $cumTmp = $cumTmp1 = array();
            $ly_actual_sales = $ly_actual_sales_cum = $actual_vs_ly_cum = array();
            $stores_selling = $store_stock = $stores_stocked = $stores_ranged = $actual_vs_forecast = $actual_vs_forecast_cum = array();
            
            $tmp['ACCOUNT'] = $account;
            $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp1['ACCOUNT'] = $account;
            $tmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp2['ACCOUNT'] = $account;
            $tmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp['ACCOUNT'] = $account;
            $cumTmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp1['ACCOUNT'] = $account;
            $cumTmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $ly_actual_sales['ACCOUNT'] = $account;
            $ly_actual_sales['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $ly_actual_sales_cum['ACCOUNT'] = $account;
            $ly_actual_sales_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_ly_cum['ACCOUNT'] = $account;
            $actual_vs_ly_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_forecast['ACCOUNT'] = $account;
            $actual_vs_forecast['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $actual_vs_forecast_cum['ACCOUNT'] = $account;
            $actual_vs_forecast_cum['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_selling['ACCOUNT'] = $account;
            $stores_selling['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $store_stock['ACCOUNT'] = $account;
            $store_stock['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_stocked['ACCOUNT'] = $account;
            $stores_stocked['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $stores_ranged['ACCOUNT'] = $account;
            $stores_ranged['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            
            $cumTyValue = $cumActualValue = $cumActualVsForecast = 0;
            $cumActualLyValue = 0;
            foreach ($dateArray as $dayMydate => $dayMonth) {

                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$dayMydate);

                    foreach ($ExtraCols as $extraCols) {
                        $tmp[$extraCols['NAME_ALIASE']]     = $data[$extraCols['NAME_ALIASE']];
                        $tmp1[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $cumTmp[$extraCols['NAME_ALIASE']]  = $data[$extraCols['NAME_ALIASE']];
                        $cumTmp1[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $tmp2[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];

                        $ly_actual_sales[$extraCols['NAME_ALIASE']]     = $data[$extraCols['NAME_ALIASE']];
                        $ly_actual_sales_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $actual_vs_ly_cum[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];

                        $actual_vs_forecast[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $actual_vs_forecast_cum[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $stores_selling[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $store_stock[$extraCols['NAME_ALIASE']]    = $data[$extraCols['NAME_ALIASE']];
                        $stores_stocked[$extraCols['NAME_ALIASE']] = $data[$extraCols['NAME_ALIASE']];
                        $stores_ranged[$extraCols['NAME_ALIASE']]  = $data[$extraCols['NAME_ALIASE']];
                    }
                    
                    $tmp[$dtKey] = $data[$havingTYValue]*1;
                    $tmp['PIN'] = $data['PIN'];
                    $tmp['RANK'] = 1;
                    $tmp['ROWDESC'] = 'STC Forecast';
                    $tmp['highlightRow'] = 1;

                    $tmp1[$dtKey] = $data['QTY']*1;
                    $tmp1['PIN'] = $data['PIN'];
                    $tmp1['RANK'] = 3;
                    $tmp1['ROWDESC'] = 'Actual Sales';
                    $tmp1['highlightRow'] = 2;
                    
                    $cumTyValue += $data[$havingTYValue];
                    $cumTmp[$dtKey] = $cumTyValue;
                    $cumTmp['PIN'] = $data['PIN'];
                    $cumTmp['RANK'] = 2;
                    $cumTmp['ROWDESC'] = 'STC Forecast Cum.';
                    $cumTmp['highlightRow'] = 1;
                    
                    $cumActualValue += $data['QTY'];
                    $cumTmp1[$dtKey] = $cumActualValue;
                    $cumTmp1['PIN'] = $data['PIN'];
                    $cumTmp1['RANK'] = 4;
                    $cumTmp1['ROWDESC'] = 'Actual Sales Cum.';
                    $cumTmp1['highlightRow'] = 1;

                        /*[START] GETTING LY SALES SEASONAL DATA */
                        $lyDate = $tyLyDateArrMapping[$dayMonth];
                        $lyData = (isset($seasonalDataLyArray[$account][$lyDate])) ? $seasonalDataLyArray[$account][$lyDate] : '';
                        $lyQty = isset($lyData['QTY']) ? $lyData['QTY']*1 : 0;
                        $lyPin = isset($lyData['PIN']) ? $lyData['PIN'] : $data['PIN'];
                        $ly_actual_sales[$dtKey]    = $lyQty;

                        $ly_actual_sales['PIN']     = $lyPin;
                        $ly_actual_sales['RANK']    = 5;
                        $ly_actual_sales['ROWDESC'] = 'LY Actual Sales';
                        $ly_actual_sales['highlightRow'] = 2;
                        
                        $cumActualLyValue += $lyQty;
                        $ly_actual_sales_cum[$dtKey]    = $cumActualLyValue;
                        $ly_actual_sales_cum['PIN']     = $lyPin;
                        $ly_actual_sales_cum['RANK']    = 6;
                        $ly_actual_sales_cum['ROWDESC'] = 'LY Actual Sales Cum.';
                        $ly_actual_sales_cum['highlightRow'] = 1;

                        $actual_vs_ly_cum[$dtKey] = ($cumActualLyValue > 0) ? ((($cumActualValue/$cumActualLyValue)-1)*100) : 0;
                        $actual_vs_ly_cum['PIN'] = $lyPin;
                        $actual_vs_ly_cum['RANK'] = 7;
                        $actual_vs_ly_cum['ROWDESC'] = 'Actual vs LY Cum. %';
                        $actual_vs_ly_cum['highlightRow'] = 3;
                        
                        /*[END] GETTING LY SALES SEASONAL DATA */

                    $actual_vs_forecast[$dtKey] = $data['ACTUAL_VS_FORECAST'];
                    $actual_vs_forecast['PIN'] = $data['PIN'];
                    $actual_vs_forecast['RANK'] = 8;
                    $actual_vs_forecast['ROWDESC'] = 'Actual vs Forecast';
                    $actual_vs_forecast['highlightRow'] = 2;

                    $cumActualVsForecast += $data['ACTUAL_VS_FORECAST'];
                    $actual_vs_forecast_cum[$dtKey] = $cumActualVsForecast;
                    $actual_vs_forecast_cum['PIN'] = $data['PIN'];
                    $actual_vs_forecast_cum['RANK'] = 9;
                    $actual_vs_forecast_cum['ROWDESC'] = 'Actual vs Forecast Cum.';
                    $actual_vs_forecast_cum['highlightRow'] = 1;

                    $tmp2[$dtKey] = ($cumTyValue > 0) ? ((($cumActualValue/$cumTyValue)-1)*100) : 0;
                    $tmp2['PIN'] = $data['PIN'];
                    $tmp2['RANK'] = 10;
                    $tmp2['ROWDESC'] = 'Actual vs Forecast Cum. %';
                    $tmp2['highlightRow'] = 3;
                    
                    $stores_selling[$dtKey] = $data['STORES_SELLING']*1;
                    $stores_selling['PIN'] = $data['PIN'];
                    $stores_selling['RANK'] = 11;
                    $stores_selling['ROWDESC'] = 'Stores Selling';
                    $stores_selling['highlightRow'] = 2;

                    $store_stock[$dtKey] = $data['STORE_STOCK']*1;
                    $store_stock['PIN'] = $data['PIN'];
                    $store_stock['RANK'] = 12;
                    $store_stock['ROWDESC'] = 'Store Stock';
                    $store_stock['highlightRow'] = 2;

                    $stores_stocked[$dtKey] = $data['STORES_STOCKED']*1;
                    $stores_stocked['PIN'] = $data['PIN'];
                    $stores_stocked['RANK'] = 13;
                    $stores_stocked['ROWDESC'] = 'Stores Stocked';
                    $stores_stocked['highlightRow'] = 2;

                    $stores_ranged[$dtKey] = $data['STORES_RANGED']*1;
                    $stores_ranged['PIN'] = $data['PIN'];
                    $stores_ranged['RANK'] = 14;
                    $stores_ranged['ROWDESC'] = 'Stores Ranged';
                    $stores_ranged['highlightRow'] = 2;
                }else{



                }


            }
            
            $finalData[] = $tmp;
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
            $finalData[] = $stores_ranged;

            $finalExeclData[$account][] = $tmp;
            $finalExeclData[$account][] = $cumTmp;
            $finalExeclData[$account][] = $tmp1;
            $finalExeclData[$account][] = $cumTmp1;
                $finalExeclData[$account][] = $ly_actual_sales;
                $finalExeclData[$account][] = $ly_actual_sales_cum;
                $finalExeclData[$account][] = $actual_vs_ly_cum;
            $finalExeclData[$account][] = $actual_vs_forecast;
            $finalExeclData[$account][] = $actual_vs_forecast_cum;
            $finalExeclData[$account][] = $tmp2;
            $finalExeclData[$account][] = $stores_selling;
            $finalExeclData[$account][] = $store_stock;
            $finalExeclData[$account][] = $stores_stocked;
            $finalExeclData[$account][] = $stores_ranged;

            $cnt++;
        }
        
        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_COLUMNS_HEADER');
        $this->redisCache->setDataForStaticHash($colsHeader);
        $gridAllColumnsHeaderHash = $this->redisCache->requestHash;
        
        $this->jsonOutput['gridData'] = $finalData;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_DATA');
        $this->redisCache->setDataForStaticHash($finalExeclData);
        $gridDataHash = $this->redisCache->requestHash;
        
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

            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/Sellthru.pl "'.$filePath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$gridAllColumnsHeaderHash.'" "'.$gridDataHash.'" "'.$imgLogoPath.'" "'.$this->accountCsvName.'" "'.$appliedFiltersTxt.'"');

            /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/Sellthru.pl "'.$filePath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$gridAllColumnsHeaderHash.'" "'.$gridDataHash.'" "'.$imgLogoPath.'"';
            exit;*/

            $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Sell-Thru/".$fileName;
        }
    }
}
?>