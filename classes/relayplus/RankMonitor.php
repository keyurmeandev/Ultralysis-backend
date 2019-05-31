<?php

namespace classes\relayplus;

use filters;
use utils;
use db;
use config;

class RankMonitor extends config\UlConfig {
	
	private $requestDays,$getLatestDaysDate,$getLastDaysDate, $latestMydate;

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
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$fieldArray = array($this->accountField);
			if($this->allPage){
				$this->bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
				$fieldArray[] = $this->bottomGridField;
			}else{
				$this->byField = $this->getPageConfiguration('by_field', $this->settingVars->pageID)[0];
				$fieldArray[] = $this->byField;
			}

			$this->buildDataArray($fieldArray);
			$this->buildPageArray();
		} else {
			if($this->allPage){
				$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 'RANK_MONITOR_ALL_PAGE' : $this->settingVars->pageName;
			}else{
				$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 'RANK_MONITOR_BY_PAGE' : $this->settingVars->pageName;
			}
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];

            if($this->allPage){
	            $this->gridFieldID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['GRID_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['GRID_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['GRID_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['GRID_FIELD']]['NAME'];
	            $this->gridFieldName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['GRID_FIELD']]['NAME'];
        	}else{
        		$this->gridFieldID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['BY_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['BY_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['BY_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['BY_FIELD']]['NAME'];
	            $this->gridFieldName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['BY_FIELD']]['NAME'];
        	}
	    }
		
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
			if(!$this->allPage){
				$this->getByFieldDataList();
			}
		}

	    $this->msqName = $this->settingVars->dataArray['F14']['NAME'];
	    $this->ohqName = $this->settingVars->dataArray['F12']['NAME'];
		$this->gsqName = $this->settingVars->dataArray['F9']['NAME'];
		$this->ohaqName = $this->settingVars->dataArray['F10']['NAME'];
		$this->baqName = $this->settingVars->dataArray['F11']['NAME'];

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->queryTopPart = $this->getAllGridTop(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

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

		$allSkus	= array();
		foreach($result as $key => $data){
			$temp			= array();
			$temp['data'] 	=  $data['SKUID'];
			$temp['label'] 	= ($data['SKUID'] != $data['SKU']) ? $data['SKU']." #".$data['SKUID'] : $data['SKU'];
			$allSkus[]	= $temp;
		}
		$this->jsonOutput['byFieldDataList'] = $allSkus;
    }
		
    private function rankMonitorGrid() {
		$rankMonitor	= array();
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        if(isset($_REQUEST['SKUID']) && !empty($_REQUEST['SKUID'])) {
			if ($_REQUEST["SKUID"] != "ALL") {
				$addSkuColumn = "," . $this->gridFieldID . " AS TPNB," . $this->gridFieldName . " as SKU ";

				$addSkuGroup = ",TPNB ,SKU";

				$this->measureFields[] = $this->gridFieldID;
        		$this->measureFields[] = $this->gridFieldName;
			}else{
				$addSkuColumn = '';
				$addSkuGroup = '';
			}	

			if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
				$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
				$addTerritoryGroup = ",TERRITORY";

				$this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];
			}
			else
			{
				$addTerritoryColumn = '';
				$addTerritoryGroup = '';
				
			}
			
			$this->measureFields[] = $this->accountID;
        	$this->measureFields[] = $this->accountName;
        	$this->measureFields[] = $this->msqName;

			$this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }
	        $this->queryTopPart = $this->getAllGridTop();

			$query = "SELECT " . $this->accountID . " as SNO " .					
					"," . $this->accountName . " as STORE " .
					$addTerritoryColumn.
					$addSkuColumn.
					",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_0 " .
					",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_1 " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " = '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)* ".$this->msqName." ) AS MSQ " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryTopPart . " ".
					"GROUP BY SNO,STORE ".$addTerritoryGroup." ".$addSkuGroup." ORDER BY SNO DESC";
				
			// echo $query;exit;
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
			if ($redisOutput === false) {
			    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			    $this->redisCache->setDataForHash($result);
			} else {
			    $result = $redisOutput;
			}
					
			if (is_array($result) && !empty($result))
			{			
				$sales	= array('SALES_0','SALES_1');
				foreach($sales as $salesKey => $salesData)
				{				
					$result = utils\SortUtility::sort2DArray($result, 'SALES_' . $salesKey, utils\SortTypes::$SORT_DESCENDING);
					foreach ($result as $key => $data) {					
						$rankMonitor[$data['SNO']]['TPNB']				= ($data['TPNB'] != '') ? $data['TPNB']: '';
						$rankMonitor[$data['SNO']]['SNO']				= $data['SNO'];
						$rankMonitor[$data['SNO']]['SKU']				= ($data['SKU'] != '') ? $data['SKU'] : '';
						$rankMonitor[$data['SNO']]['STORE']				= $data['STORE'];
						$rankMonitor[$data['SNO']]['SALES_'.$salesKey]	= $data['SALES_'.$salesKey];
						$rankMonitor[$data['SNO']]['PERCENT']			= ($data['SALES_1'] > 0) ? number_format((($data['SALES_0']-$data['SALES_1'])/$data['SALES_1'])*100,1,".","") : 0;
						$rankMonitor[$data['SNO']]['RANK_'.$salesKey]	= $key+1;
						$rankMonitor[$data['SNO']]['RANKCHANGE']		= $rankMonitor[$data['SNO']]['RANK_1'] - $rankMonitor[$data['SNO']]['RANK_0'];
						$rankMonitor[$data['SNO']]['RANKCHANGELENGHT']	= strlen($rankMonitor[$data['SNO']]['RANKCHANGE']);
						
						if($data['TERRITORY'])
							$rankMonitor[$data['SNO']]['TERRITORY'] = $data['TERRITORY'];
							
						$rankMonitor[$data['SNO']]['MSQ'] = $data['MSQ'];	
					}
				}
			}
		}
		// print("<pre>");print_r($rankMonitor);exit;
        $this->jsonOutput['rankMonitorGrid'] = array_values($rankMonitor);
        
    }
	
	private function skuSelect() {

        $getLastDaysDate = filters\timeFilter::getLastN14DaysDate($this->settingVars);

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->ohqName;
        $this->measureFields[] = $this->ohaqName;
        $this->measureFields[] = $this->baqName;
        $this->measureFields[] = $this->gsqName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN " . $this->ohqName . ">0 THEN 1 ELSE 0 END)*" . $this->ohqName . ") AS STOCK " .
				",SUM(" . $this->ohaqName .") AS OHAQ " .
				",SUM(" . $this->baqName .") AS BAQ " .    
				",SUM((CASE WHEN " . $this->gsqName . ">0 THEN 1 ELSE 0 END)*" . $this->gsqName . ") AS GSQ " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN (" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY DAY, " . $this->settingVars->DatePeriod . " " .
                "ORDER BY " . $this->settingVars->DatePeriod . " ASC";
		//echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		if ($redisOutput === false) {
		    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		    $this->redisCache->setDataForHash($result);
		} else {
		    $result = $redisOutput;
		}

        $value = array();
		if (is_array($result) && !empty($result)) {
			foreach ($result as $data) {
				$value['SALES'][] = $data['SALES'];
				$value['STOCK'][] = $data['STOCK'];				
				$value['DAY'][]   = $data['DAY'];
				$value['ADJ'][]   = $data['OHAQ']+$data['BAQ'];
				$value['GSQ'][]   = $data['GSQ'];
			}
		}
		
        $this->jsonOutput['skuSelect'] = $value;
    }
	
	private function fetchSkusBySno() {

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->gridFieldID;
        $this->measureFields[] = $this->gridFieldName;
        $this->measureFields[] = $this->msqName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
		$query = "SELECT ".$this->gridFieldID." AS TPNB".
				",".$this->gridFieldName." AS SKU".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_0 " .
				",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " AND " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SALES_1 " .
				",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " = '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)* ".$this->msqName." ) AS MSQ " .
				"FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .				
				"GROUP BY TPNB,SKU ORDER BY TPNB DESC";

		// echo $query;exit;
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		if ($redisOutput === false) {
		    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		    $this->redisCache->setDataForHash($result);
		} else {
		    $result = $redisOutput;
		}

        $value = array();
        $temp = array();
		if (is_array($result) && !empty($result)){
			$sales  = array('SALES_0','SALES_1');
            foreach($sales as $salesKey => $salesData)
            {     
                $result = utils\SortUtility::sort2DArray($result, 'SALES_' . $salesKey, utils\SortTypes::$SORT_DESCENDING);
				foreach ($result as $key => $data) {
				
					$temp[$data['TPNB']]['TPNB']		= $data['TPNB'];
					$temp[$data['TPNB']]['SKU']		= $data['SKU'];
					$temp[$data['TPNB']]['SALES_0']	= $data['SALES_0'];
					$temp[$data['TPNB']]['SALES_1']	= $data['SALES_1'];
					$temp[$data['TPNB']]['VAR']		= $data['SALES_0']-$data['SALES_1'];
					$temp[$data['TPNB']]['VAR_PER']	= ($data['SALES_1'] > 0) ? number_format((($data['SALES_0']-$data['SALES_1'])/$data['SALES_1'])*100,1,'.','') : 0;
					$temp[$data['TPNB']]['MSQ']	= $data['MSQ'];

					 $temp[$data['TPNB']]['SALES_'.$salesKey]  = $data['SALES_'.$salesKey];
                    $temp[$data['TPNB']]['RANK_'.$salesKey]   = $key+1;
                    $temp[$data['TPNB']]['RANKCHANGE']        = $temp[$data['TPNB']]['RANK_1'] - $temp[$data['TPNB']]['RANK_0'];
                    $temp[$data['TPNB']]['RANKCHANGELENGHT']  = strlen($temp[$data['TPNB']]['RANKCHANGE']);
                    $value[$data['TPNB']] = $temp[$data['TPNB']];
				}
			}
		}
        $this->jsonOutput['fetchSkusBySno'] = array_values($value);
    }
	
	/*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/   
    public function getAllGridTop(){
		/*$id			= key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];*/
		$tablejoins_and_filters = "";
		$extraFields = array();
		
		if(isset($_REQUEST['SKUID']) && !empty($_REQUEST['SKUID']) && $_REQUEST['SKUID'] != 'ALL')
		{
			/*$tablejoins_and_filters	.= ' AND ' . $id . " = ".$_REQUEST['SKUID'] ." ";*/
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
		
		if(isset($_REQUEST['PIN']) && !empty($_REQUEST['PIN'])) {
			$extraFields[] = $this->gridFieldID;
			$tablejoins_and_filters	.= ' AND ' . $this->gridFieldID . " = '".$_REQUEST['PIN'] ."' ";
		}

		if(isset($_REQUEST['SNO']) && !empty($_REQUEST['SNO'])) {
			$extraFields[] = $this->accountID;
			$tablejoins_and_filters	.= ' AND ' . $this->accountID . " = '".$_REQUEST['SNO'] ."' ";
		}
		
		$this->prepareTablesUsedForQuery($extraFields);
		$tablejoins_and_filters1	= parent::getAll();
		$tablejoins_and_filters1 	.= $tablejoins_and_filters;

		return $tablejoins_and_filters1;
	}

	public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField);
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

				$this->jsonOutput['bottomGridColumns']['TPNB'] = (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];
		    	if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['bottomGridColumns']['SKU'] = $this->displayCsvNameArray[$gridFieldPart[0]];
			}else{

				$this->jsonOutput['gridColumns']['TPNB'] = (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];

				$this->jsonOutput['gridColumns']['SNO'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
		    	if(count($accountFieldPart) > 1)
		    		$this->jsonOutput['gridColumns']['STORE'] = $this->displayCsvNameArray[$accountFieldPart[0]];

			}
        }
        
        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->skuIdField = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? 
            $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->skuNameField = $this->settingVars->dataArray[$accountField]['NAME'];
        		
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