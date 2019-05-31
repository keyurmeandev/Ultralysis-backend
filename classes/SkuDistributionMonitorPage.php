<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class SkuDistributionMonitorPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
  
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_SkuDistributionMonitorPage' : $settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->customerField = $this->getPageConfiguration('customer_field', $this->settingVars->pageID)[0];
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];

            $buildDataFields = array($this->accountField, $this->customerField, $this->skuField);
            
            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->fetchConfig(); //ADDING TO OUTPUT
        }


        $action = $_REQUEST["action"];

        switch ($action) {
            case "reload":
                $this->getSkuGainLostData();                
                $this->prepareGridData();
                break;
            case "getBottomGridData":
                $this->getBottomGridData();
                break;
        }

        return $this->jsonOutput;
    }

    function fetchConfig(){
        $accountFieldPart = explode("#", $this->accountField);
        $skuFieldPart = explode("#", $this->skuField);

        $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);

        $this->jsonOutput['gridColumns']['ID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]]; 
        $this->jsonOutput['gridColumns']['PRODUCT'] = $this->displayCsvNameArray[$accountFieldPart[0]]; 
        
        $this->jsonOutput['bottomGridColumns']['SKUID'] = (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]]; ; 
        $this->jsonOutput['bottomGridColumns']['SKUNAME'] = $this->displayCsvNameArray[$skuFieldPart[0]]; 
            
    }

    function getBottomGridData(){
        /*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

        $timeRange = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST['base'],$this->settingVars); // For T4Store
        $timeRangeL4StoreArr = filters\timeFilter::getYearWeekWithinRange($_REQUEST['comparison'],$_REQUEST['base'],$this->settingVars);//For L4Store
        $timeRangeDt = array_merge($timeRange,$timeRangeL4StoreArr);

        $timeRangeT4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRange).") ";
        $timeRangeL4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeL4StoreArr).") ";

        filters\timeFilter::$tyWeekRange = $timeRangeT4Store;
        filters\timeFilter::$lyWeekRange = $timeRangeL4Store;

        if(empty($timeRangeL4StoreArr))
            filters\timeFilter::$lyWeekRange = NULL;
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes    = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->customerName;
        $this->measureFields[] = $this->skuName;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $tablename = $this->settingVars->tablename;
        
        // $this->queryPart .= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeDt).") ";
        $this->queryPart .= " AND ".$this->customerName." = '".$_REQUEST['BANNER']."' ";
        $measureSelect = implode(", ", $measureSelectionArr);

        $query = "SELECT ".
            " ".$this->skuName." AS SKU, ".
            " ".$this->skuID." AS SKUID, ".
            " ".$measureSelect." ".
            " FROM ".$tablename . $this->queryPart . " AND " . $timeRangeT4Store .
            " GROUP BY SKU,SKUID ";
        // echo $query;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $tyresult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($tyresult);
        } else {
            $tyresult = $redisOutput;
        }

        $requiredGridFields = ['SKU','SKUID', $havingTYValue, $havingLYValue];
        $tyresult = $this->redisCache->getRequiredData($tyresult, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $TyArr = [];
        if (is_array($tyresult) && !empty($tyresult)) {
            foreach ($tyresult as $key => $tyData) {
                $gdKy = $tyData['SKUID'].str_replace("'","_",str_replace('"','_',str_replace(' ','', $tyData['SKU'])));
                $tyData['SALES'] = $tyData[$havingTYValue];
                $TyArr[$gdKy] = $tyData;
            }
        }

        $query = "SELECT ".
            " ".$this->skuName." AS SKU, ".
            " ".$this->skuID." AS SKUID, ".
            " ".$measureSelect." ".
            " FROM ".$tablename . $this->queryPart . " AND " . $timeRangeL4Store .
            " GROUP BY SKU,SKUID ";
        // echo $query;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $lyresult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($lyresult);
        } else {
            $lyresult = $redisOutput;
        }

        $requiredGridFields = ['SKU','SKUID', $havingTYValue, $havingLYValue];
        $lyresult = $this->redisCache->getRequiredData($lyresult, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $LyArr = [];
        if (is_array($lyresult) && !empty($lyresult)) {
            foreach ($lyresult as $key => $lyData) {
                $gdKy = $lyData['SKUID'].str_replace("'","_",str_replace('"','_',str_replace(' ','', $lyData['SKU'])));
                $lyData['SALES'] = $lyData[$havingLYValue];
                $LyArr[$gdKy] = $lyData;
            }
        }

        $skuGain = $skuLost = array();
        if(count($TyArr) > 0 && count($LyArr) > 0){
            $skuGain = array_diff_key($TyArr,$LyArr);
            $skuLost = array_diff_key($LyArr,$TyArr);
            $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuGain = array_values($skuGain);
            $skuLost = array_values($skuLost);
        }else 
        if(count($TyArr)>0){
            $skuGain = $TyArr;
            $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuGain = array_values($skuGain);
        }else 
        if(count($LyArr)>0){
            $skuLost = $LyArr;
            $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuLost = array_values($skuLost);
        }
        
        $this->jsonOutput["skuGainGridValue"] = $skuGain;
        $this->jsonOutput["skuLostGridValue"] = $skuLost;
    }



    function getSkuGainLostData(){
        /*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

        $timeRange = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST['base'],$this->settingVars); // For T4Store
        $timeRangeL4StoreArr = filters\timeFilter::getYearWeekWithinRange($_REQUEST['comparison'],$_REQUEST['base'],$this->settingVars);//For L4Store
        $timeRangeDt = array_merge($timeRange,$timeRangeL4StoreArr);

        $timeRangeT4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRange).") ";
        $timeRangeL4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeL4StoreArr).") ";

        filters\timeFilter::$tyWeekRange = $timeRangeT4Store;
        filters\timeFilter::$lyWeekRange = $timeRangeL4Store;

        if(empty($timeRangeL4StoreArr))
            filters\timeFilter::$lyWeekRange = NULL;
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        // $measureSelectRes    = $this->prepareMeasureSelectPart();
        // $this->measureFields = $measureSelectRes['measureFields'];
        // $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        // $havingTYValue       = $measureSelectRes['havingTYValue'];
        // $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->customerName;
        $this->measureFields[] = $this->skuName;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $tablename = $this->settingVars->tablename;
        
        // $this->queryPart .= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeDt).") ";
        // $this->queryPart .= " AND ".$this->customerName." = '".$_REQUEST['BANNER']."' ";
        // $measureSelect = implode(", ", $measureSelectionArr);

        $query = "SELECT " . $this->customerName . " AS CUSTOMER, ".
            " ".$this->skuName." AS SKU, ".
            " ".$this->skuID." AS SKUID ".
            " FROM ".$tablename . $this->queryPart . " AND " . $timeRangeT4Store .
            " GROUP BY CUSTOMER, SKU, SKUID ";
        // echo $query;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $tyresult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($tyresult);
        } else {
            $tyresult = $redisOutput;
        }

        $customerTYLYData = [];
        if (is_array($tyresult) && !empty($tyresult)) {
            foreach ($tyresult as $key => $tyData) {
                $gdKy = $tyData['SKUID'].str_replace("'","_",str_replace('"','_',str_replace(' ','', $tyData['SKU'])));
                $customerTYLYData[$tyData['CUSTOMER']]['TY'][$gdKy] = $tyData;
            }
        }

        $query = "SELECT " . $this->customerName . " AS CUSTOMER, ".
            " ".$this->skuName." AS SKU, ".
            " ".$this->skuID." AS SKUID ".
            " FROM ".$tablename . $this->queryPart . " AND " . $timeRangeL4Store .
            " GROUP BY CUSTOMER, SKU, SKUID ";
        // echo $query;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $lyresult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($lyresult);
        } else {
            $lyresult = $redisOutput;
        }

        if (is_array($lyresult) && !empty($lyresult)) {
            foreach ($lyresult as $key => $lyData) {
                $gdKy = $lyData['SKUID'].str_replace("'","_",str_replace('"','_',str_replace(' ','', $lyData['SKU'])));
                $customerTYLYData[$lyData['CUSTOMER']]['LY'][$gdKy] = $lyData;
            }
        }

        $this->customerGainLostCnt = array();

        if (is_array($customerTYLYData) && !empty($customerTYLYData)) {
            foreach ($customerTYLYData as $customer => $customerData) {
                $skuGainCnt = $skuLostCnt = 0;
                $TyArr = $customerData['TY'];
                $LyArr = $customerData['LY'];
                if(count($TyArr) > 0 && count($LyArr) > 0){
                    $skuGain = array_diff_key($TyArr,$LyArr);
                    $skuLost = array_diff_key($LyArr,$TyArr);
                    $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
                    $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
                    $skuGainCnt = count(array_values($skuGain));
                    $skuLostCnt = count(array_values($skuLost));
                }else 
                if(count($TyArr)>0){
                    $skuGain = $TyArr;
                    $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
                    $skuGainCnt = count(array_values($skuGain));
                }else 
                if(count($LyArr)>0){
                    $skuLost = $LyArr;
                    $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
                    $skuLostCnt = count(array_values($skuLost));
                }

                $this->customerGainLostCnt[$customer]['GAIN'] = $skuGainCnt;
                $this->customerGainLostCnt[$customer]['LOST'] = $skuLostCnt;
            }
        }

        /*$skuGain = $skuLost = array();
        if(count($TyArr) > 0 && count($LyArr) > 0){
            $skuGain = array_diff_key($TyArr,$LyArr);
            $skuLost = array_diff_key($LyArr,$TyArr);
            $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuGain = array_values($skuGain);
            $skuLost = array_values($skuLost);
        }else 
        if(count($TyArr)>0){
            $skuGain = $TyArr;
            $skuGain = utils\SortUtility::sort2DArray($skuGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuGain = array_values($skuGain);
        }else 
        if(count($LyArr)>0){
            $skuLost = $LyArr;
            $skuLost = utils\SortUtility::sort2DArray($skuLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
            $skuLost = array_values($skuLost);
        }
        
        $this->jsonOutput["skuGainGridValue"] = $skuGain;
        $this->jsonOutput["skuLostGridValue"] = $skuLost;*/
    }
 

    function prepareGridData() {
/*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

        $timeRange = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST['base'],$this->settingVars); // For T4Store

        $timeRangeL4Store = $timeRangeL4StoreTmp = filters\timeFilter::getYearWeekWithinRange($_REQUEST['comparison'],$_REQUEST['base'],$this->settingVars);//For L4Store

        $timeRangeDt = array_merge($timeRange,$timeRangeL4Store);
  
        $timeRangeT4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRange).") ";

        $timeRangeL4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeL4Store).") ";

        filters\timeFilter::$tyWeekRange = $timeRangeT4Store;
        filters\timeFilter::$lyWeekRange = $timeRangeL4Store;

        if(empty($timeRangeL4Store))
            filters\timeFilter::$lyWeekRange = NULL;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes    = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

       
        $this->measureFields[] = $this->customerName;
        $this->measureFields[] = $this->skuID;
       

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $tablename = $this->settingVars->tablename;
       
        $this->queryPart .= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeDt).") ";
        $measureSelect = implode(", ", $measureSelectionArr);
        // echo "<pre>";print_r($measureSelect );exit();
                        // "." SUM(DISTINCT(CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->ProjectValue." > 0 AND ".$timeRangeT4Store." THEN 1 END) * ".$this->settingVars->maintable.".".$this->settingVars->ProjectValue.") AS SALES_VALUE_L4, 
        $query ="SELECT ".$this->customerName." AS BANNER, ".
                "COUNT(DISTINCT(CASE WHEN ".$this->settingVars->ProjectVolume." > 0 AND ".$timeRangeT4Store." THEN 1 END) * ".$this->skuID.") AS SALES_VALUE_L4_COUNT, ";

        if(!empty($timeRangeL4StoreTmp)){
            $query .= " COUNT(DISTINCT(CASE WHEN ".$this->settingVars->ProjectVolume." > 0 AND ".$timeRangeL4Store." THEN 1 END) * ".$this->skuID.") AS SALES_VALUE_L4_WEEK_COUNT, ";
        } else {
            $query .= " 0 AS SALES_VALUE_L4_WEEK_COUNT, ";
        }

        $query .= " ".$measureSelect." "." FROM ".$tablename . $this->queryPart ." GROUP BY BANNER ";
                   
        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
  
        $requiredGridFields = ['BANNER', 'SALES_VALUE_L4_WEEK_COUNT', 'SALES_VALUE_L4_COUNT', $havingTYValue, $havingLYValue];
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $result = utils\SortUtility::sort2DArray($result,$havingTYValue, utils\SortTypes::$SORT_DESCENDING);

        $rows = array();
      
        if(isset($result) && !empty($result))
        {
            foreach ($result as $key => $data) {
                
                if(isset($data[$havingTYValue])) {
                    $data['SALES_VALUE_L4'] = $data[$havingTYValue];
                    $data['SALES_VALUE_L4_WEEK'] = $data[$havingLYValue];

                    $data['VAR'] = $data[$havingTYValue] - $data[$havingLYValue];
                    $data['VAR_PER'] = ($data['VAR']/$data[$havingLYValue])*100;
                    
                    $data['L4_SALES_VALUE_L4_WEEK_VAR'] = $data['SALES_VALUE_L4_COUNT'] - $data['SALES_VALUE_L4_WEEK_COUNT'];

                    $data['GAIN_SKU'] = $this->customerGainLostCnt[$data['BANNER']]['GAIN'];
                    $data['LOST_SKU'] = $this->customerGainLostCnt[$data['BANNER']]['LOST'];
                }
                $rows[]=$data;   
            }

        }
        $this->jsonOutput["gridValue"] = $rows;
        $this->jsonOutput['customerCsvName'] = $this->customerCsvName;
    }

    public function buildPageArray() {

        $accountFieldPart = explode("#", $this->accountField);
        
        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $customerField = strtoupper($this->dbColumnsArray[$this->customerField]);
        $this->customerName = $this->settingVars->dataArray[$customerField]['NAME'];
        $this->customerCsvName = $this->settingVars->dataArray[$customerField]['NAME_CSV'];

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuCsvName = $this->settingVars->dataArray[$skuField]['NAME_CSV'];

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