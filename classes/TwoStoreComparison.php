<?php
namespace classes;

use projectsettings;
use filters;
use utils;
use db;
use config;

//ini_set ('allow_url_fopen', '1');

class TwoStoreComparison extends config\UlConfig {

    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        //$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll	
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_TwoStoreComparisonPage' : $this->settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
		if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField, $this->storeField));
			$this->buildPageArray();
		} else {
			if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

	        $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['NAME'];
            $this->storeID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
            $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
	    }
		
		$action = $_REQUEST["action"];
        switch ($action) {
            case "changeStore": 
				$this->crossStoreAnalysisGrid();
                break;
            default: 
				$this->getStores();
                break;
        }
        return $this->jsonOutput;
    }
	
	private function getStores()
	{		
        $arr = array();
        $query = "SELECT ".$this->storeID." AS PRIMARY_ID,".$this->storeName." AS PRIMARY_LABEL  FROM ".$this->settingVars->geoHelperTables." ".$this->settingVars->geoHelperLink. " GROUP BY PRIMARY_ID,PRIMARY_LABEL HAVING PRIMARY_LABEL <>'' ORDER BY PRIMARY_LABEL ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if(is_array($result) && !empty($result))
        {
            foreach($result as $row)
            {
                $temp			= array();
                $temp['data']	= $row['PRIMARY_ID'];
                $temp['label']	= $row['PRIMARY_LABEL']." (".$row['PRIMARY_ID'].")";
                $arr[] 			= $temp;
            }			
        }

        $this->jsonOutput['ACCOUNT'] = $arr;
	}
	
    /**
     * crossStoreAnalysisGrid()
     * It will prepare data for SKU trails and driving in selected stores
     * 
     * @param Store fields name value and other filters
     *
     * @return Void
     */
    private function crossStoreAnalysisGrid()
    {        
        $primaryStoreID         = $_REQUEST['primaryStore'];
        $secondaryStoreID       = $_REQUEST['compareStore'];
        $crossStoreAnalysisGrid = array();
				
		$totalSku 				= 0;
		$totalStoreSalesSum		= 0;
		$totalStoreSalesQtySum	= 0;
		
        if (!empty($primaryStoreID) && !empty($secondaryStoreID)) {
        	$this->settingVars->tableUsedForQuery = $this->measureFields = array();
			$this->measureFields[] = $this->storeID;
			$this->measureFields[] = $this->accountID;
			$this->measureFields[] = $this->accountName;
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}

			$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

			$options = array();
	        if (!empty(filters\timeFilter::$tyWeekRange)){
	            $options['tyLyRange']['SALES_0'] = $this->storeID . "= '" . $primaryStoreID . "' AND " .filters\timeFilter::$tyWeekRange;
	            $options['tyLyRange']['SALES_1'] = $this->storeID . "= '" . $secondaryStoreID . "' AND " .filters\timeFilter::$tyWeekRange;
	        }

	        if (!empty(filters\timeFilter::$lyWeekRange)){
	            $options['tyLyRange']['SALES_P0'] = $this->storeID . "= '" . $primaryStoreID . "' AND " .filters\timeFilter::$lyWeekRange;
	            $options['tyLyRange']['SALES_P0'] = $this->storeID . "= '" . $secondaryStoreID . "' AND " .filters\timeFilter::$lyWeekRange;
	        }

	        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
	        $measureSelect = implode(", ", $measureSelect);

			$query = "SELECT ".$this->accountID." AS TPNB " .
					",TRIM(".$this->accountName.") AS SKU " .						
					", ".$measureSelect." ".
					//",SUM((CASE WHEN " . $this->storeID . "= '" . $primaryStoreID . "' AND " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_0 " .
					//",SUM((CASE WHEN " . $this->storeID . "= '" . $primaryStoreID . "' AND " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_P0 " .
					//",SUM((CASE WHEN " . $this->storeID . "= '" . $secondaryStoreID . "' AND " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_1 " .
					//",SUM((CASE WHEN " . $this->storeID . "= '" . $secondaryStoreID . "' AND " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_P1 " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					"AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") AND " .$this->storeID." IN ('".
                    $primaryStoreID."','".$secondaryStoreID."') ".
					"GROUP BY TPNB,SKU HAVING SALES_0 > 0 AND SALES_1 > 0 ORDER BY TPNB DESC";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }            
            
			$crossStoreAnalysisGrid = array();
			if (is_array($result) && !empty($result)) {
				$compareStores = array(
					$primaryStoreID,
					$secondaryStoreID
				);
				foreach ($compareStores as $storeIndex => $storeID) {						
					$result = utils\SortUtility::sort2DArray($result, 'SALES_' . $storeIndex, utils\SortTypes::$SORT_DESCENDING);
					foreach ($result as $key => $value) {							
						$crossStoreAnalysisGrid[$value['TPNB']]['SKUID']               = $value['TPNB'];
						$crossStoreAnalysisGrid[$value['TPNB']]['SKU']                 = utf8_encode($value['SKU']);
						$crossStoreAnalysisGrid[$value['TPNB']]['RANK_'.$storeIndex]   = $key+1;
						$crossStoreAnalysisGrid[$value['TPNB']]['SALES_'.$storeIndex]  = (isset($value['SALES_'.$storeIndex])) ? (float)$value['SALES_'.$storeIndex] : 0;
						$crossStoreAnalysisGrid[$value['TPNB']]['SALES_P'.$storeIndex] = (isset($value['SALES_P'.$storeIndex])) ? (float)$value['SALES_P'.$storeIndex] : 0;
						$crossStoreAnalysisGrid[$value['TPNB']]['VAR_0']			   = ($value['SALES_P0'] > 0) ? ((($value['SALES_0']-$value['SALES_P0'])/$value['SALES_P0'])*100) : 0;
						$crossStoreAnalysisGrid[$value['TPNB']]['VAR_1']			   = ($value['SALES_P1'] > 0) ? ((($value['SALES_1']-$value['SALES_P1'])/$value['SALES_P1'])*100) : 0;
						$crossStoreAnalysisGrid[$value['TPNB']]['VARCHANGE']		   = ceil($crossStoreAnalysisGrid[$value['TPNB']]['VAR_0'] - $crossStoreAnalysisGrid[$value['TPNB']]['VAR_1']);
						$crossStoreAnalysisGrid[$value['TPNB']]['RANKCHANGE']		   = ceil($crossStoreAnalysisGrid[$value['TPNB']]['RANK_1'] - $crossStoreAnalysisGrid[$value['TPNB']]['RANK_0']);
					}
				}
			} // end if
        }		
        $this->jsonOutput['crossStoreAnalysisGrid'] = array_values($crossStoreAnalysisGrid);		
    }
	
	
	public function buildPageArray() {
        $accountFieldPart = explode("#", $this->accountField);
		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);
			$this->jsonOutput['gridColumns']['SKUID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
			$this->jsonOutput['gridColumns']['SKU'] = $this->displayCsvNameArray[$accountFieldPart[0]];
        }

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];
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