<?php

namespace classes\relayplus;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class VisualOnlineTracker extends config\UlConfig {

    public $tyHavingField;
    public $redisCache;

    public function __construct() {
        $this->tyHavingField = "VALUE";

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

        if (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchChartData') {
            $this->fetchChartData();
        }

        return $this->jsonOutput;
    }

    public function fetchChartData() 
    {
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        $requestedSeason      = $requestedCombination[0];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $this->measureFields[] = $_REQUEST['ACCOUNT'] = $this->settingVars->skutable.'.pname_rollup';
        
        $accountField = $this->settingVars->maintable.".PNAME";
        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $accountField = $_REQUEST['ACCOUNT'];
        }else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $accountField = $this->jsonOutput["defaultSelectedField"];
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;

        $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);
        
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyMydatePart[0] = $tyMydatePart[0]-1;
                $lyDate = implode('-', $tyMydatePart);

                $colsHeader['TY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($tyDate)), "MYDATE" => $tyDate);
                $colsHeader['LY'][] = array("FORMATED_DATE" => date('D', strtotime($tyDate)), "MYDATE" => $tyDate);
                $dateArray[$tyDate] = date('j-n', strtotime($tyDate));
            }
        }
        
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        $query = "SELECT DATE FROM ".$this->settingVars->volumeSellthruActualsTable." WHERE PIN IN (SELECT DISTINCT PIN FROM ".$this->settingVars->maintable." WHERE gid = ".$this->settingVars->GID." AND accountID = '".$this->settingVars->cid."') AND QTY > 0 GROUP BY DATE ORDER BY DATE DESC";
        
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
            $actualSalesDate = $result[0]['DATE'];
            $this->jsonOutput['actualDate'] = "Data to ".date("l dS M Y", strtotime($actualSalesDate));
        }
        
        $query = "SELECT PIN, DATE, MAX(QTY) as QTY FROM ".$this->settingVars->volumeSellthruActualsTable." WHERE PIN IN (SELECT DISTINCT PIN FROM ".$this->settingVars->maintable." WHERE gid = ".$this->settingVars->GID." AND accountID = '".$this->settingVars->cid."') GROUP BY PIN, DATE";
        
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
        }
        
        $query = "SELECT ".$accountField." AS ACCOUNT, ".
            "MAX(".$maintable.".pin) as PIN, " . 
            "MAX(".$this->settingVars->volumeSellthruTable.".MYDATE) as MYDATE, ".
            "DATE_FORMAT(".$this->settingVars->volumeSellthruTable.".MYDATE, '%e-%c') as FORMATED_DATE, ".
            "MAX(agreed_buy) as AGREED_BUY, ".
            "MAX(STC_FORECAST) as STC_FORECAST " . 
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . ")".
            " GROUP BY ACCOUNT, FORMATED_DATE ORDER BY MYDATE ASC";
        
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
                $seasonalData[$havingTYValue] = $seasonalData['AGREED_BUY']*($seasonalData['STC_FORECAST']/100);
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
        
        foreach (array_keys($seasonalDataArray) as $account) {
            $cumTmp = array();

            $cumTmp['ACCOUNT'] = $account;
            
            $tmpCumulativeFcastSum = $cumTyValue = $cumActualValue = 0;
            $tmpCumulativeSales = array();
            foreach ($dateArray as $dayMydate => $dayMonth) {
                $tyMydate = $dayMydate;
                
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$tyMydate);

                    $cumTmp['BUY_VOLUME'] = (double)$data['AGREED_BUY'];
                    $cumTmp['PIN'] = $data['PIN'];
                    $cumTyValue += $data[$havingTYValue];
                    
                    $cumActualValue += $actualSalesArray[$data['PIN'].$data['MYDATE']];
                    $tmpCumulativeSales[] = $cumActualValue;
                    
                    $unitToGo = ($data['AGREED_BUY'] - $cumActualValue);
                    $cumTmp['UNITS_TO_GO'] =  $unitToGo;
                    $cumTmp['UNITS_TO_GO_PER'] = ($data['AGREED_BUY'] > 0) ? ($unitToGo/$data['AGREED_BUY'])*100 : 0;
                    
                    if($actualSalesDate == $tyMydate)
                        $tmpCumulativeFcastSum = $cumTyValue;
                }
            }
            
            $cumTmp['cumulativeFcastMax'] = (int)$tmpCumulativeFcastSum;
            $cumTmp['cumulativeSalesMax'] = (double)max($tmpCumulativeSales);
            
            $finalData[] = $cumTmp;
        }
        
        $this->jsonOutput['chartData'] = $finalData;
        
        //$this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
        // data: [[Cumulative Sales max, Buy Volume]]
        // from: Cumulative Fcast min, to: Cumulative Fcast max, color: "#ccc", opacity: .6
    }

}
?> 