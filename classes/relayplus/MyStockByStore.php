<?php
namespace classes\relayplus;


use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class MyStockByStore extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();
        $this->buildDataArray();
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll						

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'filterBySno':
                $this->productStockGrid();
                break;
            case "skuChange":
                $this->skuSelect();
                break;
            case 'storeStockGrid';
                $this->storeStockGrid();
				break;
			case 'productStockGrid';
                $this->productStockGrid();
                break;
        }
        
        return $this->jsonOutput;
    }
	
	/**
	 * storeStockGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeStockGrid() {
		
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->settingVars->clusterID;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        //MAIN TABLE QUERY
        $query = "SELECT ".$this->settingVars->maintable.".SNO AS SNO " .
                ",".$this->storeName." AS STORE " .
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" .
                ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectVolume." ELSE 0 END)) AS SALES" .
                ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
                "GROUP BY SNO, STORE, ITEMSTATUS HAVING OHQ > 0 ORDER BY OHQ DESC";
		//echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $arr = array();
		
		if (is_array($result) && !empty($result)) {
			
			foreach ($result as $key => $value) {
				$dataSet = array();
				$dataSet['SNO']     = $value['SNO'];
				$dataSet['STORE']   = $value['STORE'];
                $dataSet['SALES']   = number_format($value['SALES'], 2, '.', '');				
				$dataSet['OHQ']     = (int)$value['OHQ'];
                $dataSet['CLUSTER'] = $value['CLUSTER'];
                $arr[] = $dataSet;
			}
            $arr = utils\SortUtility::sort2DArray($arr, "OHQ", utils\SortTypes::$SORT_DESCENDING);
			
		} // end if

        $this->jsonOutput['storeStockGrid'] = $arr;
    }
	
	/**
	 * storeStockGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function productStockGrid() {
        // get VSI according to all mydate
        $query = "SELECT distinct skuID AS TPNB" .
                ",SNO " .
                ",VSI " .
                ",TSI " .
                "FROM " . $this->settingVars->ranged_items .
                " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $tsiStatusData 	= array();
        $vsiStatusData 	= array();   
        $dataSet 		= array();
		
		if (is_array($result) && !empty($result)) {
			
			foreach ($result as $key => $row) {
				$vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
				$tsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['TSI'];
			}
			
			// For Territory
			if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
				$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
				$addTerritoryGroup = ",TERRITORY";
			}
			else
			{
				$addTerritoryColumn = '';
				$addTerritoryGroup = '';
			}
			
			$this->settingVars->tableUsedForQuery = $this->measureFields = array();
			$this->measureFields[] = $this->skuID;
			$this->measureFields[] = $this->storeID;
			$this->measureFields[] = $this->storeName;
			$this->measureFields[] = $this->skuName;
			$this->measureFields[] = $this->settingVars->clusterID;
			
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}
			
			//[STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
			if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
				$reqLevel = $_REQUEST["Level"]; $_REQUEST["Level"] = '';
					$this->queryPart = $this->getAll();
				$_REQUEST["Level"] = $reqLevel;
			}else{
				$this->queryPart = $this->getAll();
			}
			
			//MAIN TABLE QUERY
			$query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID " .
					",TRIM(" . $this->skuName . ") AS SKU" .
					"," . $this->storeID . " AS SNO " .
					",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
					$addTerritoryColumn.
                    ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectVolume." ELSE 0 END)) AS SALES" .
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
					",(CASE WHEN ". $this->odate ." > ". $this->idate ." THEN 'N' ELSE 'Y' END) AS OPEN" .
					",TRIM(MAX((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END))) AS OHQ" .
                    ",MAX(".$this->itemStatus.") AS ITEMSTATUS " .
					" FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
					" AND OHQ > 0 ".
					"GROUP BY SKUID, SKU,SNO, OPEN ".$addTerritoryGroup." HAVING OHQ > 0 ORDER BY OHQ DESC";
			//echo $query;exit;
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }
			$cnt = 0;
			
			if (is_array($result) && !empty($result)) {
				foreach ($result as $key => $value) {
					if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
						if($_REQUEST["Level"] == '1' && $value['TERRITORY'] == 'NOT CALLED'){
							continue;
						}
						else if($_REQUEST["Level"] == '2' && $value['TERRITORY'] != 'NOT CALLED'){
							continue;
						}
					}

					$index 								= $value['SKUID'] . '_' . $value['SNO'];								
					$dataSet[$cnt]['SKUID']             = $value['SKUID'];
					$dataSet[$cnt]['SKU']               = $value['SKU'];
                    $dataSet[$cnt]['SALES']             = $value['SALES'];
					$dataSet[$cnt]['SNO'] 				= $value['SNO'];
					$dataSet[$cnt]['STORE'] 			= utf8_encode($value['STORE']);
					$dataSet[$cnt]['CLUSTER'] 			= utf8_encode($value['CLUSTER']);
					$dataSet[$cnt]['TSI']               = ($tsiStatusData[$index] == 1) ? 'Y' : 'N';
					$dataSet[$cnt]['VSI']               = ($vsiStatusData[$index] == 1) ? 'Y' : 'N';
					$dataSet[$cnt]['OHQ']               = (int)$value['OHQ'];
					$dataSet[$cnt]['OPEN']              = $value['OPEN'];
					$dataSet[$cnt]['ITEMSTATUS']        = $value['ITEMSTATUS'];
					
					if($value['TERRITORY'])
						$dataSet[$cnt]['TERRITORY'] 	= $value['TERRITORY'];

					$cnt++;
				}
                $dataSet = utils\SortUtility::sort2DArray($dataSet, "OHQ", utils\SortTypes::$SORT_DESCENDING);
			} // end if
		} // end if
		
        $this->jsonOutput['productStockGrid'] = $dataSet;
    }
	
	/**
	 * skuSelect()
     * This Function is used to retrieve sku data based on set parameters for graph     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function skuSelect() {

        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();
	
        $query = "SELECT " . $this->settingVars->DatePeriod . ",  DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                ",SUM(" . $this->settingVars->ProjectValue . ") AS SALES " .
                ",SUM(". $this->ohaq .") AS OHAQ".
                ",SUM(". $this->baq .") AS BAQ".
				",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //" AND " . filters\timeFilter::$tyWeekRange .
                " GROUP BY DAY, ". $this->settingVars->DatePeriod ." " .
                "ORDER BY ". $this->settingVars->DatePeriod ." ASC";
        // echo $query;exit;
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
				$value['SALES'][] 	= $data['SALES'];
				$value['STOCK'][] 	= $data['STOCK'];
                $value['TRANSIT'][] = 0;
				$value['DAY'][] 	= $data['DAY'];
				$value['ADJ'][] 	= $data['OHAQ'] + $data['BAQ'];
				$value['OHAQ'][] 	= $data['OHAQ'];
				$value['GSQ'][] 	= $data['GSQ'];
			}
			
		}

        $this->jsonOutput['skuSelect'] = $value;
    }
	
	/* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
	public function getAll() {
        $tablejoins_and_filters = parent::getAll();
        if ($_REQUEST['SNO'] != "") {
            $tablejoins_and_filters .= " AND " . $this->settingVars->maintable . ".SNO='" . $_REQUEST['SNO'] . "'";
        }
        return $tablejoins_and_filters;
    }

    public function checkConfiguration(){
    	if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
    		$this->configurationFailureMessage("Relay Plus TV configuration not found.");

    	$configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildDataArray() {
		
        $this->storeID 	= key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName 	= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->ohq 		= $this->settingVars->dataArray['F12']['NAME'];
        $this->itemStatus = $this->settingVars->dataArray['F20']['NAME'];      
		$this->odate 		= $this->settingVars->dataArray['F21']['NAME'];
		$this->idate 		= $this->settingVars->dataArray['F22']['NAME'];
		$this->ohaq 	= $this->settingVars->dataArray['F10']['NAME'];
        $this->baq 		= $this->settingVars->dataArray['F11']['NAME'];
        $this->gsq		= $this->settingVars->dataArray['F9']['NAME'];
        $this->msq      = $this->settingVars->dataArray['F14']['NAME']; 
        $this->plandesc = $this->settingVars->dataArray['F6']['NAME'];
    }
}

?>