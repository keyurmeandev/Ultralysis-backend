<?php
namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class StockCover extends config\UlConfig {
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

            if(isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 27) {
                $this->gridColumnHeader['PPC'] = 'PIECES PER CASE';
                $this->gridColumnHeader['STOCK_UNITS'] = 'STOCK UNITS';
                $this->gridColumnHeader['STOCK_DAYS_COVER'] = 'STOCK DAYS COVER';

                $this->isPPCInclude = 'Y';
                $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;
            }
        } else {
            $this->configurationFailureMessage();
        }
        
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


    function stockCoverGrid() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $lastSalesDays = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

        $selectPart = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->settingVars->clusterID;

        $IncludePPC = '';
        if($this->isPPCInclude == 'Y'){
            $this->measureFields[] = $this->settingVars->ppcColumnField;
            $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC";
        }
        
        foreach ($this->accountsName as $key => $data) {
            $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
            $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
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

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT "
                . $this->skuID . " AS TPNB " .
                ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                ",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
                "," . $this->storeID . " AS SNO " .
                ",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
                $IncludePPC.
				$addTerritoryColumn.((count($selectPart) > 0 ) ? " , ".implode(",", $selectPart) : "" ).
                ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*units) AS SALES " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY TPNB,SNO ".$addTerritoryGroup;
 
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $stockCoverGridDataBinding = array();
        //$stockCoverGridDataBinding2 = array();
        
        $i = 0;
        $totalValue = count($result);
        $stringArray = '';
        
        if (is_array($result) && !empty($result))
		{
			foreach ($result as $value) {
				$aveDailySales = (filters\timeFilter::$daysTimeframe > 0 ) ? $value['SALES'] / filters\timeFilter::$daysTimeframe : 0 ;
				if ($aveDailySales != 0) {

					$dc = ($value['SALES'] > 0) ? (($value['STOCK'] + $value['TRANSIT']) / $aveDailySales) : 0;

					/*$row = array();
					$row['SNO'] = $value['SNO'];
					$row['STORE'] = utf8_encode($value['STORE']);
					$row['STOREDISTRICT'] = utf8_encode($value['STOREDISTRICT']);
					$row['BANNER'] = utf8_encode($value['BANNER']);
					$row['CLUSTER'] = utf8_encode($value['CLUSTER']);
					$row['TPNB'] = $value['TPNB'];
					$row['SKU'] = utf8_encode($value['SKU']);
					$row['STOCK'] = $value['STOCK'];
					$row['TRANSIT'] = $value['TRANSIT'];*/
					$value['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
					$value['DAYS_COVER'] = number_format($dc, 2, '.', '');
					$value['LAST_SALE'] = $lastSalesDays[$value['TPNB'] . "_" . $value['SNO']];

                    if($this->isPPCInclude == 'Y'){
                        $value['STOCK_UNITS']= ($value['STOCK']*$value['PPC']);

                        $dcUnit = ($value['SALES'] > 0) ? (($value['STOCK_UNITS'] + $value['TRANSIT']) / $aveDailySales) : 0;
                        $value['STOCK_DAYS_COVER'] = number_format($dcUnit, 2, '.', '');
                    }
					
					/*if($value['TERRITORY'])
						$row['TERRITORY'] = $value['TERRITORY'];*/
					
					array_push($stockCoverGridDataBinding, $value);
				}
			}
		}
        //array_multisort($emp, SORT_DESC, $stockCoverGridDataBinding);
        $this->jsonOutput['stockCoverGrid'] = $stockCoverGridDataBinding;  
    }

    function skuSelect() {
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $IncludePPC = '';
        if($this->isPPCInclude == 'Y'){
            $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC";
        }

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM(stock) AS STOCK " .
                $IncludePPC.
                ",SUM((CASE WHEN stockTra>0 THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                // "AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                " GROUP BY DAY " .
                "ORDER BY DAY ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $value = array();
		if (is_array($result) && !empty($result)){
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
            
            $tempCol['TPNB'] = (isset($this->settingVars->dataArray[$skuField]['ID_CSV'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : $this->settingVars->dataArray[$skuField]['NAME_CSV'];
            $tempCol['SKU'] = $this->settingVars->dataArray[$skuField]['NAME_CSV'];
                
            $tempCol['SNO'] = (isset($this->settingVars->dataArray[$storeField]['ID_CSV'])) ? $this->settingVars->dataArray[$storeField]['ID_CSV'] : $this->settingVars->dataArray[$storeField]['NAME_CSV'];
            $tempCol['STORE'] = $this->settingVars->dataArray[$storeField]['NAME_CSV'];

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
        
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            if (isset($_REQUEST["FS"]['TPNB']) && $_REQUEST["FS"]['TPNB'] != '' ){
                $extraFields[] = $this->skuID;
                $tablejoins_and_filters .= " AND " . $this->skuID . " = '" . $_REQUEST["FS"]['TPNB']."' ";
            }

            if (isset($_REQUEST["FS"]['SNO']) && $_REQUEST["FS"]['SNO'] != ''){
                $extraFields[] = $this->storeID;
                $tablejoins_and_filters .= " AND " . $this->storeID." = '".$_REQUEST["FS"]['SNO']."' ";
            }
        }
        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;
        return $tablejoins_and_filters1;
    }
}
?>