<?php

namespace classes\tsd;

use db;
use config;
use filters;

class RangedInstockMonitor extends config\UlConfig {
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
		
        $action = $_REQUEST["action"];

		if ($this->settingVars->isDynamicPage) {
			$this->extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID);
            
			$tempBuildFieldsArray = array();
			$tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->extraColumns, $this->skuField);
			
            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
			
			$this->pinIdField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID'];
			$this->pinNameField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME'];
		}        
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->setGridColumns();
        }        
        
        switch ($action) {
            case "topGrid":
                $this->topGrid();
                break;
            case "bottomGrid":
                $this->bottomGrid();
                break;
        }

        return $this->jsonOutput;
    }

    function bottomGrid(){

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->settingVars->clusterID;
        
        $selectPart = array();
        $groupByPart = array();
        
        foreach ($this->extraColumns as $key => $data) {
            $selectPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
            $groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
            $this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
        }

        $requestedPin = $_REQUEST['PIN'];
        $requestedCluster = $_REQUEST['cluster'];
        $requestedDynamicParams = $_REQUEST['dynamicParams'];
        $requestedPname = $_REQUEST['pname'];
        $DynamicParamsStr = "";
        foreach($requestedDynamicParams as $key => $data)
            $DynamicParamsStr .= "," . $key . "=" . "'". $data . "' ";
        
        $DynamicParamsStr = trim($DynamicParamsStr, ",");        
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();        
        
        $query = "SELECT " .
                $this->pinIdField . " AS TPNB " .
                ", ".$this->pinNameField . " AS SKU " .
                ", " . $this->settingVars->clusterID . " AS CLUSTER " .
                ", ".$this->settingVars->rangedtable.".SNO AS RANGED_SNO " .
                ", " . implode(",", $selectPart) . 
                " FROM " . $this->settingVars->tablename . ", ". $this->settingVars->rangedtable . $this->queryPart .
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
                " AND ".$this->settingVars->rangedtable.".skuID = ".$this->settingVars->maintable.".skuID ".
                " AND ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->maintable.".SNO ".
                " AND ".$this->settingVars->rangedtable.".skuID = $requestedPin ".
                " AND ".$this->settingVars->clusterID." = '$requestedCluster' AND $DynamicParamsStr ".
                " AND ".$this->settingVars->rangedtable.".TSI = 1 AND stock > 0 ".
                " AND ".$this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0]."' ".
                " GROUP BY TPNB, SKU, CLUSTER, RANGED_SNO, ".implode(",", $groupByPart)." "; // ORDER BY SALES DESC
        //echo $query.'<BR>';exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $INSTOCK_SNO = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
                $INSTOCK_SNO[] = $data['RANGED_SNO'];
        }
       
        $query = "SELECT ".
                $this->settingVars->rangedtable.".SNO as SNO, skuID as TPNB , '$requestedPname' AS PNAME,".
                " ".$this->settingVars->clusterID . " AS CLUSTER " . 
                ", ".$this->settingVars->storetable . ".SNAME AS SNAME " . 
                ((count($selectPart) > 0 ) ? (", " . implode(",", $selectPart)) : " " ). 
                " FROM " . $this->settingVars->rangedtable .",". $this->settingVars->storetable ." WHERE " .
                " ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->storetable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->storetable.".SNO ".
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->storetable.".gid ".
                " AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
                " AND TSI=1 AND skuID = $requestedPin AND ".$this->settingVars->clusterID." = '$requestedCluster' AND $DynamicParamsStr ".
                " AND ".$this->settingVars->rangedtable.".SNO NOT IN (".implode(",", $INSTOCK_SNO).") ".
                "GROUP BY TPNB, SNO, CLUSTER, SNAME ". ((count($groupByPart) > 0 ) ? " , ".implode(",", $groupByPart) : " " )." ";
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $bottomGridData = array();
        if(is_array($result) && !empty($result))
            $bottomGridData = $result;
        
        $this->jsonOutput['bottomGrid'] = $bottomGridData;
    }
    
    function topGrid() 
    {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->settingVars->clusterID;
		
        $selectPart = array();
        $groupByPart = array();
        
        foreach ($this->extraColumns as $key => $data) {
            $selectPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
            $groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
            $this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
        }

        $query = "SELECT count(*) as COUNT_RANGED , skuID as skuID ,".
                $this->settingVars->clusterID . " AS CLUSTER " . ((count($selectPart) > 0 ) ? (", " . implode(",", $selectPart)) : " " ). 
                " FROM " . $this->settingVars->rangedtable .",". $this->settingVars->storetable ." WHERE " .
                " ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->storetable.".SNO ".
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->storetable.".gid ".
                " AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
                " AND TSI=1 ".
                "GROUP BY skuID, CLUSTER". ((count($groupByPart) > 0 ) ? " , ".implode(",", $groupByPart) : " " )." ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $rangedItems = array();
        if(is_array($result) && !empty($result)){
            foreach($result as $key => $data){
                $index = $data['skuID'].$data['CLUSTER'];
                if(is_array($groupByPart) && !empty($groupByPart)){
                    foreach($groupByPart as $group){
                      $index .= $data[$group];     
                    }
                }
                $rangedItems[$index] = $data['COUNT_RANGED'];
            }
        }


        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();        
        
        $query = "SELECT " .
                $this->pinIdField . " AS TPNB " .
                ", ".$this->pinNameField . " AS SKU " .
                ", " . $this->settingVars->clusterID . " AS CLUSTER " .
                ((count($selectPart) > 0 ) ? (", " . implode(",", $selectPart)) : " " ) . 
                ", SUM((CASE WHEN ".$this->settingVars->ProjectValue." > 0 THEN 1 END)*value) AS SALES " .
                //", COUNT(CASE WHEN ".$this->settingVars->rangedtable.".tsi = 1 THEN 1 END) AS COUNT_RANGED " .
                ", COUNT( DISTINCT (CASE WHEN " . $this->settingVars->rangedtable.".tsi = 1 AND stock > 0  
                    AND ".$this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 END) * ".$this->settingVars->maintable.".SNO) AS INSTOCK " .
                //", COUNT( DISTINCT (CASE WHEN " . $this->settingVars->rangedtable.".tsi = 1 AND stock > 0 THEN ".$this->settingVars->maintable.".SNO END)) AS INSTOCK_PER " .
                /*", SUM( ( CASE WHEN " . $this->settingVars->rangedtable.".tsi = 1 ".
                    " AND ".$this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END) * stock ) AS INSTOCK " .*/
                ", COUNT(DISTINCT ".$this->settingVars->rangedtable.".SNO) AS RANGED_SNO " .
                ", COUNT(DISTINCT ".$this->settingVars->maintable.".SNO) AS MAIN_SNO " .
                "FROM " . $this->settingVars->tablename . ", ". $this->settingVars->rangedtable . $this->queryPart .
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
                " AND ".$this->settingVars->rangedtable.".skuID = ".$this->settingVars->maintable.".skuID ".
                " AND ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->maintable.".SNO ".
                "GROUP BY TPNB, SKU, CLUSTER ".((count($groupByPart) > 0 ) ? " , ".implode(",", $groupByPart) : " " )." ORDER BY SALES DESC";
        // echo $query.'<BR>';exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        foreach($result as $key => $data){
            $index = $data['TPNB'].$data['CLUSTER'];
            if(is_array($groupByPart) && !empty($groupByPart)){
                foreach($groupByPart as $group){
                  $index .= $data[$group];     
                }
            }
            $ranged = $rangedItems[$index];
            //$result[$key]['INSTOCK_CAP'] = $data['MAIN_SNO'] - $data['RANGED_SNO'];
            $result[$key]['INSTOCK_CAP'] = $ranged - $data['INSTOCK'];
            $result[$key]['INSTOCK_PER'] = ($ranged > 0 ) ? number_format((($data['INSTOCK']/$ranged)*100),1) : 0;
            $result[$key]['COUNT_RANGED'] = $ranged;
        }
        
        $this->jsonOutput['topGrid'] = $result;
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
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
       
        $this->extraColumns = $this->makeFieldsToAccounts($this->extraColumns);
		$this->skuField = $this->makeFieldsToAccounts($this->skuField);
        return;
    }
	
    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = $this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]];
            else
                $tempArr[] = $this->dbColumnsArray[$value[0]];
        }
        return $tempArr;
    }	
   
    private function setGridColumns() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $tempCol = array();
            foreach ($this->extraColumns as $key => $value) {
                $tempCol[$this->settingVars->dataArray[strtoupper($this->extraColumns[$key])]['NAME_ALIASE']] = array($this->settingVars->dataArray[strtoupper($this->extraColumns[$key])]['NAME_CSV'], $this->settingVars->dataArray[strtoupper($this->extraColumns[$key])]['NAME']);
            }
            $this->jsonOutput["TOP_GRID_COLUMN_NAMES"] = $tempCol;
        }
    }
    
}

?>