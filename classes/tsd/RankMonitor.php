<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class RankMonitor extends config\UlConfig {
	
	private $requestDays,$getLatestDaysDate,$getLastDaysDate;
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->allPage = (isset($_REQUEST['ALL']) && !empty($_REQUEST['ALL'])) ? true : false;

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_RankMonitorPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
			//$fieldArray = array($this->accountField);
            $fieldArray = array();
            
            $tempBuildFieldsArray = array();
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->extraColumns, $this->accountField);
            
			if($this->allPage){
				$this->bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
				$fieldArray[] = $this->bottomGridField;
			}else{
				$this->byField = $this->getPageConfiguration('by_field', $this->settingVars->pageID)[0];
				$fieldArray[] = $this->byField;
			}

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $fieldArray))
                    $fieldArray[] = $value;
                    
			$this->buildDataArray($fieldArray);
			$this->buildPageArray();
		} else {
			$this->configurationFailureMessage();
	    }
		
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
			if(!$this->allPage){
				$this->getByFieldDataList();
			}
		}


        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->jsonOutput['requestDays'] = count(filters\timeFilter::$tyDaysRange);
		
		$action = $_REQUEST["action"];

        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
			case "fetchSkusBySno":
                $this->fetchSkusBySno();
                break;
            case "rankMonitorGrid":
                $this->rankMonitorGrid();
				break;
        }	
        
		return $this->jsonOutput;
    }

    private function getByFieldDataList(){
    	$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->gridFieldID;
        $this->measureFields[] = $this->gridFieldName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

    	$query = "SELECT DISTINCT ". $this->gridFieldID . " AS SKUID" .
            ", TRIM(" . $this->gridFieldName . ") AS SKU".
            " FROM ". $this->settingVars->tablename . $this->queryPart .
			" GROUP BY SKUID, SKU ORDER BY SKU ASC";		
		//echo $query;exit;
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		$allSkus = array();
		foreach($result as $key => $data){
			$temp			= array();
			$temp['data'] 	= $data['SKUID'];
			$temp['label'] 	= ($data['SKUID'] != $data['SKU'] ) ? $data['SKU']." #".$data['SKUID'] : $data['SKU']; 
			$allSkus[]	= $temp;
		}
		$this->jsonOutput['byFieldDataList'] = $allSkus;
    }
		
    private function rankMonitorGrid() {

		$rankMonitor	= array();
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		
		if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
			$addTerritoryGroup = ",TERRITORY";
			$this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];
		}else{
			$addTerritoryColumn = '';
			$addTerritoryGroup = '';
		}
		
        foreach ($this->extraColumns as $key => $data) {
            $selectPart[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$data])]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$data])]['NAME_ALIASE'] . "";
            $groupByPart[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$data])]['NAME_ALIASE'];
            $this->measureFields[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$data])]['NAME'];
        }
        
        if(isset($_REQUEST['SKUID']) && !empty($_REQUEST['SKUID'])){	
			if ($_REQUEST["SKUID"] != "ALL") {
				$addSkuColumn = "," . $this->gridFieldID . " AS TPNB," . $this->gridFieldName . " as SKU ";
				$addSkuGroup = ",TPNB ,SKU";
				$this->measureFields[] = $this->gridFieldID;
        		$this->measureFields[] = $this->gridFieldName;
			}
			else
			{
				$addSkuColumn = '';
				$addSkuGroup = '';
			}
			
			$this->measureFields[] = $this->accountID;
        	$this->measureFields[] = $this->accountName;

        	$this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }
	        $this->queryTopPart = $this->getAllGridTop();
			
			$query = "SELECT " . $this->accountID . " as SNO " .					
					"," . $this->accountName . " as STORE " .
					//",TRIM(MAX(" . $storeDistrict . ")) AS STOREDISTRICT " .
					$addTerritoryColumn.
					$addSkuColumn;

            if(is_array($selectPart) && !empty($selectPart))
                $query .= ",".implode(",", $selectPart);

            $query .= ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange ." AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_0 " .
					",SUM((CASE WHEN " . ((filters\timeFilter::$lyWeekRange != "") ? filters\timeFilter::$lyWeekRange." AND " : "") . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_1 " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryTopPart . " " .					
					"GROUP BY SNO,STORE ".$addTerritoryGroup." ".$addSkuGroup;

            if(is_array($groupByPart) && !empty($groupByPart))                    
                $query .= ", ".implode(",", $groupByPart);

            $query .= " ORDER BY SNO DESC";
			//echo $query;exit;
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }

			//$rankMonitor	= array();
			if (is_array($result) && !empty($result)){
				$sales	= array('SALES_0','SALES_1');
				foreach($sales as $salesKey => $salesData){

					$result = utils\SortUtility::sort2DArray($result, 'SALES_' . $salesKey, utils\SortTypes::$SORT_DESCENDING);
					foreach ($result as $key => $data) {					
						$rankMonitor[$data['SNO']]['TPNB']				= ($data['TPNB'] != '') ? $data['TPNB']: '';
						$rankMonitor[$data['SNO']]['SNO']				= $data['SNO'];
						$rankMonitor[$data['SNO']]['SKU']				= ($data['SKU'] != '') ? $data['SKU'] : '';
						$rankMonitor[$data['SNO']]['STORE']				= $data['STORE'];
						//$rankMonitor[$data['SNO']]['STOREDISTRICT']		= $data['STOREDISTRICT'];
						$rankMonitor[$data['SNO']]['SALES_'.$salesKey]	= $data['SALES_'.$salesKey];
						$rankMonitor[$data['SNO']]['PERCENT']			= ($data['SALES_1'] > 0) ? number_format((($data['SALES_0']-$data['SALES_1'])/$data['SALES_1'])*100,1,".","") : 0;
						$rankMonitor[$data['SNO']]['RANK_'.$salesKey]	= $key+1;
						$rankMonitor[$data['SNO']]['RANKCHANGE']		= $rankMonitor[$data['SNO']]['RANK_1'] - $rankMonitor[$data['SNO']]['RANK_0'];
						$rankMonitor[$data['SNO']]['RANKCHANGELENGHT']	= strlen($rankMonitor[$data['SNO']]['RANKCHANGE']);
						
                        foreach ($this->extraColumns as $key => $subdata) {
                            $rankMonitor[$data['SNO']][$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$subdata])]['NAME_ALIASE']] = $data[$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$subdata])]['NAME_ALIASE']];
                        }
                        
						if($data['TERRITORY'])
							$rankMonitor[$data['SNO']]['TERRITORY'] = $data['TERRITORY'];
					}
				}
			} // end if		
		}
		//print("<pre>");print_r($rankMonitor);exit;
        $this->jsonOutput['rankMonitorGrid'] = array_values($rankMonitor);
    }
	
	private function skuSelect() {

        $getLastDaysDate = filters\timeFilter::getLastN14DaysDate($this->settingVars);
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN stock>0 THEN 1 ELSE 0 END)*stock) AS STOCK " .
                ",SUM((CASE WHEN stockTra>0 THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN (" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY DAY " .
                "ORDER BY DAY ASC";
		//echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $value = array();
		if (is_array($result) && !empty($result)){
			foreach ($result as $data) {
				$value['SALES'][] = $data['SALES'];
				$value['STOCK'][] = $data['STOCK'];
				$value['TRANSIT'][] = $data['TRANSIT'];
				$value['DAY'][] = $data['DAY'];
			}
		}
        $this->jsonOutput['skuSelect'] = $value;
    }
	
	private function fetchSkusBySno() {
		
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->gridFieldID;
        $this->measureFields[] = $this->gridFieldName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll();
		        
        $query = "SELECT ".$this->gridFieldID." AS TPNB" .
				",".$this->gridFieldName." AS SKU".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_0 ";
                
        if(filters\timeFilter::$lyWeekRange != "")
            $query .= ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_1 ";
        else
            $query .= ", 0 AS SALES_1 ";

        $query .= "FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .				
				"GROUP BY TPNB,SKU ORDER BY TPNB DESC";
		//echo $query;exit;
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
        	$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $value = array();
		if (is_array($result) && !empty($result)){
			foreach ($result as $data) {
				$temp 				= array();
				$temp['TPNB']		= $data['TPNB'];
				$temp['SKU']		= $data['SKU'];
				$temp['SALES_0']	= $data['SALES_0'];
				$temp['SALES_1']	= $data['SALES_1'];
				$temp['VAR']		= $data['SALES_0']-$data['SALES_1'];
				$temp['VAR_PER']	= ($data['SALES_1'] > 0) ? number_format((($data['SALES_0']-$data['SALES_1'])/$data['SALES_1'])*100,1,'.','') : 0;
				$value[]			= $temp;
			}
		}
        $this->jsonOutput['fetchSkusBySno'] = $value;
    }

    public function getAllGridTop(){
		$tablejoins_and_filters = "";
		$extraFields = array();
		
		if(isset($_REQUEST['SKUID']) && !empty($_REQUEST['SKUID']) && $_REQUEST['SKUID'] != 'ALL')
		{
			$extraFields[] = $this->gridFieldID;
			$tablejoins_and_filters	.= ' AND ' . $this->gridFieldID . " = '".$_REQUEST['SKUID'] ."' ";
		}
		
		$this->prepareTablesUsedForQuery($extraFields);
		$tablejoins_and_filters1	= parent::getAll();
		$tablejoins_and_filters1 	.= $tablejoins_and_filters;

		return $tablejoins_and_filters1;
	}
	
	/*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/   
    public function getAll(){
		
		$tablejoins_and_filters = "";
		$extraFields = array();
		
		if(isset($_REQUEST['PIN']) && !empty($_REQUEST['PIN']) )
		{
			$extraFields[] = $this->gridFieldID;
			$tablejoins_and_filters	.= ' AND ' . $this->gridFieldID . " = '".$_REQUEST['PIN'] ."' ";
		}

		if(isset($_REQUEST['SNO']) && !empty($_REQUEST['SNO']) )
		{
			$extraFields[] = $this->accountID;
			$tablejoins_and_filters	.= ' AND ' . $this->accountID . " = '".$_REQUEST['SNO'] ."' ";
		}
		
		$this->prepareTablesUsedForQuery($extraFields);
		$tablejoins_and_filters1	= parent::getAll();
		$tablejoins_and_filters1 	.= $tablejoins_and_filters;

		return $tablejoins_and_filters1;
	}

	public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField[0]);

		$this->gridField = ($this->allPage) ? $this->bottomGridField : $this->byField;        	
        
        $gridFieldPart = explode("#", $this->gridField);

    	$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);

			if($this->allPage){
				
				$this->jsonOutput['topGridColumns']['SNO'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
		    	if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['topGridColumns']['STORE'] = $this->displayCsvNameArray[$accountFieldPart[0]];
                    
                foreach ($this->extraColumns as $key => $value) {
                    $this->jsonOutput['topGridColumns'][$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$value])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$value])]['NAME_CSV'];
                }
                
				$this->jsonOutput['bottomGridColumns']['TPNB'] = (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];
		    	if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['bottomGridColumns']['SKU'] = $this->displayCsvNameArray[$gridFieldPart[0]];
			}else{

				$this->jsonOutput['gridColumns']['TPNB'] = (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];

				if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['gridColumns']['SKU'] = $this->displayCsvNameArray[$gridFieldPart[0]];

                foreach ($this->extraColumns as $key => $value) {
                    $this->jsonOutput['gridColumns'][$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$value])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$value])]['NAME_CSV'];
                }                    
                    
				$this->jsonOutput['gridColumns']['SNO'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
		    	if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['gridColumns']['STORE'] = $this->displayCsvNameArray[$accountFieldPart[0]];

			}
        }
        
        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        		
        $gridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $gridField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $this->gridFieldID = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->gridFieldName = $this->settingVars->dataArray[$gridField]['NAME'];

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