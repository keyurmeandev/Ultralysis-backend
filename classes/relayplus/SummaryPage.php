<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class SummaryPage extends \classes\SummaryPage {

    private $getField;      //get Field to set in pod and grid
    public $bottomGridFieldStatus = 1;

    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->redisCache = new utils\RedisCache($this->queryVars);

        $this->fetchConfig(); // Fetching filter configuration for page

        $bottomGridFieldStatusFg = $this->getPageConfiguration('bottom_grid_status', $this->settingVars->pageID);
        if(isset($bottomGridFieldStatusFg[0]) && $bottomGridFieldStatusFg[0] != '') {
            $this->bottomGridFieldStatus = $bottomGridFieldStatusFg[0];
        }
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            // $this->getSkuSelectionList();
            
            $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
            if(in_array("sku-selection", $this->jsonOutput['gridConfig']['enabledFilters']) && !$configurationCheck->settings['has_sku_filter'])
            {
                $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
                echo json_encode($response);
                exit();
            }
            
            $this->jsonOutput['gridConfig']['bottomGridFieldStatus'] = $this->bottomGridFieldStatus;
            $performancePodDefaultSelection = $this->getPageConfiguration('performance_pod_default_selection', $this->settingVars->pageID);
            if(!empty($performancePodDefaultSelection) && isset($performancePodDefaultSelection[0])) {
                $this->jsonOutput['performancePodDefaultSelection'] = $performancePodDefaultSelection[0];
            }
            // echo "<pre>";print_r($this->settingVars);exit();
        }
        
        $this->configurePage();
        $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING PARENT getAll
		
        $action = $_REQUEST['action'];
				
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'totalSales':
                $this->totalSales();
                break;
            case 'performanceInABox':
                $this->performanceInABox();
                break;
            case 'sharePerformance':
                $this->categoryPerformanceGrid();
                break;
            case 'performanceSkuGrid':
				$this->productPerformanceSkuGrid($this->settingVars->ProjectValue);
                break;
        }

        return $this->jsonOutput;
    }

    public function configurePage()
    {
        if ($this->settingVars->isDynamicPage) {
            $this->performanceBoxSettings = $this->getPageConfiguration('performance_box_settings', $this->settingVars->pageID);
            if(is_array($this->settingVars->pageArray['PerformanceBoxSettings']) && !empty($this->settingVars->pageArray['PerformanceBoxSettings'])){
                foreach ($this->settingVars->pageArray['PerformanceBoxSettings'] as $key => $value) {
                    if(!in_array($key, $this->performanceBoxSettings))
                        unset($this->settingVars->pageArray['PerformanceBoxSettings'][$key]);
                }
            }
        }
        
        if(isset($_REQUEST['pageAnalysisField']))
            $this->fieldsTable = $_REQUEST['pageAnalysisField'];
        
        if(isset($_REQUEST['Field'])) {
            $getField = $_REQUEST['Field'];
            $this->buildDataArray(array($getField), true);
			$getField = array_keys($this->displayCsvNameArray)[0];
        } else {
            $this->buildPageArray();
            $getField = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]; // DEFINED ACCOUNT NAME FROM PAGE SETTING
            $this->jsonOutput['selectedField'] = $getField;
        }
        $this->accountName = strtoupper($this->dbColumnsArray[$getField]);
    }

    /* public function getSkuSelectionList()
    {
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);

        $skuList = $configurationCheck->settings['sku_settings'];
        $skuDefaultLoad = $configurationCheck->settings['sku_default_load'];
        
        if($configurationCheck->settings['has_sku_filter'])
        {
            if($skuList != "" && $skuDefaultLoad != "")
            {
                $settings = explode("|", $skuList);
                foreach($settings as $key => $field)
                    $fields[] = $field;
                
                $this->buildDataArray($fields, false);
                $tmpArr = array();
                
                foreach($settings as $key => $field)
                {
                    $fieldPart = explode("#", $field);
                    $fieldName = (count(explode(".", $fieldPart[0])) > 1 ) ? strtoupper($fieldPart[0]) : strtoupper("product.".$fieldPart[0]);
                    $fieldName2 = (count($fieldPart) > 1 && count(explode(".", $fieldPart[1])) > 1) ? strtoupper($fieldPart[1]) : ((count($fieldPart) > 1) ? strtoupper("product.".$fieldPart[1]) : "" );
                    $fieldName = (!empty($fieldName2)) ? $fieldName . "_" . $fieldName2 : $fieldName;
                
                    if($fieldPart[0] == $skuDefaultLoad)
                        $selected = true;
                    else
                        $selected = false;
                    
                    $data = "product.".$this->settingVars->dataArray[$fieldName]['NAME_CSV'];
                    $data = (array_key_exists("ID",$this->settingVars->dataArray[$fieldName])) ? $data."#product.".$this->settingVars->dataArray[$fieldName]['ID_CSV'] : $data;
                    
                    $tmpArr[] = array('label' => $this->settingVars->dataArray[$fieldName]['NAME_CSV'], 'data' => $data, 'selected' => $selected);
                }
            }
        }
        else if(in_array("sku-selection", $this->jsonOutput['gridConfig']['enabledFilters']) && !$configurationCheck->settings['has_sku_filter'])
        {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
        else
        {
            if($this->bottomGridFieldStatus == 1){
                $bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
                $this->buildDataArray(array($bottomGridField),true);
                $bottomGridFieldPart = explode("#", $bottomGridField);
                $tmpArr[] = array('label' => $this->displayCsvNameArray[$bottomGridFieldPart[0]], 'data' => $bottomGridField, 'selected' => true);
            }
        }
        $this->jsonOutput['skuSelectionList'] = $tmpArr;
    } */
	
    /**
     * performanceInABox()
     * It fetch data to show in performance box
     * 
     * @return void
     */
    private function performanceInABox() {

        $arr = $options = $maxTimeArr = $qPartArr = $measureSelect = array();

        $maximumTime = $qPart = "";
        $maxTimeArr = array_column($this->settingVars->pageArray["PerformanceBoxSettings"],'timeFrame');

        filters\timeFilter::getYTD($this->settingVars);
        filters\timeFilter::calculateTotalWeek($this->settingVars);
        $ytdWeekCnt = filters\timeFilter::$totalWeek;

        $maximumTime = max($maxTimeArr);
        $maximumTime = ($maximumTime > $ytdWeekCnt) ? $maximumTime : $ytdWeekCnt;

        if(is_array($this->settingVars->pageArray["PerformanceBoxSettings"]) && !empty($this->settingVars->pageArray["PerformanceBoxSettings"])){
            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                $options = array();
                $measureKey = 'M' . $measureVal['measureID'];
                $measure = $this->settingVars->measureArray[$measureKey];

                if (isset($this->settingVars->fetchAllMeasureForSummaryPerformanceInBox) && $this->settingVars->fetchAllMeasureForSummaryPerformanceInBox == false && $_REQUEST['ValueVolume'] != $measureVal['measureID'])
                    continue;

                // "((period_list.accountyear=2018 AND period_list.accountweek>=1) AND (period_list.accountyear=2018 AND period_list.accountweek<=25))"
                foreach ($this->settingVars->pageArray["PerformanceBoxSettings"] as $performanceTimeSetting ) {
                    $items = array();
                    if($performanceTimeSetting['timeFrame'] === 0 ){
                        $latestWeeks    = filters\timeFilter::getLatest_n_dates(0, 2, $this->settingVars, false, "-");

                        $latestWeek     = array_slice($latestWeeks, 0, 1);
                        $latestWeekPart = explode("-", $latestWeek[0]);
                        $tyYear         = $latestWeekPart[0];
                        $tyWeek         = $latestWeekPart[1];

                        $previousWeek   = array_slice($latestWeeks, 1, 1);
                        $previousWeekPart = explode("-", $previousWeek[0]);
                        $lyYear         = $previousWeekPart[0];
                        $lyWeek         = $previousWeekPart[1];

                        $options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']] = " (" . $this->settingVars->yearperiod . "=".$tyYear." AND " . $this->settingVars->weekperiod . "=".$tyWeek.")";
                        $options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']] = " (" . $this->settingVars->yearperiod . "=".$lyYear." AND " . $this->settingVars->weekperiod . "=".$lyWeek.")";

                        if(in_array($maximumTime, array(0,1), true)){
                            $qPartArr[] = $latestWeek[0];
                            $qPartArr[] = $previousWeek[0];
                        }
                    }
                    elseif($performanceTimeSetting['timeFrame'] === 'YTD' ) {
                        //COLLECTING YTD TIME RANGE
                        filters\timeFilter::getYTD($this->settingVars);
                        $options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']] = filters\timeFilter::$tyWeekRange;
                        $options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']] = filters\timeFilter::$lyWeekRange;
                        if($maximumTime === $performanceTimeSetting['timeFrame'])
                            $qPart =  " AND ( ".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange." ) ";
                    }
                    else{
                        $items = explode("#", filters\timeFilter::getLatest_n_dates(0, $performanceTimeSetting['timeFrame'], $this->settingVars, true, "-"));

                        $tyDates = explode(",", $items[0]);
                        $tyMaxDate = $tyDates[0];
                        $tyMaxDatePart = explode("-", $tyMaxDate);
                        
                        $tyMinDate = $tyDates[count($tyDates)-1];
                        $tyMinDatePart = explode("-", $tyMinDate);


                        $lyDates = explode(",", $items[1]);
                        $lyMaxDate = $lyDates[0];
                        $lyMaxDatePart = explode("-", $lyMaxDate);

                        $lyMinDate = $lyDates[count($lyDates)-1];
                        $lyMinDatePart = explode("-", $lyMinDate);

                        $tyConnect = $tyMinDatePart[0]==$tyMaxDatePart[0] ? " AND ": " OR ";
                        $lyConnect = $lyMinDatePart[0]==$lyMaxDatePart[0] ? " AND ": " OR ";
                        
                        // $options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']] = " CONCAT ( " . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $items[1] . " ) ";
                        // $options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']] = " CONCAT ( " . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $items[0] . " ) ";
                        
                        if (is_array($tyMinDatePart) && !empty($tyMinDatePart) && 
                            is_array($tyMaxDatePart) && !empty($tyMaxDatePart)) {
                            $options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']] = " ((" . $this->settingVars->yearperiod . "=" . $tyMinDatePart[0] . " AND " . $this->settingVars->weekperiod . ">=" . $tyMinDatePart[1] . ") ".$tyConnect." ( " . $this->settingVars->yearperiod . "=" . $tyMaxDatePart[0] . " AND " . $this->settingVars->weekperiod . "<=" . $tyMaxDatePart[1] . ")) ";
                        }
                        
                        if (is_array($lyMinDatePart) && !empty($lyMinDatePart) && 
                            is_array($lyMaxDatePart) && !empty($lyMaxDatePart)) {
                            $options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']] = " ((" . $this->settingVars->yearperiod . "=" . $lyMinDatePart[0] . " AND " . $this->settingVars->weekperiod . ">=" . $lyMinDatePart[1] . ") ".$lyConnect." ( " . $this->settingVars->yearperiod . "=" . $lyMaxDatePart[0] . " AND " . $this->settingVars->weekperiod . "<=" . $lyMaxDatePart[1] . ")) ";
                        }

                        if($maximumTime === $performanceTimeSetting['timeFrame'] && !in_array($maximumTime, array(0,1), true) ){
                            if (isset($options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']]) && isset($options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']])) {
                                $qPart =  " AND ( ".$options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']]." OR ".$options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']]." ) ";
                            } else if (isset($options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']])) {
                                $qPart =  " AND ( ".$options['tyLyRange'][$performanceTimeSetting['TY'].$measure['ALIASE']]." ) ";
                            } else if (isset($options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']])) {
                                $qPart =  " AND ( ".$options['tyLyRange'][$performanceTimeSetting['LY'].$measure['ALIASE']]." ) ";
                            }
                            // $qPart =  " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . $items[0] . "," . $items[1] . ") ";
                        }
                        elseif($maximumTime === $performanceTimeSetting['timeFrame'] && in_array($maximumTime, array(0,1), true) ){
                            $qPartArr[] = $items[0];
                            $qPartArr[] = $items[1];
                        }
                    }
                }

                if (array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                    foreach ($measure['usedFields'] as $usedField) {
                        $this->measureFields[] = $usedField;
                    }
                }

                $selectPart = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);
                $measureSelect = array_merge($measureSelect, $selectPart);
            }
        }

        if(!empty($qPartArr))
            $qPart = " AND CONCAT(" . $this->settingVars->yearperiod . ",'-', " . $this->settingVars->weekperiod . ") IN (" .implode(",", $qPartArr). ") ";
        

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        // $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);

        if(is_array($measureSelect) && !empty($measureSelect)) {
            $measureSelect = implode(", ", $measureSelect);
 
            $query = "SELECT " .
                " ".$measureSelect." ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . $qPart ;
              //echo $query;exit;

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            if (is_array($result) && !empty($result)) {

                $data = $result[0];
                $measureKey = 'M'.$_REQUEST['ValueVolume'];
                $measure = $this->settingVars->measureArray[$measureKey];

                if(is_array($this->settingVars->pageArray["PerformanceBoxSettings"]) && !empty($this->settingVars->pageArray["PerformanceBoxSettings"])){
                    foreach ($this->settingVars->pageArray["PerformanceBoxSettings"] as $key => $performanceTimeSetting ) {
                        $row = array();

                        $row['title'] = $performanceTimeSetting['title'];
                        $row['rank'] = $performanceTimeSetting['rank'];
                        $row['TY_VALUE'] = number_format($data[$performanceTimeSetting['TY'].$measure['ALIASE']], 0);
                        $row['VAR_VALUE'] = number_format(($data[$performanceTimeSetting['TY'].$measure['ALIASE']] - $data[$performanceTimeSetting['LY'].$measure['ALIASE']]), 0);
                        $row['VAR_PER'] = ($data[$performanceTimeSetting['LY'].$measure['ALIASE']] > 0) ? number_format((($data[$performanceTimeSetting['TY'].$measure['ALIASE']] / $data[$performanceTimeSetting['LY'].$measure['ALIASE']]) - 1) * 100, 1, '.', '') : "0";
                        $row['LY_VALUE'] = number_format($data[$performanceTimeSetting['LY'].$measure['ALIASE']], 0);

                        $row['VAR_VALUE'] = ($row['VAR_VALUE'] > 0) ? '+' . $row['VAR_VALUE'] . '' : $row['VAR_VALUE'];
                        $row['VAR_PER'] = ($row['VAR_PER'] > 0) ? '+' . $row['VAR_PER'] . '' : $row['VAR_PER'];

                        $arr[] = $row;
                    }
                }

                $arr = utils\SortUtility::sort2DArray($arr, "rank", utils\SortTypes::$SORT_ASCENDING);
            }
        }
        $this->jsonOutput['PerformanceInABox'] = $arr;
    }

    /**
     * categoryPerformanceGrid()
     * It fetch data to show in category performance grid
     *
     * @param $gmargin String To store field name used
     * @param $sales   String To store field name used
     * 
     * @return void
     */
    private function categoryPerformanceGrid() {
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

		$accountName = $this->settingVars->dataArray[$this->accountName]['NAME'];
        $this->measureFields[] = $accountName;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
		
		$query = "SELECT " . $accountName . " as ACCOUNT, " .
                implode(",", $measureSelectionArr).
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
                " GROUP BY ACCOUNT";
        // echo $query;exit;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $result = utils\SortUtility::sort2DArray($result, $havingTYValue, utils\SortTypes::$SORT_DESCENDING);
        $totalTyear = $totalLyear = 0;

        $fieldPerformanceGrid = $performanceChartData = array();
        if (is_array($result) && !empty($result)) {
            $totalTyear = array_sum(array_column($result, $havingTYValue));
            $totalLyear = array_sum(array_column($result, $havingLYValue));

            foreach ($result as $key => $row) {
                $podData = $fieldPerformance = array();

                if($row[$havingTYValue] == 0 && $row[$havingLYValue] == 0 )
                    continue;

                $varPer = ($row[$havingLYValue] > 0) ? (($row[$havingTYValue] - $row[$havingLYValue]) / $row[$havingLYValue]) * 100 : 0;
                $tyShare = ($totalTyear!=0) ? ($row[$havingTYValue] / $totalTyear) * 100 : 0;
                $lyShare = ($totalLyear!=0) ? ($row[$havingLYValue] / $totalLyear) * 100 : 0;
                
                $fieldPerformance['ACCOUNT']        = $row['ACCOUNT'];
                $fieldPerformance['TYEAR']          = number_format($row[$havingTYValue], 0, '', '');
                $fieldPerformance['LYEAR']          = number_format($row[$havingLYValue], 0, '', '');
                $fieldPerformance['VAR']            = number_format(($row[$havingTYValue] - $row[$havingLYValue]), 1, '.', '');
                $fieldPerformance['VAR_PERCENT']    = number_format($varPer, 1, '.', '');
                $fieldPerformance['SHARE']          = number_format($tyShare, 1, '.', '');
                $fieldPerformance['SHARE_LY']       = number_format($lyShare, 1, '.', '');
                $fieldPerformance['SHARE_CHG']      = number_format(($tyShare - $lyShare), 1, '.', '');

                $fieldPerformanceGrid[]       = $fieldPerformance;


                $podData['ACCOUNT']     = $row['ACCOUNT'];
                $podData['TYEAR']       = $row[$havingTYValue]; 
                $podData['LYEAR']       = $row[$havingLYValue]; 
                $podData['VAR_PERCENT'] = $varPer;

                $performanceChartData[] = $podData;
            }

            $fieldPerformance = array();
            $fieldPerformance['ACCOUNT']        = "TOTAL";
            $fieldPerformance['TYEAR']          = number_format($totalTyear, 0, '', '');
            $fieldPerformance['LYEAR']          = number_format($totalLyear, 0, '', '');
            $fieldPerformance['VAR']            = number_format(($totalTyear - $totalLyear), 1, '.', '');
            $fieldPerformance['VAR_PERCENT']    = ($totalLyear > 0) ? number_format((($totalTyear - $totalLyear) / $totalLyear) * 100, 1, '.', '') : 0;
            $fieldPerformance['SHARE']          = number_format(100, 1, '.', '');
            $fieldPerformance['SHARE_LY']       = number_format(100, 1, '.', '');
            $fieldPerformance['SHARE_CHG']      = number_format(0, 1, '.', '');

            array_unshift($fieldPerformanceGrid, $fieldPerformance);

            ksort($fieldPerformanceGrid);
        }

        $this->jsonOutput['categoryPerformance'] = $fieldPerformanceGrid;
        $this->jsonOutput['POD_CLIENT_BRAND'] = $performanceChartData;
    }

    /**
     * productPerformanceSkuGrid()
     * It fetch data to show in product performance grid based on SKU
     *
     * @param $gmargin String To store field name used
     * @param $sales   String To store field name used
     * 
     * @return void
     */
    private function productPerformanceSkuGrid($sales) {
        if(empty($this->bottomGridFieldStatus))
            return;

        $isShowSkuIDCol = false;
		if ($this->settingVars->isDynamicPage) {
            if(isset($_REQUEST['skuField']) && !empty($_REQUEST['skuField']) )
                $bottomGridField  = $_REQUEST['skuField'];
            else{
                $bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
            }
			$this->buildDataArray(array($bottomGridField),true);
            $bottomGridFieldPart = explode("#", $bottomGridField);
            $accountField = strtoupper($this->dbColumnsArray[$bottomGridFieldPart[0]]);
            $accountField = (count($bottomGridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$bottomGridFieldPart[1]]) : $accountField;
			
            $isShowSkuIDCol = (count($bottomGridFieldPart) > 1) ? true : false;
			
			$accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
			$accountName = $this->settingVars->dataArray[$accountField]['NAME'];
		} else {
			$accountID = $this->settingVars->dataArray[$_REQUEST['productAccount']]['ID'];
			$accountName = $this->settingVars->dataArray[$_REQUEST['productAccount']]['NAME'];
		}

        foreach($this->dbColumnsArray as $csvCol => $dbCol)
        {
            if($accountName == $dbCol)
                $this->jsonOutput['bottomGridlblName'] = $this->displayCsvNameArray[$csvCol];
        }

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $this->measureFields[] = $accountID;
        $this->measureFields[] = $accountName;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        $query = "SELECT MAX(".$accountID.") as skuID, " . $accountName . " as ACCOUNT, " .
                implode(",", $measureSelectionArr) .
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
                " GROUP BY ACCOUNT";
		// echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        // $result = utils\SortUtility::sort2DArray($result, $havingTYValue, utils\SortTypes::$SORT_DESCENDING);
        // $requiredGridFields = array("skuID", "ACCOUNT", $havingTYValue, $havingLYValue);
        $result = $this->redisCache->getRequiredDataWithHaving($result, array(), $havingTYValue, $havingTYValue, $havingLYValue);

        $totalTyear = $totalLyear = 0;
        $arr = array();
        if (is_array($result) && !empty($result)) {
            $totalTyear = array_sum(array_column($result, $havingTYValue));
            $totalLyear = array_sum(array_column($result, $havingLYValue));

            foreach ($result as $key => $row) {
                $varPer = ($row[$havingLYValue] > 0) ? (($row[$havingTYValue] - $row[$havingLYValue]) / $row[$havingLYValue]) * 100 : 0;
                $tyShare = ($totalTyear!=0) ? ($row[$havingTYValue] / $totalTyear) * 100 : 0;
                $lyShare = ($totalLyear!=0) ? ($row[$havingLYValue] / $totalLyear) * 100 : 0;

                $temp = array();
                $temp['skuID']          = $row['skuID'];
                $temp['SKU']            = $row['ACCOUNT'];
                $temp['TYEAR']          = number_format($row[$havingTYValue], 0, '', '');
                $temp['LYEAR']          = number_format($row[$havingLYValue], 0, '', '');
                $temp['VAR']            = number_format(($row[$havingTYValue] - $row[$havingLYValue]), 1, '.', '');
                $temp['VAR_PERCENT']    = number_format($varPer, 1, '.', '');
                $temp['SHARE']          = number_format($tyShare, 1, '.', '');
                $temp['SHARE_LY']       = number_format($lyShare, 1, '.', '');
                $temp['SHARE_CHG']      = number_format(($tyShare - $lyShare), 1, '.', '');

                $arr[] = $temp;
            }
        }

        $this->jsonOutput['productPerformanceSku'] = $arr;
        $this->jsonOutput['isShowSkuIDCol'] = $isShowSkuIDCol;
    }
    
    public function buildDataArray($fields, $isCsvColumn) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
		$this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }
    
    public function buildPageArray()
    {
        $tables = array();
        if ($this->settingVars->isDynamicPage) {
            $tables = $this->getPageConfiguration('table_field', $this->settingVars->pageID);
        } else {
            if(isset($this->fieldsTable) && $this->fieldsTable== 'Product')
                $tables = array($this->settingVars->skutable);
            elseif(isset($this->fieldsTable) && $this->fieldsTable == 'Store')
                $tables = array($this->settingVars->storetable);
        }

        if(is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            $this->prepareFieldsFromFieldSelectionSettings($tables);
            /*foreach($tables as $table)
            {
                if(isset($this->queryVars->projectConfiguration[$table."_settings"]) && !empty($this->queryVars->projectConfiguration[$table."_settings"])) {
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table."_settings"]);
                    foreach($settings as $key => $field)
                    {
                        $val = explode("#", $field);
                        $fields[] = $val[0];
						
						if($key == 0)
						{
                            $appendTable = ($table == 'market') ? 'store' : $table;
							$this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable.".".$val[0];							
						}
                    }
				} else {
					$response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
					echo json_encode($response);
					exit();
				}
            }
            
            $this->buildDataArray($fields, false);
            
            foreach($this->dbColumnsArray as $csvCol => $dbCol)
            {
				
                if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $dbCol)
                    $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $csvCol;

				$tmpArr[] = array('label' => $this->displayCsvNameArray[$csvCol], 'data' => $csvCol);
            }
            
            $this->jsonOutput['fieldSelection'] = $tmpArr;*/
        }
        else
        {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }

    /**
     * getAll()
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     *
     * @return String
     */
    public function getAll() {
        //$tablejoins_and_filters = $this->settingVars->link;
        $tablejoins_and_filters = parent::getAll();

		if ($_REQUEST['Field'] != "" && $_REQUEST['FieldValue'] != "") {
            $fieldName = $this->settingVars->dataArray[$this->accountName]['NAME'];

            if (isset($fieldName) && !empty($fieldName) && strpos($fieldName, ".") === false)
                $tablejoins_and_filters .= " AND " . $this->settingVars->skutable . ".".$fieldName."='" . $_REQUEST['FieldValue'] . "'";
            else
                $tablejoins_and_filters .= " AND " .$fieldName."='" . $_REQUEST['FieldValue'] . "'";
        }

        return $tablejoins_and_filters;
    }
}

?>