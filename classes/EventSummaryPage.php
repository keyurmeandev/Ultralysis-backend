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
            
            $this->productAndMarketFilterData = $this->redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
            
            if(empty($this->productAndMarketFilterData))
                $this->productAndMarketFilterData = $this->prepareProductAndMarketFilterData();

            $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? "EVENT_SUMMARY" : $settingVars->pageName;
            $this->buildPageArray();
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

            //$this->jsonOutput["filterDataConfig"] = $filterDataConfig;
            $defaultSelectedField = end($filterDataConfig);
            $this->jsonOutput["defaultSelectedField"] = $defaultSelectedField['field'];
            $this->jsonOutput["defaultSelectedFieldName"] = $defaultSelectedField['label'];
            //$this->jsonOutput["productAndMarketFilterData"] = $productFilterData;
            /*$this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );*/
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
            if(isset($this->settingVars->morrisonsGid) && !empty($this->settingVars->morrisonsGid))
                $this->jsonOutput['morrisonsItemStatus'] = $this->findMorrisonsItemStatus();
        } else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchInlineMarketAndProductFilter')) {
            $this->settingVars->pageName = '';
            $this->fetchInlineMarketAndProductFilterData();
        }
        
        if (isset($this->jsonOutput['allSeasonalHardStopDatesHashKey']) && !empty($this->jsonOutput['allSeasonalHardStopDatesHashKey'])) {
            unset($this->jsonOutput['allSeasonalHardStopDatesHashKey'], $this->jsonOutput['allSeasonalHardStopDatesHashKeyLy']);
        }
        return $this->jsonOutput;
    }

    public function fetchInlineMarketAndProductFilterData()
    {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true); //collecting time selection data
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function findMorrisonsItemStatus()
    {
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedSeason      = $requestedCombination[0];
        $requestedYear        = $requestedCombination[1];
        
        $status = false;
        
        $query = "SELECT ".$this->settingVars->pnameField." as PNAME FROM ".$this->settingVars->maintable." WHERE ".$this->settingVars->pnameField." NOT IN (SELECT DISTINCT ".$this->settingVars->pnameMorrisonsPriceTableField." FROM ".$this->settingVars->seasonalmorrisonspricetable." WHERE GID = ".$this->settingVars->morrisonsGid." ) AND GID = ".$this->settingVars->morrisonsGid." AND accountID = ".$this->settingVars->aid." AND seasonal_description = '".$requestedSeason."' AND seasonal_year = ".$requestedYear." GROUP BY PNAME";
        
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
            // $query = "SELECT DISTINCT PNAME FROM ".$this->settingVars->seasonalmorrisonspricetable." WHERE GID = ".$this->settingVars->morrisonsGid." AND PRICE = 0.01";
            $query = "SELECT DISTINCT ".$this->settingVars->pnameMorrisonsPriceTableField." FROM ".$this->settingVars->seasonalmorrisonspricetable.
                ", ".$this->settingVars->maintable." WHERE ".
                $this->settingVars->maintable.".PNAME=".$this->settingVars->seasonalmorrisonspricetable.".PNAME AND ".$this->settingVars->seasonalmorrisonspricetable.".GID = ".$this->settingVars->morrisonsGid." AND ".$this->settingVars->seasonalmorrisonspricetable.".PRICE = 0.01 AND ".$this->settingVars->maintable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->maintable.".seasonal_description = '".$requestedSeason."' AND ".$this->settingVars->maintable.".seasonal_year = ".$requestedYear;

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
    
    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $tabsSettings = $this->getPageConfiguration('tabs_settings', $this->settingVars->pageID);
            $firstTabMeasure = (is_array($tabsSettings) && !empty($tabsSettings)) ? $this->settingVars->performanceTabMappings[$tabsSettings[0]] : "";

            if (is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
                foreach ($this->settingVars->measureArray as $mKey => $measure) {
                    $measureAliase[$mKey] = array(
                        "ALIASE" => $measure['ALIASE'], 
                        "dataDecimalPlaces" => (isset($measure['dataDecimalPlaces']) && !empty($measure['dataDecimalPlaces'])) ? $measure['dataDecimalPlaces'] : 0, 
                        'NAME' => (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE']
                    );
                }
            }
            
            $this->jsonOutput['gridConfig'] = array(
                'measuresAliases' => $measureAliase,
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

    public function gridData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        

        $selectionFields = []; $selectionGroupBy = [];
        foreach ($this->productAndMarketFilterData as $key => $value) {
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

        $finalDataArray = array();
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

                    $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['VAR'] = $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['TYVALUE'] - $finalArray[$fieldAlias][$dataArray[$fieldAlias]]['LYVALUE'];
                    
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


            if (is_array($finalArray) && !empty($finalArray)) {
                $finalGridArray = array();
                foreach ($finalArray as $key => $detailArray) {
                    foreach ($detailArray as $datakey => $detail) {
                        if ($detail['TYVALUE'] == 0 && $detail['LYVALUE'] == 0 && $detail['LYCLOSE'] == 0)
                            continue;

                        $finalGridArray[$key][$datakey] = $detail;
                    }
                }
            }

            foreach ($finalGridArray as $key => $detailArray) {
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

            $tyWeekRange  = " ((". $this->settingVars->dateField ." > '". $startDate ."' AND ". $this->settingVars->dateField ." <= '". $endDate ."') AND ". $this->settingVars->weekField ."='".filters\timeFilter::$FromSeason."' ) ";
            $options['tyLyRange'][$measureTYValue] = trim($tyWeekRange);
            
            $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);           
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
        }

        $result = array("measureSelectionArr" => $measureSelectionArr, "tyRemainingWeekRange" => $tyWeekRange, "havingTYValue" => $havingTYValue);
        return $result;
    }

    public function getAllFilterData(){
        $selectionFields = []; $selectionTables = []; 
        foreach ($this->productAndMarketFilterData as $key => $value) {
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

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        return $result;
    }
}
?>