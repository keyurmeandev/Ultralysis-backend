<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class SkuPerformancePage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;    

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        //$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_SKUPerformancePage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->gridFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];

            $tempBuildFieldsArray = array($this->storeField);
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->gridFields);

            $buildDataFields = array();
            foreach($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->getAllStore();
            $this->fetchConfig();
        }else{
            $this->prepareGridData(); //ADDING TO OUTPUT
        }

        return $this->jsonOutput;
    }

    public function fetchConfig(){
        $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);

        $gridColumns = array();

        foreach ($this->gridColumnName as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $gridColumns[$this->settingVars->dataArray[$data]['ID_ALIASE']] = $this->displayCsvNameArray[$this->gridFields[$key]];
            } else {
                $gridColumns[$this->settingVars->dataArray[$data]['NAME_ALIASE']] = $this->displayCsvNameArray[$this->gridFields[$key]];
            }
        }

        $this->jsonOutput['gridColumns'] = $gridColumns;

    }

    public function getAllStore(){

        $query = "SELECT ".$this->storeID." AS PRIMARY_ID,".$this->storeName." AS PRIMARY_LABEL  FROM ".$this->settingVars->geoHelperTables." ".$this->settingVars->geoHelperLink. " GROUP BY PRIMARY_ID,PRIMARY_LABEL HAVING PRIMARY_LABEL <>'' ORDER BY PRIMARY_LABEL ASC";
            
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $row = array();
        foreach ($result as $data) {

            $data['label'] = ($data['PRIMARY_LABEL'] != $data['PRIMARY_ID'] ) ? $data['PRIMARY_LABEL'] .' ( '.$data['PRIMARY_ID'].' ) ' : $data['PRIMARY_LABEL'];
            $row[] = $data;
        }
        
        $this->jsonOutput['storeList'] = $row;
    }

    function prepareGridData() {
        $rows = $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        $querypart = "";
        if ($_REQUEST["SNO"] != ""){
            $querypart .=" AND $this->storeID = '".$_REQUEST['SNO']."' ";
            $this->measureFields[] = $this->storeID;
        }

        foreach ($this->gridColumnName as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $groupByPart[] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];

                $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $groupByPart[] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . implode(",", $selectPart) .
                " , SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS  TYVAL, ".
                " SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS LYVAL, ".
                " SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS TYUNIT , ".
                " SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS LYUNIT ".
                " FROM " . $this->settingVars->tablename . $this->queryPart . $querypart ." " .
                " GROUP BY " . implode(",", $groupByPart) .
                " ORDER BY TYVAL DESC ";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }        

        if(isset($result) && !empty($result))
        {                   
            foreach ($result as $key => $data) {

                $data['TYVAL'] = (float) $data['TYVAL'];
                $data['LYVAL'] = (float) $data['LYVAL'];
                $data['VAR1'] = ($data['LYVAL'] > 0) ? (($data['TYVAL'] - $data['LYVAL']) / $data['LYVAL']) * 100 : 0 ;
                $data['VAR2'] = ($data['LYUNIT'] > 0) ? (($data['TYUNIT'] - $data['LYUNIT']) / $data['LYUNIT']) * 100 : 0;

                $rows[] = $data;
            }
        }

        $this->jsonOutput["gridValue"] = $rows;
    }

    private function makeFieldsToAccounts($srcArray){
        $tempArr = array();
        foreach($srcArray as $value){
            $tempArr[] = strtoupper($this->dbColumnsArray[$value]);
        }
        return $tempArr;
    }

    public function buildPageArray() {

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;
        
        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];

        $this->gridColumnName = $this->makeFieldsToAccounts($this->gridFields);

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