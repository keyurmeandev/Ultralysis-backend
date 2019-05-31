<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class ExceptionExplorer extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /*if (empty($_REQUEST["requestDays"]) || $_REQUEST["requestDays"] == "undefined") {
            $_REQUEST["requestDays"] = 7;
        }*/

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
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "skuChange":
                $this->skuChange();
                break;
            default:
                $this->form();
        }

        return $this->jsonOutput;
    }

    function form() {

    	if ((isset($_REQUEST["Exception"]) && $_REQUEST["Exception"] == 0) || !isset($_REQUEST["Exception"]) || empty($_REQUEST["Exception"])) {
    		$this->jsonOutput['exceptionExplorerGrid'] = array();
    		return;
    	}

        $this->exceptionExplorerGrid();
    }

    function skuChange() {
        $this->skuSelect();
    }

    function exceptionExplorerGrid() {

    	$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $lastSalesDays = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

        /*$lastSalesDays = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $getLastDaysDate = filters\timeFilter::getLastNDaysDate($this->settingVars);*/

        $id = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];
        $storeid = key_exists('ID', $this->settingVars->dataArray['F4']) ? $this->settingVars->dataArray['F4']['ID'] : $this->settingVars->dataArray['F4']['NAME'];
        $storename = $this->settingVars->dataArray['F4']['NAME'];
        $skuname = $this->settingVars->dataArray['F2']['NAME'];
        $catname = $this->settingVars->dataArray['F1']['NAME'];
        $bannername = $this->settingVars->dataArray['F5']['NAME'];
		$storeDistrict = $this->settingVars->dataArray['F6']['NAME'];

        $salesArr 						  = array();
        $stockArr 						  = array();
		$exceptionExplorerGridDataBinding = array();

        /*$query = "SELECT TRIM(" . $catname . ") AS CLUSTER" .
                "," . $id . " AS TPNB" .
                ",COUNT(DISTINCT " . $storeid . ") as TOTALSNO " .
                ",TRIM(" . $bannername . ") AS BANNER" .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*units) AS SALES " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDaysDate[0] . " AND stock>0 THEN 1 ELSE 0 END)*stock) AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .
                "AND " . $this->settingVars->DatePeriod . " IN(" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY CLUSTER,TPNB,BANNER ";*/

        $query = "SELECT TRIM(" . $this->settingVars->clusterID . ") AS CLUSTER" .
                "," . $this->skuID . " AS TPNB" .
                ",COUNT(DISTINCT " . $this->storeID . ") as TOTALSNO " .
                ",TRIM(" . $bannername . ") AS BANNER" .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*units) AS SALES " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND stock>0 THEN 1 ELSE 0 END)*stock) AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .
                "AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange ) . "') " .
                "GROUP BY CLUSTER,TPNB,BANNER ";		
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
		if (is_array($result) && !empty($result))
		{			
			foreach ($result as $data) {
				$CLUSTER = utf8_encode($data['CLUSTER']);
				$TPNB = $data['TPNB'];
				$BANNER = $data['BANNER'];
				if ($data['TOTALSNO'] > 0) {
					$salesArr[$CLUSTER][$TPNB][$BANNER] = $data['SALES'] / $data['TOTALSNO'];
					$stockArr[$CLUSTER][$TPNB][$BANNER] = $data['STOCK'] / $data['TOTALSNO'];
				} else {
					$salesArr[$CLUSTER][$TPNB][$BANNER] = 0;
					$stockArr[$CLUSTER][$TPNB][$BANNER] = 0;
				}
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

			$havingPart = "";
			if (isset($_REQUEST["Exception"]) && $_REQUEST["Exception"] == 1) {
				$havingPart = " HAVING STOCK <1 ";
			}
			
			/*$query = "SELECT TRIM(MAX(" . $catname . " )) AS CLUSTER" .
					"," . $id . " AS TPNB ,TRIM(MAX(" . $skuname . ")) AS SKU" .
					",TRIM(MAX(" . $bannername . ")) AS BANNER ," . $storeid . " AS SNO " .
					",TRIM(MAX(" . $storename . ")) AS STORE " .
					",TRIM(MAX(" . $storeDistrict . ")) AS STOREDISTRICT " .
					$addTerritoryColumn.
					",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*units) AS SALESQ " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDaysDate[0] . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDaysDate[0] . " THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->querypart .
					"AND " . $this->settingVars->DatePeriod . " IN(" . implode(',', $getLastDaysDate) . ") " .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup." $havingPart LIMIT 0,20000";*/

			$query = "SELECT TRIM(MAX(" . $this->settingVars->clusterID . " )) AS CLUSTER" .
					"," . $this->skuID . " AS TPNB ,TRIM(MAX(" . $this->skuName . ")) AS SKU" .
					",TRIM(MAX(" . $bannername . ")) AS BANNER ," . $this->storeID . " AS SNO " .
					",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
					$addTerritoryColumn.((count($selectPart) > 0 ) ? " , ".implode(",", $selectPart) : "" ).
					",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*units) AS SALESQ " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->querypart .
					"AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup." $havingPart LIMIT 0,20000";
			//echo $query;exit;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
			if (is_array($result) && !empty($result))
			{
				foreach ($result as $data) {

					$index = $data['TPNB'] . $data['SNO'];
					$CLUSTER = utf8_encode($data['CLUSTER']);
					$TPNB = $data['TPNB'];
					$BANNER = $data['BANNER'];
					$aveDailySales = $data['SALESQ'] / $_REQUEST['requestDays'];
					$daysCover = ($data['SALESQ'] > 0) ? (($data['STOCK'] + $data['TRANSIT']) / $aveDailySales) : 0;

					$row = array();

					$row['CLUSTER'] = utf8_encode($data['CLUSTER']);
					$row['BANNER'] = utf8_encode($data['BANNER']);
					$row['TPNB'] = $data['TPNB'];
					$row['SKU'] = utf8_encode($data['SKU']);
					$row['CLST_STOCK'] = number_format($stockArr[$CLUSTER][$TPNB][$BANNER], 0);
					$row['CLST_SALES'] = number_format($salesArr[$CLUSTER][$TPNB][$BANNER], 0);
					$row['SNO'] = $data['SNO'];
					$row['STORE'] = $data['STORE'];
					$row['STOREDISTRICT'] = utf8_encode($data['STOREDISTRICT']);
					$row['STOCK'] = $data['STOCK'];
					$row['SALESQ'] = $data['SALESQ'];

					if ($salesArr[$CLUSTER][$TPNB][$BANNER] > 0) {
						$row['SALES_DEV'] = number_format($data['SALESQ'] / $salesArr[$CLUSTER][$TPNB][$BANNER], 2, '.', '');
					} else {
						$row['SALES_DEV'] = 0;
					}

					if ($stockArr[$CLUSTER][$TPNB][$BANNER] > 0) {
						$row['STOCK_DEV'] = number_format($data['STOCK'] / $stockArr[$CLUSTER][$TPNB][$BANNER], 2, '.', '');
					} else {
						$row['STOCK_DEV'] = 0;
					}

					$row['DAYS_COVER'] = number_format($daysCover, 2, '.', '');
					//$row['SALES_IN_14_DAYS'] 	= $salesIn14Days[$data['TPNB']."_".$data['SNO']];
					$row['LAST_SALE'] = $lastSalesDays[$data['TPNB'] . "_" . $data['SNO']];
					
					if($data['TERRITORY'])
						$row['TERRITORY'] = $data['TERRITORY'];					
					
					switch ($_REQUEST["Exception"]) {
						case '1':
							array_push($exceptionExplorerGridDataBinding, $row);
							break;
						case '2':
							if ($row['STOCK_DEV'] < 0.9 && $row['SALES_DEV'] > 2 && $row['DAYS_COVER'] < 5) {
								array_push($exceptionExplorerGridDataBinding, $row);
							}
							break;
						case '3':
							if ($row['STOCK_DEV'] > 2 && $row['SALES_DEV'] < 0.8) {
								array_push($exceptionExplorerGridDataBinding, $row);
							}
							break;
						case '4':
							if ($row['DAYS_COVER'] < 7) {
								array_push($exceptionExplorerGridDataBinding, $row);
							}
							break;
						default:
							array_push($exceptionExplorerGridDataBinding, $row);
							break;
					}
					
				}
			} // end if
		} // end if
        $this->jsonOutput['exceptionExplorerGrid'] = $exceptionExplorerGridDataBinding;
    }

    function skuSelect() {

        $getLastDaysDate = filters\timeFilter::getLastN14DaysDate($this->settingVars);
        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN stock>0 THEN 1 ELSE 0 END)*stock) AS STOCK " .
                ",SUM((CASE WHEN stockTra>0 THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . $this->queryPart .
                //" AND " . $this->settingVars->DatePeriod . " IN(" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY DAY  ORDER BY DAY ASC";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $value = array();
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) 
			{
				$value['SALES'][] = $data['SALES'];
				$value['STOCK'][] = $data['STOCK'];
				$value['TRANSIT'][] = $data['TRANSIT'];
				$value['DAY'][] = $data['DAY'];
			}
		} // end if
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

            $tempCol = array();
            
            $tempCol['PIN_1'] = (isset($this->settingVars->dataArray[$skuField]['ID_CSV'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : $this->settingVars->dataArray[$skuField]['NAME_CSV'];
            $tempCol['SKU_1'] = $this->settingVars->dataArray[$skuField]['NAME_CSV'];
                
            $tempCol['SNO_1'] = (isset($this->settingVars->dataArray[$storeField]['ID_CSV'])) ? $this->settingVars->dataArray[$storeField]['ID_CSV'] : $this->settingVars->dataArray[$storeField]['NAME_CSV'];
            $tempCol['SNAME_1'] = $this->settingVars->dataArray[$storeField]['NAME_CSV'];

            foreach ($this->accountsName as $key => $value) {
                $tempCol[$this->settingVars->dataArray[$value]['NAME_ALIASE']] = $this->settingVars->dataArray[$value]['NAME_CSV'];
            }
            
            $this->jsonOutput["GRID_COLUMN_NAMES"] = $tempCol;
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

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }
        
       

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

}

?>