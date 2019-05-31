<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class MeasureByProductPage extends config\UlConfig {

    public $gridNameArray;
    public $pageName;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $lyHavingField;
    public $tyHavingField;
    public $dh;
    public $redisCache;

    public function __construct() {
        $this->lyHavingField = "VALUE";
        $this->tyHavingField = "VALUE";

        $this->gridNameArray = array();
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

        if ($this->settingVars->isDynamicPage) 
        {
            $this->account = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->scannedStoreField = $this->getPageConfiguration('scanned_store_field', $this->settingVars->pageID)[0];
            $this->scannedStoreAggregate = $this->getPageConfiguration('scanned_store_aggregate', $this->settingVars->pageID)[0];
            
            $fields[] = $this->account;
            if (!empty($this->scannedStoreField))
                $fields[] = $this->scannedStoreField;
            
            $this->buildDataArray($fields);
            $this->buildPageArray();
            
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') 
        {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchGrid') {
            $this->gridData();
        }
        
        return $this->jsonOutput;
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

    public function buildPageArray() {
        $fieldPart = explode("#", $this->account);
        $accountField = strtoupper($this->dbColumnsArray[$fieldPart[0]]);
        $accountField = (count($fieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$fieldPart[1]]) : $accountField;
        
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];    
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountCsvName = $this->settingVars->dataArray[$accountField]['NAME_CSV'];
        
        $this->countField = "";
        $this->countFieldAggregate = "";
        if (!empty($this->scannedStoreField)) {
            $scannedStoreField = strtoupper($this->dbColumnsArray[$this->scannedStoreField]);
            $this->countField = $this->settingVars->dataArray[$scannedStoreField]['NAME'];
            $this->countFieldAggregate = $this->scannedStoreAggregate;
        }
    }

    public function customSelectPartForDist() {
        $this->customSelectPart = "";

        if (!empty($this->countField)) {
            switch ($this->countFieldAggregate) {
                case "MAX":
                    $this->customSelectPart = ", MAX(CASE WHEN ". filters\timeFilter::$tyWeekRange ." THEN IFNULL(".$this->countField.",0) END) AS TYSTORES_SELLING";
                    break;
                case "COUNT_DISTINCT":
                    if (!empty($this->settingVars->ProjectVolume) && !empty($this->countField) && !empty(filters\timeFilter::$tyWeekRange)) {
                        $this->customSelectPart = ", COUNT( DISTINCT (CASE WHEN ". filters\timeFilter::$tyWeekRange ." AND " . $this->settingVars->ProjectVolume . " > 0 THEN " . $this->countField . " END)) AS TYSTORES_SELLING ";
                    }
                    break;
            }
        }
    }
    
    public function gridData() 
    {   
        $dateWithPeriod = (isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 1) ? false : true;
        filters\timeFilter::$lyWeekRange = "";
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $this->measureFields[] = $this->accountName;
        if (!empty($this->countField))
            $this->measureFields[] = $this->countField;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        $aggDateFromat        = '%e-%c';
        $aggDate              = 'Y-m-d';
        $aggDateArrayFormat   = 'j-n';
        $aggDateColHeadFormat = 'd/m/Y';
        
        $mydate = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $mydateFormated = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
        
        $this->customSelectPartForDist();
        
        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            $this->accountID." AS ID, ".
            $this->settingVars->dateField." as PERIOD, ".
            $mydate." as MYDATE, ".
            "DATE_FORMAT(".$mydateFormated.", '".$aggDateFromat."') as FORMATED_DATE, ".
            $measuresFldsAll.$this->customSelectPart.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange .")".
            " GROUP BY ACCOUNT, ID, PERIOD, FORMATED_DATE ORDER BY MYDATE ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
       
        $measureArray = array();
        foreach($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $data)
            $measureArray["TY".$data['jsonKey']] = $data['jsonKey'];

        if (!empty($this->countField)) {
            $measureArray["TYSTORES_SELLING"] = "STORES SELLING";
            $measureArray["TYVOLUME_PER_STORE"] = "VOLUME PER STORE";
        }
        
        if (is_array($result) && !empty($result)) 
        {
            foreach ($result as $seasonalData) 
                $seasonalDataArray[$seasonalData['ACCOUNT']."##".$seasonalData['ID']][$seasonalData['FORMATED_DATE']] = $seasonalData;
        }

        //$footerTotal = array();
        foreach ($seasonalDataArray as $key => $data)
        {
            foreach($measureArray as $measureKey => $measure)
            {
                $tmp = array();
                $tmp['MEASURE'] = $measure;
                $extra = explode("##", $key);
                $tmp['ACCOUNT'] = $extra[1] ." ". $extra[0];
                
                foreach($data as $innerKey => $periodData)
                {
                    $dtKey = 'dt'.str_replace('-','',$periodData['MYDATE']);
                    
                    $decimal = ($measureKey == "TYVALUE" || $measureKey == "TYVOLUME_PER_STORE") ? 2 : 0;
                    
                    if($measureKey == "TYVOLUME_PER_STORE" && !empty($this->countField))
                        $tmp[$dtKey] = number_format(($periodData["TYVOLUME"]/$periodData["TYSTORES_SELLING"])*1, $decimal, '.', ',');
                    else
                        $tmp[$dtKey] = number_format($periodData[$measureKey]*1, $decimal, '.', ',');
                    
                    $tmp[$dtKey] = (double)$tmp[$dtKey];
                    
                    $tmpDtFmt = date($aggDate, strtotime($periodData['MYDATE']));
                    if(!isset($arrayTmp[$tmpDtFmt]))
                    {
                        $colsHeader[] = array("FORMATED_DATE" => (($dateWithPeriod) ? "W ". $periodData['PERIOD'] . " - W/E ". date($aggDateColHeadFormat, strtotime($periodData['MYDATE'])) : "W/E ". date($aggDateColHeadFormat, strtotime($periodData['MYDATE']))), "MYDATE" => $tmpDtFmt);
                        $arrayTmp[$tmpDtFmt] = $tmpDtFmt;
                    }
                    //$footerTotal[$dtKey] += $periodData[$measureKey];
                }
                $finalData[] = $tmp;
            }
        }
        
        /* $footerTotal["MEASURE"] = "";
        $footerTotal["ACCOUNT"] = "Grand Total";
        foreach($footerTotal as $key => $data)
        {
            if(is_numeric($data))
                $footerTotal[$key] = number_format($data, 0, '.', ',');
        } */
        
        $this->jsonOutput['accountColumnName'] = $this->accountCsvName;
        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $measureList = array_unique(array_column($finalData, 'MEASURE'));
        $this->jsonOutput['measureList'] = $measureList;
        $this->jsonOutput['gridData'] = $finalData;
        //$this->jsonOutput['footerTotal'] = $footerTotal;
    }
}
?> 