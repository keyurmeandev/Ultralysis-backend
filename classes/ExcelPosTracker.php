<?php

namespace classes;

/** PHPExcel_IOFactory */
require_once '../ppt/Classes/PHPExcel/IOFactory.php';

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ExcelPosTracker extends config\UlConfig {

    public $catList;

    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->catList = array();
        $this->gridListAliase = array();
        $this->selcetdClorCode = array();


        $this->gridTimeSelectionUnit = isset($this->settingVars->timeSelectionUnit) && $this->settingVars->timeSelectionUnit == 'weekMonth' ? 'Month' : 'Week';

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_RankMonitorPage' : $this->settingVars->pageName;

        
        if ($this->settingVars->isDynamicPage) {
            $this->categoryField = $this->getPageConfiguration('category_field', $this->settingVars->pageID)[0];
            $this->subCategoryField = $this->getPageConfiguration('sub_category_field', $this->settingVars->pageID)[0];
            $this->measureFilterSettings = $this->getPageConfiguration('measure_filter_settings', $this->settingVars->pageID);
            if (empty($this->measureFilterSettings)) {
                $this->measureFilterSettings = array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID');
            }

            $fieldArray = array($this->categoryField,$this->subCategoryField);

            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        /*if(is_array($this->measureFilterSettings) && empty($this->measureFilterSettings[0])){
            $this->measureFilterSettings = array(1,2);

            $this->settingVars->hasMeasureFilter = false;
            $this->settingVars->measureArray = array();
            $this->settingVars->measureArray['M1']['VAL'] = $this->settingVars->ProjectValue;
            $this->settingVars->measureArray['M1']['ALIASE'] = "SALES";
            $this->settingVars->measureArray['M1']['attr'] = "SUM";
            $this->settingVars->measureArray['M2']['VAL'] = $this->settingVars->ProjectVolume;
            $this->settingVars->measureArray['M2']['ALIASE'] = "QTY";
            $this->settingVars->measureArray['M2']['attr'] = "SUM";
        }*/

        $selMeasuresForExport = $this->selcetdClorCode = $selMeastmp = array();
        if(isset($_REQUEST['selMeasures']) && !empty($_REQUEST['selMeasures'])){
            $selMeasuresForExport = explode(',', $_REQUEST['selMeasures']);
            if(count($selMeasuresForExport)>0){
                foreach ($selMeasuresForExport as $SelKey => $SelVal) {
                    if(empty($SelVal)) {
                        unset($selMeasuresForExport[$SelKey]);
                    }else{
                        $SelValTmp = explode('##', $SelVal);
                        $this->selcetdClorCode[$SelValTmp[0]] = $SelKey;
                        if($SelValTmp[1] == 'true'){
                            $selMeastmp[] = $SelValTmp[0];
                        }
                    }
                }
                $this->measureFilterSettings = $selMeastmp;
            }
        }
        //print_r($this->selcetdClorCode); exit;
        //filters\timeFilter::getLatestWeek($settingVars);
        if ($this->settingVars->timeSelectionUnit == 'weekMonth'){
            $this->timeArray  = array( '1' => 'LW',  '3' => 'LW3', '12' => 'LW12', 'YTD' => 'YTD');
        }else{
            $this->timeArray  = array( '1' => 'LW',  '4' => 'LW4', '13' => 'LW13', '52' => 'LW52', 'YTD' => 'YTD');
            // $this->settingVars->timeSelectionUnit = 'weekMonth';
            // $this->gridTimeSelectionUnit = 'Month';
            // $this->timeArray  = array( '1' => 'LW',  '3' => 'LW3', '12' => 'LW12', 'YTD' => 'YTD');
        }

        $this->gridTitle = "Excel Pos Tracker ";
        $this->colorColArrPair = [
                                    array('#87a9af','#a2bc65','#c9d19c'),
                                    array('#72A2D3','#83BBD3','#8FDBEA'),
                                    array('#009688','#09b7a7','#13d0be'),
                                    array('#9c7f26','#c79e22','#f9bc08'),
                                    array('#9c27b0','#a872b1','#cda6d4'),
                                    array('#0a84bb','#03a9f4','#86c7e4'),
                                 ];

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getGridColumnsConfig();
        }

        $action = $_REQUEST['action'];        
        $this->isexport = ($action == 'exportGrid') ? true : false;
        switch ($action) {
            case "getGridData":
                $this->getCategoryData();
                break;
            case "exportGrid":
                $this->exportData();
                //$this->getCategoryData();
                break;
        }
        return $this->jsonOutput;
    }

    public function getGridColumnsConfig(){
        $measureArr = $timeSelectionSettings = $measureFilterSettingsIds = $measureDecimalMapping = array();
        if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings)){
            foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                $key = array_search($measureVal, array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));

                $measureKey = 'M' . $measureVal;
                $measure = $this->settingVars->measureArray[$measureKey];

                if ($key !== false)
                    $measureName = (isset( $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key])) ? $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'] : '';
                else {
                    $measureName = (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE'];
                }
                

                // print_r($measure['measureName']);exit();
                // $measureName = (!empty($measureName)) ? $measureName : $measure['ALIASE'];
                $measureArr[] = $measureName;
                $measureFilterSettingsIds[$measureName] = $measureVal;

                $measureDecimalMapping[$measure['ALIASE']] = (isset($measure['dataDecimalPlaces']) ? (int)$measure['dataDecimalPlaces'] : 0);
            }
        }

        if(is_array($this->timeArray) && !empty($this->timeArray)){
            foreach ($this->timeArray as $timekey => $timeval) {
                $timeSelectionSettings[$timekey] = $timeval;
            }
        }

        $this->jsonOutput['measureFilterSettings']= $measureArr;
        $this->jsonOutput['measureFilterSettingsIds']= $measureFilterSettingsIds;
        $this->jsonOutput['timeSelectionSettings'] = $timeSelectionSettings;
        $this->jsonOutput['measureDecimalMapping'] = $measureDecimalMapping;
    }


    private function exportData() {
        $preparedQuery = $this->getCategoryData();
        $fileName    = "Excel-Pos-Tracker-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath    = dirname(__FILE__)."/../uploads/Excel-Pos-Tracker/";
        $imgLogoPath = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
        $filePath    = $savePath.$fileName;
        $projectID   = $this->settingVars->projectID;
        $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
        $RedisPassword = $this->queryVars->RedisPassword;
        
        if ($this->settingVars->timeSelectionUnit == 'weekMonth'){
            $arrTitl = ['Last '.$this->gridTimeSelectionUnit,
                        'Last 3 '.$this->gridTimeSelectionUnit.'s',
                        'Last 12 '.$this->gridTimeSelectionUnit.'s',
                        $this->settingVars->groupName.' YTD'];
        }else{
            $arrTitl = ['Last '.$this->gridTimeSelectionUnit,
                        'Last 4 '.$this->gridTimeSelectionUnit.'s',
                        'Last 13 '.$this->gridTimeSelectionUnit.'s',
                        'Last 52 '.$this->gridTimeSelectionUnit.'s',
                        $this->settingVars->groupName.' YTD'];
        }
        $headerColumns = implode("##", $arrTitl);
        $timekey       = implode("##", array_keys($this->timeArray));
        $timeval       = implode("##", $this->timeArray);
        //$measureFilterSettings = implode("##", $this->measureFilterSettings);
        foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
            $measureKey = 'M' . $measureVal;
            $measure = $this->settingVars->measureArray[$measureKey];
            $measureAliaseArr[] = $measure['ALIASE'];
        }
        $measureFilterSettings = implode("##", $measureAliaseArr);

        $appliedFilters = [];
        $enabledFilters = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        /*[START] ADDING THE FILTERS AT THE TOP */
            /*[START] Time*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('time-selection', $enabledFilters)){
                $titleTimeSelection = '';
                if(isset($_REQUEST['FromWeek']) && isset($_REQUEST['ToWeek']) && isset($_REQUEST['FromWeekPrv']) && isset($_REQUEST['ToWeekPrv'])){
                    $titleTimeSelection = $_REQUEST['FromWeek']." To ".$_REQUEST['ToWeek']." VS ".$_REQUEST['FromWeekPrv']." To ".$_REQUEST['ToWeekPrv'];
                    $appliedFilters[] = "Time Selection##".$titleTimeSelection;
                }
            }
            /*[END] Time*/

            /*[START] Product*/
            $productFilterData = $marketFilterData = [];
            if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                foreach($_REQUEST['FS'] as $ky=>$valDt) {
                    if(!empty($valDt)) {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                $dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                            }
                            if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $productFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                $marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }
                        }
                    }
                }
            }

            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('product-selection', $enabledFilters)){
                $objRichText = '';
                if(count($productFilterData)>0){
                    $lstV = count($productFilterData);
                    $i = 0;
                    foreach ($productFilterData as $kIds => $Val) {
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $objRichText.= $kIds.' : '.$Val;
                    }
                }else{
                    $objRichText = 'All';
                }
                $appliedFilters[] = 'Product Filter##'.$objRichText;
            }
            /*[END] Procuct*/
            
            /*[START] Market*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('market-selection', $enabledFilters)){
                $objRichText = '';
                if(count($marketFilterData)>0){
                    $lstV = count($marketFilterData);
                    foreach ($marketFilterData as $kIds => $Val) {
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $objRichText .= $kIds.' : '.$Val;
                    }
                }else{
                    $objRichText = 'All';
                }
                $appliedFilters[] =  'Market Filter##'.$objRichText;
            }
            /*[END] Market*/

            /*[START] Measure*/
            $measNameArr = [];
            if(isset($this->measureFilterSettings) && count($this->measureFilterSettings)>0){
                foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                    $key = array_search($measureVal, array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));
                    // $measNameArr[] = (isset( $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key])) ? $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'] : '';

                    $measureKey = 'M' . $measureVal;
                    $measure = $this->settingVars->measureArray[$measureKey];

                    if ($key !== false)
                        $measureName = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'];
                    else {
                        $measureName = (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE'];
                    }
                    $measNameArr[] = $measureName;
                }
            }
            $appliedFilters[] =  'Measure Settings##'.implode(',', $measNameArr);
            /*[END] Measure*/
            
            if(isset($_REQUEST['FSG']) && is_array($_REQUEST['FSG']) && count($_REQUEST['FSG'])>0)
            {
                foreach($_REQUEST['FSG'] as $ky => $valDt)
                {
                    if(!empty($valDt)) 
                    {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                $dataList = $this->getAllDataFromIds('FSG',$ky,$dataList);
                            }
                            if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $globalFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                $globalFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }
                        }
                    }
                }
                $objRichText = '';
                if(count($globalFilterData)>0){
                    $lstV = count($globalFilterData);
                    $i = 0;
                    foreach ($globalFilterData as $kIds => $Val) {
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $objRichText.= $kIds.' : '.$Val;
                    }
                $appliedFilters[] = 'Global Settings##'.$objRichText;
                }
            }
        /*[END] ADDING THE FILTERS AT THE TOP */
        $appliedFiltersTxt = implode('$$', $appliedFilters);

        if(isset($this->gridListAliase) && !empty($this->gridListAliase)){
            $measureColorCode = [];
            foreach ($this->gridListAliase as $key => $value) {
                $measureColorCode[] = $key.'@@'.str_replace("#", "", $value[0]).'##'.str_replace("#", "", $value[1]).'##'.str_replace("#", "", $value[2]);
            }
            $measureColorCodeTxt = implode("$$", $measureColorCode);
        }

        /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/ExcelPosTrackerReport.pl "'.$filePath.'" "'.$preparedQuery.'" "'.$timekey.'" "'.$timeval.'" "'.$measureFilterSettings.'" "'.$headerColumns.'" "'.$this->settingVars->currencySign.'" "'.$appliedFiltersTxt.'" "'.$measureColorCodeTxt.'" "'.$imgLogoPath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'"';
        exit;*/

        $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/ExcelPosTrackerReport.pl "'.$filePath.'" "'.$preparedQuery.'" "'.$timekey.'" "'.$timeval.'" "'.$measureFilterSettings.'" "'.$headerColumns.'" "'.$this->settingVars->currencySign.'" "'.$appliedFiltersTxt.'" "'.$measureColorCodeTxt.'" "'.$imgLogoPath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'"');

        $this->jsonOutput['fileName'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Excel-Pos-Tracker/".$fileName;
    }

    /**
     * getCategoryData()
     * It will list all category 
     * 
     * @return void
     */
    private function getCategoryData() {

        $redisCache = new utils\RedisCache($this->queryVars);
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->weekperiod . " AS WEEK" .
                (($this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";

        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }

        if(is_array($dateList) && !empty($dateList))
        {
            $latestDate = $dateList[0];

            if(isset($latestDate[2]) && !empty($latestDate[2]))
                $this->gridTitle .= "(W/E ".date('jS F Y', strtotime($latestDate[2])).")";
            else
                $this->gridTitle .= $latestDate[1]."-".$latestDate[0];
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
        $this->measureFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
        
        $this->measureFields[] = $this->categoryName;
        $this->measureFields[] = $this->subCategoryName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();  

        $measureArr = $measureSelectionArr = $arrAliase = $arrAliaseColorCd = $this->arrAliaseMap = array();
        $timeWhereCluase = '';
        if(is_array($this->timeArray) && !empty($this->timeArray)){
            
            $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
            $structurePageClass = new $projectStructureTypePage();
            
            foreach ($this->timeArray as $timekey => $timeval) {
                if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings)){
                    filters\timeFilter::getTimeFrame($timekey, $this->settingVars);
                    foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                        $measureArrTmp = $arrAliaseTmp = $arrAliaseMapTmp = $arrAliaseColorCdTmp = $orderByTmp = [];
                        $measureKey = '';
                        list($measureArrTmp, $arrAliaseTmp, $arrAliaseMapTmp, $arrAliaseColorCdTmp, $orderByTmp, $measureKey) = $structurePageClass->excelPosTrackerTyLyLogic($this->settingVars, $this->queryVars, $timeval, $measureVal, $this->selcetdClorCode, $orderBy);

                        $measureSelectionArr = array_merge($measureSelectionArr,$measureArrTmp[$measureKey]);
                        $arrAliase = array_merge($arrAliase, $arrAliaseTmp);
                        $this->arrAliaseMap = array_merge($this->arrAliaseMap, $arrAliaseMapTmp);
                        $arrAliaseColorCd = array_merge($arrAliaseColorCd, $arrAliaseColorCdTmp);
                        if(!empty($orderByTmp))
                            $orderBy = $orderByTmp;
                    }
                    if ($timekey == '52')
                        $timeWhereCluase = " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                }
            }
        }
        
        
        
        $measureSelect = implode(", ", $measureSelectionArr);

        /* Removed the DISTINCT and added the GROUP BY */
        $query = "SELECT " .$this->subCategoryName . " AS ACCOUNT" .
                " , ".$this->categoryName." AS CATCOLUMN ".
                " , ".$measureSelect." ".
                " FROM " . $this->settingVars->tablename . $this->queryPart. $timeWhereCluase.
                " GROUP BY ".$this->categoryName.",ACCOUNT ".
                ((!empty($orderBy)) ? " ORDER BY ".$orderBy." DESC, CATCOLUMN ASC" : " ");

        /*[START] MAPPING THE COLOR CODE ARRAY*/        
        if(isset($arrAliase) && is_array($arrAliase) && count($arrAliase)>0){
            $clCnt = 0;
            foreach($arrAliase as $k=>$aval){
                if(isset($arrAliaseColorCd[$aval])){
                    $this->gridListAliase[$aval] = isset($this->colorColArrPair[$arrAliaseColorCd[$aval]]) ? $this->colorColArrPair[$arrAliaseColorCd[$aval]] : $this->colorColArrPair[$clCnt];
                }else{
                    $this->gridListAliase[$aval] = $this->colorColArrPair[$clCnt];
                }
                $clCnt++;
                if($clCnt > 6) $clCnt = 0;
            }
        }
        /*[END] MAPPING THE COLOR CODE ARRAY*/
        
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $subcatResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(empty($redisCache->requestHash))
                $redisCache->requestHash = $redisCache->prepareQueryHash($query);

            $redisCache->setDataForStaticHash($subcatResult);
        } else {
            $subcatResult = $redisOutput;
        }

        if($this->isexport){
            return $redisCache->requestHash;
        }

        $this->totalCatData = array();
        if(is_array($subcatResult) && !empty($subcatResult)) {
            foreach ($subcatResult as $key => $catdata) {
                $this->prepareTempArray($catdata);
            }

            foreach ($this->catData as $category => $catData) {
                $subCatList = $this->subCatList[$category];
                foreach ($subCatList as $subCatData) {
                    $this->catList[] = $subCatData;
                }
                $this->catList[] = $catData;
            }
            $this->catList[] = $this->totalCatData;
        }

        if(!$this->isexport){
            $this->jsonOutput['gridList'] = $this->catList;
            $this->jsonOutput['gridTitle'] = $this->gridTitle;
            $this->jsonOutput['timeSelectionUnit'] = $this->gridTimeSelectionUnit;
            $this->jsonOutput['gridListAliase'] = $this->gridListAliase;
            $this->jsonOutput['arrAliaseMap'] = $this->arrAliaseMap;
        }else{
            $this->exportXls();
        }
    }

    private function prepareTempArray($result, $isCategory = false)
    {
        $temp = array();
        $temp['ACCOUNT'] = $result['ACCOUNT'];
        $temp['isTotalRow'] = 0;
        if (!isset($this->subCatList[$result['CATCOLUMN']]))
            $this->subCatList[$result['CATCOLUMN']] = array();
          
        foreach ($this->timeArray as $timekey => $timeval) 
        {
            if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings)){
                foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                    $measureKey = 'M' . $measureVal;
                    $measure = $this->settingVars->measureArray[$measureKey];
                    
                    /*$temp[$timeval.'_TY_'.$measure['ALIASE']] = number_format($result[$timeval.'_TY_'.$measure['ALIASE']],0);
                    $temp[$timeval.'_LY_'.$measure['ALIASE']] = number_format($result[$timeval.'_LY_'.$measure['ALIASE']],0);
                    $temp[$timeval.'_VAR_'.$measure['ALIASE']] = number_format(($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']]),0);
                    $temp[$timeval.'_VARP_'.$measure['ALIASE']] = $result[$timeval.'_LY_'.$measure['ALIASE']] > 0 ? number_format((($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']])/$result[$timeval.'_LY_'.$measure['ALIASE']])*100,1,'.',','):0;*/

                    $temp[$timeval.'_TY_'.$measure['ALIASE']] = (double)$result[$timeval.'_TY_'.$measure['ALIASE']];
                    $temp[$timeval.'_LY_'.$measure['ALIASE']] = (double)$result[$timeval.'_LY_'.$measure['ALIASE']];
                    $temp[$timeval.'_VAR_'.$measure['ALIASE']] = ($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']]);
                    $temp[$timeval.'_VARP_'.$measure['ALIASE']] = $result[$timeval.'_LY_'.$measure['ALIASE']] > 0 ? (($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']])/$result[$timeval.'_LY_'.$measure['ALIASE']])*100:0;

                    $this->catData[$result['CATCOLUMN']]['ACCOUNT'] = $result['CATCOLUMN'];
                    $this->catData[$result['CATCOLUMN']]['isTotalRow'] = 0;
                    $this->catData[$result['CATCOLUMN']]['isCategory'] = 1;
                    $this->catData[$result['CATCOLUMN']]['colorCode'] = '#ceaf96';

                    $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] = str_replace(',', '', $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] += $result[$timeval.'_TY_'.$measure['ALIASE']];

                    $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] = str_replace(',', '', $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']]);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] += $result[$timeval.'_LY_'.$measure['ALIASE']];
                    
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']] = ($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]-$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']]);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']] = (($this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] > 0) ? 
                        ((($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]-$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']])/$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']])*100) : 0 );

                    /*$this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']], 0);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']], 0);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']], 0);
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']],1,'.',',');*/


                    $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] = $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']];
                    $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] = $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']];
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']] = $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']];
                    $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']] = $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']];

                    $this->totalCatData['ACCOUNT'] = "Total";
                    $this->totalCatData['isTotalRow'] = 1;

                    $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] = str_replace(',', '', $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]);
                    $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] += $result[$timeval.'_TY_'.$measure['ALIASE']];

                    $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] = str_replace(',', '', $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']]);
                    $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] += $result[$timeval.'_LY_'.$measure['ALIASE']];
                    
                    $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']] = ($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]-$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']]);
                    $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']] = (($this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] > 0) ? 
                        ((($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]-$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']])/$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']])*100) : 0 );

                    /*$this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']],0);
                    $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']],0);
                    $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']],0);
                    $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']],1,'.',',');*/

                    $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] = $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']];
                    $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] = $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']];
                    $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']] = $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']];
                    $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']] = $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']];

                }
            }
        }
        
        $this->subCatList[$result['CATCOLUMN']][] = $temp;
    }

    public function exportXls() {
        $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
        $this->objPHPExcel = $objReader->load("exportphps/templates/poswalmart_export_template.xlsx");
        $this->addData($this->catList);
        $this->fileName = "PosTracker.xlsx";
        $this->saveAndDownload();
    }

    public function addData($data) {

        $enabledFilters = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);

        /*[START] ADDING THE FILTERS AT THE TOP */
            /*[START] Time*/
            $colCntr = 2;
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('time-selection', $enabledFilters)){
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $colCntr, "Time Selection");
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0,$colCntr)->getColumn().$colCntr;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CEAF96')));
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                $titleTimeSelection = '';
                if(isset($_REQUEST['FromWeek']) && isset($_REQUEST['ToWeek']) && isset($_REQUEST['FromWeekPrv']) && isset($_REQUEST['ToWeekPrv']))
                    $titleTimeSelection = $_REQUEST['FromWeek']." To ".$_REQUEST['ToWeek']." VS ".$_REQUEST['FromWeekPrv']." To ".$_REQUEST['ToWeekPrv'];
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, $titleTimeSelection);
                $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(1,$colCntr,11,$colCntr);
                
                $productFilterData = $marketFilterData = [];
                if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                    foreach($_REQUEST['FS'] as $ky=>$valDt) {
                        if(!empty($valDt)) {
                            if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                                $dataList = $valDt;
                                if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                    $dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                                }
                                if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                    $productFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                                }else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                    $marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                                }
                            }
                        }
                    }
                }
            $colCntr++;
            }
            /*[END] Time*/

            /*[START] Product*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('product-selection', $enabledFilters)){
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $colCntr, 'Product Filter');
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0,$colCntr)->getColumn().$colCntr;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CEAF96')));
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                if(count($productFilterData)>0){
                    $objRichText = new \PHPExcel_RichText();
                    $lstV = count($productFilterData);
                    $i = 0;
                    foreach ($productFilterData as $kIds => $Val) {
                        $i++;
                        $rn = $objRichText->createTextRun($kIds.' : ');
                        $rn->getFont()->applyFromArray(array("bold"=>true,"color"=>array("rgb" => "0070C0")));
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $rn1 = $objRichText->createTextRun($Val);
                        $rn1->getFont()->applyFromArray(array("bold"=>false,"color"=>array("rgb" => "000000")));
                    }
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, $objRichText);
                    //$this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, implode("\r\n", $productFilterData));
                }else{
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, 'All');
                }
                $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(1,$colCntr,11,$colCntr);
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(1,$colCntr)->getColumn().$colCntr;
                //$this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('horizontal'=>\PHPExcel_Style_Alignment::HORIZONTAL_LEFT,'vertical'=>\PHPExcel_Style_Alignment::VERTICAL_TOP));

                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
                $this->objPHPExcel->getActiveSheet()->getRowDimension($colCntr)->setRowHeight(45);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setWrapText(true);
            $colCntr++;
            }
            /*[END] Procuct*/
            
            /*[START] Market*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('market-selection', $enabledFilters)){
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $colCntr, 'Market Filter');
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0,$colCntr)->getColumn().$colCntr;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CEAF96')));
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                if(count($marketFilterData)>0){
                    $objRichText = new \PHPExcel_RichText();
                    $lstV = count($marketFilterData);
                    $i = 0;
                    foreach ($marketFilterData as $kIds => $Val) {
                        $i++;
                        $rn = $objRichText->createTextRun($kIds.' : ');
                        $rn->getFont()->applyFromArray(array("bold"=>true,"color"=>array("rgb" => "0070C0")));
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $rn1 = $objRichText->createTextRun($Val);
                        $rn1->getFont()->applyFromArray(array("bold"=>false,"color"=>array("rgb" => "000000")));
                    }
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, $objRichText);
                    //$this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, implode("\r\n", $marketFilterData));
                }else{
                        $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, 'All');
                }
                $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(1,$colCntr,11,$colCntr);
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(1,$colCntr)->getColumn().$colCntr;
                //$this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('horizontal'=>\PHPExcel_Style_Alignment::HORIZONTAL_LEFT,'vertical'=>\PHPExcel_Style_Alignment::VERTICAL_TOP));
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
                $this->objPHPExcel->getActiveSheet()->getRowDimension($colCntr)->setRowHeight(45);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setWrapText(true);
            $colCntr++;
            }
            /*[END] Market*/

            /*[START] Measure*/
            $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $colCntr, 'Measure Settings');
            $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0,$colCntr)->getColumn().$colCntr;
            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CEAF96')));
            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $measNameArr = [];
            if(isset($this->measureFilterSettings) && count($this->measureFilterSettings)>0){
                foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                    $key = array_search($measureVal, array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));
                    $measureKey = 'M' . $measureVal;
                    $measure = $this->settingVars->measureArray[$measureKey];

                    if ($key !== false)
                        $measureName = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'];
                    else {
                        $measureName = (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE'];
                    }
                    $measNameArr[] = $measureName;
                }
            }
            $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, implode(',', $measNameArr));
            $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(1,$colCntr,19,$colCntr);
            $colCntr++;
            /*[START] Measure*/
            
            if(isset($_REQUEST['FSG']) && is_array($_REQUEST['FSG']) && count($_REQUEST['FSG'])>0)
            {
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, $colCntr, 'Global Settings');
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0,$colCntr)->getColumn().$colCntr;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CEAF96')));
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            
                foreach($_REQUEST['FSG'] as $ky => $valDt)
                {
                    if(!empty($valDt)) 
                    {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                $dataList = $this->getAllDataFromIds('FSG',$ky,$dataList);
                            }
                            if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $globalFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                $globalFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }
                        }
                    }
                }
               
                if(count($globalFilterData)>0){
                    $objRichText = new \PHPExcel_RichText();
                    $lstV = count($globalFilterData);
                    $i = 0;
                    foreach ($globalFilterData as $kIds => $Val) {
                        $i++;
                        $rn = $objRichText->createTextRun($kIds.' : ');
                        $rn->getFont()->applyFromArray(array("bold"=>true,"color"=>array("rgb" => "0070C0")));
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\r\n";
                        $rn1 = $objRichText->createTextRun($Val);
                        $rn1->getFont()->applyFromArray(array("bold"=>false,"color"=>array("rgb" => "000000")));
                    }
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $colCntr, $objRichText);
                    $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(1,$colCntr,19,$colCntr);
                }
            $colCntr++;    
            }
            
        /*[END] ADDING THE FILTERS AT THE TOP */

        $baseRow = $colCntr + 4;
        $columnsArray = array();
        $columnsArray[] = ['id' => "ACCOUNT", 'format' => ''];
        $colorColomnsArray = array();
        $headerColomnsArray = array();
        
        if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings)){
            foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                $measureKey = 'M' . $measureVal;
                $measure = $this->settingVars->measureArray[$measureKey];
                $count = 1;
                foreach ($this->timeArray as $timekey => $timeval) 
                {
                    $bgStyle      =  $this->gridListAliase[$measure['ALIASE']][1];
                    if($count % 2 == 0){
                        $bgStyle  =  $this->gridListAliase[$measure['ALIASE']][2];
                    }
                    
                    $colorColomnsArray[$timeval.'_TY_'.$measure['ALIASE']] = array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $bgStyle)));
                        $columnsArray[] = ['id' => $timeval.'_TY_'.$measure['ALIASE'],
                                          'format' => '#,##0'
                                          ];

                    $colorColomnsArray[$timeval.'_LY_'.$measure['ALIASE']] = array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $bgStyle)));
                        $columnsArray[] = ['id' => $timeval.'_LY_'.$measure['ALIASE'],
                                           'format' => '#,##0'
                                          ];

                    $colorColomnsArray[$timeval.'_VARP_'.$measure['ALIASE']] = array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $bgStyle)));
                        $columnsArray[] = ['id' => $timeval.'_VARP_'.$measure['ALIASE'],
                                           'format' => '#,##0.0'
                                          ];

                    $colorColomnsArray[$timeval.'_VAR_'.$measure['ALIASE']] = array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $bgStyle)));
                        $columnsArray[] = ['id' => $timeval.'_VAR_'.$measure['ALIASE'],
                                           'format' => '#,##0'
                                          ];

                    $headerColomnsArray[] = "TY";
                    $headerColomnsArray[] = "LY";
                    $headerColomnsArray[] = "%";
                    $headerColomnsArray[] = $this->settingVars->currencySign." - DIFF";

                $count++;
                }
            }
        }

        $catStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "CEAF96"
            )
        );

        $orageBGStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "FFA500"
            )
        );
        
        $greenBGStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "A2BC65"
            )
        );
        
        $lightGreenBGStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "C9D19C"
            )
        );

        $blueBGStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "83BBD3"
            )
        );

        $lightBlueBGStyle = array(
            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array(
                 'rgb' => "8FDBEA"
            )
        );

        $redFontStyle = array(
            'font'  => array(
                'color' => array('rgb' => 'FF0000'),
            )
        );

        $cellAlignmentStyle = array(
            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
        );

        if ($this->settingVars->timeSelectionUnit == 'weekMonth'){
            $arrTitl = ['Last '.$this->gridTimeSelectionUnit,
                        'Last 3 '.$this->gridTimeSelectionUnit.'s',
                        'Last 12 '.$this->gridTimeSelectionUnit.'s',
                        $this->settingVars->groupName.' YTD'];
        }else{
            $arrTitl = ['Last '.$this->gridTimeSelectionUnit,
                        'Last 4 '.$this->gridTimeSelectionUnit.'s',
                        'Last 13 '.$this->gridTimeSelectionUnit.'s',
                        'Last 52 '.$this->gridTimeSelectionUnit.'s',
                        $this->settingVars->groupName.' YTD'];
        }

        $headerColomnsArray = ['TY', 'LY', '%', $this->settingVars->currencySign.'- DIFF'];
        $headerRow = $colCntr++;
        $headerInrrow = $headerRow + 1; 
        $hdInnerRow = $headerRow + 2;

        $col = 1; $headerInrcol = 1; $hdInnercol = 1;
        $colLength = (count($arrTitl) * 4);
        foreach ($this->gridListAliase as $Hvalk =>$colColr) {
            $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $headerRow, $this->arrAliaseMap[$Hvalk]);
            $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow($col,$headerRow,$col+($colLength-1),$headerRow);
            $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($col,$headerRow)->getColumn().$headerRow;
            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $colColr[0]))));
            $tglcls = 1;
            foreach ($arrTitl as $innerColVal) {
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($headerInrcol, $headerInrrow, $innerColVal);
                $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow($headerInrcol,$headerInrrow,$headerInrcol+3,$headerInrrow);
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($headerInrcol,$headerInrrow)->getColumn().$headerInrrow;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $colColr[$tglcls]))));
                $headerInrcol = $headerInrcol + 4;

                foreach ($headerColomnsArray as $headerVal) {
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, $headerVal);
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => str_replace('#', '', $colColr[$tglcls]))));
                    $hdInnercol++;
                }

            if($tglcls == 1){ $tglcls = 2; }else{ $tglcls = 1; }
            }
            $col = $col + $colLength;
        }

        /*$headerRow = 4;
        $col = 1;
        foreach ($headerColomnsArray as $headerVal) {
            $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
            $col++;
        }*/

        $this->objPHPExcel->getActiveSheet()->setCellValue('A1', $this->gridTitle);
        $this->objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->applyFromArray($cellAlignmentStyle);
        $this->objPHPExcel->getActiveSheet()->mergeCellsByColumnAndRow(0,1,$col-1,1);
        
        //$this->objPHPExcel->getActiveSheet()->setCellValue('B2', "Sales ".$this->settingVars->currencySign);
        //$this->objPHPExcel->getActiveSheet()->setCellValue('R3', $this->settingVars->groupName." YTD");
        //$this->objPHPExcel->getActiveSheet()->setCellValue('AL3', $this->settingVars->groupName." YTD");

        if(is_array($data) && !empty($data)){
            foreach ($data as $r => $dataRow) {
                $row = $baseRow + (int) $r;
                $col = 0;

                foreach ($columnsArray as $value) {

                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($col,$row)->getColumn().$row;

                    if($value['id'] != "ACCOUNT"){
                        //$this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, floatval(preg_replace('/[^\d.-]/', '', $dataRow[$value])) );
                        $dataRow[$value['id']] = (float) str_replace(',','', $dataRow[$value['id']]);
                        $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, $dataRow[$value['id']]);
                        $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                        if(isset($value['format']) && !empty($value['format']))
                            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode($value['format']);

                        if($dataRow[$value['id']] < 0 ){
                            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($redFontStyle);
                        }
                    }
                    else{
                        $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, $dataRow[$value['id']]);    
                    }

                    if($dataRow['isCategory'] == 1){
                        $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray($catStyle);
                    }

                    if($dataRow['isTotalRow'] == 1){

                        if($value['id'] == "ACCOUNT"){
                            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray($orageBGStyle);
                        }

                        if(isset($colorColomnsArray[$value['id']]) && !empty($colorColomnsArray[$value['id']])){
                            $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray($colorColomnsArray[$value['id']]);
                        }
                    }
                    
                    $col++;
                }
            }
        }
    }

    public function saveAndDownload() {
        $this->objPHPExcel->setActiveSheetIndex(0);
        $filepath = $this->saveXlsxFileToServer();
        $this->jsonOutput['fileName'] = $filepath;
    }

    public function saveXlsxFileToServer() {
        global $objPHPExcel;
        $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel2007');
        $objWriter->save(getcwd() . DIRECTORY_SEPARATOR . "../zip/" . $this->fileName);
        $filePath = "/zip/" . $this->fileName;
        return $filePath;
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
        $tablejoins_and_filters = parent::getAll();
        return $tablejoins_and_filters;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['groupName'] = $this->settingVars->groupName;
            $this->jsonOutput['lyShowHide'] = $this->getPageConfiguration('show_hide_ly', $this->settingVars->pageID)[0];
        }
        
        $categoryField = strtoupper($this->dbColumnsArray[$this->categoryField]);
        $subCategoryField = strtoupper($this->dbColumnsArray[$this->subCategoryField]);
        $this->categoryName = $this->settingVars->dataArray[$categoryField]['NAME'];
        $this->subCategoryName = $this->settingVars->dataArray[$subCategoryField]['NAME'];
        
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
}
?>