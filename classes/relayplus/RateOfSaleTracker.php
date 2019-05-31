<?php

namespace classes\relayplus;

use datahelper;
use filters;
use db;
use config;

class RateOfSaleTracker extends config\UlConfig {
	
	private $ytdTyWeekRange,$ytdLyWeekRange;
	
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

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_RateOfSaleTrackerPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->storeCountField = $this->getPageConfiguration('store_count_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField, $this->storeCountField));
			$this->buildPageArray();
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                $hidableCols = array('cash_ros' => false, 'qty_ros' => false, 'ave_price' => false, 'share_per' => false);
                $this->hideGridCols = $this->getPageConfiguration('rate_of_sale_tracker_grid_hide_column_settings', $this->settingVars->pageID);
                if(is_array($this->hideGridCols) && count($this->hideGridCols) > 0 && $this->hideGridCols[0] != "")
                {
                    foreach($this->hideGridCols as $col)
                    {
                        if(array_key_exists($col, $hidableCols))
                            $hidableCols[$col] = true;
                    }        
                }
                $this->jsonOutput['hidableCols'] = $hidableCols;
            }
		} else {
			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 'RATE_OF_SALE_TRACKER_PAGE' : $this->settingVars->pageName;
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->storeCount = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['NAME'];
	    }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$this->ytdTyWeekRange = filters\timeFilter::$tyWeekRange;
		$this->ytdLyWeekRange = filters\timeFilter::$lyWeekRange;
		
		$this->ValueVolume = getValueVolume($this->settingVars);

		$action = $_REQUEST['action'];
		
		switch ($action) {
			case 'rateOfSaleTrackerGrid':
				$this->rateOfSaleTrackerGrid();
				break;
		}
		
        return $this->jsonOutput;
    }
	
	/**
	 * rateOfSaleTrackerGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function rateOfSaleTrackerGrid() {
		$arr = array();		
        /*[PART 1] GETTING THE STORE COUNT DATA*/
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->storeCount;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
		$getStoreData = $this->storeCountData($this->accountID,$this->storeCount);

		/*[PART 2] GETTING THE GRID DATA*/
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
    	$measureSelectRes = $this->prepareMeasureSelectPart();
		$this->measureFields = $measureSelectRes['measureFields'];

		$measureSelectionArr = $measureSelectRes['measureSelectionArr'];
		$havingTYValue 		 = $measureSelectRes['havingTYValue'];
		$havingLYValue 		 = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        /*$options = array();
        if (!empty($this->ytdTyWeekRange))
            $options['tyLyRange']['SALES'] = $this->ytdTyWeekRange;

        if (!empty($this->ytdLyWeekRange))
            $options['tyLyRange']['LYSALES'] = $this->ytdLyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);*/

		$measureSelect = implode(", ", $measureSelectionArr);
        $query = "SELECT ".$this->accountID." as SKUID" .
				",".$this->accountName." as SKU".
				",".$measureSelect." ".
                ",SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") as QTY ".
                ",SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") as QTY_LY ".
                ",SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") as VAL_TY ".
				",SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") as VAL_LY ".
                "FROM " . $this->settingVars->tablename." ".$this->queryPart.
				" AND (" . $this->ytdTyWeekRange . " OR " . $this->ytdLyWeekRange . ") ".
				"GROUP BY SKUID, SKU ";
		
		//HAVING (SALES > 0 AND QTY > 0) ORDER BY SALES DESC
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }


		$requiredGridFields = ['SKUID','SKU','QTY','QTY_LY','VAL_TY','VAL_LY',$havingTYValue, $havingLYValue];
		$result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue);
		
		if (is_array($result) && !empty($result)) {

			$result = \utils\SortUtility::sort2DArray($result, $havingTYValue, \utils\SortTypes::$SORT_DESCENDING);	
			//$total = array_sum(array_column($result,'SALES'));
            //$totalSALESLY = array_sum(array_column($result,'LYSALES'));
			
            // $total = array_sum(array_column($result,$havingTYValue));
            // $totalSALESLY = array_sum(array_column($result,$havingLYValue));
            
            $total = $totalSALESLY = 0;
            $resultFn = [];
            foreach($result as $data){
                if(round($data[$havingTYValue]) > 0 && $data['QTY'] > 0){
                    $total += $data[$havingTYValue];
                    $totalSALESLY += $data[$havingLYValue];
                    $resultFn[] = $data;
                }
            }

			foreach($resultFn as $data){
				if(round($data[$havingTYValue]) > 0 && $data['QTY'] > 0){
                    $data[$havingTYValue] = round($data[$havingTYValue]);
                    $data[$havingLYValue] = round($data[$havingLYValue]);
                    
					$var = $data[$havingLYValue] != 0 ? ((($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue]) * 100) : 0;
	                $share = $total != 0 ? (($data[$havingTYValue] / $total) * 100) : 0;
	                $shareLY = $totalSALESLY != 0 ? (($data[$havingLYValue] / $totalSALESLY) * 100) : 0;

					$temp = array();
					$temp['SKUID'] 		= $data['SKUID'];
					$temp['SKU'] 		= $data['SKU'];
					$temp['SALES'] 		= $data[$havingTYValue];
					$temp['LYSALES'] 	= $data[$havingLYValue];
					$temp['VAR'] 		= $var;
					$temp['SHARE'] 		= $share;
					$temp['SHARELY'] 	= $shareLY;
					$temp['QTY'] 		= $data['QTY'];
                    $temp['QTY_LY']     = $data['QTY_LY'];
                    $temp['QTY_VAR']    = ($data['QTY_LY'] > 0) ? number_format( (($data['QTY'] - $data['QTY_LY']) / $data['QTY_LY']) * 100, 1, '.', '') : 0;
					$temp['CASH_ROS'] 	= ($getStoreData[$data['SKUID']]['ID_TY'] > 0) ? number_format($getStoreData[$data['SKUID']]['VALUE_TY']/$getStoreData[$data['SKUID']]['ID_TY'], 2, '.', '') : 0;
					$temp['QTY_ROS'] 	= ($getStoreData[$data['SKUID']]['ID_TY'] > 0) ? number_format($getStoreData[$data['SKUID']]['VOLUME_TY']/$getStoreData[$data['SKUID']]['ID_TY'], 2, '.', '') : 0;
					$temp['CROS_LY'] 	= ($getStoreData[$data['SKUID']]['ID_LY'] > 0) ? number_format($getStoreData[$data['SKUID']]['VALUE_LY']/$getStoreData[$data['SKUID']]['ID_LY'], 2, '.', '') : 0;
					$temp['QROS_LY'] 	= ($getStoreData[$data['SKUID']]['ID_LY'] > 0) ? number_format($getStoreData[$data['SKUID']]['VOLUME_LY']/$getStoreData[$data['SKUID']]['ID_LY'], 2, '.', '') : 0;
	                $temp['AVE_STORE_TY']    = number_format($getStoreData[$data['SKUID']]['AVE_STORE_TY'], 2, '.', '');
	                $temp['AVE_STORE_LY']    = number_format($getStoreData[$data['SKUID']]['AVE_STORE_LY'], 2, '.', '');
                    
                    $temp['AVE_PRICE_TY']    = ($data['QTY'] > 0) ? number_format($data['VAL_TY'] / $data['QTY'], 2, '.', '') : 0;
                    $temp['AVE_PRICE_LY']    = ($data['QTY_LY'] > 0) ? number_format($data['VAL_LY'] / $data['QTY_LY'], 2, '.', '') : 0;

					$arr[]				= $temp;
				}
			}
		} // end if
        $this->jsonOutput['rateOfSaleTrackerGrid'] = $arr;
    }

	/**
	 * storeCountData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeCountData($id, $storeCount) {
    	$totalWeek = 0;
        if (filters\timeFilter::$FromYear == filters\timeFilter::$ToYear) {
               $totalWeek = (filters\timeFilter::$ToWeek - filters\timeFilter::$FromWeek)+1;
        } elseif ((filters\timeFilter::$ToYear - filters\timeFilter::$FromYear) == 1) {
               $toYearWeek = filters\timeFilter::$ToWeek;
               $fromYearWeek = (52 - filters\timeFilter::$FromWeek) + 1;
               $totalWeek = $toYearWeek + $fromYearWeek;
        } elseif ((filters\timeFilter::$ToYear - filters\timeFilter::$FromYear) > 1) {
               $yearDiff = (filters\timeFilter::$ToYear - filters\timeFilter::$FromYear) - 1;
               $toYearWeek = filters\timeFilter::$ToWeek;
               $fromYearWeek = (52 - filters\timeFilter::$FromWeek) + 1;
               $totalWeek = ($toYearWeek + $fromYearWeek) + (52*$yearDiff);
        }
		$arr = array();
        $query = "SELECT  ".$id." as SKUID " .
				",".$this->settingVars->maintable . ".period as PERIOD ".
				",SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.")/SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") AS STORECOUNTDIV_TY ".
				",SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.")/SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") as QTY_TY ".
				",SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.")/SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") AS STORECOUNTDIV_LY ".
				",SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.")/SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") as QTY_LY ".				
                ",SUM((CASE WHEN " . $this->ytdTyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") AS AVE_STORE_TY ".
                ",SUM((CASE WHEN " . $this->ytdLyWeekRange . " THEN 1 ELSE 0 END)*".$storeCount.") AS AVE_STORE_LY ".
                "FROM " . $this->settingVars->tablename." ".$this->queryPart.
				" AND (" . $this->ytdTyWeekRange . " OR " . $this->ytdLyWeekRange . ") ".
				" GROUP BY SKUID, PERIOD HAVING (QTY_TY>0 OR QTY_LY>0)";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		if(is_array($result) && !empty($result)){
			foreach($result as $data){
				if($data['QTY_TY'] > 0) {
					$arr[$data['SKUID']]['ID_TY']		+= 1;
					$arr[$data['SKUID']]['VALUE_TY']	+= $data['STORECOUNTDIV_TY'];
					$arr[$data['SKUID']]['VOLUME_TY']	+= $data['QTY_TY'];
                    $arr[$data['SKUID']]['AVE_STORE_TY']+= $data['AVE_STORE_TY']/$totalWeek;
				}
				if($data['QTY_LY'] > 0) {
					$arr[$data['SKUID']]['ID_LY']		+= 1;
					$arr[$data['SKUID']]['VALUE_LY']	+= $data['STORECOUNTDIV_LY'];
					$arr[$data['SKUID']]['VOLUME_LY']	+= $data['QTY_LY'];
                    $arr[$data['SKUID']]['AVE_STORE_LY']+= $data['AVE_STORE_LY']/$totalWeek;
				}
			}
		} // end if
        return $arr;
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
		    if(count($accountFieldPart) > 1)
		    	$this->jsonOutput['gridColumns']['SKU'] = $this->displayCsvNameArray[$accountFieldPart[0]];
        }
        
		$accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
		$accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $storeCountField = strtoupper($this->dbColumnsArray[$this->storeCountField]);

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $this->storeCount = (isset($this->settingVars->dataArray[$storeCountField]) && isset($this->settingVars->dataArray[$storeCountField]['ID'])) ? $this->settingVars->dataArray[$storeCountField]['ID'] : $this->settingVars->dataArray[$storeCountField]['NAME'];

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
