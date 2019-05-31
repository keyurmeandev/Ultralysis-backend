<?php
namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class Adjustments extends config\UlConfig {
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
            case "adjustmentsGrid":
                $this->adjustmentsGrid();
				break;
        }
        return $this->jsonOutput;
    }
	
	/**
	 * adjustmentsGrid()
     * This Function is used to retrieve list based on requested parameters for [project settingsGateway variables $ohq, $ohaq, $baq] 
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function adjustmentsGrid() {
		
		$adjustmentsGridDataBinding = array();

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        //$lastSalesDays = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $lastSalesDays 		= datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

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

        $tsiStatusData = array();
        $vsiStatusData = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
		
		if (is_array($result) && !empty($result)) {
			foreach ($result as $key => $row) {
				$vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
				$tsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['TSI'];
			}
			
			// For Territory
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
	        $this->measureFields[] = $this->storeID;
	        $this->measureFields[] = $this->storeName;
	        $this->measureFields[] = $this->bannerName;
	        $this->measureFields[] = $this->ohaq;
	        $this->measureFields[] = $this->baq;
	        $this->measureFields[] = $this->msq;
	        $this->measureFields[] = $this->ohq;
	        $this->measureFields[] = $this->settingVars->clusterID;
			
	        $this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }

	        // [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART ]
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
					",TRIM(MAX(" . $this->bannerName . ")) AS BANNER " .
					"," . $this->settingVars->DatePeriod . " AS MYDATE " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
					",SUM(" . $this->ohaq . ") AS OHQ " .
					",SUM(" . $this->baq . ") AS BAQ " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					"AND " . filters\timeFilter::$tyWeekRange .
					"AND ($this->ohaq<>0 OR $this->baq<>0) ".
					"GROUP BY TPNB,SNO,MYDATE ".$addTerritoryGroup." ORDER BY MYDATE DESC";
			
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
							
							$row 				= array();
							$row['SNO'] 		= $value['SNO'];
							$row['STORE'] 		= utf8_encode($value['STORE']);				
							$row['CLUSTER'] 	= utf8_encode($value['CLUSTER']);				
							$row['SKUID'] 		= $value['TPNB'];
							$row['SKU'] 		= utf8_encode($value['SKU']);
							$row['MYDATE'] 		= $value['MYDATE'];
							$row['OHQ'] 		= $value['OHQ'];
							$row['BAQ'] 		= $value['BAQ'];
							$row['STOCK'] 		= $value['STOCK'];
							$row['SHELF'] 		= $value['SHELF'];
							$row['LAST_SALE'] 	= $lastSalesDays[$value['TPNB'] . "_" . $value['SNO']];
							$row['TSIVSI'] 		= $tsiVsi;
							
							if($value['TERRITORY'])
								$row['TERRITORY'] 	= $value['TERRITORY'];

							array_push($adjustmentsGridDataBinding, $row);
					}
				}				

				foreach ($adjustmentsGridDataBinding as $key => $value) {
					//still going to sort by firstname
					$emp[$key] = $value['AVE_DAILY_SALES'];
				}

				if (is_array($emp) && !empty($emp))
                    array_multisort($emp, SORT_DESC, $adjustmentsGridDataBinding);
			
			} // end if
		} // end if
        $this->jsonOutput['adjustmentsGrid'] = $adjustmentsGridDataBinding;
    }
	
	/**
	 * skuSelect()
     * This Function is used to retrieve sku data based on requested parameters for graph [project settingsGateway variables $ohq, $ohaq, $baq]    
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function skuSelect() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->ohq;
        $this->measureFields[] = $this->gsq;
        $this->measureFields[] = $this->ohaq;
        $this->measureFields[] = $this->baq;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT ". $this->settingVars->DatePeriod .", DATE_FORMAT(". $this->settingVars->DatePeriod .",'%a %e %b') AS DAY" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                ",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                // ",SUM((CASE WHEN " . $storeTrans . ">0 THEN 1 ELSE 0 END)*" . $storeTrans . ") AS TRANSIT " .
                ",SUM(" . $this->ohaq . ") AS OHAQ " .
                ",SUM(" . $this->baq . ") AS BAQ " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //"AND " . filters\timeFilter::$tyWeekRange .
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
				// $value['TRANSIT'][] = $data['TRANSIT'];
                $value['TRANSIT'][] = 0;
				$value['DAY'][] 	= $data['DAY'];
				$value['OHAQ'][] 	= $data['OHAQ'];
				$value['BAQ'][] 	= $data['BAQ'];
				$value['ADJ'][] 	= $data['OHAQ']+$data['BAQ'];
				$value['GSQ'][] 	= $data['GSQ'];
			}
		}// end if
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
    	$this->gsq		= $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq 	= $this->settingVars->dataArray['F10']['NAME'];
        $this->baq 		= $this->settingVars->dataArray['F11']['NAME'];
        $this->bannerName = $this->settingVars->dataArray['F4']['NAME'];
    }
}
?>