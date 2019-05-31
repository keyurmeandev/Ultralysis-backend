<?php

namespace classes\retailer;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class ZeroSales extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        		
        $action = $_REQUEST["action"];
		
        if ($_GET['DataHelper'] == "true") {

            $this->jsonOutput['projectID']          	= utils\Encryption::encode($this->settingVars->projectID);
            
            if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
            else
                $this->jsonOutput['clientLogo']         = 'no-logo.jpg';
            
            if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
            else
                $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';    

            if(isset($this->settingVars->default_load_pageID) && !empty($this->settingVars->default_load_pageID))
                $this->jsonOutput['default_load_pageID'] = $this->settingVars->default_load_pageID;
        }
		
		if ($this->settingVars->isDynamicPage) {
			$this->topGridAccountFields = $this->getPageConfiguration('top_grid_accounts', $this->settingVars->pageID);
			$this->bottomGridAccountFields = $this->getPageConfiguration('bottom_grid_accounts', $this->settingVars->pageID);
			$this->lostValuesAccounts = $this->getPageConfiguration('lost_values_accounts', $this->settingVars->pageID);
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID);
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID);

			$tempBuildFieldsArray = array();
			$tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->topGridAccountFields, $this->bottomGridAccountFields, $this->lostValuesAccounts,$this->storeField, $this->skuField);
			
            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
			
			$this->pinIdField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID'];
			$this->pinNameField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME'];
			$this->snoIdField = $this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID'];
			$this->snoNameField = $this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME'];
		}
		
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->setGridColumns();
        }		
		
		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
			case "getTopGridData":
				$this->topGrid();
				break;
			case "getBottomGridData":
				$this->bottomGrid();
				break;
        }		
		
        return $this->jsonOutput;
    }

	function topGrid() {
		
        $selectPart = array();
        $groupByPart = array();
		
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->pinNameField;
		
		foreach ($this->topGridAccountFields as $key => $data) {
			$selectPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
			$groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
			$this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
		}
				
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
		
		$query = "SELECT ".$this->pinIdField." as PIN_1, ". $this->pinNameField." as PNAME_1 ".((!empty($selectPart)) ? ", ".implode(",", $selectPart) : "") ." ".
			//",SUM((CASE WHEN ".$this->settingVars->ProjectValue.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 END)* ".$this->settingVars->ProjectValue." )/COUNT(DISTINCT(CASE WHEN (".$this->settingVars->ProjectValue.") > 1 AND ".$this->settingVars->recordType."='SAL' then " . $this->settingVars->maintable . ".SNO end)) AS LOST " .
			",SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND ".$this->settingVars->recordType."='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
			",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES_QTY " .
			"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
			"AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
			"GROUP BY PIN_1, PNAME_1 " .((!empty($groupByPart)) ? ", " . implode(",", $groupByPart) : " ") . 
			" ORDER BY SALES_QTY DESC";
        //echo $query.'<BR>';exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$this->jsonOutput['topGrid'] = $result;
	}
	
    function bottomGrid() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->snoIdField;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$lastSalesDays = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->pinIdField, $this->snoIdField, $this->settingVars, $this->queryPart);
		
        $selectPart = array();
        $groupByPart = array();
		
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
		
		foreach ($this->lostValuesAccounts as $key => $data) {
			$selectPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'] . " AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
			$groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
			$this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
		}
				
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$query = "SELECT " . implode(",", $selectPart).", ". 
			$this->pinIdField." as PIN_1 ".
			",SUM((CASE WHEN ".$this->settingVars->ProjectValue.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 END)* ".$this->settingVars->ProjectValue." )/COUNT(DISTINCT(CASE WHEN (".$this->settingVars->ProjectValue.") > 1 AND ".$this->settingVars->recordType."='SAL' then " . $this->settingVars->maintable . ".SNO end)) AS LOST_VALUE " .
			"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
			"AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
			"GROUP BY PIN_1, " . implode(",", $groupByPart) . 
			" ORDER BY LOST_VALUE DESC";
        //echo $query.'<BR>';exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
        $lostValueArray 		  = array();
		$topLost 				  = array();
		$summaryPod 			  = array();
		$zeroSalesGridDataBinding = array();
		$summaryPod['TOTAL_LOST'] = 0;
		
        $selectPart = array();
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $key => $value) {
				$index = $value["PIN_1"];
				foreach($this->lostValuesAccounts as $account) 
					$index .= $value[$this->settingVars->dataArray[strtoupper($account)]['NAME_ALIASE']];
				$lostValueArray[$index] = $value['LOST_VALUE'];
			}

			$this->settingVars->tableUsedForQuery = $this->measureFields = array();
			$this->measureFields[] = $this->pinIdField;
			$this->measureFields[] = $this->pinNameField;
			$this->measureFields[] = $this->snoIdField;
			$this->measureFields[] = $this->snoNameField;
			
			foreach ($this->bottomGridAccountFields as $key => $data) {
				$selectPart[] = "MAX(".$this->settingVars->dataArray[strtoupper($data)]['NAME'] . ") AS " . $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'] . "";
				$groupByPart[] = $this->settingVars->dataArray[strtoupper($data)]['NAME_ALIASE'];
				$this->measureFields[] = $this->settingVars->dataArray[strtoupper($data)]['NAME'];
			}
					
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}
			$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
			
			$query = "SELECT " .((!empty($selectPart)) ? implode(",", $selectPart).", " : "") ." ".
				$this->pinIdField." as PIN_1, MAX(". $this->pinNameField. ") as PNAME_1, ". $this->snoIdField. " as SNO_1, MAX(".$this->snoNameField.") as SNAME_1 ".
				",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
				",SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND ".$this->settingVars->recordType."='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
				"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
				" AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
				"GROUP BY PIN_1, SNO_1 ".
				" HAVING (STOCK>0 AND SALES=0)";
			//echo $query.'<BR>';exit;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
			$uniqueTpnb = array();
			if (is_array($result) && !empty($result))
			{
				foreach ($result as $key => $value) {
					$indexLostValue = $value["PIN_1"];
					foreach($this->lostValuesAccounts as $account) 
						$indexLostValue .= $value[$this->settingVars->dataArray[strtoupper($account)]['NAME_ALIASE']];
					
					$result[$key]['STOCK'] = $value['STOCK'];
					$result[$key]['LOST'] = number_format($lostValueArray[$indexLostValue], 2, '.', '');
					$result[$key]['LAST_SALE'] = $lastSalesDays[$value['PIN_1'] . "_" . $value['SNO_1']];
					$summaryPod['TOTAL_LOST'] += $result[$key]['LOST'];
					
					if (!isset($uniqueTpnb[$result[$key]['PIN_1']])) {
						$uniqueTpnb[$result[$key]['PIN_1']] = $result[$key]['PIN_1'];
					}
				}
			}
			$zeroSalesGridDataBinding = $result;
			
			foreach($this->bottomGridAccountFields as $key => $account)
				$bottomGridAccountFields[] = $this->settingVars->dataArray[strtoupper($this->bottomGridAccountFields[$key])]['NAME_ALIASE'];
			
			$sumOfShare = 0;
			if (is_array($zeroSalesGridDataBinding) && !empty($zeroSalesGridDataBinding))
			{
				foreach ($zeroSalesGridDataBinding as $value) {
					if (!isset($topLost[$value['SNO_1']])) 
					{
						foreach($bottomGridAccountFields as $data)
							$topLost[$value['SNO_1']][$data] = $value[$data];
						
						$topLost[$value['SNO_1']]['SNAME_1'] = $value['SNAME_1'];
						$topLost[$value['SNO_1']]['SNO_1'] = $value['SNO_1'];
						$topLost[$value['SNO_1']]['ZERO_SKU'] = 1;
						$topLost[$value['SNO_1']]['LOST'] = $value['LOST'];
						$topLost[$value['SNO_1']]['PIN_1'] = $value['PIN_1'];
						$topLost[$value['SNO_1']]['PNAME_1'] = $value['PNAME_1'];
						$topLost[$value['SNO_1']]['STOCK'] = $value['STOCK'];
						$topLost[$value['SNO_1']]['LAST_SALE'] = $value['LAST_SALE'];
					
					} else {
						$topLost[$value['SNO_1']]['ZERO_SKU'] += 1;
						$topLost[$value['SNO_1']]['LOST'] += $value['LOST'];
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
			}
		}
        $this->jsonOutput['bottomGrid'] = array_values($topLost);
    }

    function skuSelect() {

        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll	
	
        $query = "SELECT " . $this->settingVars->dateperiod . " AS DAY" .
				",SUM((CASE WHEN ".$this->settingVars->recordType." = 'SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType." = 'STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //"AND " . $this->settingVars->dateperiod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY DAY " .
                "ORDER BY DAY ASC";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $value = array();
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {

				$value['SALES'][] = $data['SALES'];
				$value['STOCK'][] = $data['STOCK'];
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

        $this->topGridAccountFields = $this->makeFieldsToAccounts($this->topGridAccountFields);
        $this->bottomGridAccountFields = $this->makeFieldsToAccounts($this->bottomGridAccountFields);
		$this->lostValuesAccounts = $this->makeFieldsToAccounts($this->lostValuesAccounts);
		$this->storeField = $this->makeFieldsToAccounts($this->storeField);
		$this->skuField = $this->makeFieldsToAccounts($this->skuField);
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
            $tempCol = $tempColBottom = array();
			
			$tempColBottom['PIN_1'] = $tempCol['PIN_1'] = (isset($this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID_CSV'] : $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME_CSV'];
			$tempColBottom['PNAME_1'] = $tempCol['PNAME_1'] = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME_CSV'];
				
            foreach ($this->topGridAccountFields as $key => $value) {
                $tempCol[$this->settingVars->dataArray[strtoupper($this->topGridAccountFields[$key])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->topGridAccountFields[$key])]['NAME_CSV'];
            }
			
			$tempColBottom['SNO_1'] = (isset($this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID_CSV'])) ? $this->settingVars->dataArray[strtoupper($this->storeField[0])]['ID_CSV'] : $this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME_CSV'];
			$tempColBottom['SNAME_1'] = $this->settingVars->dataArray[strtoupper($this->storeField[0])]['NAME_CSV'];
			
            $this->jsonOutput["TOP_GRID_COLUMN_NAMES"] = $tempCol;
			
            foreach ($this->bottomGridAccountFields as $key => $value) {
				$tempColBottom[$this->settingVars->dataArray[strtoupper($this->bottomGridAccountFields[$key])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->bottomGridAccountFields[$key])]['NAME_CSV'];
            }
            $this->jsonOutput["BOTTOM_GRID_COLUMN_NAMES"] = $tempColBottom;
        }
    }
	
    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $tablejoins_and_filters = "";
        
        $tablejoins_and_filters .= " AND ".$this->settingVars->maintable.".SNO < 7258 ";
        
        $extraFields[] = $this->settingVars->skutable.".supplier";
        $tablejoins_and_filters .= " AND ".$this->settingVars->skutable.".supplier='FERRERO UK LTD' ";

        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '')
        {
            if (isset($_REQUEST["FS"]['PIN_1']) && $_REQUEST["FS"]['PIN_1'] != '' ){
                $extraFields[] = $this->pinIdField;
                $tablejoins_and_filters .= " AND " . $this->pinIdField . " = " . $_REQUEST["FS"]['PIN_1']." ";
            }

            if (isset($_REQUEST["FS"]['SNO_1']) && $_REQUEST["FS"]['SNO_1'] != '') 
            {
                $extraFields[] = $this->snoIdField;
                $tablejoins_and_filters .= " AND " . $this->snoIdField." = ".$_REQUEST["FS"]['SNO_1']." ";
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }	
	
}
?>