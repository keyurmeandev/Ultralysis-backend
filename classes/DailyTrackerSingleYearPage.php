<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class DailyTrackerSingleYearPage extends config\UlConfig {

    public $pageName;
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

        if ($this->settingVars->isDynamicPage) 
        {
            $account = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
            $fieldPart = explode("#", $account[0]);
            $fields[] = $this->accountField = $fieldPart[0];
            $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
            
            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }
            
            $this->buildDataArray($fields);
            $this->buildPageArray();
            
            $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
            $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
            
            $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];    
            $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
            
            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["BAR_ACCOUNT_TITLE" => $this->displayCsvNameArray[$vl], "ACCOUNT" => strtoupper($this->dbColumnsArray[$vl])];
                }
            }
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') 
        {
            $ExtraCols = [];
            if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0)
            {
                foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) 
                {
                    $this->jsonOutput["extraCols"][] = array("fieldName" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], "title" => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']);
                }
            }
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchGrid') {
            $this->gridData();
        }
        return $this->jsonOutput;
    }

    public function buildPageArray() 
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {

            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );

            /*[START] CODE FOR INLINE FILTER STYLE*/
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            /*[END] CODE FOR INLINE FILTER STYLE*/
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

    public function gridData() 
    {
        filters\timeFilter::$lyWeekRange = "";
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
        $this->measureFields[] = $this->settingVars->skutable.'.pname';
        
        $this->measureFields[] = $this->accountName;
        
        $ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {

                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']];

                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }
        
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
        
        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            "MAX(".$this->settingVars->maintable.".mydate) as MYDATE, ".
            "DATE_FORMAT(".$this->settingVars->maintable.".mydate, '".$aggDateFromat."') as FORMATED_DATE, ".
            $measuresFldsAll.
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") .
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . ")".
            " GROUP BY ACCOUNT, FORMATED_DATE ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "")." ORDER BY MYDATE ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $dates = array_values(array_unique(array_column($result, "MYDATE")));

        foreach ($dates as $tyDate) 
        {
            $tmpDtFmt = date($aggDate, strtotime($tyDate));
            if(!isset($arrayTmp['TY'][$tmpDtFmt])){
                $dateArray[$tmpDtFmt] = date($aggDateArrayFormat, strtotime($tyDate));
                $colsHeader[] = array("FORMATED_DATE" => ((!$tyDateHasError) ? date($aggDateColHeadFormat, strtotime($tyDate)) : "NULL"), "MYDATE" => $tmpDtFmt);
                $arrayTmp['TY'][$tmpDtFmt] = $tmpDtFmt;
            }            
        }

        $dataPnameSumIndexed = [];
        if (is_array($result) && !empty($result)) 
        {
            foreach ($result as $seasonalData) 
            {
                $extraColsVal = array();
                foreach($ExtraCols as $extraCols)
                    $extraColsVal[] = $seasonalData[$extraCols['NAME_ALIASE']];
                    
                $extraColsVal = implode("##",$extraColsVal);
                
                $seasonalDataArray[$seasonalData['ACCOUNT']][$extraColsVal][$seasonalData['FORMATED_DATE']] = $seasonalData;
            }
        }
        
        $footerTotal = array();
        foreach ($seasonalDataArray as $key => $data)
        {
            foreach($data as $depotKey => $depotData)
            {
                $tmp = array();
                $account = $tmp['ACCOUNT'] = $key;
                
                $extra = explode("##", $depotKey);
                foreach($ExtraCols as $k => $extraCols)
                    $tmp[$extraCols['NAME_ALIASE']] = $extra[$k];
                
                foreach ($dateArray as $dayMydate => $dayMonth) 
                {
                    $tyMydate = $dayMydate;
                    if (isset($seasonalDataArray[$account][$depotKey][$dayMonth])) 
                    {
                        $value = $seasonalDataArray[$account][$depotKey][$dayMonth];
                        $dtKey = 'dt'.str_replace('-','',$tyMydate);
                        $tmp[$dtKey] = number_format($value[$havingTYValue]*1, 0, '.', ',');
                        $footerTotal[$dtKey] += $value[$havingTYValue]*1;
                    }
                }
                $finalData[] = $tmp;
            }
        }
        
        $footerTotal["ACCOUNT"] = "";
        foreach($ExtraCols as $k => $extraCols)
        {
            if($k == 0)
                $footerTotal[$extraCols['NAME_ALIASE']] = "Grand Total";
            else
                $footerTotal[$extraCols['NAME_ALIASE']] = "";
        }
            
        foreach($footerTotal as $key => $data)
        {
            if(is_numeric($data))
                $footerTotal[$key] = number_format($data, 0, '.', ',');
        }
        
        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->jsonOutput['gridData'] = $finalData;
        $this->jsonOutput['footerTotal'] = $footerTotal;
    }
}
?> 