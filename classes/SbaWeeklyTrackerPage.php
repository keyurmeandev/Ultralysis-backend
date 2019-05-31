<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class SbaWeeklyTrackerPage extends config\UlConfig {

    public $pageName;
    public $gridFields;
    public $countOfGrid;
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

        if ($this->settingVars->isDynamicPage) {
            $this->pageType = $this->getPageConfiguration('default_page_type', $this->settingVars->pageID)[0];
            $this->tableField = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->defaultSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID)[0];

            if($this->defaultSelectedField)
                $tempBuildFieldsArray[] = $this->defaultSelectedField;

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value){
                if (!empty($value) && !in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            }

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "fetchGrid":
                $this->gridData();
                break;
            case "export":
                $this->export();
                break;
        }
        return $this->jsonOutput;
    }

    /*public function buildPageArray() {

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray(array($skuField));

        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;

        $skuFieldPart = explode("#", $skuField);
        $skuField     = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField     = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuID   = $this->settingVars->dataArray[$skuField]["ID"];
        $this->skuName = $this->settingVars->dataArray[$skuField]["NAME"];
        return;
    }*/

    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['defaultSelectedField'] = '';
            $this->jsonOutput['tableField'] = ($this->tableField !== "") ? $this->tableField : $this->settingVars->skutable;
            /*if($this->defaultSelectedField !== ""){
                $dbClmNameVars = strtolower($this->dbColumnsArray[$this->defaultSelectedField]);
                if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['product_settings'])){
                    $product_settings_db_flds = explode('|', $this->queryVars->projectConfiguration['product_settings']);
                    if(!empty($product_settings_db_flds) && count($product_settings_db_flds)>0){
                        foreach ($product_settings_db_flds as $k => $flds) {
                            $fld = explode('#', $flds);
                            $cmpnm = strtolower($this->tableField.'.'.$fld[0]);
                            if($cmpnm == $dbClmNameVars){
                                $this->defaultSelectedField = $this->tableField.'.'.$fld[0];
                                if(isset($fld[1]) && !empty($fld[1]))
                                    $this->defaultSelectedField .='#'.$this->tableField.'.'.$fld[1];
                            }
                        }
                    }
                }
            //$this->jsonOutput['defaultSelectedField'] = $this->settingVars->dataArray[strtoupper($this->defaultSelectedField)]['NAME'];
            $this->jsonOutput['defaultSelectedField'] = $this->defaultSelectedField;
            }*/

            $this->prepareFieldsFromFieldSelectionSettings([$this->tableField]);
            if (isset($this->jsonOutput['fieldSelection']) && count($this->jsonOutput['fieldSelection']) > 0 && $this->defaultSelectedField !== "") {
                foreach ($this->jsonOutput['fieldSelection'] as $key => $val) {
                    $fldArr = explode('#', $val['data']);
                    if($this->defaultSelectedField == $fldArr[0]) {
                        $this->jsonOutput['defaultSelectedField'] = $val['mapField'];
                        return ;
                    }
                }
            }

            $this->jsonOutput['pageType'] = 'SBA Weekly Tracker';
            if($this->pageType == 'customerServiceLevelWeeklyTracker'){
                $this->jsonOutput['pageType'] = 'Customer Service Level Weekly Tracker';
            }else if($this->pageType == 'dotComServiceLevel'){
                $this->jsonOutput['pageType'] = 'Dot.Com Service Level';
            }else if($this->pageType == 'notAvailableGaps'){
                $this->jsonOutput['pageType'] = 'Not Available Gaps';
            }
        }
        return;
    }

    public function gridData($isExport = false) {
        //Getting distinct year nad week form the database
        $query = 'SELECT DISTINCT CONCAT(week,"-",year) AS mydate, week, year FROM '.$this->settingVars->ferreroTescoSbaTable.' WHERE accountID = '.$this->settingVars->aid.' AND GID = '.$this->settingVars->GID.' ORDER BY week ASC, year DESC';
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dateArray = array_unique(array_column($result, 'mydate'));
        $allYearRows = array_unique(array_column($result, 'year'));
        $allWeekColumns = array_unique(array_column($result, 'week'));

        $selectPart = $groupByPart = $selectedField = array();
        //$selectedField = explode("#", $_REQUEST['selectedField']);
        $selField = $_REQUEST['selectedField'];
        $selectedField[] = $this->settingVars->dataArray[$selField]['NAME'];
        if(isset($this->settingVars->dataArray[$selField]['ID']))
            $selectedField[] = $this->settingVars->dataArray[$selField]['ID'];

        $selectPart[] = $selectedField[0] ." AS ACCOUNT";
        $groupByPart [] = "ACCOUNT";
        $this->columnNames = array();
        $this->columnNames["ACCOUNT"] = $this->settingVars->dataArray[$selField]['NAME_CSV'];
        if(count($selectedField) > 1){
            $selectPart[] = $selectedField[1] ." AS ACCOUNT_ID";
            $groupByPart[] = "ACCOUNT_ID";
        }

        if(count($selectedField) > 1){
            $this->columnNames["ACCOUNT"] = $this->settingVars->dataArray[$selField]['NAME_CSV'];
            $this->columnNames["ACCOUNT_ID"] = $this->settingVars->dataArray[$selField]["ID_CSV"];
        }

        $sbaGapsFld = 'sba_gaps';
        $sbaRangedFld = 'sba_ranged';
        if($this->pageType == 'customerServiceLevelWeeklyTracker'){
            $sbaGapsFld = 'lost_sales';
            $sbaRangedFld = 'sales_singles';
        }else if($this->pageType == 'dotComServiceLevel'){
            $sbaGapsFld = 'dotcom_picked';                                                                                                                         
            $sbaRangedFld = 'dotcom_ordered';
        }else if($this->pageType == 'notAvailableGaps'){
            $sbaGapsFld = 'not_available_gaps';
            $sbaRangedFld = 'not_available_gaps';
        }

        $this->queryPart = $this->getAll();
        $query = "SELECT ". implode(",", $selectPart) ." , ".
                 $this->settingVars->ferreroTescoSbaTable.".week as WEEK, ".
                 $this->settingVars->ferreroTescoSbaTable.".year as YEAR, ".
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".".$sbaGapsFld.") AS SBA_GAPS, ". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".".$sbaRangedFld.") AS SBA_RANGED". 
            " FROM ".$this->settingVars->ferreroTescoSbaTable .",".$this->settingVars->skutable.
            " WHERE ".$this->settingVars->ferreroTescoSbaTable.".accountID = ".$this->settingVars->aid.
                    " AND ".$this->settingVars->ferreroTescoSbaTable.".GID = ".$this->settingVars->GID.
                    " AND ".$this->settingVars->ferreroTescoSbaTable.".PIN = ".$this->settingVars->skutable.".PIN ".
                    " AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND ".$this->settingVars->skutable.".GID = ".$this->settingVars->GID.' '.$this->queryPart.
            " GROUP BY ".implode(",", $groupByPart).",WEEK,YEAR ORDER BY WEEK ASC, YEAR DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        if (is_array($result) && !empty($result)) {
            foreach ($result as $seasonalData) {
                $seasonalData['FORMATED_DATE'] = $seasonalData['WEEK'].'-'.$seasonalData['YEAR'];
                //$seasonalData['FORMATED_DATE'] = $seasonalData['WEEK'];
                $seasonalData['SBA_GAPS'] = ($seasonalData['SBA_GAPS'] * 1);
                $seasonalData['SBA_RANGED'] = ($seasonalData['SBA_RANGED'] * 1);

                if($this->pageType == 'customerServiceLevelWeeklyTracker'){
                    $acSum = ($seasonalData['SBA_GAPS'] + $seasonalData['SBA_RANGED']);
                    if ($acSum!=0)
                        $seasonalData['SBA_PERCENTAGE'] = (1 - ($seasonalData['SBA_GAPS'] / $acSum)) * 100;
                    else
                        $seasonalData['SBA_PERCENTAGE'] = 100;
                }else if($this->pageType == 'dotComServiceLevel'){
                    if ($seasonalData['SBA_RANGED']!='' && $seasonalData['SBA_RANGED']!=0)
                        $seasonalData['SBA_PERCENTAGE'] = ($seasonalData['SBA_GAPS'] / $seasonalData['SBA_RANGED']) * 100;
                    else
                        $seasonalData['SBA_PERCENTAGE'] = 100;
                }else if($this->pageType == 'notAvailableGaps'){
                    $seasonalData['SBA_PERCENTAGE'] = $seasonalData['SBA_GAPS'];
                }else{
                    if ($seasonalData['SBA_RANGED']!='' && $seasonalData['SBA_RANGED']!=0)
                        $seasonalData['SBA_PERCENTAGE'] = (1 - ($seasonalData['SBA_GAPS'] / $seasonalData['SBA_RANGED'])) * 100;
                    else
                        $seasonalData['SBA_PERCENTAGE'] = 100;
                }

                $accountNameID = $seasonalData['ACCOUNT'];
                if(count($selectedField) > 1){
                    $accountNameID.= " (".$seasonalData['ACCOUNT_ID'].")";
                }
                $seasonalDataArray[$accountNameID][$seasonalData['FORMATED_DATE']] = $seasonalData;
                //[START] Used to calculate total column
                    $dataPnameSum[$accountNameID] += $seasonalData['SBA_GAPS'];
                //[END] Used to calculate total column
            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        $cmpTotal = [];
        foreach (array_keys($seasonalDataArray) as $account) {
            $cumValue =  0;
            foreach ($allYearRows as $yk => $year) {
                $tmp = array();
                $tmp['ACCOUNT'] = $account;
                $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
                $tmp['YEAR']     = $year;
                $tmp['RANK']     = (int)'1'.$year;
                $tmp['ROWDESC']  = '';
                $tmp['highlightRow'] = 1;
                foreach ($allWeekColumns as $wk => $week) {
                    $tmp['dt'.$week] = 0;
                    $dayWeekYear = $week.'-'.$year;
                    if (isset($seasonalDataArray[$account][$dayWeekYear])) {
                        $data  = $seasonalDataArray[$account][$dayWeekYear];
                        $dtKey = 'dt'.str_replace('-','',$dayWeekYear);
                        $tmp['dt'.$week] = $data['SBA_PERCENTAGE'];

                        if (isset($cmpTotal[$dtKey]['SBA_GAPS']))
                            $cmpTotal[$dtKey]['SBA_GAPS'] += $data['SBA_GAPS'];
                        else
                            $cmpTotal[$dtKey]['SBA_GAPS'] = $data['SBA_GAPS'];

                        if(isset($cmpTotal[$dtKey]['SBA_RANGED']))
                            $cmpTotal[$dtKey]['SBA_RANGED'] += $data['SBA_RANGED'];
                        else
                            $cmpTotal[$dtKey]['SBA_RANGED'] = $data['SBA_RANGED'];
                    }
                }
            $finalData[] = $tmp;
            }
        }

        /*[START] ADDING THE TOTAL COLUMN*/
            $accTotal = [];
            foreach ($cmpTotal as $k1 => $tval){
                $sba_gaps   = ($tval['SBA_GAPS'] * 1);
                $sba_ranged = ($tval['SBA_RANGED'] * 1);

                if($this->pageType == 'customerServiceLevelWeeklyTracker'){
                    $acSum = $sba_gaps + $sba_ranged;
                    if ($acSum != 0) 
                        $accTotal[$k1] = (1 - ($sba_gaps / $acSum)) * 100; 
                    else 
                        $accTotal[$k1] = 100;
                }else if($this->pageType == 'dotComServiceLevel'){
                    if ($sba_ranged != '' && $sba_ranged != 0)
                        $accTotal[$k1] = ($sba_gaps / $sba_ranged) * 100;
                    else
                        $accTotal[$k1] = 100;
                }else if($this->pageType == 'notAvailableGaps'){
                        $accTotal[$k1] = $sba_gaps;
                }else{
                    if ($sba_ranged != '' && $sba_ranged != 0)
                        $accTotal[$k1] = (1 - ($sba_gaps / $sba_ranged)) * 100;
                    else
                        $accTotal[$k1] = 100;
                }
            }
            
            ksort($accTotal);
            if(isset($accTotal)) {
                $dataPnameSum['TOTAL']      = 1;
                foreach ($allYearRows as $yk => $year) {
                    $tmp = array();
                    $tmp['TOTAL']        = 1;
                    $tmp['ACCOUNT']      = 'TOTAL';
                    $tmp['YEAR']         = $year;
                    $tmp['RANK']         = (int)'1'.$year;
                    $tmp['ROWDESC']      = 'TOTAL';
                    $tmp['highlightRow'] = 1;
                    foreach ($allWeekColumns as $wk => $week) {
                        $tmp['dt'.$week] = 0;
                        $dayWeekYear = 'dt'.$week.$year;
                        if (isset($accTotal[$dayWeekYear])) {
                            $tmp['dt'.$week] = $accTotal[$dayWeekYear];
                        }
                    }
                $finalData[] = $tmp;
                }
            }
        /*[END] ADDING THE TOTAL COLUMN*/

        $totalArray = array_column($finalData, 'TOTAL');
        array_multisort($totalArray, SORT_DESC, SORT_NUMERIC, $finalData);

        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }

        if ($isExport) {
            ksort($seasonalDataArray);
            sort($allYearRows);
            return [$seasonalDataArray, array_values($allYearRows), array_values($allWeekColumns), $accTotal];
        } else {
            $this->jsonOutput['gridAllColumnsHeaderYear'] = array_values($allYearRows);
            $this->jsonOutput['gridAllColumnsHeaderWeek'] = array_values($allWeekColumns);
            $this->jsonOutput['gridData'] = $finalData;
            $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
        }
    }

    public function getall(){
        $tablejoins_and_filters = '';
        $this->productFilterData = $this->marketFilterData = [];
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
            foreach($_REQUEST['FS'] as $key=>$data){
                if(!empty($data) && isset($this->settingVars->dataArray[$key])){
                        $filterKey      = !key_exists('ID',$this->settingVars->dataArray[$key]) ? $this->settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
                        if($filterKey=="CLUSTER") {
                            $this->settingVars->tablename    = $this->settingVars->tablename.",".$this->settingVars->clustertable;
                            $tablejoins_and_filters .= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
                        }
                        
                        $dataList = $data;
                        if(isset($this->settingVars->dataArray[$key]['ID'])) {
                            $dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$key]['TYPE'],$key,$dataList);
                        }
                        if($this->settingVars->dataArray[$key]['TYPE'] == 'P') {
                            $this->productFilterData[$this->settingVars->dataArray[$key]['NAME_CSV']] = urldecode($dataList);
                        }else if($this->settingVars->dataArray[$key]['TYPE'] == 'M') {
                            $this->marketFilterData[$this->settingVars->dataArray[$key]['NAME_CSV']] = urldecode($dataList);
                        }
                }
            }
        }
    return $tablejoins_and_filters;
    }

    public function export(){
        list($gridData, $gridAllColumnsHeaderYear, $gridAllColumnsHeaderWeek, $gridDataTotal) = $this->gridData(true);
        $dataHash = '';
        if(is_array($gridData) && count($gridData) > 0){
            $redisCache = new utils\RedisCache($this->queryVars);
            $redisCache->requestHash = $redisCache->prepareQueryHash('sbaWeeklyTrackerReportData');
            $redisCache->setDataForStaticHash([$gridData, $gridAllColumnsHeaderYear, $gridAllColumnsHeaderWeek, $gridDataTotal]);
            $dataHash = $redisCache->requestHash;
        }

        $objRichText = '';
        if(count($this->productFilterData)>0){
            $lstV = count($this->productFilterData);
            $i = 0;
            foreach ($this->productFilterData as $kIds => $Val) {
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
        
        $fileName      = "SBA-Weekly-Tracker-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        if($this->pageType == 'customerServiceLevelWeeklyTracker'){
            $fileName  = "Customer-Service-Level-Weekly-Tracker-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        }else if($this->pageType == 'dotComServiceLevel'){
            $fileName  = "DotCom-Service-Level-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        }else if($this->pageType == 'notAvailableGaps'){
            $fileName  = "Not-Available-Gaps-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        }

        $savePath      = dirname(__FILE__)."/../uploads/Sba-Weekly-Tracker-Report/";
        $imgLogoPath   = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
        $filePath      = $savePath.$fileName;
        $projectID     = $this->settingVars->projectID;
        $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
        $RedisPassword = $this->queryVars->RedisPassword;
        $appliedFiltersTxt = implode('$$', $appliedFilters);
        $appliedFieldName = $this->columnNames['ACCOUNT'];

        /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/SBAWeeklyTrackerReport.pl "'.$filePath.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$dataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'"';*/

        $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/SBAWeeklyTrackerReport.pl "'.$filePath.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$dataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$appliedFieldName.'"');

        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Sba-Weekly-Tracker-Report/".$fileName;
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