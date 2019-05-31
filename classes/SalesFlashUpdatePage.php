<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class SalesFlashUpdatePage extends config\UlConfig {

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
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["ACCOUNT" => strtoupper($this->dbColumnsArray[$vl])];
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

            $this->jsonOutput["displayColumn"][] = array("fieldName" => 'ACCOUNT', "title" => $this->accountCsvName);
            $ExtraCols = [];
            if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0)
            {
                foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) 
                {
                    $this->jsonOutput["displayColumn"][] = array("fieldName" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], "title" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']);
                }
            }
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
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $maintable = $this->settingVars->maintable;

        $timeFilterQue   = filters\timeFilter::$tyWeekRange;
        
        $timeFilterQueLy = filters\timeFilter::$lyWeekRange;

        $timeFilterQueFieldExtraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere;

        // 
        $query = "SELECT MAX((CASE WHEN " . $timeFilterQue . " THEN ".$this->settingVars->dateField." END)) AS MAX_DATE_TY,  MAX((CASE WHEN " . $timeFilterQueLy . " THEN ".$this->settingVars->dateField." END)) AS MAX_DATE_LY FROM ".$this->settingVars->timetable.$this->settingVars->timeHelperLink." AND ".$this->settingVars->dateField." IN (SELECT DISTINCT ".$this->settingVars->dateField." FROM ".$this->settingVars->timetable.$this->settingVars->timeHelperLink." HAVING SUM(".$this->settingVars->ProjectValue.") > 0 AND (".$timeFilterQue." OR ".$timeFilterQueLy.")) ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $tyMaxDate = (is_array($result) && !empty($result)) ? $result[0]['MAX_DATE_TY'] : '';
        $tyMaxDatePart = explode('-', $tyMaxDate);
        $tyMaxDatePart[0] = $tyMaxDatePart[0]-1;
        $lyMaxDate = (is_array($tyMaxDatePart) && !empty($tyMaxDatePart)) ? implode('-', $tyMaxDatePart) : '';

        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
                (!empty($ExtraCols) && count($ExtraCols)>0 ? implode(',', array_column($ExtraCols,'NAME')).", " : "") . 
                // "SUM(".$maintable.".forecast1) as TOTAL_BUY, " . 
                "SUM((CASE WHEN " . $timeFilterQue . " THEN 1 ELSE 0 END) * IFNULL(".$maintable.".forecast1, 0) ) AS TOTAL_BUY, ".
                "SUM((CASE WHEN " . $timeFilterQue . " AND ".$this->settingVars->dateField." <= '".$tyMaxDate."' THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_YTD, ".
                "SUM((CASE WHEN " . $timeFilterQue . " AND ".$this->settingVars->dateField." <= '".$tyMaxDate."' THEN 1 ELSE 0 END) * IFNULL(".$maintable.".forecast1, 0) ) AS FORECAST_YTD, ".
                "SUM((CASE WHEN " . $timeFilterQueLy . " AND ".$this->settingVars->dateField." <= '".$lyMaxDate."' THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_YTD_LY, ".

                "SUM((CASE WHEN (".$this->settingVars->dateField." BETWEEN DATE_SUB('".$tyMaxDate."',INTERVAL 6 DAY) AND '".$tyMaxDate."') THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_LAT7_DAY, ".  
                "SUM((CASE WHEN (".$this->settingVars->dateField." BETWEEN DATE_SUB('".$tyMaxDate."',INTERVAL 13 DAY) AND DATE_SUB('".$tyMaxDate."',INTERVAL 7 DAY)) THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_PREV7_DAY, ".  

                "SUM((CASE WHEN (".$this->settingVars->dateField." BETWEEN DATE_SUB('".$lyMaxDate."',INTERVAL 6 DAY) AND '".$lyMaxDate."') THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_LAT7_DAY_LY, ".  
                "SUM((CASE WHEN (".$this->settingVars->dateField." BETWEEN DATE_SUB('".$lyMaxDate."',INTERVAL 13 DAY) AND DATE_SUB('".$lyMaxDate."',INTERVAL 7 DAY)) THEN 1 ELSE 0 END) * IFNULL(".$maintable.".qty, 0) ) AS EPOS_QTY_PREV7_DAY_LY ".  
                " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                " AND (" .$timeFilterQue. " OR ".$timeFilterQueLy.") ". 
                $timeFilterQueFieldExtraWhere. 
                " GROUP BY ACCOUNT ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY EPOS_QTY_YTD DESC";
        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $salesFlashDataFinal = [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $salesFlashData) {
                $salesFlashData['TOTAL_BUY'] = $salesFlashData['TOTAL_BUY']*1;
                $salesFlashData['EPOS_QTY_YTD'] = $salesFlashData['EPOS_QTY_YTD']*1;
                $salesFlashData['FORECAST_YTD'] = $salesFlashData['FORECAST_YTD']*1;
                $salesFlashData['EPOS_QTY_YTD_LY'] = $salesFlashData['EPOS_QTY_YTD_LY']*1;
                $salesFlashData['DUMMY_1'] = ($salesFlashData['EPOS_QTY_YTD'] > $salesFlashData['FORECAST_YTD']) ? "AHEAD" : "BEHIND";
                $salesFlashData['DUMMY_2'] = ($salesFlashData['EPOS_QTY_YTD'] > $salesFlashData['EPOS_QTY_YTD_LY']) ? "AHEAD" : "BEHIND";

                $salesFlashData['EPOS_QTY_LAT7_DAY'] = $salesFlashData['EPOS_QTY_LAT7_DAY']*1;
                $salesFlashData['EPOS_QTY_PREV7_DAY'] = $salesFlashData['EPOS_QTY_PREV7_DAY']*1;
                $salesFlashData['EPOS_QTY_LAT7_DAY_LY'] = $salesFlashData['EPOS_QTY_LAT7_DAY_LY']*1;
                $salesFlashData['EPOS_QTY_PREV7_DAY_LY'] = $salesFlashData['EPOS_QTY_PREV7_DAY_LY']*1;

                $salesFlashData['DUMMY_3'] = ($salesFlashData['EPOS_QTY_LAT7_DAY'] > $salesFlashData['EPOS_QTY_PREV7_DAY']) ? "ACCELERATING" : "SLOWING";

                $salesFlashData['SELL_THRU_VS_BUY'] = ($salesFlashData['TOTAL_BUY'] > 0) ? ($salesFlashData['EPOS_QTY_YTD'] / $salesFlashData['TOTAL_BUY']) : 0;
                $salesFlashData['SELL_THRU_VS_FCAST'] = ($salesFlashData['FORECAST_YTD'] > 0) ? ($salesFlashData['EPOS_QTY_YTD'] / $salesFlashData['FORECAST_YTD']) : 0;
                $salesFlashData['YOY'] = ($salesFlashData['EPOS_QTY_YTD_LY'] > 0) ? (($salesFlashData['EPOS_QTY_YTD'] / $salesFlashData['EPOS_QTY_YTD_LY']) - 1) : 0;
                $salesFlashData['TY7_DAY_VAR'] = ($salesFlashData['EPOS_QTY_PREV7_DAY'] > 0 ) ? (($salesFlashData['EPOS_QTY_LAT7_DAY'] / $salesFlashData['EPOS_QTY_PREV7_DAY']) - 1) : 0;
                $salesFlashData['LY7_DAY_VAR'] = ($salesFlashData['EPOS_QTY_PREV7_DAY_LY'] > 0 ) ? (($salesFlashData['EPOS_QTY_LAT7_DAY_LY'] / $salesFlashData['EPOS_QTY_PREV7_DAY_LY']) - 1) : 0;

                if($salesFlashData['EPOS_QTY_LAT7_DAY_LY'] == 0){
                    $salesFlashData['DUMMY_4'] = "NEW";
                } else if($salesFlashData['TY7_DAY_VAR'] > $salesFlashData['LY7_DAY_VAR']) {
                    $salesFlashData['DUMMY_4'] = "QUICKER THAN LY";
                } else {
                    $salesFlashData['DUMMY_4'] = "SLOWER VS LY";
                }

                $salesFlashDataFinal[] = $salesFlashData;
            }
        }

        $this->jsonOutput['gridData'] = $salesFlashDataFinal;
    }
}
?>