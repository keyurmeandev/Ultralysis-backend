<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;


class UpliftMonitor extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public $ValueVolume;
    public $backgroundImage;
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->ValueVolume = getValueVolume($settingVars);

        $action = $_REQUEST["action"];

		if ($this->settingVars->isDynamicPage) {
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];     
            $this->backgroundImage = $this->getPageConfiguration('uplift_background_image', $this->settingVars->pageID)[0];            

			$tempBuildFieldsArray = array($this->storeField, $this->skuField);
			
            $this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();
		}else {
            $this->configurationFailureMessage();
        }        
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['measureSelectionList'] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
        }        
        
        $this->isIncludeTerritory = false;
        $this->msqName = $this->settingVars->dataArray['F14']['NAME'];
        if ($this->settingVars->projectType == 2) {
            $this->gsqName = $this->settingVars->dataArray['F9']['NAME'];
            $this->ohaqName = $this->settingVars->dataArray['F10']['NAME'];
            $this->baqName = $this->settingVars->dataArray['F11']['NAME'];
            
            if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "")
                $this->isIncludeTerritory = true;
        }

        switch ($action) {
            case "getBottomGridData":
                $this->getBottomGridData();
                break;
            case "getTopGridData":
                $this->getTopGridData();
                $this->jsonOutput['requestDays'] = count(filters\timeFilter::$tyDaysRange);
                break;                
            case "getChartData":
                $this->getChartData();
                break;
        }

        return $this->jsonOutput;
    }
    
    public function getTopGridData(){
        $gridData = $clusterData = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->settingVars->clusterID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $measureSelect = implode(", ", $measureSelectionArr);
        if(!empty($measureSelect)) $measureSelect =','. $measureSelect;

        $query = "SELECT " . $this->skuIdField . " as SKU_ID " .                    
                "," . $this->settingVars->clusterID . " AS CLUSTER " .
                $measureSelect." ".
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .   
                " GROUP BY SKU_ID,CLUSTER ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $clusterResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($clusterResult);
        } else {
            $clusterResult = $redisOutput;
        }
        
        $requiredGridFields = ['SKU_ID','CLUSTER',$havingTYValue, $havingLYValue];
        $clusterResult = $this->redisCache->getRequiredData($clusterResult, $requiredGridFields);
        if (is_array($clusterResult) && !empty($clusterResult)){
            $clusterResult = \utils\SortUtility::sort2DArray($clusterResult, 'SKU_ID', \utils\SortTypes::$SORT_ASCENDING);
            foreach ($clusterResult as $row) {
                $clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_TY'] = $row[$havingTYValue]; 
                $clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_LY'] = $row[$havingLYValue]; 
            }
        }
        //print_r($clusterData);exit;
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->skuNameField;
        $this->measureFields[] = $this->storeIdField;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();
        $maintable = $this->settingVars->maintable;

        $query = "SELECT " . $this->skuIdField . " as SKU_ID " .                    
            ",MAX(" . $this->skuNameField . ") as SKU_NAME " .
            $measureSelect." ".
            ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->settingVars->stockFieldName." ELSE 0 END)) AS STOCK " .

            ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS LAST_7_DAY_SALES " .
            ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS PREV_7_DAY_SALES " .

            ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS LAST_7_DAY_QTY " .
            ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS PREV_7_DAY_QTY " .
           
            ",COUNT(DISTINCT((CASE WHEN " . $this->settingVars->ProjectVolume . " > 0 AND " . filters\timeFilter::$tyWeekRange . " THEN " . $this->storeIdField . " END))) AS SELL_LAST_7_DAY_COUNT".
            ",COUNT(DISTINCT((CASE WHEN " . $this->settingVars->ProjectVolume . " > 0 AND " . filters\timeFilter::$lyWeekRange . " THEN " . $this->storeIdField . " END))) AS SELL_PREV_7_DAY_COUNT".
         
            " FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .
            " GROUP BY SKU_ID ";
        
        // echo $query; exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

          

        $requiredGridFields = ['SKU_ID','SKU_NAME','CLUSTER','STOCK','TYVOLUME','LYVOLUME','LAST_7_DAY_SALES','LAST_7_DAY_QTY','PREV_7_DAY_SALES','PREV_7_DAY_QTY','SELL_PREV_7_DAY_COUNT','SELL_LAST_7_DAY_COUNT',$havingTYValue, $havingLYValue];
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, 'STOCK','TYVOLUME','LYVOLUME','LAST_7_DAY_SALES','LAST_7_DAY_QTY','PREV_7_DAY_SALES','PREV_7_DAY_QTY','SELL_PREV_7_DAY_COUNT','SELL_LAST_7_DAY_COUNT');

        if (is_array($result) && !empty($result))
        {       
            foreach ($result as $row) {
                $temp = array();
                $temp['SKU_ID'] = $row['SKU_ID'];
                $temp['SKU_NAME'] = $row['SKU_NAME'];
                $temp['SALES_TY'] = number_format($row[$havingTYValue], 0, '.', ',');
                $temp['SALES_LY'] = number_format($row[$havingLYValue], 0, '.', ',');
                $upliftPer = ($row[$havingLYValue] != 0 ) ? ((($row[$havingTYValue]/$row[$havingLYValue])-1)*100) : 0 ;
                $temp['UPLIFT_PER'] = number_format($upliftPer, 1, '.', ',');
                $temp['STOCK'] = number_format($row['STOCK'], 0, '.', ',');
                if(isset($row['LAST_7_DAY_SALES']) && isset($row['LAST_7_DAY_QTY'])){
                    $temp['AVG_PRICE_LAST7_DAYS'] = ($row['LAST_7_DAY_SALES']/$row['LAST_7_DAY_QTY']);
                }else{
                    $temp['AVG_PRICE_LAST7_DAYS'] = 0;
                }
                $temp['AVG_PRICE_LAST7_DAYS'] =  $temp['AVG_PRICE_LAST7_DAYS'];

                if(isset($row['PREV_7_DAY_SALES']) && isset($row['PREV_7_DAY_QTY'])){
                    $temp['AVG_PRICE_PREV7_DAYS'] = ($row['PREV_7_DAY_SALES']/$row['PREV_7_DAY_QTY']);
                }else{
                    $temp['AVG_PRICE_PREV7_DAYS'] = 0;
                }
                $temp['AVG_PRICE_PREV7_DAYS'] =  $temp['AVG_PRICE_PREV7_DAYS'];
                $temp['SELL_PREV_7_DAY_COUNT']= $row['SELL_PREV_7_DAY_COUNT'];
                $temp['SELL_LAST_7_DAY_COUNT']= $row['SELL_LAST_7_DAY_COUNT'];


                $gridData[] = $temp;
            }
        }
        // echo "<pre>";print_r($gridData);
        $this->jsonOutput['topGridData'] = $gridData;
    }

    public function getBottomGridData(){
        $gridData = $clusterData = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->settingVars->clusterID;
        $this->measureFields[] = $this->msqName;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $measureSelect = implode(", ", $measureSelectionArr);
        if(!empty($measureSelect)) $measureSelect =','. $measureSelect;

        $query = "SELECT " . $this->skuIdField . " as SKU_ID " .                    
                "," . $this->settingVars->clusterID . " AS CLUSTER " .
                $measureSelect." ".
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart;

        if($_REQUEST['skuIds'] != "")        
            $query .= " AND ".$this->skuIdField." = '".$_REQUEST['skuIds']."' ";

        $query .= " GROUP BY SKU_ID,CLUSTER";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $clusterResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($clusterResult);
        } else {
            $clusterResult = $redisOutput;
        }
        
        $requiredGridFields = ['SKU_ID','CLUSTER',$havingTYValue, $havingLYValue];
        $clusterResult = $this->redisCache->getRequiredData($clusterResult, $requiredGridFields);
        if (is_array($clusterResult) && !empty($clusterResult)){
            $clusterResult = \utils\SortUtility::sort2DArray($clusterResult, 'SKU_ID', \utils\SortTypes::$SORT_ASCENDING);
            foreach ($clusterResult as $row) {
                $clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_TY'] = $row[$havingTYValue]; 
                $clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_LY'] = $row[$havingLYValue]; 
            }
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->skuNameField;
        $this->measureFields[] = $this->storeIdField;
        $this->measureFields[] = $this->storeNameField;
        $this->measureFields[] = $this->settingVars->clusterID;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $addTerritoryColumn = $addTerritoryGroup = '';
        if($this->isIncludeTerritory){
            $addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
            $addTerritoryGroup = ",TERRITORY";
        }

        $this->queryPart = $this->getAll();
        $measureSelect = implode(", ", $measureSelectionArr);
        if(!empty($measureSelect)) $measureSelect =','. $measureSelect;

        $query = "SELECT " . $this->skuIdField . " as SKU_ID " .
                ",MAX(" . $this->skuNameField . ") as SKU_NAME " .
                "," . $this->storeIdField . " as STORE_ID " .
                ",MAX(" . $this->storeNameField . ") as STORE_NAME " .
                ",MAX(" . $this->settingVars->clusterID . ") AS CLUSTER " .
                $measureSelect." ".$addTerritoryColumn.
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->settingVars->stockFieldName." ELSE 0 END)) AS STOCK " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " = '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)* ".$this->msqName." ) AS MSQ ".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_0 " .
                    ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_1 " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart;

        if($_REQUEST['skuIds'] != "")        
            $query .= " AND ".$this->skuIdField." = '".$_REQUEST['skuIds']."' ";
        
        $query .= "GROUP BY SKU_ID,STORE_ID".$addTerritoryGroup;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = ['SKU_ID','SKU_NAME','STORE_ID','STORE_NAME','CLUSTER','STOCK',$havingTYValue, $havingLYValue,'MSQ','SALES_0','SALES_1','RANK_0','RANK_1'];
        if($this->isIncludeTerritory){
            array_push($requiredGridFields,'TERRITORY');
        }
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, 'STOCK');
        $temp = array();

        if (is_array($result) && !empty($result)){
            $sales  = array('SALES_0','SALES_1');
            foreach($sales as $salesKey => $salesData)
            {     

                $result = utils\SortUtility::sort2DArray($result, 'SALES_' . $salesKey, utils\SortTypes::$SORT_DESCENDING);

                foreach ($result as $key => $row) {
                    

                    $temp[$row['STORE_ID']]['SKU_ID'] = $row['SKU_ID'];
                    $temp[$row['STORE_ID']]['SKU_NAME'] = $row['SKU_NAME'];
                    $temp[$row['STORE_ID']]['STORE_ID'] = $row['STORE_ID'];
                    $temp[$row['STORE_ID']]['STORE_NAME'] = $row['STORE_NAME'];
                    $temp[$row['STORE_ID']]['CLUSTER'] = $row['CLUSTER'];
                    $temp[$row['STORE_ID']]['SALES_TY'] = number_format($row[$havingTYValue], 0, '.', ',');
                    $temp[$row['STORE_ID']]['SALES_LY'] = number_format($row[$havingLYValue], 0, '.', ',');
 
                    $upliftPer = ($row[$havingLYValue] != 0 ) ? ((($row[$havingTYValue]/$row[$havingLYValue])-1)*100) : 0 ;
                    $temp[$row['STORE_ID']]['UPLIFT_PER'] = number_format($upliftPer, 1, '.', ',');

                    $clusterUpliftPer = (isset($clusterData[$row['SKU_ID']][$row['CLUSTER']]) && $clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_LY'] != 0 ) ? ((($clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_TY'] /$clusterData[$row['SKU_ID']][$row['CLUSTER']]['SALES_LY'])-1)*100) : 0 ;
                    $temp[$row['STORE_ID']]['CLUSTER_AVE_UPLIFT_PER'] = number_format($clusterUpliftPer, 1, '.', ',');
                    $deviation = $upliftPer - $clusterUpliftPer;
                    $temp[$row['STORE_ID']]['DEVIATION'] = number_format($deviation, 1, '.', ',');
                    $temp[$row['STORE_ID']]['STOCK'] = number_format($row['STOCK'], 0, '.', ',');
                    
                    $temp[$row['STORE_ID']]['SALES_'.$salesKey]  = $row['SALES_'.$salesKey];
                    $temp[$row['STORE_ID']]['RANK_'.$salesKey]   = $key+1;
                    $temp[$row['STORE_ID']]['RANKCHANGE']        = $temp[$row['STORE_ID']]['RANK_1'] - $temp[$row['STORE_ID']]['RANK_0'];
                    $temp[$row['STORE_ID']]['RANKCHANGELENGHT']  = strlen($temp[$row['STORE_ID']]['RANKCHANGE']);
                    $temp[$row['STORE_ID']]['MSQ'] = $row['MSQ'];
                    if(isset($row['TERRITORY']))
                        $temp[$row['STORE_ID']]['TERRITORY'] = $row['TERRITORY'];
                    $gridData[$row['STORE_ID']] = $temp[$row['STORE_ID']];
                }
            }
            // echo "<pre>";print_r($gridData);exit();
        }
        $this->jsonOutput['isIncludeTerritory'] = $this->isIncludeTerritory;
        $this->jsonOutput['bottomGridData'] = array_values($gridData);
        $this->jsonOutput['backgroundImage'] = $this->backgroundImage ;
    }

    function getChartData() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            if ($this->settingVars->projectType == 2) {
                $this->measureFields[] = $this->ohaqName;
                $this->measureFields[] = $this->baqName;
                $this->measureFields[] = $this->gsqName;
            }
        $this->measureFields[] = $this->settingVars->stockFieldName;
        if (isset($this->settingVars->stockTraFieldName) && !empty($this->settingVars->stockTraFieldName)) {
            $this->measureFields[] = $this->settingVars->stockTraFieldName;
        }
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $options = array();
        if (!empty(filters\timeFilter::$tyDaysRange))
            $options['tyLyRange']['SALES'] = " 1=1 ";

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);
        if(!empty($measureSelect)) $measureSelect =','. $measureSelect;

        $extraFldForRL = '';
        if ($this->settingVars->projectType == 2) {
            $extraFldForRL = ",SUM(" . $this->ohaqName .") AS OHAQ ".
                             ",SUM(" . $this->baqName .") AS BAQ " .    
                             ",SUM((CASE WHEN " . $this->gsqName . ">0 THEN 1 ELSE 0 END)*" . $this->gsqName . ") AS GSQ ";
        }

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                //",SUM(".$this->ValueVolume.") AS SALES " .
                $measureSelect." " .
                ",SUM((CASE WHEN ".$this->settingVars->stockFieldName.">0 THEN 1 ELSE 0 END)* ".$this->settingVars->stockFieldName." ) AS STOCK " .$extraFldForRL.
                ((isset($this->settingVars->stockTraFieldName) && !empty($this->settingVars->stockTraFieldName)) ? ",SUM((CASE WHEN ".$this->settingVars->stockTraFieldName.">0 THEN 1 ELSE 0 END)* ".$this->settingVars->stockTraFieldName." ) AS TRANSIT " : "") .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY DAY " .
                "ORDER BY DAY ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $value = array();
        if (is_array($result) && !empty($result))
        {
            foreach ($result as $data) {
                $value['SALES'][] = $data['SALES'];
                $value['STOCK'][] = $data['STOCK'];
                $value['TRANSIT'][] = (isset($data['TRANSIT'])) ? $data['TRANSIT'] : 0 ;
                $value['DAY'][]   = $data['DAY'];
                if ($this->settingVars->projectType == 2) {
                    $value['ADJ'][]   = $data['OHAQ']+$data['BAQ'];
                    $value['GSQ'][]   = $data['GSQ'];
                }
            }
        }
        
        $this->jsonOutput['chartData'] = $value;
    }
    

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }	
	
	public function buildPageArray() {

        $fetchConfig = false;
        $skuFieldPart = explode("#", $this->skuField);
        $storeFieldPart = explode("#", $this->storeField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['gridColumns']['STORE_ID'] =  (count($storeFieldPart) > 1) ? $this->displayCsvNameArray[$storeFieldPart[1]] : $this->displayCsvNameArray[$storeFieldPart[0]];
            $this->jsonOutput['gridColumns']['STORE_NAME'] =  $this->displayCsvNameArray[$storeFieldPart[0]];

            $this->jsonOutput['gridColumns']['SKU_ID'] =  (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['gridColumns']['SKU_NAME'] =  $this->displayCsvNameArray[$skuFieldPart[0]];
            
        }
       
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuIdField = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];

        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeIdField = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? 
            $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeNameField = $this->settingVars->dataArray[$storeField]['NAME'];
        
        return;
    }
	
    public function getAll() {
        $tablejoins_and_filters = "";

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }

        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '')
        {
            if (isset($_REQUEST["FS"]['TPNB']) && $_REQUEST["FS"]['TPNB'] != '' ){
                $extraFields[] = $this->skuIdField;
                $tablejoins_and_filters .= " AND " . $this->skuIdField . " = '" . $_REQUEST["FS"]['TPNB']."' ";
            }

            if (isset($_REQUEST["FS"]['SNO']) && $_REQUEST["FS"]['SNO'] != '') 
            {
                $extraFields[] = $this->storeIdField;
                $tablejoins_and_filters .= " AND " . $this->storeIdField." = '".$_REQUEST["FS"]['SNO']."' ";
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;
        return $tablejoins_and_filters1;
    }   
}
?>