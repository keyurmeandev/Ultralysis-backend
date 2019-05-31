<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class SeasonalPromoTracker extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->tpndField = $this->getPageConfiguration('tpnd_field', $this->settingVars->pageID)[0];
            $this->caseSizeField = $this->getPageConfiguration('case_size_field', $this->settingVars->pageID)[0];
            $this->totalBuyAgreedField = $this->getPageConfiguration('total_buy_agreed_field', $this->settingVars->pageID)[0];
            $this->totalBuyActualField = $this->getPageConfiguration('total_buy_actual_field', $this->settingVars->pageID)[0];
            
            $tempBuildFieldsArray = array($this->skuField, $this->tpndField, $this->caseSizeField, $this->totalBuyAgreedField, $this->totalBuyActualField);
            
            $this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        //$this->getLastDaysDate = filters\timeFilter::getLastNDaysDate($this->settingVars);
        switch ($action) {
            case "getGridData":
                $this->getData();
                $this->getChart();
                break;
        }
        
        return $this->jsonOutput;
    }

    function getData()
    {
        //$id         = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];

        $query = "SELECT " . "MYDATE as MYDATE, ". 
                $this->skuIdField. " as TPNB, ".
                "SUM((CASE WHEN " . $this->settingVars->tesco_depot_daily . ".mydate = '" . filters\timeFilter::$tyDaysRange[0] . 
                    "' AND ".$this->settingVars->tesco_depot_daily.".stock > 0 THEN 1 ELSE 0 END) * stock) AS DEPOT_STK, " .
                "SUM((CASE WHEN ".$this->settingVars->tesco_depot_daily.".stock > 0 THEN 1 ELSE 0 END)*".$this->settingVars->tesco_depot_daily.".stock) AS SUM_DCS " .
                "FROM " . $this->settingVars->depotTableName . " " . $this->settingVars->depotLink .
                "AND " . $this->settingVars->tesco_depot_daily . ".mydate IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') GROUP BY MYDATE, TPNB ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dsc = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data) {
                $dscByMydate[$data['MYDATE']] += $data['SUM_DCS'];
                $dscByTPNB[$data['TPNB']] = $data['DEPOT_STK'];
            }
        }   
    
        // GRID DATA
        /*$skuname            = $this->settingVars->dataArray['F2']['NAME'];
        $tpnd               = $this->settingVars->dataArray['F14']['NAME'];
        $caseSize           = $this->settingVars->dataArray['F15']['NAME'];
        $TOTAL_BUY_AGREED   = $this->settingVars->dataArray['F16']['NAME'];
        $TOTAL_BUY_ACTUAL   = $this->settingVars->dataArray['F17']['NAME'];
        
        $query = "SELECT ".$id." as TPNB, MAX(".$tpnd.") as TPND, MAX(".$skuname.") as SKU_NAME, ".
                " MAX(".$caseSize.") as CASE_SIZE, MAX(".$TOTAL_BUY_AGREED.") as TOTAL_BUY_AGREED, ".
                " MAX(".$TOTAL_BUY_ACTUAL.") as TOTAL_BUY_ACTUAL, ".
                " SUM((CASE WHEN " .$this->settingVars->DatePeriod . "=" . $this->getLastDaysDate[0] . " THEN 1 ELSE 0 END) * stock) AS STK " .
                // " SUM((CASE WHEN " . $this->settingVars->maintable . ".stockTra > 0 THEN 1 ELSE 0 END) * stockTra) AS STK_TRA ".
                " FROM ".$this->settingVars->tablename." ".$this->queryPart.
                " AND " . $this->settingVars->DatePeriod . " IN (" . implode(",", $this->getLastDaysDate) . ") GROUP BY TPNB";
        // echo $query;exit();*/

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->skuNameField;
        $this->measureFields[] = $this->tpndNameField;
        $this->measureFields[] = $this->caseSizeNameField;
        $this->measureFields[] = $this->totalBuyAgreedNameField;
        $this->measureFields[] = $this->totalBuyActualNameField;
        

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT ".$this->skuIdField." as TPNB, MAX(".$this->tpndNameField.") as TPND, MAX(".$this->skuNameField.") as SKU_NAME, ".
                " MAX(".$this->caseSizeNameField.") as CASE_SIZE, MAX(".$this->totalBuyAgreedNameField.") as TOTAL_BUY_AGREED, ".
                " MAX(".$this->totalBuyActualNameField.") as TOTAL_BUY_ACTUAL, ".
                " SUM((CASE WHEN " .$this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END) * stock) AS STK " .
                // " SUM((CASE WHEN " . $this->settingVars->maintable . ".stockTra > 0 THEN 1 ELSE 0 END) * stockTra) AS STK_TRA ".
                " FROM ".$this->settingVars->tablename." ".$this->queryPart.
                " AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') GROUP BY TPNB";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if(!empty($result) && is_array($result)){
            foreach($result as $key => $data){
                $result[$key]['VAR'] = $data['TOTAL_BUY_AGREED'] - $data['TOTAL_BUY_ACTUAL'];
                $result[$key]['DCS'] = (isset($dscByTPNB[$data['TPNB']]) && $dscByTPNB[$data['TPNB']] != "") ? $dscByTPNB[$data['TPNB']] : 0;
                $result[$key]['STK_TRA'] = $result[$key]['STK'] + $result[$key]['DCS'];
            }
        }
        $this->jsonOutput['bottomGridData'] = $result;
        
        // CHART DATA
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
    
        $query = "SELECT " . "MYDATE as MYDATE ". 
                ", SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS SUM_SAL, " .
                " SUM(stock) AS SUM_STK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') GROUP BY MYDATE ";
        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if(!empty($result) && is_array($result))
        {
            foreach($result as $key => $data)
            {
                $result[$key]['SUM_DCS'] = (int)$dscByMydate[$data['MYDATE']];
                $result[$key]['SUM_STK'] = (int)$data['SUM_STK'];
                $result[$key]['SUM_SAL'] = (int)$data['SUM_SAL'];
                $result[$key]['MYDATE_LABEL'] = date("d M Y", strtotime($data['MYDATE']));
            }
        }
        $this->jsonOutput['performanceChartData'] = $result;
    }
    
    public function getChart() {
        // $id      = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];

		/*$query = "SELECT DISTINCT depotID as depotID, ".
                "SUM((CASE WHEN ".$this->settingVars->tesco_depot_daily.".stock > 0 THEN 1 ELSE 0 END) * stock) AS DEPOT_STK " .
                "FROM " . $this->settingVars->depotTableName . " " . $this->settingVars->depotLink .
                "AND " . $this->settingVars->tesco_depot_daily . ".mydate = (SELECT MAX(".$this->settingVars->tesco_depot_daily.".mydate) from ".$this->settingVars->tesco_depot_daily.") ";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['stockDepotColumnChartData'] = $result;*/

        $getLastDepotDate = filters\timeFilter::getLastNDaysDateFromDepotDaily($this->settingVars);
        $query = "SELECT DISTINCT depotID as depotID, ".
                "MYDATE as MYDATE, ". 
                "SUM((CASE WHEN ".$this->settingVars->tesco_depot_daily.".stock > 0 THEN 1 ELSE 0 END) * stock) AS DEPOT_STK " .
                "FROM " . $this->settingVars->depotTableName . " " . $this->settingVars->depotLink." ORDER BY depotID ASC, MYDATE ASC";
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $finalData = $finalDataColumnChart = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $depotStock) {
            	$chkDepotdt = "'".$depotStock['MYDATE']."'";
				if($chkDepotdt == $getLastDepotDate) {
                	$finalDataColumnChart[$key]['depotID']   = $depotStock['depotID'];
                 	$finalDataColumnChart[$key]['DEPOT_STK'] = $depotStock['DEPOT_STK'];
                }
				$depotStock['MYDATE'] = date('d M Y', strtotime($depotStock['MYDATE']));
				$finalData[$depotStock['depotID']][] = $depotStock;
            }
        }
        $this->jsonOutput['stockDepotLineChartData'] = array_values($finalData);
        $this->jsonOutput['stockDepotColumnChartData'] = array_values($finalDataColumnChart);
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        $skuFieldPart = explode("#", $this->skuField);
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['bottomGridColumns']['TPNB'] =  (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['bottomGridColumns']['SKU_NAME'] =  $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['bottomGridColumns']['TPND'] =  $this->displayCsvNameArray[$this->tpndField];
            $this->jsonOutput['bottomGridColumns']['CASE_SIZE'] =  $this->displayCsvNameArray[$this->caseSizeField];
            $this->jsonOutput['bottomGridColumns']['TOTAL_BUY_AGREED'] =  $this->displayCsvNameArray[$this->totalBuyAgreedField];
            $this->jsonOutput['bottomGridColumns']['TOTAL_BUY_ACTUAL'] =  $this->displayCsvNameArray[$this->totalBuyActualField];
            
        }

        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuIdField = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];

        $tpndField = strtoupper($this->dbColumnsArray[$this->tpndField]);
        $this->tpndNameField = $this->settingVars->dataArray[$tpndField]['NAME'];

        $caseSizeField = strtoupper($this->dbColumnsArray[$this->caseSizeField]);
        $this->caseSizeNameField = $this->settingVars->dataArray[$caseSizeField]['NAME'];

        $totalBuyAgreedField = strtoupper($this->dbColumnsArray[$this->totalBuyAgreedField]);
        $this->totalBuyAgreedNameField = $this->settingVars->dataArray[$totalBuyAgreedField]['NAME'];

        $totalBuyActualField = strtoupper($this->dbColumnsArray[$this->totalBuyActualField]);
        $this->totalBuyActualNameField = $this->settingVars->dataArray[$totalBuyActualField]['NAME'];

        return;
    }
}
?>