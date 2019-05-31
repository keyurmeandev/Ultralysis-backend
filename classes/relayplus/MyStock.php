<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class MyStock extends config\UlConfig {
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
            case 'filterBySku':
                $this->storeStockGrid();
                break;
            case "skuChange":
                $this->skuSelect();
                break;
            case 'productStockGrid';
                $this->productStockGrid();
				break;
			case 'storeStockGrid';
                $this->storeStockGrid();
                break;
        }
        
        return $this->jsonOutput;
    }
	
	/**
	 * productStockGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function productStockGrid() {
		$this->isStockedStores = false;
    	if (isset($this->settingVars->myStockByProductStockedStoresCol) && !empty($this->settingVars->myStockByProductStockedStoresCol) && $this->settingVars->myStockByProductStockedStoresCol == true) {
			$this->isStockedStores = true;
			$this->jsonOutput['isStockedStores'] = $this->isStockedStores;
		}

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

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
        $arr = array();
		
		if (is_array($result) && !empty($result)) {

			foreach ($result as $key => $row) {
				$vsiStatusData[$row['TPNB']] = $row['VSI'];
				$tsiStatusData[$row['TPNB']] = $row['TSI'];
			}

			$this->settingVars->tableUsedForQuery = $this->measureFields = array();
	        $this->measureFields[] = $this->skuName;
			
	        $this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }
	        $this->queryPart = $this->getAll();

	        $stockedStoresArr = [];
			if ($this->isStockedStores) {
			    $query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID" .
						", COUNT(DISTINCT((CASE WHEN ".$this->ohq." > 0  AND ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->settingVars->maintable.".SNO END))) AS CNTSNO".
						" FROM " . $this->settingVars->tablename . " " . $this->queryPart .
						"GROUP BY SKUID";
			    
			    $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
			    if ($redisOutput === false) {
			        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			        $this->redisCache->setDataForHash($result);
			    } else {
			        $result = $redisOutput;
			    }

			    $stockedStoresArr = array_column($result, 'CNTSNO','SKUID');
			}

	        //MAIN TABLE QUERY
	        $query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID " . 
	                ",".$this->skuName." AS SKU " . 
	                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" . 
	                ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectVolume." ELSE 0 END)) AS SALES" . 
					",".$this->itemStatus." AS ITEMSTATUS " . 
	                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . 
	                "GROUP BY SKUID, SKU, ITEMSTATUS HAVING OHQ > 0 ORDER BY OHQ DESC";

		    $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }

            if (is_array($result) && !empty($result)) {
				foreach ($result as $key => $value) {
					$index = $value['SKUID'];
                    $status = (array_key_exists($index, $vsiStatusData) && $vsiStatusData[$index] != '0') ? 1 : 0;
					if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {
						$dataSet = array();
						$dataSet['SKUID']             = $value['SKUID'];
						$dataSet['SKU']               = $value['SKU'];
		                $dataSet['SALES']             = number_format($value['SALES'], 2, '.', '');				
						$dataSet['OHQ']               = (int)$value['OHQ'];
						$dataSet['ITEMSTATUS']        = $value['ITEMSTATUS'];

						if ($this->isStockedStores)
                    		$dataSet['STOCKED_STORES'] = isset($stockedStoresArr[$value['SKUID']]) ? (double) $stockedStoresArr[$value['SKUID']] : 0;

		                $arr[] = $dataSet;
					}
				}
				$arr = utils\SortUtility::sort2DArray($arr, "OHQ", utils\SortTypes::$SORT_DESCENDING);
			} // end if
		}

        $this->jsonOutput['productStockGrid'] = $arr;
    }

    
	/**
	 * storeStockGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeStockGrid() {
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

			// [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
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
					" FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
					" AND OHQ > 0 ".
					"GROUP BY SKUID, SKU,SNO, OPEN ".$addTerritoryGroup." HAVING OHQ > 0 ORDER BY OHQ DESC";
			
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
					$index = $value['SKUID'] . '_' . $value['SNO'];
                    $status = (array_key_exists($index, $vsiStatusData) && $vsiStatusData[$index] != '0') ? 1 : 0;
					if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {

						if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
							if($_REQUEST["Level"] == '1' && $value['TERRITORY'] == 'NOT CALLED'){
								continue;
							}
							else if($_REQUEST["Level"] == '2' && $value['TERRITORY'] != 'NOT CALLED'){
								continue;
							}
						}

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

						if($value['TERRITORY'])
							$dataSet[$cnt]['TERRITORY'] 	= $value['TERRITORY'];

						$cnt++;
					}
				}
                $dataSet = utils\SortUtility::sort2DArray($dataSet, "OHQ", utils\SortTypes::$SORT_DESCENDING);
			} // end if
		} // end if
		
        $this->jsonOutput['storeStockGrid'] = $dataSet;
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

        if ($_REQUEST['SKU'] != "") {
            $tablejoins_and_filters .= " AND " . $this->settingVars->maintable . ".SIN='" . $_REQUEST['SKU'] . "'";
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
    }
}

?>