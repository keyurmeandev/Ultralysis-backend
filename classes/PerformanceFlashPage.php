<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;
use filters;
use datahelper;
use lib;

class PerformanceFlashPage extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_PerformanceFlashPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) 
        {
            $this->tableField = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->defaultSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID)[0];

            $buildDataFields = array();
            if($this->defaultSelectedField)
                $buildDataFields[] = $this->defaultSelectedField;
            
            $this->buildDataArray($buildDataFields, true);
            
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                $this->getConfig();
            }
        } else {
            $this->configurationFailureMessage();
        }

        switch ($_REQUEST['action']) {
            case "gridData":
                $this->prepareData();
                break;
        }
		
        return $this->jsonOutput;
    }

    public function prepareData()
    {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];        
        $measureSelect = implode(",", $measureSelectionArr);
        
        $this->buildDataArray(array($_REQUEST['selectedField']), true,false);
        $getField = array_keys($this->displayCsvNameArray)[0];
        $this->accountName = strtoupper($this->dbColumnsArray[$getField]);
        $name = $this->settingVars->dataArray[$this->accountName]["NAME"];
        
        $this->measureFields[] = $name;
        $this->measureFields[] = $this->settingVars->pageArray["PerformanceFlashPage"]['fieldName'];
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll().$this->settingVars->pageArray["PerformanceFlashPage"]['Where'];
        
        $query = "SELECT $name AS ACCOUNT" . ", ". $measureSelect." ".
                "FROM " . $this->settingVars->tablename .' '. $this->queryPart." AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY ACCOUNT HAVING $havingTYValue <> 0 OR $havingLYValue <> 0 ORDER BY $havingTYValue DESC";
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultCached);
        } else {
            $result = $redisOutput;
        }
        
        $gridData = $allothers = array();
        if(is_array($result) && !empty($result))
        {
            $tyTotal = array_sum(array_column($result, $havingTYValue));
            
            foreach($result as $key => $data)
            {   
                if($key < 20)
                {
                    $tmp = array();
                    $tmp['ACCOUNT'] = $data['ACCOUNT'];
                    $tmp['ROWID'] = str_replace(" ", "-",$data['ACCOUNT']);
                    $tmp['MRKT_SHARE'] = ($data[$havingTYValue]/$tyTotal)*100;
                    $tmp['TYVALUE'] = (double)$data[$havingTYValue];
                    $tmp['LYVALUE'] = (double)$data[$havingLYValue];
                    $gridData[] = $tmp;
                }
                else
                {
                    $allothers['ACCOUNT'] = "All Others";
                    $allothers['ROWID'] = str_replace(" ", "-",$allothers['ACCOUNT']);
                    $allothers['MRKT_SHARE'] += ($data[$havingTYValue]/$tyTotal)*100;
                    $allothers['TYVALUE'] += (double)$data[$havingTYValue];
                    $allothers['LYVALUE'] += (double)$data[$havingLYValue];
                }
            }
            if(!empty($allothers))
                $gridData[] = $allothers;
        }
        $this->jsonOutput['gridData'] = $gridData;
    }
    
    public function getConfig()
    {
        $tables = array($this->getPageConfiguration('table_field', $this->settingVars->pageID)[0]);
        
        if (is_array($tables) && !empty($tables)) 
        {
            $fields = $tmpArr = array();
            $fields = $this->prepareFieldsFromFieldSelectionSettings($tables, false);

            /*foreach ($tables as $table) {
                if(is_array($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration[$table . "_settings"]) ){
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_settings"]);
                    foreach ($settings as $key => $field) {
                        $val = explode("#", $field);
                        
                        if($table == 'market')
                            $fields[] = (($table == 'market') ? $this->settingVars->storetable : $table).".".$val[0];
                        elseif($table == 'account')
                            $fields[] = (($table == 'account') ? $this->settingVars->accounttable : $table).".".$val[0];
                        elseif($table == 'product')
                            $fields[] = ((isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table).".".$val[0];
                        else
                            $fields[] = $table.".".$val[0];
                        
                        if ($key == 0) {
                            if($table == 'market')
                                $appendTable = ($table == 'market') ? $this->settingVars->storetable : $table;
                            elseif($table == 'account')
                                $appendTable = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            elseif($table == 'product')
                                $appendTable = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            else
                                $appendTable = $table;
                        }
                    }
                }
            }*/

            $this->buildDataArray($fields, false, true);
            foreach ($this->dbColumnsArray as $csvCol => $dbCol) {
                $tmpArr[] = array('label' => strtoupper($this->displayCsvNameArray[$csvCol])." VIEW", 'data' => $csvCol, 'dispLabel' => strtoupper($this->displayCsvNameArray[$csvCol]));
            }
            $this->jsonOutput['fieldSelection'] = $tmpArr;
        }
        
        if($this->defaultSelectedField != "")
            $this->jsonOutput['defaultSelectedField'] = $this->defaultSelectedField;
            
        $mydate = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        //$this->prepareTablesUsedForQuery($mydate);
        $this->queryPart = $this->getAll();
        $query = "SELECT ".$mydate." as MYDATE FROM " . $this->settingVars->tablename .' '. $this->queryPart;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultCached);
        } else {
            $result = $redisOutput;
        }

        $mydate = "";
        if (is_array($result) && !empty($result)) {
            $mydate = date("d/m/Y", strtotime($result[0]['MYDATE']));
        }
        
        $this->jsonOutput['latestMydate'] = $mydate;
    }
    
    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn = false ) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

}

?>