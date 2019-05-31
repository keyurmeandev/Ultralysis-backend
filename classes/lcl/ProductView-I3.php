<?php

namespace classes\lcl;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ProductView extends config\UlConfig {

    public $customSelectPart;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_ViewPage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->distributionField = $this->getPageConfiguration('distribution_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->accountField, $this->distributionField));
            $this->buildPageArray();
        }else{
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->skuID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->distributionID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['NAME'];
        }

        $this->customSelectPart = "COUNT( DISTINCT(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$tyWeekRange . " THEN 1 END )* " . $this->distributionID . " ) AS DIS" .
                                  ",COUNT( DISTINCT(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$lyWeekRange . " THEN 1 END )* " . $this->distributionID . " ) AS DISLY";
                                  
        if (!isset($_REQUEST["fetchConfig"]) )
            $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    function prepareGridData() {
        $arr = array();
        $temp = array();

        if (filters\timeFilter::$ToYear == filters\timeFilter::$FromYear)
            $week = (filters\timeFilter::$ToWeek - filters\timeFilter::$FromWeek) + 1;
        else
            $week = ( filters\timeFilter::$ToWeek) + (52 - filters\timeFilter::$FromWeek) + 1;

        /* Custome Part */
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->distributionID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "SELECT ". ($this->skuID!='' ? $this->skuID." AS ID, " : "" ) .$this->skuName." AS ACCOUNT " .
            (!empty($this->customSelectPart) ? ", " . $this->customSelectPart : "") .
            " FROM " . $this->settingVars->tablename . $this->queryPart .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ".($this->skuID!='' ? " ID, " : "" )." ACCOUNT";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }        
        
        $disList = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
                $disList[$data['ID'].$data['ACCOUNT']] = array("DIS" => $data['DIS'], "DISLY" => $data['DISLY']);
        }
        /* Custome Part */
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();
        
        $result = $this->prepareMeasureSelectPart();
        $measureSelectionArr = $result['measureSelectionArr'];
        $havingTYValue = $result['havingTYValue'];
        $havingLYValue = $result['havingLYValue'];
        
        $query = "SELECT " . ($this->skuID!='' ? $this->skuID." AS ID, " : "" ) . $this->skuName." AS ACCOUNT, ". implode(",", $measureSelectionArr)." ".
            ",SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS VOLUMETY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS VOLUMELY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS VALUE_TY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS VALUE_LY" .
            " FROM " . $this->settingVars->tablename . $this->queryPart .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ".($this->skuID!='' ? " ID, " : "" )." ACCOUNT";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $fields = $this->redisCache->getRequiredFieldsArray(array("ID", "ACCOUNT", "VOLUMETY", "VOLUMELY", "VALUE_TY", "VALUE_LY"), false, $this->settingVars->measureArray);
        $result = $this->redisCache->getRequiredData($result, $fields, $havingTYValue);
                
        $tempResult = array();
        if(isset($result) && !empty($result))
        {
            $total = array_sum(array_column($result,$havingTYValue));
            $totalSALESLY = array_sum(array_column($result,$havingLYValue));
            foreach ($result as $key => $data) {
                $accountId = $data['ID'].$data['ACCOUNT'];
                $var = $data[$havingLYValue] != 0 ? ((($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue]) * 100) : 0;
                $share = $total != 0 ? (($data[$havingTYValue] / $total) * 100) : 0;
                $shareLY = $totalSALESLY != 0 ? (($data[$havingLYValue] / $totalSALESLY) * 100) : 0;
                $cros = $disList[$accountId]['DIS'] > 0 ? (($data['VALUE_TY'] / $disList[$accountId]['DIS']) / $week) : 0;
                $crosLY = $disList[$accountId]['DISLY'] > 0 ? (($data['VALUE_LY'] / $disList[$accountId]['DISLY']) / $week) : 0;
                $uros = $disList[$accountId]['DIS'] > 0 ? (($data['VOLUMETY'] / $disList[$accountId]['DIS']) / $week) : 0;
                $urosLY = $disList[$accountId]['DISLY'] > 0 ? (($data['VOLUMELY'] / $disList[$accountId]['DISLY']) / $week) : 0;
                
                $idValue = ($this->skuID!='') ? $data['ID'] : htmlspecialchars_decode($data['ACCOUNT']);
                
                if ($data[$havingTYValue] > 0) {
                    $temp = array(
                        'SKUID' => $idValue,
                        'SKU' => htmlspecialchars_decode($data['ACCOUNT']),
                        'SALES' => $data[$havingTYValue],
                        'LYSALES' => $data[$havingLYValue],
                        'VAR' => number_format($var, 1, '.', ''),
                        'SHARE' => number_format($share, 1, '.', ''),
                        'SHARELY' => number_format($shareLY, 1, '.', ''),
                        'DIS' => $disList[$accountId]['DIS'],
                        'DISLY' => $disList[$accountId]['DISLY'],
                        'CROS' => number_format($cros, 2, '.', ''),
                        'CROSLY' => number_format($crosLY, 2, '.', ''),
                        'UROS' => number_format($uros, 2, '.', ''),
                        'UROSLY' => number_format($urosLY, 2, '.', ''),
                    );
                    $tempResult[] = $temp;
                }
            }
        }
        $this->jsonOutput["gridValue"] = $tempResult;
    }

    public function buildPageArray() {

        $accountFieldPart = explode("#", $this->accountField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
            $this->jsonOutput["gridColumns"]['SKUID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
            $this->jsonOutput["gridColumns"]['SKU'] = $this->displayCsvNameArray[$accountFieldPart[0]];
        }        

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        
        $this->skuID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$accountField]['NAME'];
        
        $distributionFieldPart = explode("#", $this->distributionField);
        $distributionField = strtoupper($this->dbColumnsArray[$distributionFieldPart[0]]);
        $distributionField = (count($distributionFieldPart) > 1) ? strtoupper($distributionField."_".$this->dbColumnsArray[$distributionFieldPart[1]]) : $distributionField;


        $this->distributionID = (isset($this->settingVars->dataArray[$distributionField]) && isset($this->settingVars->dataArray[$distributionField]['ID'])) ? $this->settingVars->dataArray[$distributionField]['ID'] : $this->settingVars->dataArray[$distributionField]['NAME'];

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
}

?>