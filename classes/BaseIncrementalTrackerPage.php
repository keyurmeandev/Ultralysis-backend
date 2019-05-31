<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class BaseIncrementalTrackerPage extends config\UlConfig {

    public $pageName;
    public $gridFields;
    public $countOfGrid;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $dh;
    public $redisCache;

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

        // By default it is 52 week data
        filters\timeFilter::getTimeFrame(52, $settingVars);

        /*if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $tempBuildFieldsArray = array($this->accountField);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value){
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            }

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }*/

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->fetchConfig(); // Fetching filter configuration for page
            /*[START] CODE FOR INLINE FILTER STYLE*/
                $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
                if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                    $this->jsonOutput['pageConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
                }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }

        $this->configurePage();
        $action = $_REQUEST["action"];
        switch ($action) {
            case "fetchGrid":
                $this->gridData();
                break;
            case "export":
                $this->export();
                break;
        }
        return $this->jsonOutput;
    }

    public function gridData($isExport = false) {

        //Getting distinct year nad week form the database
        $query = 'SELECT DISTINCT CONCAT(WEEK,"-",YEAR) AS mydate, WEEK, YEAR FROM '.$this->settingVars->timetable.' WHERE GID = '.$this->settingVars->GID.' AND '.filters\timeFilter::$tyWeekRange.' ORDER BY YEAR DESC, WEEK DESC';

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dateArray = array_reverse(array_unique(array_column($result, 'mydate')));
        $allYearRows = array_reverse(array_unique(array_column($result, 'YEAR')));

        /*
            $this->jsonOutput['thisYear'] = $ty = (int) $allYearRows[0];
            $this->jsonOutput['lastYear'] = $ly = $ty - 1;
        */

        $allWeekColumns = array_unique(array_column($result, 'WEEK'));
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT $this->accountID AS ACCOUNT, $this->accountName AS ACCOUNT_NAME, ". 
                 $this->settingVars->maintable.".WEEK AS WEEK, ".
                 $this->settingVars->maintable.".YEAR AS YEAR, ".
                 " MAX(CASE WHEN (".filters\timeFilter::$tyWeekRange.") THEN 'TY' ELSE 'LY' END) AS TYLYTYPE, ".
                 " SUM(".$this->salesField.") AS SALES, ".
                 " SUM(".$this->baselineField.") AS BASELINE, ".
                 //" AVG(IFNULL(".$this->avgPriceField.",0)) AS AVG_PRICE, ".
                 " AVG((CASE WHEN (IFNULL(".$this->avgPriceField.",0)>0) THEN 1 END) * IFNULL(".$this->avgPriceField.",0) ) AS AVG_PRICE ". 
                 " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
                     " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
                     " GROUP BY ACCOUNT, ACCOUNT_NAME, WEEK, YEAR ORDER BY WEEK ASC, YEAR DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dataPnameSum = $dataPnameBaseLineSum = $dataPnameIncrementalSum = $dataPnameAvgPriceSum = $dataPnameAvgPriceAccountData = [];
        $dataPnameSumIndexed = $dataIndexedChart2 = [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $seasonalData) {
                $seasonalData['FORMATED_DATE'] = $seasonalData['WEEK'].'-'.$seasonalData['YEAR'];

                $seasonalData['SALES'] = ($seasonalData['SALES'] * 1);
                $seasonalData['BASELINE'] = ($seasonalData['BASELINE'] * 1);
                $seasonalData['INCREMENTAL'] = ($seasonalData['SALES'] - $seasonalData['BASELINE']);
                $seasonalData['AVG_PRICE'] = ($seasonalData['AVG_PRICE'] * 1);
                
                $accountNameID = $seasonalData['ACCOUNT'];
                if($seasonalData['ACCOUNT'] != $seasonalData['ACCOUNT_NAME'])
                    $accountNameID.= " (".$seasonalData['ACCOUNT_NAME'].")";

                $seasonalDataArray[$accountNameID][$seasonalData['FORMATED_DATE']] = $seasonalData;

                //[START] Used to calculate total column
                    $dataPnameSum[$accountNameID]            += $seasonalData['SALES'];
                    $dataPnameBaseLineSum[$accountNameID]    += $seasonalData['BASELINE'];
                    $dataPnameIncrementalSum[$accountNameID] += $seasonalData['INCREMENTAL'];
                    $dataPnameAvgPriceSum[$accountNameID]    += $seasonalData['AVG_PRICE'];
                //[END] Used to calculate total column
            }

            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $dataPnameBaseLineSum[$pnm] = $rankCnt;
                $dataPnameIncrementalSum[$pnm] = $rankCnt;
                $dataPnameAvgPriceSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        $cnt = 0; $cmpTyTotal = $cmpLyTotal = $cmpBaseLineTyTotal = $cmpBaseLineLyTotal = $cmpIncrementalTyTotal = $cmpIncrementalLyTotal = $cmpAvgPriceTyTotal = $cmpAvgPriceLyTotal = [];
        $cmpAvgPriceTyTotalAvgData = $cmpAvgPriceLyTotalAvgData = [];

        foreach (array_keys($seasonalDataArray) as $account) {
            $tmpSales = $tmpSales1 = $tmpSales2 = $tmpSales3 = $cumTmpSales = $cumTmpSales1 = $cumTmpSales2 = $cumTmpSales3 = array();
            $tmpBaseLine = $tmpBaseLine1 = $tmpBaseLine2 = $tmpBaseLine3 = $cumTmpBaseLine = $cumTmpBaseLine1 = $cumTmpBaseLine2 = $cumTmpBaseLine3 = array();
            $tmpIncremental = $tmpIncremental1 = $tmpIncremental2 = $tmpIncremental3 = $cumTmpIncremental = $cumTmpIncremental1 = $cumTmpIncremental2 = $cumTmpIncremental3 = array();
            $tmpAvgPrice = $tmpAvgPrice1 = $tmpAvgPrice2 = $tmpAvgPrice3 = $cumTmpAvgPrice = $cumTmpAvgPrice1 = $cumTmpAvgPrice2 = $cumTmpAvgPrice3 = array();
            
            /*[SALES DATA]*/
            $tmpSales['ACCOUNT']  = $account; $tmpSales['TOTAL']  = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $tmpSales1['ACCOUNT'] = $account; $tmpSales1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $tmpSales2['ACCOUNT'] = $account; $tmpSales2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $tmpSales3['ACCOUNT'] = $account; $tmpSales3['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            /*[BASELINE DATA]*/
            $tmpBaseLine['ACCOUNT']  = $account; $tmpBaseLine['TOTAL']  = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $tmpBaseLine1['ACCOUNT'] = $account; $tmpBaseLine1['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $tmpBaseLine2['ACCOUNT'] = $account; $tmpBaseLine2['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $tmpBaseLine3['ACCOUNT'] = $account; $tmpBaseLine3['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;

            /*[INCREMENTAL DATA]*/
            $tmpIncremental['ACCOUNT']  = $account; $tmpIncremental['TOTAL']  = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $tmpIncremental1['ACCOUNT'] = $account; $tmpIncremental1['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $tmpIncremental2['ACCOUNT'] = $account; $tmpIncremental2['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $tmpIncremental3['ACCOUNT'] = $account; $tmpIncremental3['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;

            /*[AVGPRICE DATA]*/
            $tmpAvgPrice['ACCOUNT']  = $account; $tmpAvgPrice['TOTAL']  = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $tmpAvgPrice1['ACCOUNT'] = $account; $tmpAvgPrice1['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $tmpAvgPrice2['ACCOUNT'] = $account; $tmpAvgPrice2['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $tmpAvgPrice3['ACCOUNT'] = $account; $tmpAvgPrice3['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;

            /*[SALES CUM DATA]*/
            $cumTmpSales['ACCOUNT']  = $account; $cumTmpSales['TOTAL']  = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $cumTmpSales1['ACCOUNT'] = $account; $cumTmpSales1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $cumTmpSales2['ACCOUNT'] = $account; $cumTmpSales2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            $cumTmpSales3['ACCOUNT'] = $account; $cumTmpSales3['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            /*[BASELINE CUM DATA]*/
            $cumTmpBaseLine['ACCOUNT']  = $account; $cumTmpBaseLine['TOTAL']  = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $cumTmpBaseLine1['ACCOUNT'] = $account; $cumTmpBaseLine1['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $cumTmpBaseLine2['ACCOUNT'] = $account; $cumTmpBaseLine2['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;
            $cumTmpBaseLine3['ACCOUNT'] = $account; $cumTmpBaseLine3['TOTAL'] = (isset($dataPnameBaseLineSum[$account])) ? $dataPnameBaseLineSum[$account] : 0;

            /*[INCREMENTAL CUM DATA]*/
            $cumTmpIncremental['ACCOUNT']  = $account; $cumTmpIncremental['TOTAL']  = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $cumTmpIncremental1['ACCOUNT'] = $account; $cumTmpIncremental1['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $cumTmpIncremental2['ACCOUNT'] = $account; $cumTmpIncremental2['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;
            $cumTmpIncremental3['ACCOUNT'] = $account; $cumTmpIncremental3['TOTAL'] = (isset($dataPnameIncrementalSum[$account])) ? $dataPnameIncrementalSum[$account] : 0;

            /*[AVGPRICE CUM DATA]*/
            $cumTmpAvgPrice['ACCOUNT']  = $account; $cumTmpAvgPrice['TOTAL']  = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $cumTmpAvgPrice1['ACCOUNT'] = $account; $cumTmpAvgPrice1['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $cumTmpAvgPrice2['ACCOUNT'] = $account; $cumTmpAvgPrice2['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;
            $cumTmpAvgPrice3['ACCOUNT'] = $account; $cumTmpAvgPrice3['TOTAL'] = (isset($dataPnameAvgPriceSum[$account])) ? $dataPnameAvgPriceSum[$account] : 0;

            $cumTyValue = $cumLyValue = 0;
            $cumTyValueBaseLine = $cumLyValueBaseLine = 0;
            $cumTyValueIncremental = $cumLyValueIncremental = 0;
            $cumTyValueAvgPrice = $cumLyValueAvgPrice = 0;

            $latestCumDate = end($dateArray);
            foreach ($dateArray as $dayMydate => $dayMonth) {
                $tyMydate = $dayMonth;

                $tmplyMydateArr = explode('-', $dayMonth);
                $lyMydate = $tmplyMydateArr[0].'-'.($tmplyMydateArr[1] - 1);

                $ty = (int) $tmplyMydateArr[1];
                $ly = $ty - 1;
                
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    
                    $data       = $seasonalDataArray[$account][$dayMonth];
                    $lydata     = $seasonalDataArray[$account][$lyMydate];
                    $dtKey      = 'dt'.str_replace('-','',$tyMydate);
                    $dtTotalKey = 'dt'.str_replace('-','',$tyMydate);

                    /*[SALES DATA]*/
                    $tmpSales       = array_merge($tmpSales,  [$dtKey=>$data['SALES'], 'YEAR'=>$this->salesFieldName.' TY', 'RANK'=>(int)'1'.$data['YEAR'], 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpSales1      = array_merge($tmpSales1, [$dtKey=>$lydata['SALES'], 'YEAR'=>$this->salesFieldName.' LY', 'RANK'=>(int)'1'.($data['YEAR']-1), 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpSales2      = array_merge($tmpSales2, [$dtKey=>($data['SALES'] - $lydata['SALES']), 'YEAR'=>'VAR', 'RANK'=>'1VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
                    $salesVarPer    = ($lydata['SALES'] > 0) ? (($data['SALES'] - $lydata['SALES']) / $lydata['SALES'])*100 : 0;
                    $tmpSales3      = array_merge($tmpSales3, [$dtKey=>$salesVarPer, 'YEAR'=>'VAR%', 'RANK'=>'1VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

                    /*[BASELINE DATA]*/
                    $tmpBaseLine    = array_merge($tmpBaseLine,  [$dtKey=>$data['BASELINE'], 'YEAR'=>$this->baselineFieldName.' TY', 'RANK'=>(int)'2'.$data['YEAR'], 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpBaseLine1   = array_merge($tmpBaseLine1, [$dtKey=>$lydata['BASELINE'], 'YEAR'=>$this->baselineFieldName.' LY', 'RANK'=>(int)'2'.($data['YEAR']-1), 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpBaseLine2   = array_merge($tmpBaseLine2, [$dtKey=>($data['BASELINE'] - $lydata['BASELINE']), 'YEAR'=>'VAR', 'RANK'=>'2VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
                    $baselineVarPer = ($lydata['BASELINE'] > 0) ? (($data['BASELINE'] - $lydata['BASELINE']) / $lydata['BASELINE'])*100 : 0;
                    $tmpBaseLine3   = array_merge($tmpBaseLine3, [$dtKey=>$baselineVarPer, 'YEAR'=>'VAR%', 'RANK'=>'2VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

                    /*[INCREMENTAL DATA]*/
                    $tmpIncremental    = array_merge($tmpIncremental,  [$dtKey=>$data['INCREMENTAL'], 'YEAR'=>'INCREMENTAL TY', 'RANK'=>(int)'3'.$data['YEAR'], 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpIncremental1   = array_merge($tmpIncremental1, [$dtKey=>$lydata['INCREMENTAL'], 'YEAR'=>'INCREMENTAL LY', 'RANK'=>(int)'3'.($data['YEAR']-1), 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
                    $tmpIncremental2   = array_merge($tmpIncremental2, [$dtKey=>($data['INCREMENTAL'] - $lydata['INCREMENTAL']), 'YEAR'=>'VAR', 'RANK'=>'3VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
                    $incrementalVarPer = ($lydata['INCREMENTAL'] > 0) ? (($data['INCREMENTAL'] - $lydata['INCREMENTAL']) / $lydata['INCREMENTAL'])*100 : 0;
                    $tmpIncremental3   = array_merge($tmpIncremental3, [$dtKey=>$incrementalVarPer, 'YEAR'=>'VAR%', 'RANK'=>'3VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

                    /*[AVGPRICE DATA]*/
                    $tmpAvgPrice    = array_merge($tmpAvgPrice,  [$dtKey=>$data['AVG_PRICE'], 'YEAR'=>'PRICE TY', 'RANK'=>(int)'4'.$data['YEAR'], 'ROWDESC'=>'Weekly', 'highlightRow'=>1, 'dataDecimalPoint'=>2]);
                    $tmpAvgPrice1   = array_merge($tmpAvgPrice1, [$dtKey=>$lydata['AVG_PRICE'], 'YEAR'=>'PRICE LY', 'RANK'=>(int)'4'.($data['YEAR']-1), 'ROWDESC'=>'Weekly', 'highlightRow'=>1, 'dataDecimalPoint'=>2]);
                    $tmpAvgPrice2   = array_merge($tmpAvgPrice2, [$dtKey=>($data['AVG_PRICE'] - $lydata['AVG_PRICE']), 'YEAR'=>'VAR', 'RANK'=>'4VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>4, 'dataDecimalPoint'=>2]);
                    $avgPriceVarPer = ($lydata['AVG_PRICE'] > 0) ? (($data['AVG_PRICE'] - $lydata['AVG_PRICE']) / $lydata['AVG_PRICE'])*100 : 0;
                    $tmpAvgPrice3   = array_merge($tmpAvgPrice3, [$dtKey=>$avgPriceVarPer, 'YEAR'=>'VAR%', 'RANK'=>'4VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3,'dataDecimalPoint'=>2]);
                               
                    /*[SALES CUM DATA]*/
                    $cumTyValue += $data['SALES']; $cumLyValue += $lydata['SALES'];
                    $cumTmpSales    = array_merge($cumTmpSales,  [$dtKey=>$cumTyValue, 'YEAR'=>$this->salesFieldName.' TY', 'RANK'=>(int)'5'.$data['YEAR'], 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpSales1   = array_merge($cumTmpSales1, [$dtKey=>$cumLyValue, 'YEAR'=>$this->salesFieldName.' LY', 'RANK'=>(int)'5'.($data['YEAR']-1), 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpSales2   = array_merge($cumTmpSales2, [$dtKey=>($cumTyValue - $cumLyValue), 'YEAR'=>'VAR', 'RANK'=>'5VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
                    $salesCumVarPer = ($cumLyValue > 0) ? (($cumTyValue - $cumLyValue) / $cumLyValue)*100 : 0;
                    $cumTmpSales3   = array_merge($cumTmpSales3, [$dtKey=>$salesCumVarPer, 'YEAR'=>'VAR%', 'RANK'=>'5VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

                    /*[BASELINE CUM DATA]*/
                    $cumTyValueBaseLine += $data['BASELINE']; $cumLyValueBaseLine += $lydata['BASELINE'];
                    $cumTmpBaseLine    = array_merge($cumTmpBaseLine, [$dtKey=>$cumTyValueBaseLine, 'YEAR'=>$this->baselineFieldName.' TY', 'RANK'=>(int)'6'.$data['YEAR'], 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpBaseLine1   = array_merge($cumTmpBaseLine1, [$dtKey=>$cumLyValueBaseLine, 'YEAR'=>$this->baselineFieldName.' LY', 'RANK'=>(int)'6'.($data['YEAR']-1), 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpBaseLine2   = array_merge($cumTmpBaseLine2, [$dtKey=>($cumTyValueBaseLine - $cumLyValueBaseLine), 'YEAR'=>'VAR', 'RANK'=>'6VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
                    $baselineCumVarPer = ($cumLyValueBaseLine > 0) ? (($cumTyValueBaseLine - $cumLyValueBaseLine) / $cumLyValueBaseLine)*100 : 0;
                    $cumTmpBaseLine3   = array_merge($cumTmpBaseLine3, [$dtKey=>$baselineCumVarPer, 'YEAR'=>'VAR%', 'RANK'=>'6VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

                    /*[INCREMENTAL CUM DATA]*/
                    $cumTyValueIncremental += $data['INCREMENTAL']; $cumLyValueIncremental += $lydata['INCREMENTAL'];
                    $cumTmpIncremental    = array_merge($cumTmpIncremental, [$dtKey=>$cumTyValueIncremental, 'YEAR'=>'INCREMENTAL TY', 'RANK'=>(int)'7'.$data['YEAR'], 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpIncremental1   = array_merge($cumTmpIncremental1, [$dtKey=>$cumLyValueIncremental, 'YEAR'=>'INCREMENTAL LY', 'RANK'=>(int)'7'.($data['YEAR']-1), 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
                    $cumTmpIncremental2   = array_merge($cumTmpIncremental2, [$dtKey=>($cumTyValueIncremental - $cumLyValueIncremental), 'YEAR'=>'VAR', 'RANK'=>'7VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
                    $incrementalCumVarPer = ($cumLyValueIncremental > 0) ? (($cumTyValueIncremental - $cumLyValueIncremental) / $cumLyValueIncremental)*100 : 0;
                    $cumTmpIncremental3   = array_merge($cumTmpIncremental3, [$dtKey=>$incrementalCumVarPer, 'YEAR'=>'VAR%', 'RANK'=>'7VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

                    /*[AVGPRICE CUM DATA]*/
                    $cumTyValueAvgPrice += $data['AVG_PRICE']; $cumLyValueAvgPrice += $lydata['AVG_PRICE'];
                    $cumTmpAvgPrice    = array_merge($cumTmpAvgPrice, [$dtKey=>$cumTyValueAvgPrice, 'YEAR'=>'PRICE TY (AVE)', 'RANK'=>(int)'8'.$data['YEAR'], 'ROWDESC'=>'Cumulative', 'highlightRow'=>0, 'dataDecimalPoint'=>2]);
                    $cumTmpAvgPrice1   = array_merge($cumTmpAvgPrice1, [$dtKey=>$cumLyValueAvgPrice, 'YEAR'=>'PRICE LY (AVE)', 'RANK'=>(int)'8'.($data['YEAR']-1), 'ROWDESC'=>'Cumulative', 'highlightRow'=>0, 'dataDecimalPoint'=>2]);
                    $cumTmpAvgPrice2   = array_merge($cumTmpAvgPrice2, [$dtKey=>($cumTyValueAvgPrice - $cumLyValueAvgPrice), 'YEAR'=>'VAR', 'RANK'=>'8VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>4, 'dataDecimalPoint'=>2]);
                    $avgPriceCumVarPer = ($cumLyValueAvgPrice > 0) ? (($cumTyValueAvgPrice - $cumLyValueAvgPrice) / $cumLyValueAvgPrice)*100 : 0;
                    $cumTmpAvgPrice3   = array_merge($cumTmpAvgPrice3, [$dtKey=>$avgPriceCumVarPer, 'YEAR'=>'VAR%', 'RANK'=>'8VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3, 'dataDecimalPoint'=>2]);

                    /*[SALES DATA TOTAL]*/
                    $cmpTyTotal[$dtTotalKey]            += $data['SALES'];
                    $cmpLyTotal[$dtTotalKey]            += $lydata['SALES'];
                    $cmpBaseLineTyTotal[$dtTotalKey]    += $data['BASELINE'];
                    $cmpBaseLineLyTotal[$dtTotalKey]    += $lydata['BASELINE'];
                    $cmpIncrementalTyTotal[$dtTotalKey] += $data['INCREMENTAL'];
                    $cmpIncrementalLyTotal[$dtTotalKey] += $lydata['INCREMENTAL'];
                    $cmpAvgPriceTyTotalAvgData[$dtTotalKey][$account] = $data['AVG_PRICE'];
                    $cmpAvgPriceLyTotalAvgData[$dtTotalKey][$account] = $lydata['AVG_PRICE'];
                }
            }

            $dataIndexedChart2[$cumTmpBaseLine['ACCOUNT']]['BASELINE']    = $cumTmpBaseLine['dt'.str_replace('-', '', $latestCumDate)];
            $dataIndexedChart2[$cumTmpIncremental['ACCOUNT']]['INCREMENTAL'] = $cumTmpIncremental['dt'.str_replace('-', '', $latestCumDate)];

            $finalData[] = $tmpSales;
            $finalData[] = $tmpSales1;
            $finalData[] = $tmpSales2;
            $finalData[] = $tmpSales3;

            $finalData[] = $tmpBaseLine;
            $finalData[] = $tmpBaseLine1;
            $finalData[] = $tmpBaseLine2;
            $finalData[] = $tmpBaseLine3;

            $finalData[] = $tmpIncremental;
            $finalData[] = $tmpIncremental1;
            $finalData[] = $tmpIncremental2;
            $finalData[] = $tmpIncremental3;

            $finalData[] = $tmpAvgPrice;
            $finalData[] = $tmpAvgPrice1;
            $finalData[] = $tmpAvgPrice2;
            //$finalData[] = $tmpAvgPrice3;

            $finalData[] = $cumTmpSales;
            $finalData[] = $cumTmpSales1;
            $finalData[] = $cumTmpSales2;
            $finalData[] = $cumTmpSales3;
            
            $finalData[] = $cumTmpBaseLine;
            $finalData[] = $cumTmpBaseLine1;
            $finalData[] = $cumTmpBaseLine2;
            $finalData[] = $cumTmpBaseLine3;

            $finalData[] = $cumTmpIncremental;
            $finalData[] = $cumTmpIncremental1;
            $finalData[] = $cumTmpIncremental2;
            $finalData[] = $cumTmpIncremental3;

            //$finalData[] = $cumTmpAvgPrice;
            //$finalData[] = $cumTmpAvgPrice1;
            //$finalData[] = $cumTmpAvgPrice2;
            //$finalData[] = $cumTmpAvgPrice3;

            $cnt++;
        }

        /*[START] ADDING THE TOTAL COLUMN*/
        $cmpTyLyVarTotal = $cmpTyLyYoyTotal = $cmpTyLyVarCumTotal = $cmpTyLyYoyCumTotal = $cmpTyCumTotal = $cmpLyCumTotal = [];
        $cmpTyLyBaseLineVarTotal = $cmpTyLyBaseLineYoyTotal = $cmpTyLyBaseLineVarCumTotal = $cmpTyLyBaseLineYoyCumTotal = $cmpTyBaseLineCumTotal = $cmpLyBaseLineCumTotal = [];
        $cmpTyLyIncrementalVarTotal = $cmpTyLyIncrementalYoyTotal = $cmpTyLyIncrementalVarCumTotal = $cmpTyLyIncrementalYoyCumTotal = $cmpTyIncrementalCumTotal = $cmpLyIncrementalCumTotal = [];
        $cmpTyLyAvgPriceVarTotal = $cmpTyLyAvgPriceYoyTotal = $cmpTyLyAvgPriceVarCumTotal = $cmpTyLyAvgPriceYoyCumTotal = $cmpTyAvgPriceCumTotal = $cmpLyAvgPriceCumTotal = [];

        //ksort($cmpTyTotal); ksort($cmpLyTotal); ksort($cmpBaseLineTyTotal); ksort($cmpBaseLineLyTotal);
        //ksort($cmpIncrementalTyTotal); ksort($cmpIncrementalLyTotal); 

        /*[START] CALCULTING PRICE FIELD ARRAY VALUE */
            if(count($cmpAvgPriceTyTotalAvgData)){
                foreach ($cmpAvgPriceTyTotalAvgData as $dtKey => $dateAccountVal) {
                    $cmpAvgPriceTyTotal[$dtKey] = 0;
                    if(count($dateAccountVal)) {
                        //$dateAccountVal = array_filter($dateAccountVal);
                        $cmpAvgPriceTyTotal[$dtKey] = array_sum($dateAccountVal)/count($dateAccountVal);
                    }
                }
            }

            if(count($cmpAvgPriceLyTotalAvgData)){
                foreach ($cmpAvgPriceLyTotalAvgData as $dtKey => $dateAccountVal) {
                    $cmpAvgPriceLyTotal[$dtKey] = 0;
                    if(count($dateAccountVal)) {
                        //$dateAccountVal = array_filter($dateAccountVal);
                        $cmpAvgPriceLyTotal[$dtKey] = array_sum($dateAccountVal)/count($dateAccountVal);
                    }
                }
            }
        /*[END] CALCULTING PRICE FIELD ARRAY VALUE */
        //ksort($cmpAvgPriceTyTotal); ksort($cmpAvgPriceLyTotal);

        if(isset($cmpTyTotal) && isset($cmpLyTotal)) {
            $cumTyValue = $cumLyValue = $cumBaseLineTyValue = $cumBaseLineLyValue = $cumIncrementalTyValue = $cumIncrementalLyValue = $cumAvgPriceTyValue = $cumAvgPriceLyValue = 0;
            foreach ($cmpTyTotal as $cpTykey => $cmTyVal) {
                $cpTykey = $cpTykey;

                /*[SALES DATA]*/
                $cumTyValue += $cmTyVal;
                $cmpTyCumTotal[$cpTykey]   = $cumTyValue;
                $cmpTyLyVarTotal[$cpTykey] = ($cmTyVal - $cmpLyTotal[$cpTykey]);
                $cmpTyLyYoyTotal[$cpTykey] = ($cmpLyTotal[$cpTykey] > 0) ? ((($cmTyVal - $cmpLyTotal[$cpTykey]) / $cmpLyTotal[$cpTykey])*100) : 0;

                    /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumLyValue += $cmpLyTotal[$cpTykey];
                    $cmpLyCumTotal[$cpTykey] = $cumLyValue;
                    $cmpTyLyVarCumTotal[$cpTykey] = ($cmpTyCumTotal[$cpTykey] - $cmpLyCumTotal[$cpTykey]);
                    $cmpTyLyYoyCumTotal[$cpTykey] = ($cmpLyCumTotal[$cpTykey] > 0) ? ((($cmpTyCumTotal[$cpTykey] - $cmpLyCumTotal[$cpTykey]) / $cmpLyCumTotal[$cpTykey])*100) : 0;
                    /*[END] GETTING CUMULATIVE LY VALUE*/

                /*[BASELINE DATA]*/
                $cumBaseLineTyValue += $cmpBaseLineTyTotal[$cpTykey];
                $cmpTyBaseLineCumTotal[$cpTykey] = $cumBaseLineTyValue;
                $cmpTyLyBaseLineVarTotal[$cpTykey] = ($cmpBaseLineTyTotal[$cpTykey] - $cmpBaseLineLyTotal[$cpTykey]);
                $cmpTyLyBaseLineYoyTotal[$cpTykey] = ($cmpBaseLineLyTotal[$cpTykey] > 0) ? ((($cmpBaseLineTyTotal[$cpTykey] - $cmpBaseLineLyTotal[$cpTykey]) / $cmpBaseLineLyTotal[$cpTykey])*100) : 0;

                    /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumBaseLineLyValue += $cmpBaseLineLyTotal[$cpTykey];
                    $cmpLyBaseLineCumTotal[$cpTykey]      = $cumBaseLineLyValue;
                    $cmpTyLyBaseLineVarCumTotal[$cpTykey] = ($cmpTyBaseLineCumTotal[$cpTykey] - $cmpLyBaseLineCumTotal[$cpTykey]);
                    $cmpTyLyBaseLineYoyCumTotal[$cpTykey] = ($cmpLyBaseLineCumTotal[$cpTykey] > 0) ? ((($cmpTyBaseLineCumTotal[$cpTykey] - $cmpLyBaseLineCumTotal[$cpTykey]) / $cmpLyBaseLineCumTotal[$cpTykey])*100) : 0;
                    /*[END] GETTING CUMULATIVE LY VALUE*/

                /*[INCREMENTAL DATA]*/
                $cumIncrementalTyValue += $cmpIncrementalTyTotal[$cpTykey];
                $cmpTyIncrementalCumTotal[$cpTykey] = $cumIncrementalTyValue;
                $cmpTyLyIncrementalVarTotal[$cpTykey] = ($cmpIncrementalTyTotal[$cpTykey] - $cmpIncrementalLyTotal[$cpTykey]);
                $cmpTyLyIncrementalYoyTotal[$cpTykey] = ($cmpIncrementalLyTotal[$cpTykey] > 0) ? ((($cmpIncrementalTyTotal[$cpTykey] - $cmpIncrementalLyTotal[$cpTykey]) / $cmpIncrementalLyTotal[$cpTykey])*100) : 0;

                    /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumIncrementalLyValue += $cmpIncrementalLyTotal[$cpTykey];
                    $cmpLyIncrementalCumTotal[$cpTykey]   = $cumIncrementalLyValue;
                    $cmpTyLyIncrementalVarCumTotal[$cpTykey] = ($cmpTyIncrementalCumTotal[$cpTykey] - $cmpLyIncrementalCumTotal[$cpTykey]);
                    $cmpTyLyIncrementalYoyCumTotal[$cpTykey] = ($cmpLyIncrementalCumTotal[$cpTykey] > 0) ? ((($cmpTyIncrementalCumTotal[$cpTykey] - $cmpLyIncrementalCumTotal[$cpTykey]) / $cmpLyIncrementalCumTotal[$cpTykey])*100) : 0;
                    /*[END] GETTING CUMULATIVE LY VALUE*/

                /*[AVGPRICE DATA]*/
                $cumAvgPriceTyValue += $cmpAvgPriceTyTotal[$cpTykey];
                $cmpTyAvgPriceCumTotal[$cpTykey] = $cumAvgPriceTyValue;
                $cmpTyLyAvgPriceVarTotal[$cpTykey] = ($cmpAvgPriceTyTotal[$cpTykey] - $cmpAvgPriceLyTotal[$cpTykey]);
                $cmpTyLyAvgPriceYoyTotal[$cpTykey] = ($cmpAvgPriceLyTotal[$cpTykey] > 0) ? ((($cmpAvgPriceTyTotal[$cpTykey] - $cmpAvgPriceLyTotal[$cpTykey]) / $cmpAvgPriceLyTotal[$cpTykey])*100) : 0;

                    /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumAvgPriceLyValue += $cmpAvgPriceLyTotal[$cpTykey];
                    $cmpLyAvgPriceCumTotal[$cpTykey]   = $cumAvgPriceLyValue;
                    $cmpTyLyAvgPriceVarCumTotal[$cpTykey] = ($cmpTyAvgPriceCumTotal[$cpTykey] - $cmpLyAvgPriceCumTotal[$cpTykey]);
                    $cmpTyLyAvgPriceYoyCumTotal[$cpTykey] = ($cmpLyAvgPriceCumTotal[$cpTykey] > 0) ? ((($cmpTyAvgPriceCumTotal[$cpTykey] - $cmpLyAvgPriceCumTotal[$cpTykey]) / $cmpLyAvgPriceCumTotal[$cpTykey])*100) : 0;
                    /*[END] GETTING CUMULATIVE LY VALUE*/
            }

            //if ($isExport) {
                $allColumnTotalList = [
                    'SALES_WEEKLY'          => [$cmpTyTotal, $cmpLyTotal, $cmpTyLyVarTotal, $cmpTyLyYoyTotal],
                    'SALES_CUMULATIVE'      => [$cmpTyCumTotal, $cmpLyCumTotal, $cmpTyLyVarCumTotal, $cmpTyLyYoyCumTotal],
                    'BASELINE_WEEKLY'       => [$cmpBaseLineTyTotal, $cmpBaseLineLyTotal, $cmpTyLyBaseLineVarTotal, $cmpTyLyBaseLineYoyTotal],
                    'BASELINE_CUMULATIVE'   => [$cmpTyBaseLineCumTotal, $cmpLyBaseLineCumTotal, $cmpTyLyBaseLineVarCumTotal, $cmpTyLyBaseLineYoyCumTotal],
                    'INCREMENTAL_WEEKLY'    => [$cmpIncrementalTyTotal, $cmpIncrementalLyTotal, $cmpTyLyIncrementalVarTotal, $cmpTyLyIncrementalYoyTotal],
                    'INCREMENTAL_CUMULATIVE'=> [$cmpTyIncrementalCumTotal, $cmpLyIncrementalCumTotal, $cmpTyLyIncrementalVarCumTotal, $cmpTyLyIncrementalYoyCumTotal],
                    'AVG_PRICE_WEEKLY'    => [$cmpAvgPriceTyTotal, $cmpAvgPriceLyTotal, $cmpTyLyAvgPriceVarTotal, $cmpTyLyAvgPriceYoyTotal],
                    'AVG_PRICE_CUMULATIVE'=> [$cmpTyAvgPriceCumTotal, $cmpLyAvgPriceCumTotal, $cmpTyLyAvgPriceVarCumTotal, $cmpTyLyAvgPriceYoyCumTotal]
                ];
            //}
            $dataIndexedChart2['TOTAL']['BASELINE']    = $cmpTyBaseLineCumTotal['dt'.str_replace('-', '', $latestCumDate)];
            $dataIndexedChart2['TOTAL']['INCREMENTAL'] = $cmpTyIncrementalCumTotal['dt'.str_replace('-', '', $latestCumDate)];
            
            $dataPnameSum['TOTAL']      = 1;
            /*[SALES DATA]*/
            $cmpTyTotal                 = array_merge($cmpTyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->salesFieldName.' TY', 'RANK'=>(int)'1'.$ty, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpLyTotal                 = array_merge($cmpLyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->salesFieldName.' LY', 'RANK'=>(int)'1'.$ly, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpTyLyVarTotal            = array_merge($cmpTyLyVarTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'1VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
            $cmpTyLyYoyTotal            = array_merge($cmpTyLyYoyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'1VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

            /*[BASELINE DATA]*/
            $cmpBaseLineTyTotal         = array_merge($cmpBaseLineTyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->baselineFieldName.' TY', 'RANK'=>(int)'2'.$ty, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpBaseLineLyTotal         = array_merge($cmpBaseLineLyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->baselineFieldName.' LY', 'RANK'=>(int)'2'.$ly, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpTyLyBaseLineVarTotal    = array_merge($cmpTyLyBaseLineVarTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'2VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
            $cmpTyLyBaseLineYoyTotal    = array_merge($cmpTyLyBaseLineYoyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'2VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

            /*[INCREMENTAL DATA]*/
            $cmpIncrementalTyTotal      = array_merge($cmpIncrementalTyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'INCREMENTAL TY', 'RANK'=>(int)'3'.$ty, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpIncrementalLyTotal      = array_merge($cmpIncrementalLyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'INCREMENTAL LY', 'RANK'=>(int)'3'.$ly, 'ROWDESC'=>'Weekly', 'highlightRow'=>1]);
            $cmpTyLyIncrementalVarTotal = array_merge($cmpTyLyIncrementalVarTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'3VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>2]);
            $cmpTyLyIncrementalYoyTotal = array_merge($cmpTyLyIncrementalYoyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'3VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3]);

            /*[AVGPRICE DATA]*/
            $cmpAvgPriceTyTotal         = array_merge($cmpAvgPriceTyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'PRICE TY', 'RANK'=>(int)'4'.$ty, 'ROWDESC'=>'Weekly', 'highlightRow'=>1, 'dataDecimalPoint'=>2]);
            $cmpAvgPriceLyTotal         = array_merge($cmpAvgPriceLyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'PRICE LY', 'RANK'=>(int)'4'.$ly, 'ROWDESC'=>'Weekly', 'highlightRow'=>1, 'dataDecimalPoint'=>2]);
            $cmpTyLyAvgPriceVarTotal    = array_merge($cmpTyLyAvgPriceVarTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'4VAR', 'ROWDESC'=>'Weekly', 'highlightRow'=>4, 'dataDecimalPoint'=>2]);
            $cmpTyLyAvgPriceYoyTotal    = array_merge($cmpTyLyAvgPriceYoyTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'4VAR%', 'ROWDESC'=>'Weekly', 'highlightRow'=>3, 'dataDecimalPoint'=>2]);
                        
            /*[SALES CUM DATA]*/
            $cmpTyCumTotal              = array_merge($cmpTyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->salesFieldName.' TY', 'RANK'=>(int)'5'.$ty, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpLyCumTotal              = array_merge($cmpLyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->salesFieldName.' LY', 'RANK'=>(int)'5'.$ly, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpTyLyVarCumTotal         = array_merge($cmpTyLyVarCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'5VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
            $cmpTyLyYoyCumTotal         = array_merge($cmpTyLyYoyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'5VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

            /*[BASELINE CUM DATA]*/
            $cmpTyBaseLineCumTotal      = array_merge($cmpTyBaseLineCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->baselineFieldName.' TY', 'RANK'=>(int)'6'.$ty, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpLyBaseLineCumTotal      = array_merge($cmpLyBaseLineCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>$this->baselineFieldName.' LY', 'RANK'=>(int)'6'.$ly, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpTyLyBaseLineVarCumTotal = array_merge($cmpTyLyBaseLineVarCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'6VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
            $cmpTyLyBaseLineYoyCumTotal = array_merge($cmpTyLyBaseLineYoyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'6VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

            /*[INCREMENTAL CUM DATA]*/
            $cmpTyIncrementalCumTotal      = array_merge($cmpTyIncrementalCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'INCREMENTAL TY', 'RANK'=>(int)'7'.$ty, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpLyIncrementalCumTotal      = array_merge($cmpLyIncrementalCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'INCREMENTAL LY', 'RANK'=>(int)'7'.$ly, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0]);
            $cmpTyLyIncrementalVarCumTotal = array_merge($cmpTyLyIncrementalVarCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'7VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>2]);
            $cmpTyLyIncrementalYoyCumTotal = array_merge($cmpTyLyIncrementalYoyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'7VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3]);

            /*[AVGPRICE CUM DATA]*/
            /*$cmpTyAvgPriceCumTotal      = array_merge($cmpTyAvgPriceCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'PRICE TY (AVE)', 'RANK'=>(int)'8'.$ty, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0,'dataDecimalPoint'=>2]);
            $cmpLyAvgPriceCumTotal      = array_merge($cmpLyAvgPriceCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'PRICE LY (AVE)', 'RANK'=>(int)'8'.$ly, 'ROWDESC'=>'Cumulative', 'highlightRow'=>0,'dataDecimalPoint'=>2]);
            $cmpTyLyAvgPriceVarCumTotal = array_merge($cmpTyLyAvgPriceVarCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR', 'RANK'=>'8VAR', 'ROWDESC'=>'Cumulative', 'highlightRow'=>4,'dataDecimalPoint'=>2]);
            $cmpTyLyAvgPriceYoyCumTotal = array_merge($cmpTyLyAvgPriceYoyCumTotal, ['TOTAL'=>1, 'ACCOUNT'=>'TOTAL', 'YEAR'=>'VAR%', 'RANK'=>'8VAR%', 'ROWDESC'=>'Cumulative', 'highlightRow'=>3,'dataDecimalPoint'=>2]);*/

            $finalData[] = $cmpTyTotal;
            $finalData[] = $cmpLyTotal;
            $finalData[] = $cmpTyLyVarTotal;
            $finalData[] = $cmpTyLyYoyTotal;

            $finalData[] = $cmpBaseLineTyTotal;
            $finalData[] = $cmpBaseLineLyTotal;
            $finalData[] = $cmpTyLyBaseLineVarTotal;
            $finalData[] = $cmpTyLyBaseLineYoyTotal;

            $finalData[] = $cmpIncrementalTyTotal;
            $finalData[] = $cmpIncrementalLyTotal;
            $finalData[] = $cmpTyLyIncrementalVarTotal;
            $finalData[] = $cmpTyLyIncrementalYoyTotal;

            $finalData[] = $cmpAvgPriceTyTotal;
            $finalData[] = $cmpAvgPriceLyTotal;
            $finalData[] = $cmpTyLyAvgPriceVarTotal;
            //$finalData[] = $cmpTyLyAvgPriceYoyTotal;

            $finalData[] = $cmpTyCumTotal;
            $finalData[] = $cmpLyCumTotal;
            $finalData[] = $cmpTyLyVarCumTotal;
            $finalData[] = $cmpTyLyYoyCumTotal;

            $finalData[] = $cmpTyBaseLineCumTotal;
            $finalData[] = $cmpLyBaseLineCumTotal;
            $finalData[] = $cmpTyLyBaseLineVarCumTotal;
            $finalData[] = $cmpTyLyBaseLineYoyCumTotal;

            $finalData[] = $cmpTyIncrementalCumTotal;
            $finalData[] = $cmpLyIncrementalCumTotal;
            $finalData[] = $cmpTyLyIncrementalVarCumTotal;
            $finalData[] = $cmpTyLyIncrementalYoyCumTotal;

            //$finalData[] = $cmpTyAvgPriceCumTotal;
            //$finalData[] = $cmpLyAvgPriceCumTotal;
            //$finalData[] = $cmpTyLyAvgPriceVarCumTotal;
            //$finalData[] = $cmpTyLyAvgPriceYoyCumTotal;
        }
        /*[END] ADDING THE TOTAL COLUMN*/

        $totalArray = array_column($finalData, 'TOTAL');
        array_multisort($totalArray, SORT_DESC, SORT_NUMERIC, $finalData);
        
        $chart2Data = [];
        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            asort($dataPnameSum);
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
               $chart2Data['BASELINE'][]    = $dataIndexedChart2[$k1]['BASELINE'];
               $chart2Data['INCREMENTAL'][] = $dataIndexedChart2[$k1]['INCREMENTAL'];
               $chart2xAxisData[] = $k1;
            }
        }
        
        if ($isExport) {
            ksort($seasonalDataArray);
            //sort($allYearRows);
            return [$seasonalDataArray, array_values($dateArray), array_values($allWeekColumns), $allColumnTotalList];
        } else {
            /*
                $this->jsonOutput['gridAllColumnsHeaderYear'] = array_values($allYearRows);
                $this->jsonOutput['gridAllColumnsHeaderWeek'] = array_values($allWeekColumns);
            */

            $this->jsonOutput['gridAllColumnsHeaderWeek'] = array_values($dateArray);
            $this->jsonOutput['gridData'] = $finalData;
            $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
            $this->jsonOutput['chartData']['chart1'] = [
                                                'BASELINETY'    => array_values($allColumnTotalList['BASELINE_WEEKLY'][0]),
                                                'INCREMENTALTY' => array_values($allColumnTotalList['INCREMENTAL_WEEKLY'][0]),
                                                'BASELINELY'    => array_values($allColumnTotalList['BASELINE_WEEKLY'][1]),
                                                'INCREMENTALLY' => array_values($allColumnTotalList['INCREMENTAL_WEEKLY'][1]),
                                                'PRICETY'       => array_values($allColumnTotalList['AVG_PRICE_WEEKLY'][0]),
                                                'PRICELY'       => array_values($allColumnTotalList['AVG_PRICE_WEEKLY'][1]),
                                             ];
            $this->jsonOutput['chartData']['chart2'] = [
                                                'BASELINETY'    => $chart2Data['BASELINE'],
                                                'INCREMENTALTY' => $chart2Data['INCREMENTAL'],
                                                'xAxisData'     => $chart2xAxisData,
                                             ];
        }
    }

    public function export(){

        list($gridData, $gridAllColumnsHeaderYear, $gridAllColumnsHeaderWeek, $gridDataTotal) = $this->gridData(true);
        $dataHash = '';
        
        if(is_array($gridData) && count($gridData) > 0){
            $redisCache = new utils\RedisCache($this->queryVars);
            $redisCache->requestHash = $redisCache->prepareQueryHash('baseIncrementalTrackerReportData');
            $redisCache->setDataForStaticHash([$gridData, $gridAllColumnsHeaderYear, $gridAllColumnsHeaderWeek, $gridDataTotal]);
            $dataHash = $redisCache->requestHash;
        }

        /*$objRichText = '';
        if(count($this->productFilterData)>0){
            $lstV = count($this->productFilterData);
            $i = 0;
            foreach ($this->productFilterData as $kIds => $Val) {
                if($lstV == $i)
                    $Val = $Val;
                else    
                    $Val = $Val."\r\n";
                $objRichText.= $kIds.' : '.$Val;
            }
        }else{
            $objRichText = 'All';
        }
        $appliedFilters[] = 'Product Filter##'.$objRichText;*/

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
        
        $fileName      = "Base-Incremental-Tracker-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath      = dirname(__FILE__)."/../uploads/Base-Incremental-Tracker-Report/";
        $imgLogoPath   = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
        $filePath      = $savePath.$fileName;
        $projectID     = $this->settingVars->projectID;
        $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
        $RedisPassword = $this->queryVars->RedisPassword;
        $appliedFiltersTxt = implode('$$', $appliedFilters);
        $appliedFieldName = $this->accountFieldName;
        

        /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/BaseIncrementalTrackerReport.pl "'.$filePath.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$dataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$appliedFieldName.'" "'.$this->salesFieldName.'" "'.$this->baselineFieldName.'"';
        exit;*/

        $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/BaseIncrementalTrackerReport.pl "'.$filePath.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$dataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$appliedFieldName.'" "'.$this->salesFieldName.'" "'.$this->baselineFieldName.'"');

        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Base-Incremental-Tracker-Report/".$fileName;
    }

    public function getAllDataFromIds($type,$id,$data){
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketSelectionTabsRedisList');
        if ($redisOutput === false) {
            return $data;
        } else {
            
            $selectionTabsRedisList = $redisOutput[$type];
            if(!is_array($selectionTabsRedisList) || empty($selectionTabsRedisList))
                 return $data;

            if($type == 'FSG')
            {
                $storeList = array_column($selectionTabsRedisList, 'data');
                $Skey = array_search($data, $storeList);
                if(is_numeric($Skey))
                {
                    $data = $selectionTabsRedisList[$Skey]['label'];
                }
            }
            else
            {
                $aKey = array_search($id, array_column($selectionTabsRedisList, 'data'));
                if(isset($selectionTabsRedisList[$aKey]) && isset($selectionTabsRedisList[$aKey]['dataList']) && is_array($selectionTabsRedisList[$aKey]['dataList']) && count($selectionTabsRedisList[$aKey]['dataList'])>0){

                    $mainArr = array_column($selectionTabsRedisList[$aKey]['dataList'], 'label','data');
                    $fndata = [];
                    $data = explode(',', $data);
                    if(is_array($data) && count($data)>0){
                        foreach ($data as $k => $vl) {
                            if(isset($mainArr[$vl]) && !empty($mainArr[$vl]))
                                $fndata[] = $mainArr[$vl];
                            else
                                $fndata[] = $vl;
                        }
                        $data = implode(',', $fndata);
                    }
                }
            }
            return $data;
        }
    }

    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn = false ) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {
        $tables = array();
        if ($this->settingVars->isDynamicPage){
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
            $tablesSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID);
        }else {
            if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                $tables = array($this->settingVars->skutable);
            else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                $tables = array("market");
        }

        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            
            $fields = $this->prepareFieldsFromFieldSelectionSettings($tables, false);

            /*foreach ($tables as $table) {
                if(is_array($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration[$table . "_settings"]) ){
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_field_selection_settings"]); //Field Selection
                    if(empty($settings))
                        $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_settings"]);

                    foreach ($settings as $key => $field) {
                        $val = explode("#", $field);
                        
                        if($table == 'market')
                        {
                            $tbl = ($table == 'market') ? $this->settingVars->storetable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'market') ? $this->settingVars->storetable : $table).".".$val[0];
                        }
                        elseif($table == 'account')
                        {
                            $tbl = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'account') ? $this->settingVars->accounttable : $table).".".$val[0];
                        }
                        elseif($table == 'product')
                        {
                            $tbl = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = ((isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table).".".$val[0];
                        }
                        else
                        {
                            $fields[] = (count($val) > 1) ? $table.".".$val[0]."#".$table.".".$val[1] : $table.".".$val[0];
                            //$fields[] = $table.".".$val[0];
                        }
                        
                        if ($key == 0) {
                            if($table == 'market')
                                $appendTable = ($table == 'market') ? $this->settingVars->storetable : $table;
                            elseif($table == 'account')
                                $appendTable = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            elseif($table == 'product')
                                $appendTable = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            else
                                $appendTable = $table;
                            
                            //$this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable . "." . $val[0];
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = (count($val) > 1) ? $appendTable.".".$val[0]."#".$appendTable.".".$val[1] : $appendTable.".".$val[0];
                        }
                    }
                }
            }*/
            
            $this->buildDataArray($fields, false, true);
            if(isset($tablesSelectedField) && is_array($tablesSelectedField) && !empty($tablesSelectedField)){
                $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $tablesSelectedField[0];
            } else {
                $account = explode("#", $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]);
                $search = array_search($account[0],$this->dbColumnsArray);
                
                if($search !== false) {
                    if(count($account) > 1) {
                        $search1 = array_search($account[1],$this->dbColumnsArray);
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                    }
                    else
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                }
            }
            
            foreach ($fields as $field) {
                $val = explode("#", $field);
                $search = array_search($val[0],$this->dbColumnsArray);
                
                if($search !== false) {
                    if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $search && $search !== false) {
                        if(count($val) > 1) {
                            $search1 = array_search($val[1],$this->dbColumnsArray);
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                        }
                        else
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                    }

                    if(count($val) > 1) {
                        $search1 = array_search($val[1],$this->dbColumnsArray);
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search."#".$search1);
                    }
                    else
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search);
                }
            }
            $this->jsonOutput['fieldSelection'] = $tmpArr;
            
        } elseif (isset($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]) &&
                !empty($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"])) {
            $this->skipDbcolumnArray = true;
        } else {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }

    public function configurePage() {
        $fetchConfig = false;
        if (isset($_REQUEST['Field']) && !empty($_REQUEST['Field'])) {
            $getField = $_REQUEST['Field'];
        } else {
            $this->buildPageArray();
            $getField = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]; // DEFINED ACCOUNT NAME FROM PAGE SETTING
            $this->jsonOutput['selectedField'] = $getField;
        }
        
        $this->isShowSkuIDCol = false;
        $this->buildDataArray(array($getField), true, false);
        $gridFieldPart = explode("#", $getField);
        $accountField  = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $accountField  = (count($gridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $accountField;
        
        $this->isShowSkuIDCol = (count($gridFieldPart) > 1) ? true : false;
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $this->accountFieldName = ($this->settingVars->dataArray[$accountField] && $this->settingVars->dataArray[$accountField]['NAME_CSV']) ? $this->settingVars->dataArray[$accountField]['NAME_CSV'] : 'SUMMARY';

        if ($this->isShowSkuIDCol)
            $this->jsonOutput['skuIDColName'] = $this->settingVars->dataArray[$accountField]['ID_CSV'];
        
        $this->salesField = $this->settingVars->maintable.'.SALES'; $this->salesFieldName = 'SALES';
        $this->baselineField = $this->settingVars->maintable.'.BASELINE'; $this->baselineFieldName = 'BASELINE';
        $this->avgPriceField = $this->settingVars->maintable.'.AVG_PRICE';
        if (isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])) {
            $valueVolumeKey = array_search($_REQUEST['ValueVolume'], array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"],'measureID'));
            if ($valueVolumeKey === false) {
                //not found no changes
            }else{
                if(isset($this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$valueVolumeKey]) && $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$valueVolumeKey]['jsonKey'] == 'VOLUME'){                
                    $this->salesField = $this->settingVars->maintable.'.QTY'; $this->salesFieldName = 'SALES QTY';
                    $this->baselineField = $this->settingVars->maintable.'.BASELINE_QTY'; $this->baselineFieldName = 'BASELINE QTY';
                }
            }
        }

        /*[START] CODE FOR INLINE FILTER STYLE*/
        $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
        if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
            $this->jsonOutput['pageConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
        }
        /*[END] CODE FOR INLINE FILTER STYLE*/

        $this->jsonOutput['pageType'] = 'Arla LCL Base/Incremental Tracker';
    }

    public function fetchConfig() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
    }

}
?>