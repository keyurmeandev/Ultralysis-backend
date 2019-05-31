<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class Calendar extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/

    public $displayCsvNameArray;
    public $dbColumnsArray;

    public function go($settingVars){
       $this->initiate($settingVars); //INITIATE COMMON VARIABLES

       $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_CalendarPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->tableGridField = $this->getPageConfiguration('table_grid_field', $this->settingVars->pageID);
            $this->buildDataArray($this->tableGridField);
            $this->buildPageArray();
        }

        if (!isset($_REQUEST["fetchConfig"]))
            $this->prepareCalendarData();
    
	   return $this->jsonOutput;
    }

    public function prepareCalendarData(){
        $selectPart         = array();
        $groupByPart        = array();
        
        foreach($this->settingVars->calendarItems as $key=>$item)
        {
            $selectPart[]   = $item['DATA']." AS ".$item['ALIASE'];
            $groupByPart[]  = $item['ALIASE'];
        }
        
        $selectPart         = implode(",",$selectPart);
        $groupByPart        = implode(",",$groupByPart);
        
        $query              = "SELECT $selectPart ".                    
                                    "FROM ". $this->settingVars->tablename_for_calendar." ".
                                    "WHERE ".$this->settingVars->where_clause_for_calendar." ".
                                    "GROUP BY $groupByPart ".
                                    "ORDER BY 1 DESC";
                                    //print $query;exit;
        $result             = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $tempResult = array();
        foreach($result as $key=>$data)
        {
            $tempData = array();
            foreach($this->settingVars->calendarItems as $jey=>$item)
            {
                $tempData[$item['ALIASE']] = $data[$item['ALIASE']];
            }
            $tempResult[] = $tempData;
        }
        $this->jsonOutput['Calender'] = $tempResult;
    } 

    public function buildPageArray() {

        $this->settingVars->calendarItems = array();
        foreach ($this->tableGridField as $key => $field) {
            $fieldName = strtoupper($this->dbColumnsArray[$field]);
            
            $this->settingVars->calendarItems[] = array(
                    'DATA' => $this->settingVars->dataArray[$fieldName]['NAME'],
                    'ALIASE' => $this->settingVars->dataArray[$fieldName]['NAME_ALIASE'],
                );
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
                $this->jsonOutput['ColumnList'][$this->settingVars->dataArray[$fieldName]['NAME_ALIASE']] = $this->displayCsvNameArray[$field];
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
}
?>