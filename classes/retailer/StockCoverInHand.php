<?php

namespace classes\retailer;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class StockCoverInHand extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_StockCoverInHandPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $tempBuildFieldsArray = array($this->skuField,$this->storeField);
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        }else{
            $this->configurationFailureMessage();
        }
		
        $action = $_REQUEST["action"];
		
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
			case "getTopGrid":
				$this->topGrid();
				break;
			case "getBottomGrid":
				$this->bottomGrid();
				break;				
        }
		
        return $this->jsonOutput;
    }



	function topGrid() {
		
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        
        if (is_array($this->accountsName) && !empty($this->accountsName)) {
            foreach ($this->accountsName as $key => $data) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS " . $this->settingVars->dataArray[$data]['NAME_ALIASE'];
                $groupByPart[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$query = "SELECT " . $this->skuID . " AS PIN_1 ," .
                " TRIM(MAX(" . $this->skuName . ")) AS SKU_1" .
                ((is_array($selectPart) && !empty($selectPart)) ? ",".implode(",", $selectPart) : "") .
				",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
				",SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND ".$this->settingVars->recordType."='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
				" FROM " . $this->settingVars->tablename . " " . $this->queryPart .
				" AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
				" GROUP BY PIN_1".((is_array($groupByPart) && !empty($groupByPart)) ? ", ".implode(", ", $groupByPart) : "");
		// echo $query.'<BR>';exit;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$stockCoverGridDataBinding = array();
		
		$i = 0;
		$totalValue = count($result);
		$stringArray = '';
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $value)
			{
				$aveDailySales = $value['SALES'] / (int)filters\timeFilter::$daysTimeframe;
				if ($aveDailySales != 0) {

					$dc = ($value['SALES'] > 0) ? (($value['STOCK']) / $aveDailySales) : 0;

                    $value['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
                    $value['DAYS_COVER'] = number_format($dc, 2, '.', '');
					
					array_push($stockCoverGridDataBinding, $value);
				}
			}
		}			
        
        usort($stockCoverGridDataBinding, function($a, $b) {
         //return $a['AVE_DAILY_SALES'] - $b['AVE_DAILY_SALES'];
          return ($a['AVE_DAILY_SALES'] < $b['AVE_DAILY_SALES']) ? 1 : -1;
       });

        $this->jsonOutput['topGrid'] = $stockCoverGridDataBinding;
	}	
	



    function bottomGrid() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $lastSalesDays = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        
        foreach ($this->accountsName as $key => $data) {
            $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
            $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

		
		$query = "SELECT " . $this->skuID . " AS PIN_1 ," .
                " TRIM(MAX(" . $this->skuName . ")) AS SKU_1 ," .
                $this->storeID . " AS SNO_1 ," .
                " TRIM(MAX(" . $this->storeName . ")) AS SNAME_1 , " .
                 implode(",", $selectPart) .
				",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
				",SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND ".$this->settingVars->recordType."='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
				" FROM " . $this->settingVars->tablename . " " . $this->queryPart .
				" AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
				" GROUP BY PIN_1 , SNO_1 ";

		//echo $query.'<BR>';exit;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$stockCoverGridDataBinding = array();
		
		$i = 0;
		$totalValue = count($result);
		$stringArray = '';
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $value)
			{
				$aveDailySales = $value['SALES'] / (int)filters\timeFilter::$daysTimeframe;
				if ($aveDailySales != 0) {

					$dc = ($value['SALES'] > 0) ? (($value['STOCK']) / $aveDailySales) : 0;

                    $value['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
                    $value['DAYS_COVER'] = number_format($dc, 2, '.', '');
                    $value['LAST_SALE'] = $lastSalesDays[$value['PIN_1'] . "_" . $value['SNO_1']];
					
					array_push($stockCoverGridDataBinding, $value);
				}
			}
		}			
        

        usort($stockCoverGridDataBinding, function($a, $b) {
         //return $a['AVE_DAILY_SALES'] - $b['AVE_DAILY_SALES'];
          return ($a['AVE_DAILY_SALES'] < $b['AVE_DAILY_SALES']) ? 1 : -1;
       });

               
        $this->jsonOutput['bottomGrid'] = $stockCoverGridDataBinding;
    }

    function skuSelect() {

        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . $this->settingVars->dateperiod . " AS DAY" .
				",SUM((CASE WHEN ".$this->settingVars->recordType." = 'SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType." = 'STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //"AND " . $this->settingVars->dateperiod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                " GROUP BY DAY " .
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

    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
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
        
        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
        
        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->accountsName = $this->makeFieldsToAccounts($this->accountFields);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $tempCol = $tempColBottom = array();
            
            $tempColBottom['PIN_1'] = $tempCol['PIN_1'] = (isset($this->settingVars->dataArray[$skuField]['ID_CSV'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : $this->settingVars->dataArray[$skuField]['NAME_CSV'];
            $tempColBottom['SKU_1'] = $tempCol['SKU_1'] = $this->settingVars->dataArray[$skuField]['NAME_CSV'];
                
            $tempColBottom['SNO_1'] = (isset($this->settingVars->dataArray[$storeField]['ID_CSV'])) ? $this->settingVars->dataArray[$storeField]['ID_CSV'] : $this->settingVars->dataArray[$storeField]['NAME_CSV'];
            $tempColBottom['SNAME_1'] = $this->settingVars->dataArray[$storeField]['NAME_CSV'];

            foreach ($this->accountsName as $key => $value) {
                $tempCol[$this->settingVars->dataArray[$value]['NAME_ALIASE']] = $this->settingVars->dataArray[$value]['NAME_CSV'];
                $tempColBottom[$this->settingVars->dataArray[$value]['NAME_ALIASE']] = $this->settingVars->dataArray[$value]['NAME_CSV'];
            }
            
            $this->jsonOutput["TOP_GRID_COLUMN_NAMES"] = $tempCol;
            $this->jsonOutput["BOTTOM_GRID_COLUMN_NAMES"] = $tempColBottom;
        }

        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];

        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];

        return;
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
                $extraFields[] = $this->skuID;
                $tablejoins_and_filters .= " AND " . $this->skuID . " = " . $_REQUEST["FS"]['PIN_1']." ";
            }

            if (isset($_REQUEST["FS"]['SNO_1']) && $_REQUEST["FS"]['SNO_1'] != '') 
            {
                $extraFields[] = $this->storeID;
                $tablejoins_and_filters .= " AND " . $this->storeID." = ".$_REQUEST["FS"]['SNO_1']." ";
            }
        }


        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }
}
?>