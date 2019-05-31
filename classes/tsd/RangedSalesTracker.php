<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class RangedSalesTracker extends config\UlConfig {
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
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];

            $tempBuildFieldsArray = array($this->accountField);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "getGridData":
                $this->getGridData();
                break;
        }
        return $this->jsonOutput;
    }
    
    function getGridData() {
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->settingVars->clusterID;
        
        $selectPart = array();
        $groupByPart = array();
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        //$last7Days = filters\timeFilter::getLastNDaysDate($this->settingVars);
        $last7Days = filters\timeFilter::getLastN14DaysDate($this->settingVars);
        $last7Days = array_slice($last7Days, 0, $_REQUEST["requestDays"]);

        $this->queryPart = $this->getAll();
        $query = "SELECT " .
                $this->accountID . " AS accountID " .
                ", MAX(".$this->accountName . ") AS accountName " .
                ", SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN (".implode(",",$last7Days).") THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES " .
                ",COUNT( DISTINCT( (CASE WHEN ".$this->settingVars->rangedtable.".TSI = 1 AND ".$this->settingVars->rangedtable.".VSI = 1 AND ".$this->settingVars->ProjectValue." > 0 THEN ".$this->settingVars->rangedtable.".SNO END)) ) AS RANGED_WITH_SALES " .
                ",COUNT( DISTINCT( (CASE WHEN ".$this->settingVars->rangedtable.".TSI = 0 AND ".$this->settingVars->ProjectValue." > 0 THEN ".$this->settingVars->rangedtable.".SNO END)) ) AS NOT_RANGED_WITH_SALES " .
                " FROM " . $this->settingVars->tablename . ", ". $this->settingVars->rangedtable . $this->queryPart .
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ".
                " AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->maintable.".gid ".
                " AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
                " AND ".$this->settingVars->rangedtable.".skuID = ".$this->settingVars->maintable.".skuID ".
                " AND ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->maintable.".SNO ".
                " AND ".$this->settingVars->DatePeriod." IN (".implode(",",$last7Days)." ) ".
                " GROUP BY accountID ";
        //echo $query.'<BR>';exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $this->getQueryPart($this->accountID);
        
        $query = "SELECT ".$this->accountID." AS accountID " .
                ",COUNT( DISTINCT( (CASE WHEN ".$this->settingVars->rangedtable.".TSI = 1 THEN ".$this->settingVars->rangedtable.".SNO END)) ) AS RANGED_STORES " .
                " FROM ". $this->rangedStoreTableName . $this->rangedStoreQpart ." GROUP BY accountID ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $rangedStoreResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($rangedStoreResult);
        } else {
            $rangedStoreResult = $redisOutput;
        }

        $rangedStore = array();
        if(is_array($rangedStoreResult) && !empty($rangedStoreResult)) {
            foreach($rangedStoreResult as $data)
                $rangedStore[$data['accountID']] = $data['RANGED_STORES'];
        }
                
        $output = array();
        if(is_array($result) && !empty($result))
        {
            $tmp = array();
            foreach($result as $data)
            {
                $tmp['accountID']               = $data['accountID'];
                $tmp['accountName']             = $data['accountName'];
                $tmp['L7_SALES']                = $data['SALES'];
                $tmp['RANGED_STORES']           = $rangedStore[$data['accountID']];
                $tmp['RANGED_WITH_SALES']       = $data['RANGED_WITH_SALES'];
                $tmp['RANGED_WITH_NO_SALES']    = $rangedStore[$data['accountID']] - $data['RANGED_WITH_SALES'];
                $tmp['NOT_RANGED_WITH_SALES']   = $data['NOT_RANGED_WITH_SALES'];
                $tmp['WKLY_ROS']                = $data['SALES']/($data['RANGED_WITH_SALES']+$data['NOT_RANGED_WITH_SALES']);
                $tmp['WKLY_LOST']               = $tmp['RANGED_WITH_NO_SALES']*$tmp['WKLY_ROS'];
                $output[] = $tmp;
            }
        }
        $this->jsonOutput["getGridData"] = $output;
    }

    private function getQueryPart($accountField)
    {
        $queryPart = "";
        $tableName = explode(".",$accountField);
        
        if($tableName[0] == "product")
        {
            $this->rangedStoreTableName = $this->settingVars->rangedtable.", ".$this->settingVars->skutable." ";
            $this->rangedStoreQpart = " WHERE ".$this->settingVars->rangedtable.".skuID = ".$this->settingVars->skutable.".PIN ".
            "AND ".$this->settingVars->rangedtable.".clientID = '".$this->settingVars->clientID."' ".
            "AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->skutable.".gid ".
            "AND ".$this->settingVars->skutable.".gid = ".$this->settingVars->GID." ".
            "AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ";
        }
        elseif($tableName[0] == "store")
        {
            $this->rangedStoreTableName = $this->settingVars->rangedtable.", ".$this->settingVars->storetable." ";
            $this->rangedStoreQpart = " WHERE ".$this->settingVars->rangedtable.".SNO = ".$this->settingVars->storetable.".SNO ".
            "AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->storetable.".gid ".
            "AND ".$this->settingVars->storetable.".gid = ".$this->settingVars->GID." ".
            "AND ".$this->settingVars->rangedtable.".gid = ".$this->settingVars->GID." ";
        }
    }
    
    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
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
        $skuFieldPart = explode("#", $this->accountField);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['gridColumns']['accountID'] =  (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['gridColumns']['accountName'] =  $this->displayCsvNameArray[$skuFieldPart[0]];
        }

        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->accountID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$skuField]['NAME'];

        return;
    }
}
?>