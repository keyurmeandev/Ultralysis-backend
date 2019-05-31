<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class TotalSalesAndStock extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /*if (empty($_REQUEST["requestDays"])) {
            $_REQUEST["requestDays"] = 14;
        }*/

        if ($this->settingVars->isDynamicPage) {
            $this->bottomGridFields = $this->getPageConfiguration('bottom_grid_accounts', $this->settingVars->pageID);
            $this->topGridField = $this->getPageConfiguration('top_grid_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->depotGridField = $this->getPageConfiguration('depot_grid_field', $this->settingVars->pageID)[0];

            $tempBuildFieldsArray = array($this->topGridField, $this->storeField, $this->depotGridField);
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->bottomGridFields);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }
        
        $this->dcViewState = ((!isset($this->settingVars->tesco_depot_daily) || $this->settingVars->tesco_depot_daily == '') ||
                (isset($_REQUEST["dcViewState"]) && $_REQUEST["dcViewState"] == '0')) ? false : true;

        $action = $_REQUEST["action"];

        switch ($action) {
            case "skuSelect":
                $this->skuSelect();
                break;
            case "getgridData":
                $this->form();
                break;
            case "skuChange":
                $this->skuChange();
                break;
        }
        return $this->jsonOutput;
    }

    function form() {
        $this->gridData1();

        if ($this->dcViewState)
            $this->gridData2();
    }

    function skuSelect() {
        if ($this->dcViewState)
            $this->gridData2();
        
        $this->gridData3();
    }

    public function skuChange() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->storeIdField;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES " .
                ",SUM(stock) AS STOCK ".
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //" AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                " GROUP BY DAY ORDER BY DAY ASC";

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
                $value['STOCK'][] = $data['STOCK'];
                $value['DAY'][] = $data['DAY'];
            }
        }
        $this->jsonOutput['skuChange'] = $value;
    }

    function gridData1() {

        /*$id = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];

        $skuname = $this->settingVars->dataArray['F2']['NAME'];*/

        //$getLastDaysDate = filters\timeFilter::getLastNDaysDate($this->settingVars);
        $daysStock = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->topGridIdField;
        $this->measureFields[] = $this->topGridNameField;

        if ($this->dcViewState) {
            $getLastDepotDate = filters\timeFilter::getLastNDaysDateFromDepotDaily($this->settingVars);

            /*$query = "SELECT " . $this->topGridIdField . " AS TPNB" .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDepotDate[0] . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
                    " FROM " . $this->settingVars->tesco_depot_daily . ", " . $this->settingVars->skutable .
                    " WHERE " . $this->settingVars->tesco_depot_daily . ".skuID = " . $this->settingVars->skutable . ".PIN" .
                    " GROUP BY TPNB";*/
                    
            $query = "SELECT " . $this->topGridIdField . " AS TPNB" .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDepotDate . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
                    " FROM " . $this->settingVars->depotTableName . $this->settingVars->depotLink ." GROUP BY TPNB";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

    		if (is_array($result) && !empty($result))
    		{
    			foreach ($result as $data) {
    				$daysStock[$data['TPNB']] = $data['STOCK'];
    			}
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

		$query = "SELECT " . $this->topGridIdField . " AS TPNB" .
				",TRIM(MAX(" . $this->topGridNameField . ")) AS SKU" .
				",SUM((CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN 1 ELSE 0 END)* " . $this->settingVars->ProjectValue . ") AS SALESV " .
				",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALESQ " .
				",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK " .
				",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
				"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
				"AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
				"GROUP BY TPNB ORDER BY SALESV DESC";
		//echo $query;exit;
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		$gridData1DataBaind = array();
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {
                $dcStock = $dc = $dci = 0;
                if ($this->dcViewState) {
                    $dcStock = $daysStock[$data['TPNB']];
				    $dci =  ($data['SALESQ'] / filters\timeFilter::$daysTimeframe > 0) ?  (($data['STOCK'] + $daysStock[$data['TPNB']]) / ($data['SALESQ'] / filters\timeFilter::$daysTimeframe )) : 0;
                }

                $aveDailySales = (filters\timeFilter::$daysTimeframe > 0 ) ? $data['SALESQ'] / filters\timeFilter::$daysTimeframe : 0;
                if ($aveDailySales != 0) {
                    $dc = ($data['SALESQ'] > 0) ? (($data['STOCK'] + $data['TRANSIT']) / $aveDailySales) : 0;
                    $dc = ($data['STOCK'] / $aveDailySales);
                } else {
                    $dc = 0;
                }

				$row['TPNB'] = $data['TPNB'];
				$row['SKU'] = utf8_encode($data['SKU']);
				$row['SALES'] = number_format($data['SALESV'], 0, '.', ',');
				$row['SALES_QTY'] = number_format($data['SALESQ'], 0, '.', ',');
				$row['STOCK'] = number_format($data['STOCK'], 0, '.', ',');
				$row['DC_STOCK'] = $dcStock;
				$row['TRANSIT'] = $data['TRANSIT'];
				$row['DAYES_COVER'] = number_format($dc, 1, '.', '');
				$row['DAYES_COVER_INC'] = number_format($dci, 1, '.', '');

				array_push($gridData1DataBaind, $row);
			}
		}

        $this->jsonOutput['gridData1'] = $gridData1DataBaind;
    }

    function gridData2() {

        //$getLastDaysDate = filters\timeFilter::getLastNDaysDate($this->settingVars);
        /*         $id = key_exists('ID', $this->settingVars->depotDataSetting) ? $this->settingVars->depotDataSetting['ID'] : $this->settingVars->depotDataSetting['NAME'];
        $depotname = $this->settingVars->depotDataSetting['NAME']; */

        $getLastDepotDate = filters\timeFilter::getLastNDaysDateFromDepotDaily($this->settingVars);
        $skuIdQueryPart = "";

        if (isset($_REQUEST["skuID"]) && $_REQUEST["skuID"] != "") {
            $skuIdQueryPart = " AND  " . $this->settingVars->tesco_depot_daily . ".skuID =" . $_REQUEST["skuID"];
        }

        $query = "SELECT  " . $this->settingVars->tesco_depot_daily . ".depotID as depotID " .
                //",max(" . $depotname . ") as depot_name " .
                ", ".$this->depotIdField." as SKUID".
                ", max(".$this->depotNameField.") as SKU ".
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDepotDate . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
                "FROM " . $this->settingVars->tesco_depot_daily . ", ".$this->settingVars->skutable.
                " WHERE ".$this->settingVars->skutable.".PIN = ".$this->settingVars->tesco_depot_daily.".skuID AND ".
                " ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' ".
                " $skuIdQueryPart " .
                "GROUP BY depotID, SKUID ORDER BY STOCK DESC ";
        // echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData2DataBaind = array();
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {
				$row['depotID'] = $data['depotID'];
				$row['STOCK'] = $data['STOCK'];
				$row['SKUID'] = $data['SKUID'];
				$row['SKU'] = $data['SKU'];
				array_push($gridData2DataBaind, $row);
			}
		}

        $this->jsonOutput['gridData2'] = $gridData2DataBaind;
    }

    function gridData3() {

        /*$id = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];
        $storeid = key_exists('ID', $this->settingVars->dataArray['F4']) ? $this->settingVars->dataArray['F4']['ID'] : $this->settingVars->dataArray['F4']['NAME'];
        $storename = $this->settingVars->dataArray['F4']['NAME'];
        $skuname = $this->settingVars->dataArray['F2']['NAME'];
        $catname = $this->settingVars->dataArray['F1']['NAME'];
        $bannername = $this->settingVars->dataArray['F5']['NAME'];
		$storeDistrict = $this->settingVars->dataArray['F6']['NAME'];*/

        //$getLastDaysDate = filters\timeFilter::getLastNDaysDate($this->settingVars);
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->clusterID;
		
		if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
			$addTerritoryGroup = ",TERRITORY";
            $this->measureFields[] = $this->settingVars->territoryTable.".Level".$_REQUEST["territoryLevel"];
		}
		else
		{
			$addTerritoryColumn = '';
			$addTerritoryGroup = '';
		}

        $selectPart = array();

        if(is_array($this->bottomGridAccounts) && !empty($this->bottomGridAccounts)){
            foreach ($this->bottomGridAccounts as $key => $data) {
                    $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                    $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
        

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        /*$query = "SELECT " . $storeid . " AS SNO" .
                ",TRIM(MAX(" . $storename . ")) AS STORE" .
				",TRIM(MAX(" . $storeDistrict . ")) AS STOREDISTRICT " .
				$addTerritoryColumn.
                ",TRIM(MAX(" . $bannername . ")) AS BANNER" .
                ",TRIM(MAX(" . $catname . ")) AS sales_cluster" .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)* " . $this->settingVars->ProjectValue . ") AS SALESV " .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALESQ " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDaysDate[0] . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDaysDate[0] . " THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN(" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY SNO ".$addTerritoryGroup." ORDER BY SALESV DESC";*/

        $query = "SELECT " . $this->storeIdField . " AS SNO" .
                ",TRIM(MAX(" . $this->storeNameField . ")) AS STORE" .
                $addTerritoryColumn.((count($selectPart) > 0 ) ? " , ".implode(",", $selectPart) : "" ).
                ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER" .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectValue . ">0 THEN 1 ELSE 0 END)* " . $this->settingVars->ProjectValue . ") AS SALESV " .
                ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALESQ " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK " .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY SNO ".$addTerritoryGroup." ORDER BY SALESV DESC";

        //echo $query;exit;        				
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData3DataBaind = array();
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {
				$aveDailySales = (filters\timeFilter::$daysTimeframe > 0 ) ? $data['SALESQ'] / filters\timeFilter::$daysTimeframe : 0;
				if ($aveDailySales != 0) {
					if ($data['SALESQ'] > 0) {
						$dc = ($data['STOCK'] + $data['TRANSIT']) / $aveDailySales;
					} else {
						$dc = 0;
					}
				} else {
					$dc = 0;
				}
				/*$row['SNO'] = $data['SNO'];
				$row['STORE'] = utf8_encode($data['STORE']);
				$row['STOREDISTRICT'] = utf8_encode($data['STOREDISTRICT']);
				$row['BANNER'] = utf8_encode($data['BANNER']);
				$data['CLUSTER'] = utf8_encode($data['sales_cluster']);*/
				$data['SALES'] = number_format($data['SALESV'], 0, '.', ',');
				$data['SALES_QTY'] = number_format($data['SALESQ'], 0, '.', ',');
				$data['STOCK'] = number_format($data['STOCK'], 0, '.', ',');
				$data['TRANSIT'] = $data['TRANSIT'];
				$data['DAYES_COVER'] = number_format($dc, 1, '.', '');
				
				/*if($data['TERRITORY'])
					$data['TERRITORY'] = $data['TERRITORY'];*/

				array_push($gridData3DataBaind, $data);
			}
		}

        $this->jsonOutput['gridData3'] = $gridData3DataBaind;
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
        $topGridFieldPart   = explode("#", $this->topGridField);
        $storeFieldPart     = explode("#", $this->storeField);
        $depotGridFieldPart = explode("#", $this->depotGridField);
        $this->bottomGridAccounts = $this->makeFieldsToAccounts($this->bottomGridFields);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['topGridColumns']['TPNB'] =  (count($topGridFieldPart) > 1) ? $this->displayCsvNameArray[$topGridFieldPart[1]] : $this->displayCsvNameArray[$topGridFieldPart[0]];
            $this->jsonOutput['topGridColumns']['SKU'] =  $this->displayCsvNameArray[$topGridFieldPart[0]];

            $this->jsonOutput['bottomGridColumns']['SNO'] = (count($storeFieldPart) > 1) ? $this->displayCsvNameArray[$storeFieldPart[1]] : $this->displayCsvNameArray[$storeFieldPart[0]];
            $this->jsonOutput['bottomGridColumns']['STORE'] = $this->displayCsvNameArray[$storeFieldPart[0]];

            if(is_array($this->bottomGridAccounts) && !empty($this->bottomGridAccounts)){
                foreach ($this->bottomGridAccounts as $key => $data) {
                        $this->jsonOutput['bottomGridColumns'][$this->settingVars->dataArray[$data]['NAME_ALIASE']] = $this->settingVars->dataArray[$data]['NAME_CSV'];
                }
            }

            $this->jsonOutput['depotGridColumns']['SKUID'] = (count($depotGridFieldPart) > 1) ? $this->displayCsvNameArray[$depotGridFieldPart[1]] : $this->displayCsvNameArray[$depotGridFieldPart[0]];
            $this->jsonOutput['depotGridColumns']['SKU'] = $this->displayCsvNameArray[$depotGridFieldPart[0]];
        }

        $topGridField = strtoupper($this->dbColumnsArray[$topGridFieldPart[0]]);
        $topGridField = (count($topGridFieldPart) > 1) ? strtoupper($topGridField . "_" . $this->dbColumnsArray[$topGridFieldPart[1]]) : $topGridField;

        $this->topGridIdField = (isset($this->settingVars->dataArray[$topGridField]) && isset($this->settingVars->dataArray[$topGridField]['ID'])) ? 
            $this->settingVars->dataArray[$topGridField]['ID'] : $this->settingVars->dataArray[$topGridField]['NAME'];
        $this->topGridNameField = $this->settingVars->dataArray[$topGridField]['NAME'];


        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeIdField = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? 
            $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeNameField = $this->settingVars->dataArray[$storeField]['NAME'];
        
        $depotGridField = strtoupper($this->dbColumnsArray[$depotGridFieldPart[0]]);
        $depotGridField = (count($depotGridFieldPart) > 1) ? strtoupper($depotGridField . "_" . $this->dbColumnsArray[$depotGridFieldPart[1]]) : $depotGridField;

        $this->depotIdField = (isset($this->settingVars->dataArray[$depotGridField]) && isset($this->settingVars->dataArray[$depotGridField]['ID'])) ? 
            $this->settingVars->dataArray[$depotGridField]['ID'] : $this->settingVars->dataArray[$depotGridField]['NAME'];
        $this->depotNameField = $this->settingVars->dataArray[$depotGridField]['NAME'];
    return;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $extraFields = array();

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }

        if (isset($_REQUEST["pin"]) && $_REQUEST["pin"] != "") {
            $extraFields[] = $this->topGridIdField;
            $tablejoins_and_filters .= " AND " . $this->topGridIdField . " ='" . $_REQUEST["pin"] . "' ";
        }

        if (isset($_REQUEST["sno"]) && $_REQUEST["sno"] != "") {
            $extraFields[] = $this->storeIdField;
            $tablejoins_and_filters .= " AND " . $this->storeIdField . " ='" . $_REQUEST["sno"] . "' ";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }
}

?>