<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class DailyTrackerPage extends config\UlConfig {

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
			$this->gridFields = $this->getPageConfiguration('grid_fields', $this->settingVars->pageID);
            
            if(isset($_REQUEST['customAccount']) && $_REQUEST['customAccount'] != "")
                $this->gridFields[0] = $_REQUEST['customAccount'];

            $this->dh = $this->settingVars->pageArray[$this->settingVars->pageName]['DH'];
			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields)."_PERFORMANCE" : $settingVars->pageName;
            
			$this->countOfGrid = count($this->gridFields);

			$this->buildDataArray($this->gridFields);
			$this->buildPageArray($this->gridFields);
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
            $defaultSelectedField = array_values($filterDataConfig)[0];
            $this->jsonOutput["defaultSelectedField"] = $defaultSelectedField['field'];
            $this->jsonOutput["defaultSelectedFieldName"] = $defaultSelectedField['label'];
            $this->jsonOutput["productAndMarketFilterData"] = $productFilterData;
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action'] != 'getTreeMapData')
        {
            $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
            $requestedCombination = explode("-", $_REQUEST['timeFrame']);
            $requestedYear        = $requestedCombination[1];
            $requestedSeason      = $requestedCombination[0];

            $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
            $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action']=='export') {
            list($preparedQuery,$thisYear,$lastYear,$activeXlsWsheet,$havingTYValue,$havingLYValue,$appliedFilters,$tyFromDate,$tyToDate) = $this->prepareExportData();

            $appliedFiltersTxt = implode('$$', $appliedFilters);
            $actXlsFldNm = implode("##", array_keys($activeXlsWsheet));
            $actXlsValNm = implode("##", $activeXlsWsheet);

            $fileName = "Daily-Tracker-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
            $savePath = dirname(__FILE__)."/../uploads/Daily-Tracker-Report/";
            $imgLogoPath = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
            $filePath = $savePath.$fileName;

            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/DailyTrackerReport.pl "'.$filePath.'" "'.$preparedQuery.'" "'.$thisYear.'" "'.$lastYear.'" "'.$actXlsFldNm.'" "'.$actXlsValNm.'" "'.$havingTYValue.'" "'.$havingLYValue.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$tyFromDate.'" "'.$tyToDate.'"');

            $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Daily-Tracker-Report/".$fileName;
        } elseif (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchGrid') {
            $this->gridData();
        } elseif (isset($_REQUEST['action']) && $_REQUEST['action']=='getTreeMapData') {
            $this->buildTreeMaps();
        }

        return $this->jsonOutput;
    }

    public function buildTreeMaps()
    {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $field = strtoupper($_REQUEST['accountField']);
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        
        $this->measureFields[] = $this->settingVars->dataArray[$field]['NAME'];
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->Tree($this->settingVars->dataArray[$field]['NAME'], $field, $field);
    }
    
    public function buildPageArray($gridFields) {
        if (empty($gridFields))
            return false;

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
        //$requestedCombination = explode("-", $_REQUEST['FromSeason']);
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        $requestedSeason      = $requestedCombination[0];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
        $this->measureFields[] = $this->settingVars->skutable.'.pname';
        
        $accountField = $this->settingVars->maintable.".PNAME";
        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $accountField = $_REQUEST['ACCOUNT'];
        }else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $accountField = $this->jsonOutput["defaultSelectedField"];
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        $this->jsonOutput['thisYear'] = $ty = (int)$requestedYear;
        $this->jsonOutput['lastYear'] = $ly = $requestedYear - 1;

        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyMydatePart[0] = $tyMydatePart[0]-1;
                $lyDate = implode('-', $tyMydatePart);

                $colsHeader['TY'][] = array("FORMATED_DATE" => date('D-d-M', strtotime($tyDate)), "MYDATE" => $tyDate);
                $colsHeader['LY'][] = array("FORMATED_DATE" => date('D-d-M', strtotime($lyDate)), "MYDATE" => $lyDate);
                $dateArray[$tyDate] = date('j-n', strtotime($tyDate));
            }
        }

        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        $query = "SELECT DISTINCT ".$accountField." AS ACCOUNT, ".
            "MAX(".$maintable.".mydate) as MYDATE, ".
            "DATE_FORMAT(".$maintable.".mydate, '%e-%c') as FORMATED_DATE, ".
            "MAX(".$maintable.".seasonal_year) as YEAR, ".
            $measuresFldsAll.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
            " ORDER BY MYDATE ASC";

        // echo $query; exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        // $dateArray = array_unique(array_column($result, 'FORMATED_DATE', 'MYDATE'));
        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $seasonalData) {
                $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        $cnt = 0; $cmpTyTotal = $cmpLyTotal = [];
        foreach (array_keys($seasonalDataArray) as $account) {
            $tmp = $tmp1 = $tmp2 = $cumTmp = $cumTmp1 = $cumTmp2 = array();
            $tmp['ACCOUNT'] = $account;
            $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp1['ACCOUNT'] = $account;
            $tmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp2['ACCOUNT'] = $account;
            $tmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp['ACCOUNT'] = $account;
            $cumTmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp1['ACCOUNT'] = $account;
            $cumTmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp2['ACCOUNT'] = $account;
            $cumTmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTyValue = $cumLyValue = 0;
            foreach ($dateArray as $dayMydate => $dayMonth) {
                $tyMydate = $dayMydate;
                
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    // $dateKey = 'dt'.
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$tyMydate);

                    $tmp[$dtKey] = $data[$havingTYValue]*1;
                    $tmp['YEAR'] = $ty;
                    $tmp['RANK'] = (int)'1'.$data['YEAR'];
                    $tmp['ROWDESC'] = 'Daily Sales';
                    $tmp['highlightRow'] = 1;

                    $cmpTyTotal[$dtKey] += $data[$havingTYValue];
                    
                    $tmp1[$dtKey] = $data[$havingLYValue]*1;
                    $tmp1['YEAR'] = $ly;
                    $tmp1['RANK'] = (int)'1'.($data['YEAR']-1);
                    $tmp1['ROWDESC'] = 'Daily Sales';
                    $tmp1['highlightRow'] = 1;
                    
                    $cmpLyTotal[$dtKey] += $data[$havingLYValue];

                    $tmp2[$dtKey] = ($data[$havingLYValue] > 0) ? (($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue])*100 : 0;
                    $tmp2[$dtKey] = $tmp2[$dtKey];
                    $tmp2['YEAR'] = 'YOY% daily';
                    $tmp2['RANK'] = '1YOY% daily';
                    $tmp2['ROWDESC'] = 'Daily Sales';
                    $tmp2['highlightRow'] = 2;

                    //$cumTyValue += round($data[$havingTYValue]);
                    $cumTyValue += $data[$havingTYValue];
                    $cumTmp[$dtKey] = $cumTyValue;
                    $cumTmp['YEAR'] = $ty;
                    $cumTmp['RANK'] = (int)'2'.$data['YEAR'];
                    $cumTmp['ROWDESC'] = 'Cumulative Sales';
                    $cumTmp['highlightRow'] = 0;
                    
                    //$cumLyValue += round($data[$havingLYValue]);
                    $cumLyValue += $data[$havingLYValue];
                    $cumTmp1[$dtKey] = $cumLyValue;
                    $cumTmp1['YEAR'] = $ly;
                    $cumTmp1['RANK'] = (int)'2'.($data['YEAR']-1);
                    $cumTmp1['ROWDESC'] = 'Cumulative Sales';
                    $cumTmp1['highlightRow'] = 0;

                    $cumTmp2[$dtKey] = ($cumLyValue > 0) ? (($cumTyValue - $cumLyValue) / $cumLyValue)*100 : 0;
                    $cumTmp2[$dtKey] = $cumTmp2[$dtKey];
                    $cumTmp2['YEAR'] = 'YOY% Cum.';
                    $cumTmp2['RANK'] = '2YOY% Cum.';
                    $cumTmp2['ROWDESC'] = 'Cumulative Sales';
                    $cumTmp2['highlightRow'] = 2;
                }
            }
            $finalData[] = $tmp1;
            $finalData[] = $tmp;
            $finalData[] = $tmp2;
            $finalData[] = $cumTmp1;
            $finalData[] = $cumTmp;
            $finalData[] = $cumTmp2;
            $cnt++;
        }

        /*[START] ADDING THE TOTAL COLUMN*/
        $cmpTyLyYoyTotal = $cmpTyLyYoyCumTotal = $cmpTyCumTotal = $cmpLyCumTotal = []; 
        if(isset($cmpTyTotal) && isset($cmpLyTotal)) {
            $cumTyValue = $cumLyValue = 0;
            foreach ($cmpTyTotal as $cpTykey => $cmTyVal) {
                $cumTyValue += $cmTyVal;
                $cmpTyCumTotal[$cpTykey] = $cumTyValue;
                $cmpTyLyYoyTotal[$cpTykey] = ($cmpLyTotal[$cpTykey] > 0) ? ((($cmTyVal - $cmpLyTotal[$cpTykey]) / $cmpLyTotal[$cpTykey])*100) : 0;

                /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumLyValue += $cmpLyTotal[$cpTykey];
                    $cmpLyCumTotal[$cpTykey] = $cumLyValue;

                    $cmpTyLyYoyCumTotal[$cpTykey] = ($cmpLyCumTotal[$cpTykey] > 0) ? ((($cmpTyCumTotal[$cpTykey] - $cmpLyCumTotal[$cpTykey]) / $cmpLyCumTotal[$cpTykey])*100) : 0;
                /*[END] GETTING CUMULATIVE LY VALUE*/
            }

            $cmpTyTotal['TOTAL']        = 1;
            $cmpLyTotal['TOTAL']        = 1;
            $cmpTyLyYoyTotal['TOTAL']   = 1;
            $cmpTyCumTotal['TOTAL']     = 1;
            $cmpLyCumTotal['TOTAL']     = 1;
            $cmpTyLyYoyCumTotal['TOTAL']= 1;
            $dataPnameSum['TOTAL']      = 1;

            $cmpTyTotal['ACCOUNT']   = 'TOTAL';
            $cmpTyTotal['YEAR']    = $ty;
            $cmpTyTotal['RANK']    = (int)'1'.$ty;
            $cmpTyTotal['ROWDESC'] = 'Daily Sales';
            $cmpTyTotal['highlightRow'] = 1;

            $cmpLyTotal['ACCOUNT']   = 'TOTAL';
            $cmpLyTotal['YEAR']    = $ly;
            $cmpLyTotal['RANK']    = (int)'1'.$ly;
            $cmpLyTotal['ROWDESC'] = 'Daily Sales';
            $cmpLyTotal['highlightRow'] = 1;
            
            $cmpTyLyYoyTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyLyYoyTotal['YEAR']  = 'YOY% daily';
            $cmpTyLyYoyTotal['RANK']  = '1YOY% daily';
            $cmpTyLyYoyTotal['ROWDESC'] = 'Daily Sales';
            $cmpTyLyYoyTotal['highlightRow'] = 2;

            $cmpTyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyCumTotal['YEAR']  = $ty;
            $cmpTyCumTotal['RANK']  = (int)'2'.$ty;
            $cmpTyCumTotal['ROWDESC'] = 'Cumulative Sales';
            $cmpTyCumTotal['highlightRow'] = 0;

            $cmpLyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpLyCumTotal['YEAR']  = $ly;
            $cmpLyCumTotal['RANK']  = (int)'2'.$ly;
            $cmpLyCumTotal['ROWDESC'] = 'Cumulative Sales';
            $cmpLyCumTotal['highlightRow'] = 0;

            $cmpTyLyYoyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyLyYoyCumTotal['YEAR']  = 'YOY% Cum.';
            $cmpTyLyYoyCumTotal['RANK']  = '2YOY% Cum.';
            $cmpTyLyYoyCumTotal['ROWDESC'] = 'Cumulative Sales';
            $cmpTyLyYoyCumTotal['highlightRow'] = 2;
        
            $finalData[] = $cmpTyTotal;
            $finalData[] = $cmpLyTotal;
            $finalData[] = $cmpTyLyYoyTotal;
            $finalData[] = $cmpTyCumTotal;
            $finalData[] = $cmpLyCumTotal;
            $finalData[] = $cmpTyLyYoyCumTotal;
        }
        /*[END] ADDING THE TOTAL COLUMN*/
        
        $totalArray = array_column($finalData, 'TOTAL');
        array_multisort($totalArray, SORT_DESC, SORT_NUMERIC, $finalData);

        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->jsonOutput['gridData'] = $finalData;
        
        /*if(!empty($colsHeader)){
            $this->jsonOutput['gridDataRang'] = date('d F Y',strtotime($colsHeader['TY'][0]['MYDATE']))." To ".date('d F Y',strtotime($colsHeader['TY'][(count($colsHeader['TY'])-1)]['MYDATE']));
        }*/

        if(!empty($colsHeader)) {
            $dtFrm = 'TY';
            // if($requestedYear == $lyYear)
            //     $dtFrm = 'LY';
            $this->jsonOutput['gridDataRang'] = date('d F Y',strtotime($colsHeader[$dtFrm][0]['MYDATE']))." To ".date('d F Y',strtotime($colsHeader[$dtFrm][(count($colsHeader[$dtFrm])-1)]['MYDATE']));
        }

        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }
        $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
    }

    public function prepareExportData(){
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
        if(empty($redisOutput))
            return ;

        $selectionFields = []; $selectionGroupBy = [];
        ksort($redisOutput);
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

        $allActiveFiltersNames = array_column($this->settingVars->dataArray,'NAME_CSV','NAME_ALIASE');
        $this->measureFields = array_merge(array_values($selectionFields),$measureSelectRes['measureSelectionArr']);
        $this->measureFields[] = $this->settingVars->skutable.'.pname_rollup2';
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
            implode(',', $selectionFields).
            ", MAX(".$maintable.".mydate) as MYDATE, ".
            "DATE_FORMAT(".$maintable.".mydate, '%e-%c') as FORMATED_DATE, ".
            "MAX(".$maintable.".seasonal_year) as YEAR, ".
            $measuresFldsAll.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
            " GROUP BY ".implode(',',$selectionGroupBy)." ,FORMATED_DATE ORDER BY MYDATE ASC";

        //$activeXlsWsheet = ['PNAME'=>'SKU'];
        $activeXlsWsheet = [];
        if(!empty($selectionGroupBy)){
            foreach ($selectionGroupBy as $key => $sgb) {
                if(isset($allActiveFiltersNames[$sgb]))
                    $activeXlsWsheet[$sgb] = $allActiveFiltersNames[$sgb];
            }
        }
        //ksort($activeXlsWsheet);
        /*[START] GETTING THE SELECTED FILTERS*/
            $appliedFilters = [];
            if(isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])){
                $appliedFilters[] = 'Time Selection##'.$_REQUEST['timeFrame'];
            }
            if(isset($_REQUEST['toDate']) && !empty($_REQUEST['toDate'])){
                $appliedFilters[] = 'Hard Stop##'.date('d-M',strtotime($_REQUEST['toDate']));
            }
            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                if($measureVal['measureID'] == $_REQUEST['ValueVolume'])
                    $appliedFilters[] = 'Measure Selection##'.$measureVal['measureName'];
            }

            if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                foreach($_REQUEST['FS'] as $ky=>$valDt) {
                    if(!empty($valDt)) {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                //$dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                            }
                            //if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $appliedFilters[] = $this->settingVars->dataArray[$ky]['NAME_CSV'].'##'.urldecode($dataList);
                            //}else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                //$marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            //}
                        }
                    }
                }
            }
        /*[END] GETTING THE SELECTED FILTERS*/
        
        /*[START] GETTING THE START AND END DATE LOGIC*/
            $fromYear       = $thisYear;
            $toYear         = (($this->settingVars->fromToDateRange['fromMonth']-$this->settingVars->fromToDateRange['toMonth']) > 0) ? $fromYear+1 : $fromYear;
            $tyFromDate     = $fromYear.'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
            if (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) {
                $tyToDate = $_REQUEST["toDate"];
            } else {
                $tyToDate = $this->settingVars->fromToDateRange['maxDate'];
            }
        /*[END] GETTING THE START AND END DATE LOGIC*/
    return array($query,$thisYear,$lastYear,$activeXlsWsheet,$havingTYValue,$havingLYValue,$appliedFilters,$tyFromDate,$tyToDate);
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

    public function getAllDataFromIds($type,$id,$data){
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketSelectionTabsRedisList');
        if ($redisOutput === false) {
            return $data;
        } else {
            
            $selectionTabsRedisList = $redisOutput[$type];
            if(!is_array($selectionTabsRedisList) || empty($selectionTabsRedisList))
                 return $data;

            if($type == 'FSG')
            {
                $storeList = array_column($selectionTabsRedisList, 'data');
                $Skey = array_search($data, $storeList);
                if(is_numeric($Skey))
                {
                    $data = $selectionTabsRedisList[$Skey]['label'];
                }
            }
            else
            {
                $aKey = array_search($id, array_column($selectionTabsRedisList, 'data'));
                if(isset($selectionTabsRedisList[$aKey]) && isset($selectionTabsRedisList[$aKey]['dataList']) && is_array($selectionTabsRedisList[$aKey]['dataList']) && count($selectionTabsRedisList[$aKey]['dataList'])>0){

                    $mainArr = array_column($selectionTabsRedisList[$aKey]['dataList'], 'label','data');
                    $fndata = [];
                    $data = explode(',', $data);
                    if(is_array($data) && count($data)>0){
                        foreach ($data as $k => $vl) {
                            if(isset($mainArr[$vl]) && !empty($mainArr[$vl]))
                                $fndata[] = $mainArr[$vl];
                            else
                                $fndata[] = $vl;
                        }
                        $data = implode(',', $fndata);
                    }
                }
            }
            return $data;
            
        }
    }
    
    public function Tree($name, $tagName, $indexInDataArray) {
        $negcolor = array('EE0202', 'D20202', 'B50202', 'A00202', '8C0101', '760101', '640101', '510101', '400101', '2E0101');
        $color = array('002D00', '014301', '015901', '016B01', '018001', '019701', '01AC01', '02C502', '02DB02', '02FB02');
        
        $dataStore = array();
        $max = 0;
        $min = 0;

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $extraWhereClause = $structurePageClass->extraWhereClause($this->settingVars, $this->measureFields);
        
        $this->measureFields[] = $name;
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(). $extraWhereClause;        
        
        $query = "SELECT $name AS ACCOUNT, ". implode(",", $measureSelectionArr).
                " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ACCOUNT";

        //echo $query; exit();
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $total = array_column($result, $havingTYValue);
        $this->TOTAL_TY_SALES = array_sum($total);
        
        $fields = $this->redisCache->getRequiredFieldsArray(array("ACCOUNT"), false, $this->settingVars->pageArray["MEASURE_SELECTION_LIST"]);
        
        $this->redisCache->getRequiredData($result, $fields, $havingTYValue);
        
        foreach ($result as $key => $row) {
            if($key < 250) {
                $row['ACCOUNT'] = str_replace('\'', ' ', $row['ACCOUNT']);
                $thisyearval = $row[$havingTYValue];
                $lastyearval = $row[$havingLYValue];

                if ($lastyearval > 0) {
                    $var = (($thisyearval - $lastyearval) / $lastyearval) * 100;
                    if ($var > $max)
                        $max = $var;
                    if ($var < $min)
                        $min = $var;
                    array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
                }else {
                    $var = 0;
                    array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
                }
            }
        }

        $tempResult = array();
        for ($i = 0; $i < count($dataStore); $i++) {
            $d = explode('#', $dataStore[$i]);

            if ($this->TOTAL_TY_SALES == 0 || $this->TOTAL_TY_SALES == NULL) {
                $percent = number_format(0);
            } else {
                $percent = number_format(($d[1] / $this->TOTAL_TY_SALES) * 100, 1);
                $chartval2 = number_format((($this->TOTAL_TY_SALES - $d[1]) / $this->TOTAL_TY_SALES) * 100, 1);
            }

            if ($d[2] >= 0) {
                $c = 0;
                $range = 10;
                for ($j = 0; $j <= $max; $j+=$range) {
                    if ($d[1] > 0) {
                        if (number_format($d[2], 2, '.', '') >= 100) {
                            $temp = array(
                                //'@attributes' => array(
                                'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                , 'value' => $d[1]
                                , 'color' => $color[9]
                                , 'alpha' => 1
                                , 'varp' => $d[2]
                                , 'chartval1' => $percent
                                , 'chartval2' => $chartval2
                                    // )
                            );
                            $tempResult[$tagName][] = $temp;
                            break;
                        } else {
                            if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                                $temp = array(
                                    //'@attributes' => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'value' => $d[1]
                                    , 'color' => $color[$c]
                                    , 'alpha' => 1
                                    , 'varp' => $d[2]
                                    , 'chartval1' => $percent
                                    , 'chartval2' => $chartval2
                                        //)
                                );
                                $tempResult[$tagName][] = $temp;
                                break;
                            }
                            $c++;
                        }
                    }
                }
            } else {
                $c = 0;
                $range = abs($min / 10);
                for ($j = $min; $j <= 0; $j+=$range) {
                    if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                        $temp = array(
                            //'@attributes' => array(
                            'name' => htmlspecialchars_decode(strtoupper($d[0]))
                            , 'value' => $d[1]
                            , 'color' => $negcolor[$c]
                            , 'alpha' => 1
                            , 'varp' => $d[2]
                            , 'chartval1' => $percent
                            , 'chartval2' => $chartval2
                                //)
                        );
                        $tempResult[$tagName][] = $temp;
                        break;
                    }
                    $c++;
                }
            }
        }
        $this->jsonOutput['treeMapData'] = $tempResult;
    }    
    
}
?> 