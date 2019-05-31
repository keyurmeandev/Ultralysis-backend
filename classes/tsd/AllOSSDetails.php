<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class AllOSSDetails extends config\UlConfig {
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
            $this->accountListField = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->skuField         = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField       = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];


            $tempBuildFieldsArray = array($this->skuField, $this->storeField);
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountListField);

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
            case "getGridData":
                $this->allOOSDetailsGrid();
                break;
        }

        return $this->jsonOutput;
    }

    function allOOSDetailsGrid() {

		if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
			$addTerritoryGroup = ",TERRITORY";
			$tbl = "," . $this->settingVars->territoryTable;
		}else{
			$addTerritoryColumn = '';
			$addTerritoryGroup = '';
			$tbl = '';
		}
        $selectPart = array();

        if(is_array($this->accountListNames) && !empty($this->accountListNames)){
            foreach ($this->accountListNames as $key => $data) {
                    $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
		
        $tablename = $this->settingVars->rangedtable . "," . $this->settingVars->skutable . "," . $this->settingVars->storetable. $tbl;
        $query = "SELECT " . $this->storeIdField . " AS SNO " .
                ",TRIM(MAX(" . $this->storeNameField . ")) AS STORE " .
				$addTerritoryColumn.((count($selectPart) > 0 ) ? " , ".implode(",", $selectPart) : "" ).
                ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                "," . $this->skuIdField . " AS TPNB " .
                ",TRIM(MAX(" . $this->skuNameField . ")) AS SKU " .
                "FROM " . $tablename . " " . $this->getAllUsingRangeTable() .
                "AND CONCAT(".$this->settingVars->rangedtable.".skuID,".$this->settingVars->rangedtable.".sno)  NOT IN (select concat(skuID,sno) from " . $this->settingVars->maintable . ") " .
                "GROUP BY SNO, TPNB ".$addTerritoryGroup;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $allOOSDetailsGridDataBindingTemp = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($allOOSDetailsGridDataBindingTemp);
        } else {
            $allOOSDetailsGridDataBindingTemp = $redisOutput;
        }

        $this->jsonOutput['allOOSDetailsGrid'] = $allOOSDetailsGridDataBindingTemp;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */
    public function getAllUsingRangeTable() {

		$qpart = '';
		if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != ""){
			$qpart = " AND " . $this->settingVars->storetable . ".SNO=" . $this->settingVars->territoryTable . ".SNO ".
					" AND " . $this->settingVars->storetable . ".GID=" . $this->settingVars->territoryTable . ".GID " .
					"AND " . $this->settingVars->territoryTable . ".accountID = " . $this->settingVars->aid;
		}
		
        $tablejoins_and_filters = " WHERE " . $this->settingVars->rangedtable . ".skuID=" . $this->settingVars->skutable . ".PIN " .
                " AND " . $this->settingVars->rangedtable . ".SNO=" . $this->settingVars->storetable . ".SNO " .   
                " AND " . $this->settingVars->rangedtable . ".GID=" . $this->settingVars->storetable . ".GID " .              
                " AND " . $this->settingVars->storetable . ".GID=".$this->settingVars->GID." " .
                " AND " . $this->settingVars->skutable . ".clientID='" . $this->settingVars->clientID . "' ".$qpart." ";
				
		if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			//Level".$_REQUEST["territoryLevel"] like Level3 or Level2 or Level1
			$tablejoins_and_filters .= " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." = " . $_REQUEST["Level"]. " ";
		}
		
		if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '')
		{ 
		   $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
		}

        return $tablejoins_and_filters;
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
        $storeFieldPart = explode("#", $this->storeField);
        $this->accountListNames = $this->makeFieldsToAccounts($this->accountListField);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['gridColumns']['TPNB'] =  (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['gridColumns']['SKU'] =  $this->displayCsvNameArray[$skuFieldPart[0]];

            $this->jsonOutput['gridColumns']['SNO'] = (count($storeFieldPart) > 1) ? $this->displayCsvNameArray[$storeFieldPart[1]] : $this->displayCsvNameArray[$storeFieldPart[0]];
            $this->jsonOutput['gridColumns']['STORE'] = $this->displayCsvNameArray[$storeFieldPart[0]];

            if(is_array($this->accountListNames) && !empty($this->accountListNames)){
                foreach ($this->accountListNames as $key => $data) {
                        $this->jsonOutput['gridColumns'][$this->settingVars->dataArray[$data]['NAME_ALIASE']] = $this->settingVars->dataArray[$data]['NAME_CSV'];
                }
            }

        }

        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuIdField = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];


        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeIdField = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? 
            $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeNameField = $this->settingVars->dataArray[$storeField]['NAME'];


        return;
    }

}

?>