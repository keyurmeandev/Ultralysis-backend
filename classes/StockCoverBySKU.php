<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class stockCoverBySKU extends config\UlConfig {

    /*****
    * go()
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    * $settingVars [project settingsGateway variables]
    * @return $this->jsonOutput with all data that should be sent to client app
    * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->isRelayPlus = false;
        if($this->settingVars->projectType == 2){
        	$this->checkConfiguration();
        	$this->isRelayPlus = true;
        }

        $this->buildDataArray();
        $action = $_REQUEST["action"];
        switch ($action) {
            case "stockCoverBySKUGrid":
                $this->stockCoverBySKUGrid();
				break;
        }
        return $this->jsonOutput;
    }

	/**
	 * stockCoverBySKUGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function stockCoverBySKUGrid() {

		$stockCoverBySKUGridDataBinding = array();
		/*[START] FOR RL+ PROJECT TYPE WE GET THE STORETRANS FROM THE RANGED ITEM TABLE*/
			if ($this->isRelayPlus) {
		        /*
		        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
		        $this->measureFields[] = $this->skuID;
		        $this->measureFields[] = $this->storeID;
				
		        $this->settingVars->useRequiredTablesOnly = true;
		        if (is_array($this->measureFields) && !empty($this->measureFields)) {
		            $this->prepareTablesUsedForQuery($this->measureFields);
		        }
		        $this->queryPart = $this->getAll();
		        */

				$query = "SELECT distinct skuID AS TPNB" .
		                ",StoreTrans " .
		                "FROM " . $this->settingVars->ranged_items .
		                " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;

		        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		        if ($redisOutput === false) {
		            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		            $this->redisCache->setDataForHash($result);
		        } else {
		            $result = $redisOutput;
		        }

		        $storeTransData = array();
		        if (is_array($result) && !empty($result)) {
			        foreach ($result as $key => $row) {
			            $storeTransData[$row['TPNB']] = $row['StoreTrans'];
					}
				}
			}
		/*[END] FOR RL+ PROJECT TYPE WE GET THE STORETRANS FROM THE RANGED ITEM TABLE*/
			
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->skuID;
		$this->measureFields[] = $this->skuName;
		$this->measureFields[] = $this->ohq;
		$this->measureFields[] = $this->storeID;

		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}
		$this->queryPart = $this->getAll();

		$transitField = '';
		if (!$this->isRelayPlus) {
			$transitField = " ,SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END) * stockTra) AS TRANSIT ";
		}

		$query = "SELECT ". $this->skuID . " AS TPNB " .
				",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
				",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALES " .
				",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                ",COUNT(DISTINCT ((CASE WHEN ".$this->settingVars->DatePeriod."= '".filters\timeFilter::$tyDaysRange[0]."' AND ".$this->ohq." > 0 THEN 1 END) * ".$this->storeID. ")) AS STORES_STOCKED ".$transitField.
				"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
				"AND " . filters\timeFilter::$tyWeekRange .
				"GROUP BY TPNB ";

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
					
		if (is_array($result) && !empty($result)) {
			foreach ($result as $value) {
				$index 	= $value['TPNB'];
				$aveDailySales 	= $value['SALES'] / filters\timeFilter::$daysTimeframe;
				if ($aveDailySales != 0) {
					$row 					= array();
					if ($this->isRelayPlus) {
				    	$row['TRANSIT']     = $storeTransData[$index];
				    }else{
				    	$row['TRANSIT']     = $value['TRANSIT'];
				    }
					$dc 					= ($value['SALES'] > 0) ? (($value['STOCK'] + $row['TRANSIT']) / $aveDailySales) : 0;
					$row['SKUID'] 			= $value['TPNB'];
					$row['SKU'] 			= utf8_encode($value['SKU']);
					$row['STOCK'] 			= $value['STOCK'];
					$row['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
					$row['DAYS_COVER'] 		= number_format($dc, 2, '.', '');
                    $row['STORES_STOCKED']  = (int) $value['STORES_STOCKED'];
					array_push($stockCoverBySKUGridDataBinding, $row);
				}
			}

			foreach ($stockCoverBySKUGridDataBinding as $key => $value) {
				$emp[$key] = $value['AVE_DAILY_SALES'];
			}

            if (is_array($emp) && !empty($emp))
			    array_multisort($emp, SORT_DESC, $stockCoverBySKUGridDataBinding);
		}
		
        $this->jsonOutput['stockCoverBySKUGrid'] = $stockCoverBySKUGridDataBinding;
    }
	
    public function checkConfiguration(){
    	if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
    		$this->configurationFailureMessage("Relay Plus TV configuration not found.");

    	$configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();
        return ;
    }

    public function buildDataArray() {

		$skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
        $storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
		$accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
    	
		$configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray(array($skuField, $storeField, $accountField));
        
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;

        $skuFieldPart = explode("#", $skuField);
		$skuField 	  = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
		$skuField  	  = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

		$storeFieldPart = explode("#", $storeField);
		$storeField 	= strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
		$storeField 	= (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

		$accountFieldPart = explode("#", $accountField);
		$accountField 	  = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
		$accountField 	  = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

		$this->skuID   = $this->settingVars->dataArray[$skuField]["ID"];
		$this->skuName = $this->settingVars->dataArray[$skuField]["NAME"];
		$this->storeID = $this->settingVars->dataArray[$storeField]["NAME"];
		$this->ohq 	   = $this->settingVars->dataArray[$accountField]["NAME"];

    	/*$this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];

        $this->storeID 	= key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];

        $this->skuName 	= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->ohq 		= $this->settingVars->dataArray['F12']['NAME'];*/

    }
}
?>