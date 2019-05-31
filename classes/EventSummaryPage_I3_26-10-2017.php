<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class EventSummaryPage extends config\UlConfig {

    public $gridNameArray;
    public $pageName;
    public $gridFields;
    public $countOfGrid;
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

        if ($this->settingVars->isDynamicPage) {
            $this->dh = $this->settingVars->pageArray[$this->settingVars->pageName]['DH'];
			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields)."_EVENT_SUMMARY" : $settingVars->pageName;

		} else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $productFilterData = $this->getAllFilterData();
            $filterDataConfig = array();
            
            $dataHelpers = explode("-", $this->dh);
            $defaultSelectedField = '';
            $rnkDyArr = [];
            if (!empty($dataHelpers)) {
                foreach ($dataHelpers as $key => $account) {
                    if($account != "") {
                        $combineAccounts = explode("#", $account);
                        foreach ($combineAccounts as $accountKey => $singleAccount) {
                            $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                            if ($tempId != "")
                                $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'];
                            else
                                $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                            
                            $filterDataConfig[$keyName]["data"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'] : $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                            
                            $filterDataConfig[$keyName]["label"] = $this->settingVars->dataArray[$singleAccount]['NAME_CSV'];
                            
                            $filterDataConfig[$keyName]["indexName"] = ($tempId != "") ? 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME'])."_".strtoupper($this->settingVars->dataArray[$singleAccount]['ID']).']' : 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME']).']';

                            $filterDataConfig[$keyName]["field"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME']."##".$this->settingVars->dataArray[$singleAccount]['ID'] : $this->settingVars->dataArray[$singleAccount]['NAME'];
                            
                            $filterDataConfig[$keyName]["TYPE"] = $this->settingVars->dataArray[$singleAccount]['TYPE'];
                            
                            $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]+1 : 1;

                            $filterDataConfig[$keyName]["RANK"] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] : 1;
                            
                            $filterDataConfig[$keyName]["dataList"] = array();
                            $filterDataConfig[$keyName]["selectedDataList"] = array();
                        }
                    }
                }
            }

            /*[START] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/
            $typeArray = array_column($filterDataConfig, 'TYPE');
            $rankArray = array_column($filterDataConfig, 'RANK');
            array_multisort($typeArray, SORT_ASC, SORT_STRING, $rankArray, SORT_ASC, SORT_NUMERIC, $filterDataConfig);

            if(!empty($filterDataConfig)) {
                $cntr = 1;
                foreach ($filterDataConfig as $ky => $vl) {
                $filterDataConfig[$ky]['RANK'] = $cntr;
                    $cntr++;
                }
            }
            /*[END] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/

            $this->jsonOutput["filterDataConfig"] = $filterDataConfig;
            $defaultSelectedField = end($filterDataConfig);
            $this->jsonOutput["defaultSelectedField"] = $defaultSelectedField['field'];
            $this->jsonOutput["defaultSelectedFieldName"] = $defaultSelectedField['label'];
            $this->jsonOutput["productAndMarketFilterData"] = $productFilterData;
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );
        }

        $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        $requestedSeason      = $requestedCombination[0];

        $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput, $requestedYear, $showAsOutput);

        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'fetchGrid')
        {
            $this->gridData();
            $this->jsonOutput['morrisonsItemStatus'] = $this->findMorrisonsItemStatus();
        }
        
        return $this->jsonOutput;
    }

    public function findMorrisonsItemStatus()
    {
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedSeason      = $requestedCombination[0];
        $requestedYear        = $requestedCombination[1];
        
        $status = false;
        
        $query = "SELECT ".$this->settingVars->maintable.".PNAME as PNAME FROM ".$this->settingVars->maintable." WHERE PNAME NOT IN (SELECT DISTINCT PNAME FROM ".$this->settingVars->seasonalmorrisonspricetable." WHERE GID = ".$this->settingVars->morrisonsGid." ) AND GID = ".$this->settingVars->morrisonsGid." AND accountID = ".$this->settingVars->aid." AND seasonal_description = '".$requestedSeason."' AND seasonal_year = ".$requestedYear." GROUP BY PNAME";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if(count($result) > 0)
            $status = true;
            
        if(!$status)
        {
            $query = "SELECT DISTINCT PNAME FROM ".$this->settingVars->seasonalmorrisonspricetable." WHERE GID = ".$this->settingVars->morrisonsGid." AND PRICE = 0.01";
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if(count($result) > 0)
                $status = true;
        }
        
        return $status;
    }    
    
    public function buildPageArray($gridFields) {
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

    public function gridData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
        if(empty($redisOutput))
            return ;

        $selectionFields = []; $selectionGroupBy = [];
        foreach ($redisOutput as $key => $value) {
            if(!empty($value)){
                $tbl = '';
                foreach ($value as $k => $val) {
                    if(!empty($val)){
                        $tbl = explode('.', $val);
                        if(!empty($tbl[0])){
                             $selectionFields[] = $val;
                        }
                        if(!empty($tbl[1])){
                            $asCnt = explode(' AS ', $tbl[1]);
                            if(!empty($asCnt[1]))
                                $selectionGroupBy[] = $asCnt[1];
                        }
                    }
                }
            }
        }

        /*$cumLyRemainingStartDate = $this->settingVars->fromToDateRange['maxDate'];
        $cumLyRemainingEndDate = (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) ? $_REQUEST["toDate"] : $cumLyRemainingStartDate;*/

        $cumLyRemainingEndDate = $this->settingVars->hardStopDates[count($this->settingVars->hardStopDates)-1];
        $maxDate = (strtotime($this->settingVars->fromToDateRange['maxDate']) < strtotime($cumLyRemainingEndDate)) ? $this->settingVars->fromToDateRange['maxDate'] : $cumLyRemainingEndDate;
        $cumLyRemainingStartDate = (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) ? $_REQUEST["toDate"] : $maxDate;

        // var_dump($cumLyRemainingStartDate);
        // var_dump($cumLyRemainingEndDate);
        // exit();

        $cumLyRemainingStartDatePart = explode("-", $cumLyRemainingStartDate);
        $cumLyRemainingStartDatePart[0] = $cumLyRemainingStartDatePart[0]-1;
        $cumLyRemainingStartDate = implode("-", $cumLyRemainingStartDatePart);

        $cumLyRemainingEndDatePart = explode("-", $cumLyRemainingEndDate);
        $cumLyRemainingEndDatePart[0] = $cumLyRemainingEndDatePart[0]-1;
        $cumLyRemainingEndDate = implode("-", $cumLyRemainingEndDatePart);

        $calculateRemaining = (strtotime($cumLyRemainingStartDate) < strtotime($cumLyRemainingEndDate)) ? true : false;

        if (!$calculateRemaining) {
            $tmp = $cumLyRemainingStartDate;
            $cumLyRemainingStartDate = $cumLyRemainingEndDate;
            $cumLyRemainingEndDate = $tmp;
            $calculateRemaining = true;
        }

        if ($calculateRemaining) 
            $measureSelectResLyRemaining = $this->prepareMeasureSelectPartForLyRemaining($cumLyRemainingStartDate, $cumLyRemainingEndDate);

        $allActiveFiltersNames = array_column($this->settingVars->dataArray,'NAME_CSV','NAME_ALIASE');
        $this->measureFields = array_merge(array_values($selectionFields),$measureSelectRes['measureSelectionArr']);
        // $this->measureFields[] = $this->settingVars->skutable.'.pname_rollup2';
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        //$requestedCombination = explode("-", $_REQUEST['FromSeason']);
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear = $requestedCombination[1];
        $requestedSeason = $requestedCombination[0];

        $thisYear = $ty = (int)$requestedYear;
        $lastYear = $ly = $requestedYear - 1;

        $selPart = ', '.$maintable.'.seasonal_year AS DATAYEAR ';
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        $query = "SELECT ".
            implode(',', $selectionFields).", ".$measuresFldsAll. " ".
            (($calculateRemaining) ? ", ".implode(',', $measureSelectResLyRemaining['measureSelectionArr'])." " : "").
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . 
            (($calculateRemaining) ? " OR " . $measureSelectResLyRemaining['tyRemainingWeekRange'] : "") . ")".
            " GROUP BY ".implode(',',$selectionGroupBy)." ";

        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        // print_r($result); exit();

        $activeXlsWsheet = [];
        if(!empty($selectionGroupBy)){
            foreach ($selectionGroupBy as $key => $sgb) {
                if(isset($allActiveFiltersNames[$sgb]))
                    $activeXlsWsheet[$sgb] = $allActiveFiltersNames[$sgb];
            }
        }

        if (is_array($result) && !empty($result)) {
            foreach ($result as $dataArray) {
                foreach ($activeXlsWsheet as $fieldAlias => $fieldCsvName) {
                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['ACCOUNT'] = $dataArray[$fieldAlias];
                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYVALUE'] += $dataArray[$havingTYValue];
                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE'] += $dataArray[$havingLYValue];
                    
                    if ($calculateRemaining)
                        $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYREMAINING'] += $dataArray[$measureSelectResLyRemaining['havingTYValue']];
                    else
                        $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYREMAINING'] = 0;
                    
                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['VARPER'] = ($finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE'] > 0) ? (($finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYVALUE'] - $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE']) / $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE'])*100 : 0;
                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['VARPER'] = number_format($finalArray[$fieldAlias][$dataArray[$fieldAlias]]['VARPER'], 1, '.', '');

                    if ($calculateRemaining)
                        $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['EXTRAPOLATED'] = ((($finalArray[$fieldAlias][$dataArray[$fieldAlias]]['VARPER']/100)+1) * $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYREMAINING'] ) + $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYVALUE'];
                    else
                        $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['EXTRAPOLATED'] = 0;

                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['FORECASTCLOSELYREMAINING'] += $dataArray[$havingTYValue] + (($calculateRemaining) ? $dataArray[$measureSelectResLyRemaining['havingTYValue']] : 0);

                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYCLOSE'] = $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE'] +  $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYREMAINING'];
                }
            }
            $finalDataArray = array();
            foreach ($finalArray as $key => $detailArray) {
                $finalDataArray[$key] = array_values($detailArray);
                
                $TYVALUE =  array_column($finalDataArray[$key], 'TYVALUE');
                $LYVALUE =  array_column($finalDataArray[$key], 'LYVALUE');
                
                array_multisort($TYVALUE, SORT_DESC, $LYVALUE, SORT_DESC, $finalDataArray[$key]);                
            }
        }

        $this->jsonOutput['gridData'] = $finalDataArray;
        $this->jsonOutput['filterMappings'] = $activeXlsWsheet;
    }

    public function prepareMeasureSelectPartForLyRemaining($startDate = '', $endDate = '')
    {
        $measureArr = $measureSelectionArr = array();
        
        foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
            $options = array();
            $measureKey = 'M' . $measureVal['measureID'];
            $measure = $this->settingVars->measureArray[$measureKey];
            
            $measureTYValue = "TY_REMAINING_" . $measure['ALIASE'];
            if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                $havingTYValue = $measureTYValue;

            $tyWeekRange  = " (". $this->settingVars->dateField ." > '". $startDate ."' AND ". $this->settingVars->dateField ." <= '". $endDate ."') ";
            $options['tyLyRange'][$measureTYValue] = trim($tyWeekRange);
            
            $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);           
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
        }

        $result = array("measureSelectionArr" => $measureSelectionArr, "tyRemainingWeekRange" => $tyWeekRange, "havingTYValue" => $havingTYValue);
        return $result;
    }

    public function getAllFilterData(){
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
        if(empty($redisOutput))
            return ;

        $selectionFields = []; $selectionTables = []; 
        foreach ($redisOutput as $key => $value) {
            if(!empty($value)){
                $tbl = '';
                foreach ($value as $k => $val) {
                    if(!empty($val)){
                        $tbl = explode('.', $val);
                        if(!empty($tbl[0])){
                             $selectionTables[] = $tbl[0];
                             $selectionFields[] = $val;
                        }
                    }
                }
            }
        }

        $this->measureFields = array_values($selectionFields);
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $query = "SELECT DISTINCT ".implode(',', $this->measureFields)." FROM ".$this->settingVars->tablename." ". $this->queryPart;

        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        return $result;
    }
}
?> 