<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class ZeroSales extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public $isPPCInclude = 'N';
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		/*if (empty($_REQUEST["requestDays"])) {
		    $_REQUEST["requestDays"] = 14;
		} */
        $action = $_REQUEST["action"];

		if ($this->settingVars->isDynamicPage) {
			$this->extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
			$this->lostValuesAccount = $this->getPageConfiguration('lost_values_account', $this->settingVars->pageID);
			$this->storeField 	= $this->getPageConfiguration('store_field', $this->settingVars->pageID);
			$this->skuField 	= $this->getPageConfiguration('sku_field', $this->settingVars->pageID);
            
			$tempBuildFieldsArray = array();
			$tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->extraColumns, $this->lostValuesAccount,$this->storeField, $this->skuField);
			
            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
			
			$this->pinIdField 	= $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID'];
			$this->gridColumnHeader['TPNB'] = (isset($this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID_CSV']) && !empty($this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID_CSV'] : 'TPNB';

			$this->pinNameField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME'];
			$this->gridColumnHeader['SKU'] = (isset($this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME_CSV']) && !empty($this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME_CSV'] : 'SKU';

			$this->snoIdField 	= $this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID'];
			$this->gridColumnHeader['SNO'] = (isset($this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID_CSV']) && !empty($this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID_CSV'] : 'SNO';

			$this->snoNameField = $this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME'];
			$this->gridColumnHeader['STORE'] = (isset($this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME_CSV']) && !empty($this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME_CSV'] : 'STORE';

			$this->lostValuesAccountField 		= $this->settingVars->dataArray[strtoupper($this->lostValuesAccount[0])]['NAME'];
			$this->lostValuesAccountFieldAlias 	= $this->settingVars->dataArray[strtoupper($this->lostValuesAccount[0])]['NAME_ALIASE'];
		}
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->setGridColumns();
            if(isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 27) {
	            $this->gridColumnHeader['PPC']   = 'PIECES PER CASE';
				$this->gridColumnHeader['STOCK_UNITS']= 'STOCK UNITS';
				$this->isPPCInclude = 'Y';
			}
            $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;
        }else{
        	if(isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 27){
	         	$this->isPPCInclude = 'Y';
			}
        }
        
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
            case "zeroSalesGrid":
                $this->zeroSalesGrid();
                break;
        }

        return $this->jsonOutput;
    }
    
    function zeroSalesGrid() {

        //$lastSalesDays = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars, "skuID");
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->maintable.".skuID";
        $this->measureFields[] = $this->settingVars->maintable.".SNO";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $lastSalesDays = datahelper\Common_Data_Fetching_Functions::getLastSalesDays($this->settingVars->maintable.".skuID", $this->settingVars->maintable.".SNO", $this->settingVars, $this->queryPart);
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->settingVars->clusterID;
        $this->measureFields[] = $this->lostValuesAccountField;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();        
        
        $query = "SELECT " .
                $this->pinIdField . " AS TPNB " .
                "," . $this->settingVars->clusterID . " AS CLUSTER " .
                "," . $this->lostValuesAccountField . " AS " . $this->lostValuesAccountFieldAlias . 
                ",SUM((CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN 1 END)*value)/COUNT(DISTINCT(case when (value) >1 then " . $this->settingVars->maintable . ".SNO end)) AS LOST_VALUE " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY TPNB,".$this->lostValuesAccountFieldAlias.",CLUSTER ORDER BY LOST_VALUE DESC";
        
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $lostValueArray 		  = array();
		$topLost 				  = array();
		$summaryPod 			  = array();
		$summaryPod['TOTAL_LOST'] = 0;
		$zeroSalesGridDataBinding = array();
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $key => $value) {
				$index = $value['TPNB'] . $value[$this->lostValuesAccountFieldAlias] . $value['CLUSTER'];
				$lostValueArray[$index] = $value['LOST_VALUE'];
			}

			if (isset($_REQUEST['RANGED']) && $_REQUEST['RANGED'] == "YES") {
				$this->settingVars->tablename .= "," . $this->settingVars->rangedtable;
				$this->queryPart .= " AND " . $this->settingVars->maintable . ".SNO=" . $this->settingVars->rangedtable . ".SNO AND " . $this->settingVars->maintable . ".skuID=" . $this->settingVars->rangedtable . ".skuID ";
			}
			
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
            $this->measureFields[] = $this->pinIdField;
            $this->measureFields[] = $this->snoIdField;
            $this->measureFields[] = $this->settingVars->clusterID;
			$IncludePPC = '';
            if($this->isPPCInclude == 'Y'){
                $this->measureFields[] = $this->settingVars->ppcColumnField;
                $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC";
            }

            $selectPart = array();
            $groupByPart = array();
            
            foreach ($this->extraColumns as $key => $data) {
                $selectPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
                $groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
                $this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
            }            
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            $this->queryPart = $this->getAll();			

			$query = "SELECT " . $this->pinIdField . " AS TPNB" .
					",TRIM(MAX(" . $this->pinNameField . ")) AS SKU" .
					"," . $this->snoIdField . " AS SNO " .
					",TRIM(MAX(" . $this->snoNameField . ")) AS STORE " .
					//",TRIM(MAX(" . $storeDistrict . ")) AS STOREDISTRICT " .
					$addTerritoryColumn.
					$IncludePPC.
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
					",".implode(",", $selectPart) . 
					",IF(min(stock)<=0, 0, 1) as NON_STOCK" .
					",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 ELSE 0 END)*units) AS SALES " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					" AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
					" AND " . $this->settingVars->skutable . ".clientID='" . $this->settingVars->clientID . "' " .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup.",".implode(",", $groupByPart)." HAVING (STOCK>0 AND SALES=0 AND NON_STOCK = 1)";

			//echo $query.'<BR>';exit;
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }
			
			$uniqueTpnb = array();
			if (is_array($result) && !empty($result))
			{
				foreach ($result as $value) {

					$index = $value['TPNB'] . $value['SNO'];
					//$indexLostValue = $value['TPNB'] . $value['BANNER'] . $value['CLUSTER'];
					$indexLostValue = $value['TPNB'] . $value[$this->lostValuesAccountFieldAlias] . $value['CLUSTER'];
					$row = array();
					$row['SNO'] = $value['SNO'];
					$row['STORE'] = utf8_encode($value['STORE']);
					//$row['STOREDISTRICT'] = utf8_encode($value['STOREDISTRICT']);
					//$row['BANNER'] = utf8_encode($value['BANNER']);
					$row['CLUSTER'] = utf8_encode($value['CLUSTER']);
					$row['TPNB'] = $value['TPNB'];
					$row['SKU'] = utf8_encode($value['SKU']);
					$row['STOCK'] = $value['STOCK'];
					
					if($this->isPPCInclude == 'Y'){
						$row['PPC']   = $value['PPC'];
						$row['STOCK_UNITS']= ($value['STOCK']*$value['PPC']);
					}
					
					$row['LOST'] = number_format($lostValueArray[$indexLostValue], 2, '.', '');
					$row['LAST_SALE'] = $lastSalesDays[$row['TPNB'] . "_" . $row['SNO']];
					$summaryPod['TOTAL_LOST'] += $row['LOST'];

					foreach ($this->extraColumns as $key => $data) {
                        $row[$this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE']] = $value[$this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE']];
                    }
					
					if($value['TERRITORY'])
						$row['TERRITORY'] = $value['TERRITORY'];

					if (!isset($uniqueTpnb[$row['TPNB']])) {
						$uniqueTpnb[$row['TPNB']] = $row['TPNB'];
					}
					array_push($zeroSalesGridDataBinding, $row);
				}
			}
			// This is new update use  
			
			$sumOfShare = 0;
			if (is_array($zeroSalesGridDataBinding) && !empty($zeroSalesGridDataBinding))
			{
				foreach ($zeroSalesGridDataBinding as $value) {
					if (!isset($topLost[$value['SNO']])) {
						$topLost[$value['SNO']]['SNO'] = $value['SNO'];
						$topLost[$value['SNO']]['STORE'] = $value['STORE'];
						//$topLost[$value['SNO']]['STOREDISTRICT'] = $value['STOREDISTRICT'];
						$topLost[$value['SNO']]['CLUSTER'] = $value['CLUSTER'];
						//$topLost[$value['SNO']]['BANNER'] = $value['BANNER'];
						$topLost[$value['SNO']]['ZERO_SKU'] = 1;
						$topLost[$value['SNO']]['LOST'] = $value['LOST'];
						
						foreach ($this->extraColumns as $key => $data) {
                            $topLost[$value['SNO']][$this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE']] = $value[$this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE']];
                        }
						
						if($value['TERRITORY'])
							$topLost[$value['SNO']]['TERRITORY'] = $value['TERRITORY'];
					
					} else {
						$topLost[$value['SNO']]['ZERO_SKU'] += 1;
						$topLost[$value['SNO']]['LOST'] += $value['LOST'];
					}
				}

				foreach ($topLost as $key => $value) {
					$tmp[$key] = $value['LOST'];
				}
				array_multisort($tmp, SORT_DESC, $topLost);

				$cumShare = 0;
				$till80PerStoreCount = 0;
				foreach ($topLost as $key => $value) {
					$val = number_format(($value['LOST'] == 0 ? 0 : $value['LOST'] / $summaryPod['TOTAL_LOST']) * 100, '2', '.', ',');
					$cumShare += $val;
					$topLost[$key]['CUM_SHARE'] = $cumShare;
					if ($cumShare <= 80) {
						$till80PerStoreCount++;
					}
				}				

				$summaryPod['TOTAL_LOST'] = number_format($summaryPod['TOTAL_LOST'], '2', '.', ',');
				$summaryPod['UNIQUE_STORE'] = number_format(count($topLost), '0', '.', ',');
				$summaryPod['SKUS_INCLUDED'] = number_format(count($uniqueTpnb), '0', '.', ',');
				$summaryPod['80_PCT_LOST'] = number_format($till80PerStoreCount, '0', '.', ',');
				
			}
		}
        $this->jsonOutput['summaryPod'] = $summaryPod;
        $this->jsonOutput['topZeroSalesGrid'] = array_values($topLost);

        // end 
		
		if (is_array($zeroSalesGridDataBinding) && !empty($zeroSalesGridDataBinding))
		{
			foreach ($zeroSalesGridDataBinding as $key => $value) {
				$emp[$key] = $value['LOST'];
			}
			array_multisort($emp, SORT_DESC, $zeroSalesGridDataBinding);
		}

        $this->jsonOutput['zeroSalesGrid'] = $zeroSalesGridDataBinding;
    }

    function skuSelect() {

        $getLastDaysDate = filters\timeFilter::getLastN14DaysDate($this->settingVars);

        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $IncludePPC = '';
        if($this->isPPCInclude == 'Y'){
            $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC";
        }

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN stock>0 THEN 1 ELSE 0 END)*stock) AS STOCK " .
                $IncludePPC.
                ",SUM((CASE WHEN stockTra>0 THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //"AND " . $this->settingVars->DatePeriod . " IN(" . implode(',', $getLastDaysDate) . ") " .
                " GROUP BY DAY " .
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
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {
				$value['SALES'][] = $data['SALES'];
				if($this->isPPCInclude == 'Y') {
					$value['STOCK'][] = $data['STOCK']*$data['PPC'];
				} else {
                    $value['STOCK'][] = $data['STOCK'];
                }
				$value['TRANSIT'][] = $data['TRANSIT'];
				$value['DAY'][] = $data['DAY'];
			}
		}
		
        $this->jsonOutput['skuSelect'] = $value;
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
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $this->extraColumns = $this->makeFieldsToAccounts($this->extraColumns);
		$this->lostValuesAccount = $this->makeFieldsToAccounts($this->lostValuesAccount);
		$this->storeField 	= $this->makeFieldsToAccounts($this->storeField);
		$this->skuField 	= $this->makeFieldsToAccounts($this->skuField);
		return;
    }
	
    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = $this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]];
            else
                $tempArr[] = $this->dbColumnsArray[$value[0]];
        }
        return $tempArr;
    }	
   
    private function setGridColumns() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $tempCol = array();
			
            foreach ($this->extraColumns as $key => $value) {
                $tempCol[$this->settingVars->dataArray[strtoupper($this->extraColumns[$key])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->extraColumns[$key])]['NAME_CSV'];
            }
			
            $this->jsonOutput["TOP_GRID_COLUMN_NAMES"] = $tempCol;
        }
    }

    public function getAll() {
        $tablejoins_and_filters = "";

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }
        
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '')
        {
            if (isset($_REQUEST["FS"]['PIN']) && $_REQUEST["FS"]['PIN'] != '' ){
                $extraFields[] = $this->pinIdField;
                $tablejoins_and_filters .= " AND " . $this->pinIdField . " = '" . $_REQUEST["FS"]['PIN']."' ";
            }

            if (isset($_REQUEST["FS"]['SNO']) && $_REQUEST["FS"]['SNO'] != '') 
            {
                $extraFields[] = $this->snoIdField;
                $tablejoins_and_filters .= " AND " . $this->snoIdField ." = '".$_REQUEST["FS"]['SNO']."' ";
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }    
    
}

?>