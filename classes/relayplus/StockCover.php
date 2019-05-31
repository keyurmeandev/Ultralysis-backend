<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class StockCover extends config\UlConfig {
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

        $action = $_REQUEST["action"];
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
            case "stockCoverGrid":
                $this->stockCoverGrid();
				break;
        }

        return $this->jsonOutput;
    }

	/**
	 * stockCoverGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function stockCoverGrid() {
		$stockCoverGridDataBinding = array();
		
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();		
		
        //$lastSalesDays 		= datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $lastSalesDays 		= datahelper\Common_Data_Fetching_Functions::getLastSalesDays($this->settingVars->maintable.".SIN", $this->settingVars->maintable.".SNO", $this->settingVars, $this->queryPart);
		
		// get VSI according to all mydate
        $query = "SELECT distinct skuID AS TPNB" .
                ",SNO " .
                ",VSI " .
                ",TSI " .
                ",StoreTrans " .
                ",StoreOrder " .
                ",StoreWhs " .
                "FROM " . $this->settingVars->ranged_items .
                " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $tsiStatusData = $vsiStatusData = $storeTransData = $storeOrderData = $storeWhsData = array();
		
		if (is_array($result) && !empty($result)) {
			
			$this->settingVars->tableUsedForQuery = $this->measureFields = array();

			foreach ($result as $key => $row) {
				$vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
				$tsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['TSI'];
                $storeTransData[$row['TPNB'] . '_' . $row['SNO']] = $row['StoreTrans'];
                $storeOrderData[$row['TPNB'] . '_' . $row['SNO']] = $row['StoreOrder'];
                $storeWhsData[$row['TPNB'] . '_' . $row['SNO']] = $row['StoreWhs'];
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
			
			
			$this->measureFields[] = $this->skuID;
			$this->measureFields[] = $this->skuName;
			$this->measureFields[] = $this->storeName;
			$this->measureFields[] = $this->planogram;
			
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
			
			$query = "SELECT "
					. $this->skuID . " AS TPNB " .
					",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
					"," . $this->storeID . " AS SNO " .
					",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
					$addTerritoryColumn.
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .                
					",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALES " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
					",TRIM(MAX(" . $this->planogram . ")) AS PLANOGRAM " .
					",MAX(" . $this->tsi . ") AS TSI " .
					",MAX(" . $this->vsi . ") AS VSI " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					"AND " . filters\timeFilter::$tyWeekRange .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup;
			// echo $query; exit;
			
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }
						
			if (is_array($result) && !empty($result)) {
				
				foreach ($result as $value) {
					
					$index 	= $value['TPNB'] . '_' . $value['SNO'];
					$status = array_key_exists($index, $vsiStatusData) ? 1 : 0;
					if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {

						if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
							if($_REQUEST["Level"] == '1' && $value['TERRITORY'] == 'NOT CALLED'){
								continue;
							}
							else if($_REQUEST["Level"] == '2' && $value['TERRITORY'] != 'NOT CALLED'){
								continue;
							}
						}

						if ($tsiStatusData[$index] == 1 && $vsiStatusData[$index] == 1)
							$tsiVsi = "Y/Y";
						else if ($tsiStatusData[$index] == 1 && $vsiStatusData[$index] == 0)
							$tsiVsi = "Y/N";
						else if ($tsiStatusData[$index] == 0 && $vsiStatusData[$index] == 1)
							$tsiVsi = "N/Y";
						else
							$tsiVsi = "N/N";
						
						$aveDailySales 	= $value['SALES'] / filters\timeFilter::$daysTimeframe;
						$indexLostValue = $value['TPNB'] . $value['CLUSTER'];
						
						if ($aveDailySales != 0) {
							
							$row 					= array();
							$dc 					= ($value['SALES'] > 0) ? (($value['STOCK'] + $value['TRANSIT']) / $aveDailySales) : 0;
                            $LEVEL_OF_FILL          = ($value['SHELF'] > 0) ? ($value['STOCK']/$value['SHELF'])*100 : 0;
							$row['SNO'] 			= $value['SNO'];
							$row['STORE'] 			= utf8_encode($value['STORE']);
							$row['CLUSTER'] 		= utf8_encode($value['CLUSTER']);
							$row['SKUID'] 			= $value['TPNB'];
							$row['SKU'] 			= utf8_encode($value['SKU']);
							$row['STOCK'] 			= $value['STOCK'];
                            $row['TRANSIT']         = $storeTransData[$index];
                            $row['STORE_ORDER']     = $storeOrderData[$index];
                            $row['STORE_WHS']       = $storeWhsData[$index];
							$row['SHELF'] 			= $value['SHELF'];
							$row['LOST'] 			= number_format($lostValueArray[$indexLostValue], 2, '.', '');
							$row['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
							$row['DAYS_COVER'] 		= number_format($dc, 2, '.', '');
                            $row['LEVEL_OF_FILL']   = number_format($LEVEL_OF_FILL, 1, '.', '');
							$row['LAST_SALE'] 		= $lastSalesDays[$row['SKUID'] . "_" . $row['SNO']];
							$row['PLANOGRAM'] 		= ($value['PLANOGRAM'] == null) ? '' : $value['PLANOGRAM'];
							$row['TSIVSI'] 			= $tsiVsi;
							
							if($value['TERRITORY'])
								$row['TERRITORY'] = $value['TERRITORY'];

							array_push($stockCoverGridDataBinding, $row);
						}						
					}
				}

				foreach ($stockCoverGridDataBinding as $key => $value) {
					//still going to sort by firstname
					$emp[$key] = $value['AVE_DAILY_SALES'];
				}

                if (is_array($emp) && !empty($emp))
				    array_multisort($emp, SORT_DESC, $stockCoverGridDataBinding);
			
			}
		
		}
		
        $this->jsonOutput['stockCoverGrid'] = $stockCoverGridDataBinding;
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
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                ",SUM(". $this->ohaq .") AS OHAQ".
                ",SUM(". $this->baq .") AS BAQ".
				",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //" AND " . filters\timeFilter::$tyWeekRange .
                " GROUP BY DAY, ". $this->settingVars->DatePeriod ." " .
                "ORDER BY ". $this->settingVars->DatePeriod ." ASC";
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

    public function checkConfiguration(){

    	if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
    		$this->configurationFailureMessage("Relay Plus TV configuration not found.");

    	$configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildDataArray() {
    	$this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID 	= key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName 	= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->catName 	= $this->settingVars->dataArray['F1']['NAME'];	
        $this->ohq 		= $this->settingVars->dataArray['F12']['NAME'];
        $this->storeTrans = $this->settingVars->dataArray['F13']['NAME'];
        $this->msq 		= $this->settingVars->dataArray['F14']['NAME'];
        $this->planogram= $this->settingVars->dataArray['F6']['NAME'];
        $this->tsi	 	= $this->settingVars->dataArray['F7']['NAME'];
        $this->vsi 		= $this->settingVars->dataArray['F8']['NAME'];
    	$this->gsq		= $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq 	= $this->settingVars->dataArray['F10']['NAME'];
        $this->baq 		= $this->settingVars->dataArray['F11']['NAME'];
    }
}
?>